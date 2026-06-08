---
paths:
  - "app/Utils/TransactionUtil.php"
  - "app/Utils/ProductUtil.php"
  - "app/Utils/Util.php"
  - "app/Utils/BusinessUtil.php"
  - "resources/js/Pages/Sells/Create.tsx"
  - "resources/js/Pages/Sells/Edit.tsx"
  - "resources/js/Pages/Sells/_components/*.tsx"
  - "resources/js/Lib/numberPtBR.ts"
  - "Modules/**/Http/Controllers/*Controller.php"
---

# Rule path-scoped — CÁLCULO de VALOR ou ESTOQUE (Tier 0 IRREVOGÁVEL)

> Carrega ao tocar arquivo que pode mexer em cálculo de **valor** (preço, total, subtotal, desconto,
> imposto, frete, `final_total`, `total_before_tax`, pagamento, comissão, parsing de número) ou
> **estoque** (quantidade, movimentação, reserva, baixa). Reforça a REGRA MESTRE de
> [`memory/proibicoes.md`](../../memory/proibicoes.md) §"CÁLCULO DE VALOR ou ESTOQUE".

## Antes de mergear/deploiar QUALQUER mudança aqui:

1. **DUPLA CONFIRMAÇÃO do cálculo** — prove o resultado por **2 caminhos independentes** com números
   concretos: ex (a) caso manual ponta-a-ponta + (b) cross-check frontend×backend (`totalGeral` vs
   `final_total` gravado), OU dry-run + recompute à mão. **Nunca um só.**
2. **APRESENTAR O IMPACTO** — tabela **antes→depois** explícita (quais vendas/valores/estoques mudam).
   Em prod, **dry-run obrigatório** mostrando o delta de cada registro afetado ANTES de qualquer escrita.
3. **Aprovação humana explícita** (Wagner vê o #2 e confirma) — pareia com R10.

## Armadilhas catalogadas (incidente 2026-06-05)

- ⛔ **Frontend manda float locale-ambíguo** (`204.99605`, do desconto %) pro parser pt-BR `num_uf` →
  ponto vira separador de milhar → **R$ 205 grava R$ 20.499.605** (×100k). **Arredonde a 2 casas no
  submit** (`Math.round(x*100)/100`).
- ⛔ **Separador de milhar tem SEMPRE 3 dígitos** — `num_uf`: 4+ casas após o "." é decimal, não milhar.
- ⛔ **Corrupção de valor propaga pro pagamento** (`total_paid` espelha `final_total`) e ao `discount_amount`.
  Corrigir valor exige checar pagamento + desconto juntos.

Ver [session 2026-06-05](../../memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md) · fix #2279 · `NumUfHeuristicPtBRTest`.

## Skills relacionadas
`multi-tenant-patterns` (Tier A) · `commit-discipline` (Tier A) · `smoke-prod-evidence` (Tier B)
