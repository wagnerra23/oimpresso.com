---
sessao: "06"
titulo: "Motor: MVA → ICMS-ST, FCP e DIFAL — recibo"
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main d9ca2bd6db (merge do #8874)
prefixo_tocado: Modules/NfeBrasil/Services/MotorTributarioService.php · Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php · Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php · memory/requisitos/NfeBrasil/SPEC.md · este arquivo
---
# _saida-06 · Motor: MVA → ICMS-ST, FCP e DIFAL

**Entregue no [#8874](https://github.com/wagnerra23/oimpresso.com/pull/8874)**, mergeado em 2026-10-07 02:43Z (`d9ca2bd6db`) com o ok do [W] (cálculo de imposto). Este recibo foi escrito pelo gerente da fila a partir do corpo do PR: a sessão que fez a thread declarou que não escreveria o `_saida`, porque não toca o espelho.

**Resposta curta:** o motor sabe calcular ST pela MVA, FCP e DIFAL, mas **nenhuma emissão usa isso ainda**. O XML, o `vNF` e os impostos destacados não mudaram. Quem liga a alíquota interna do destino na emissão é a thread 09.

## 1 · Feito

| item | onde |
|---|---|
| `calcularComDestino(..., aliquotaInternaDestino, destinatarioContribuinte)`; `calcular` mantém os 4 parâmetros e delega | `MotorTributarioService` |
| `base_st`, `valor_st`, `valor_fcp`, `valor_difal` (default 0) | `TributoCalculado` |
| Fórmulas com a norma no docblock: LC 87/1996 art. 8º II e §5º · ADCT art. 82 §1º · CF art. 155 §2º VII e VIII-b (EC 87/2015) | `calcularStFcpDifal` |
| CSOSN 500 não recalcula ST | idem |
| Sem a interna do destino, ST e DIFAL ficam 0 (o motor não inventa) | idem |
| 5 casos novos, cada um citando o R-NFE e com controle positivo | `MotorTributarioServiceTest` (já na allowlist da lane `nfebrasil-pest.yml`) |
| R-NFE-015 · 015b · 016 · 017 no SPEC | `memory/requisitos/NfeBrasil/SPEC.md` |

### Prova por dois caminhos (item de 1.000,00 · ICMS 12% · interna 20%, números de fixture)
| cenário | à mão | código PHP |
|---|---|---|
| ST, MVA 40% | base 1.400,00 · ST 160,00 | 1400.00 · 160.00 |
| ST + IPI 10% | base 1.540,00 · ST 188,00 | 1540.00 · 188.00 |
| não contribuinte, outra UF, FCP 2% | DIFAL 80,00 · FCP 20,00 | 80.00 · 20.00 |
| CSOSN 500 · com IE · mesma UF · sem interna | 0 | 0 |
| chamada de hoje (4 parâmetros) | ICMS 120,00, igual a antes | idêntico (`toEqual`) |

## 2 · Não feito, e por quê

| o que | por quê |
|---|---|
| Ligar a interna do destino na emissão | depende da tabela por UF, thread **09** (R-NFE-021) |
| DIFAL do Simples por UF (ADI 5.464) | thread **29** (R-NFE-035) |
| CST 60 (regime normal, ST já retida) | fora do escopo da thread |
| FCP-ST e base dupla do DIFAL | fora do escopo da thread |

## 3 · Pedido literal
Nenhum ao [W]: o merge já teve o ok dele. Ao Cowork: marcar a 06 como feita no índice.

## 4 · Descobertas que mudam outra thread
- **09:** o motor já aceita `aliquotaInternaDestino` e `destinatarioContribuinte` por `calcularComDestino`. A 09 só precisa da tabela por UF e de passar os dois parâmetros nos callers (`NfeService` ×2, `SpedIcmsIpiGeneratorService`).
- **Quem estender o motor:** parâmetro opcional novo em `calcular` quebra os motores falsos `new class extends MotorTributarioService` dos testes (Fatal de assinatura incompatível, lane sai com exit 2 e JUnit vazio). Foi o que aconteceu no 1º head do #8874. Acrescente método novo em vez de mudar a assinatura.
