---
sessao: "06"
titulo: Motor: MVA → ICMS-ST, FCP e DIFAL
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/NfeBrasil/Services/MotorTributarioService.php · Services/Tributacao/TributoCalculado.php · Tests/Feature/MotorTributarioServiceTest.php
nao_toca: resources/js/ · Http/Requests/
depende: 17 (sem itens reais o motor só calcula o item genérico) · D-MOTOR. 🔴 sozinho no PR
decisao: _DECISOES-W-2026-10-06.md
implementa: R-NFE-015 · R-NFE-016 · R-NFE-017 · R-NFE-015b
us: US-NFE-010 ("ICMS-ST (com MVA)") · US-FISCAL-020 ("quebra na primeira venda interestadual contribuinte, CFOP 6102 com ICMS-ST")
---
# 06 · Motor: MVA → ICMS-ST, FCP e DIFAL

`aplicarRegra` hoje só multiplica valor × alíquota; `mva` e `fcp` são gravados e ignorados. Somar a `TributoCalculado`: `base_st`, `valor_st` = (valor + IPI) × (1 + MVA) × alíquota interna do destino − ICMS próprio; `valor_fcp`; `valor_difal` quando o destinatário não é contribuinte e a UF é outra. CSOSN 500 (ST já retida) não recalcula. Fórmulas com a norma citada literal no docblock (lei 4).

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-015 | ICMS-ST calculado pela MVA | `must` `[fiscal]` | `memory/requisitos/NfeBrasil/SPEC.md` (próximo R-NFE livre — conferir) |
| R-NFE-016 | CSOSN 500 não recalcula ST | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-017 | DIFAL só pra não contribuinte em outra UF | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-015b | FCP calculado quando a regra tem FCP | `must` `[fiscal]` | SPEC NfeBrasil |

### R-NFE-015 · ICMS-ST calculado pela MVA · `must` `[fiscal]`
- **Destino:** `memory/requisitos/NfeBrasil/SPEC.md` (próximo R-NFE livre — conferir)
- **Persona:** venda interestadual a contribuinte de produto com ST
- **Aceite:** Dado regra com `mva=0.40`, IPI 0 e alíquota interna de destino conhecida · Quando `calcular` · Então `base_st = valor × 1,40` e `valor_st = base_st × interna − ICMS próprio`, com o valor esperado **calculado à mão no teste**. Controle positivo: regra sem MVA devolve `valor_st = 0` e os demais campos idênticos aos de hoje.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-015 · ICMS-ST pela MVA`
- **Contrato:** US-NFE-010 · US-FISCAL-020
- **Regressão que defende:** a nota interestadual sai sem ST e o cliente é autuado.

### R-NFE-016 · CSOSN 500 não recalcula ST · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** revenda de produto comprado com ST retida
- **Aceite:** Dado regra com `csosn=500` e `mva` preenchida · Quando `calcular` · Então `valor_st = 0`. Controle positivo: a mesma regra com `csosn=201` calcula ST.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-016 · CSOSN 500 não recalcula ST`
- **Contrato:** tabela CSOSN (ST cobrada anteriormente)
- **Regressão que defende:** ST cobrada duas vezes.

### R-NFE-017 · DIFAL só pra não contribuinte em outra UF · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** venda a consumidor final de outro estado
- **Aceite:** Dado destinatário sem IE em UF ≠ origem · Quando `calcular` · Então `valor_difal = base × (interna destino − interestadual)`. Controle positivo: destinatário com IE, ou mesma UF, dá `valor_difal = 0`.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-017 · DIFAL só não contribuinte interestadual`
- **Contrato:** EC 87/2015 (citar literal no docblock — lei 4)
- **Regressão que defende:** DIFAL cobrado de contribuinte ou esquecido no consumidor.

### R-NFE-015b · FCP calculado quando a regra tem FCP · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** venda a UF que cobra Fundo de Combate à Pobreza
- **Aceite:** Dado regra com `fcp=0.02` · Quando `calcular` · Então `valor_fcp = base × 0,02` (e, em DIFAL, o FCP do destino). Controle positivo: `fcp` nulo dá `valor_fcp = 0`.
- **Teste:** `MotorTributarioServiceTest` — `R-NFE-015b · FCP pela regra`
- **Contrato:** US-NFE-010 · coluna `fcp` (hoje gravada e ignorada)
- **Regressão que defende:** FCP gravado na regra e nunca cobrado.

## Prova
Casos em `MotorTributarioServiceTest`: ST com MVA conhecida (valor esperado calculado à mão no teste) · CSOSN 500 = 0 · DIFAL interestadual não contribuinte · regra sem MVA = comportamento atual.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-06.md` com o sha e a saída dos testes. Pare.
