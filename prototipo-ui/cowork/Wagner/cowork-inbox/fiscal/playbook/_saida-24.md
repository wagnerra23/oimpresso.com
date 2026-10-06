---
sessao: "24"
titulo: Fator R automático — FatorRService (folha ÷ receita 12 meses)
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
prefixo_tocado: Modules/NfeBrasil/Services/FatorRService.php · Modules/NfeBrasil/Tests/Feature/FatorRTest.php · este arquivo
---
# _saida-24 · Fator R automático

**Resposta curta:** o `FatorRService` existe e **só informa**: calcula folha ÷ receita bruta dos 12 meses anteriores à competência, devolve Anexo III (≥ 28%) ou V (< 28%) e a pendência quando o anexo muda entre duas apurações. Não altera regime, anexo cadastrado nem DAS. Nenhuma tela foi tocada.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/NfeBrasil/Services/FatorRService.php` | `calcular(folha, receita)` · `pendencia(anterior, atual)` · `apurar(businessId, competencia, complementoFolha?)`. LC 123/2006 art. 18 §§ 5º-J, 5º-K, 5º-M e 24 citados **literalmente** no docblock (planalto.gov.br, lido em 2026-10-06). |
| `Modules/NfeBrasil/Tests/Feature/FatorRTest.php` | `R-NFE-029`: C26 (30% → III), C27 (17,86% → V), exatamente 28% → III e 1 centavo abaixo → V, receita zero → sem dado, 29% → 27% gera "Fator R abaixo de 28%", cruzamento para cima, janela de 12 meses com bordas, devolução descontada, rascunho fora, complemento do § 24, isolamento 98 × 99. |

**Regras de cálculo, todas com fonte:**
- Limite comparado **em centavos inteiros** (`folha × 100 ≥ receita × 28`), sem float na fronteira. § 5º-J diz "igual ou superior": 28,00% é III.
- Janela (§ 5º-K, "doze meses anteriores ao período de apuração"): competência 2026-10 → de 2025-10-01 a 2026-09-30. A própria competência não entra.
- Receita = vendas `sell/final` − devoluções `sell_return/final`. Folha = `payroll/final` + complemento do § 24.
- Toda query filtra `business_id` (ADR 0093).

## 2 · Regra mestre de valor: dupla confirmação

O service não muda nenhum valor gravado nem nenhum tributo. Mas ele indica o anexo, e o anexo muda a alíquota do DAS, por isso a conta foi feita por dois caminhos.

| caso | caminho 1 · `trFatorR` do protótipo (node, linha copiada de `fiscal-tributacao.jsx:257`) | caminho 2 · `FatorRService::calcular` (php 8.4, sem framework) |
|---|---|---|
| 84.000 / 280.000 (C26) | 0,300000 · III | 0,3 · III |
| 50.000 / 280.000 (C27) | 0,178571 · V | 0,1786 · V |
| 78.400 / 280.000 | 0,280000 · III | 0,28 · III |
| 78.399,99 / 280.000 | 0,280000 · V | 0,28 · V |
| 81.200 / 280.000 | 0,290000 · III | 0,29 · III |
| 75.600 / 280.000 | 0,270000 · V | 0,27 · V |
| 84.000 / **0** | 0 · **V** | null · **sem dado** |

Pendência 29% → 27%: `Fator R abaixo de 28%` (php).

**Antes → depois em produção:** antes nada calculava o Fator R. Depois, ainda nada **muda**: o service não tem chamador e não grava. Nenhuma venda, título ou nota muda de valor.

## 3 · Não feito, e por quê

- **A folha do ERP é parcial.** A folha do Essentials é gerencial: não tem encargo patronal, FGTS nem pró-labore (`payroll_gerencial_aviso`), e o § 24 manda somar os três. Sem o complemento, `apurar()` devolve `folha_parcial = true`. Nesse caso a razão sai **subestimada** e o anexo pode aparecer como V quando é III, que é a regressão que esta thread existe para evitar. Quem renderizar **tem** que mostrar o aviso. Onde o contador informa o complemento é decisão da thread 15/28.
- **`final_total` do payroll é líquido de descontos**, e o § 24 fala em "montante pago … a título de remunerações". Não medi quanto isso difere no corpus real. Fica coberto pelo complemento do contador.
- **Resolução CGSN não citada.** O portal normas.receita.fazenda.gov.br é SPA, e o `curl` devolveu só o shell. Não cito texto que não li. Lei citada: a LC 123/2006.
- **Pest não rodou aqui** (CT 100/CI only). Lint `php -l` limpo nos dois arquivos. O bloco `apurar()` roda no lane sqlite (`modules-pest`, matriz NfeBrasil), que cria o schema mínimo de `transactions`. No MySQL ele pula, **declarado**, porque a tabela real exige location/contact/created_by. As 6 asserções puras rodam nos dois lanes.
- **Não entrou na lane MySQL `nfebrasil-pest.yml`**: ela usa allowlist explícita, e o workflow fica fora do meu prefixo.
- **Aba Serviços e Saúde fiscal**: não tocadas (`nao_toca: resources/js/`). O consumidor da `pendencia()` é a thread 14.

## 4 · Pedido literal pro [CL]/[W]

```
1. SPEC NfeBrasil: registrar R-NFE-029 (Fator R decide o anexo e avisa ao cruzar 28%,
   must, [fiscal]) com **Implementado em:** Modules/NfeBrasil/Services/FatorRService.php
   e teste Modules/NfeBrasil/Tests/Feature/FatorRTest.php.
2. .github/workflows/nfebrasil-pest.yml: acrescentar FatorRTest.php à allowlist SE
   o bloco apurar() ganhar fixture MySQL (hoje ele pula no MySQL de propósito).
3. fiscal-tributacao.casos.md UC-TRB-22: [falta] → [backend feito, tela pendente].
```

## 5 · Descobertas que mudam outra sessão

- **Protótipo diverge do aceite na receita zero.** O `trFatorR` (`fiscal-tributacao.jsx:257`) devolve `r = 0 → Anexo V` quando a receita é 0. O aceite R-NFE-029 pede "sem dado". Corrige-se no build do Cowork (C12), não no código.
- **Exibição da razão:** 78.399,99 / 280.000 arredonda para 0,28 com 4 casas e é Anexo V. A tela não pode mostrar "28,00% · Anexo V" sem mais casas, porque parece contradição. A thread que renderizar deve usar o `anexo` devolvido, nunca recalcular pela razão arredondada.
- **Thread 14 (Saúde fiscal):** consome `pendencia()`, e as duas strings são constantes públicas (`PENDENCIA_ABAIXO` / `PENDENCIA_ACIMA`).
- **Thread 28 (fechamento, CT05):** consome `apurar()`, e o complemento do § 24 é o elo com o contador.

## 6 · Prefixo tocado

`Modules/NfeBrasil/Services/FatorRService.php` (novo) · `Modules/NfeBrasil/Tests/Feature/FatorRTest.php` (novo) · este `_saida-24.md`. Nada fora disso.
