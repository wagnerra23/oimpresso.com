---
casos: Venda balcão (Sells/Create V2) · /sells/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-29"
---

# Casos de Uso & Aceite — Venda balcão (Sells/Create)

> Tela P0 do fio **venda → estoque → faturamento → caixa** (mandato ONDAS-QUALIDADE Q2).
> O encadeamento backend (venda gera título a receber +30d, recebimento baixa o título)
> é provado por `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`; aqui fica o contrato
> DO LADO DA TELA que a Larissa opera no balcão. `Status: ✅` só com veredito `pass` no
> manifesto G-7 (`scripts/casos-test-results.json`).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-S01 · Venda balcão a prazo (fiado)
- **Persona:** Larissa @ ROTA LIVRE (balcão, 1280px) — cliente leva o produto e paga depois.
- **Como usa:** abre a venda, busca o produto (nome/SKU/código de barras), confere a linha no carrinho, NÃO informa pagamento e salva. O sistema acusa o saldo devedor em vez de bloquear (decisão [W] 2026-05-27 — paridade com o POS Blade que sempre permitiu finalizar sem pagamento).
- **Aceite:** Dado cliente default (Walk-In) + location pré-selecionada · Quando adiciona produto e salva sem pagamento · Então o indicador **"Venda a prazo — saldo devedor R$ X"** aparece antes do submit, o POST cria a venda (backend `payment_status=due`) e a tela sai do formulário sem erro.
- **Teste:** `e2e/sells-venda-balcao.spec.ts` (Playwright, harness G-3 e2e-gate).
- **Status: 🧪** _(refactor só-de-layout 2026-06-18 — total de itens no rodapé + ordem desconto→pagamento; o fluxo venda-a-prazo não mudou. A prova de 2026-06-11 ficou anterior ao código; re-rodar o e2e + `npm run casos:results` revalida e restaura o status verde.)_

---

## UC-S02 · Venda com desconto percentual não infla o total (dente de cálculo)
- **Persona:** Larissa @ ROTA LIVRE (balcão, 1280px) — aplica um desconto em % sobre o total da venda.
- **Como usa:** monta o carrinho, informa desconto percentual (ex 10,05%) e finaliza. O `final_total` gravado tem que ser o total real com desconto — **nunca** um valor inflado ~×100.000 por erro de parsing de separador decimal.
- **Aceite:** Dado uma venda de `227,90` com desconto de `10,05%` · Quando o totalizador `ProductUtil::calculateInvoiceTotal` roda (que passa por `Util::num_uf`) · Então `total_before_tax = 227.90`, `discount = 22.90395` e `final_total = 204.99605` (jamais `~20.499.605`); e o invariante `final_total ≤ total_before_tax` vale sempre. Round-trip `num_uf(num_f(x)) == x` na precisão de moeda.
- **Divergência de pagamento (caracterizada, não unificada):** `getTotalPaid` é **líquido** (`SUM(IF(is_return=0, amount, amount*-1))` — desconta devolução) e é a **fonte de verdade** do `payment_status` (via `calculatePaymentStatus`); `getTotalAmountPaid` é **bruto** (`SUM(amount)` — ignora `is_return`). O teste trava as duas definições ATUAIS. Unificar = mudança de valor em prod → **US separada sob REGRA MESTRE** (dupla confirmação + antes→depois + OK [W]), nunca pega carona neste PR.
- **Teste:** `tests/Feature/Calculo/CalculoValorSellsTest.php` (Pest, property + golden no totalizador real + discriminação RED + caracterização da divergência). Guards de `num_uf` em isolamento: `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php` + `NumUfHeuristicPtBRTest.php`.
- **Status: 🧪** _(Onda 1.4 — teste green no CT100, mas o veredito ainda não entra no manifesto G-7 `scripts/casos-test-results.json` (Pest fora do harness JUnit e2e). Vira ✅ quando o manifesto carregar o veredito `pass` deste UC. Origem do vetor: incidente 2026-06-05, fix #2279.)_

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens abaixo SEM token de UC de
> propósito até existir teste real — visíveis, não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] Venda paga no ato (dinheiro/PIX) fecha sem saldo devedor** — caminho feliz com pagamento integral; exige modelar o bloco de pagamentos no harness.
- **[BACKLOG] Bloqueio por limite de crédito** — backend devolve `errors.venda` e o carrinho fica intacto (toast 8s) — exige fixture de limite no seed.
- **[BACKLOG] Emissão NF-e da venda (homolog/stub SEFAZ)** — wire já existe (suítes NfeBrasil); espelhar como UC quando o fluxo de emissão entrar no harness e2e.

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate, gate de PR desde Onda Q1) — vereditos viram manifesto via `npm run casos:results`.
2. **Cadência:** rodar ao fim de toda mexida em Sells/Create. UC ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S01 venda a prazo + spec Playwright `sells-venda-balcao.spec.ts`; produto E2E-0001 entrou no VisregTenantSeeder (enable_stock=0).
- 2026-06-18 · [CC] refactor só-de-layout (Wagner): total de itens no rodapé do card Produtos + card de desconto (Resumo) movido pra antes do Pagamento. Sem mudança de comportamento — UC-S01 baixado pra 🧪 até re-rodar o e2e (G-7 frescor).
- 2026-07-02 · [CC] Onda 1.4 (dente de cálculo): UC-S02 declarado com teste no MESMO PR (coordenação 1.3 ↔ 1.4, regra "declarar UC + teste = 1 PR"). Property `num_uf(num_f(x))==x` + golden no totalizador real `calculateInvoiceTotal` (227,90 − 10,05% = 204.99605, não infla) + discriminação RED vs strip-do-ponto + caracterização da divergência `getTotalPaid`(líquido) ≠ `getTotalAmountPaid`(bruto). TEST-ONLY — nenhum método de cálculo alterado.
