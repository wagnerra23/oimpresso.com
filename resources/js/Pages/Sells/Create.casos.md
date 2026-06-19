---
casos: Venda balcão (Sells/Create V2) · /sells/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-11"
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
- **Status: ✅**

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
