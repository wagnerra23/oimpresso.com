---
casos: Financeiro Unificado · /financeiro/unificado
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-20"
---

# Casos de Uso & Aceite — Financeiro Unificado

> Tela P0 do fio **venda → faturamento → caixa** (mandato ONDAS-QUALIDADE Q2). Os UCs
> abaixo espelham o ENCADEAMENTO que sustenta esta tela: a venda gera o título a receber,
> o recebimento baixa o título e registra entrada no caixa — provado ponta-a-ponta contra
> DB real (canon: não-mocka-DB) por `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`,
> que roda no CI (`financeiro-pest.yml`, check required) e alimenta o manifesto G-7 via
> JUnit. `Status: ✅` só com veredito `pass` no manifesto.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-F01 · Venda a prazo gera título a receber (CU-3→CU-5)
- **Persona:** Kamila (financeiro) — confiança de que NENHUMA venda a prazo fica sem cobrança.
- **Aceite:** Dado venda final a prazo 30 dias · Então nasce `fin_titulos` tipo `receber`, status `aberto`, valor total = valor da venda, vencimento +30d da data da venda.
- **Teste:** `RetencaoLoopE2ETest` ("UC-F01 · CU-3→CU-5") — Observer da venda, DB MySQL real.
- **Status: ✅**

## UC-F02 · Recebimento baixa o título e entra no caixa (CU-5)
- **Persona:** Kamila — o "recebi" do balcão tem que virar baixa + caixa sem digitação dupla.
- **Aceite:** Dado título aberto · Quando o pagamento total entra (`TransactionPayment`) · Então o título quita (`valor_aberto = 0`), nasce a `fin_titulo_baixas` ligada ao pagamento e o `fin_caixa_movimentos` registra `entrada` ligada à baixa.
- **Teste:** `RetencaoLoopE2ETest` ("UC-F02 · CU-5") — TransactionPaymentObserver no DB real.
- **Status: ✅**

## UC-F03 · Wire fiscal da venda existe (CU-4)
- **Persona:** Larissa — o botão "Emitir NF-e" da venda não pode apontar pro vazio.
- **Aceite:** Dado a rota da tela de venda · Então os endpoints fiscais que ela dispara (NF-e emitir) existem e respondem (a emissão SEFAZ em si é coberta com stub pelas suítes NfeBrasil/NFSe — não reduplicado aqui).
- **Teste:** `RetencaoLoopE2ETest` ("UC-F03 · CU-4").
- **Status: ✅**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Lentes (caixa/competência/fiscal) refinam os KPIs** — coberto por `UnificadoLentesGuardTest` (Pest GUARD); vira UC com id quando os GUARDs ganharem UC-id no título.
- **[BACKLOG] Baixa manual pela tela (dialog)** — coberto por `UnificadoBaixaDialogGuardTest`; idem.
- **[BACKLOG] Conciliação bancária (extrato ↔ título)** — fluxo Pluggy/Inter; espelhar quando o harness tiver extrato fixture.

## Como rodar a suíte
1. **Pest (MySQL real):** lane `financeiro-pest.yml` (check required `PHP / Pest (Financeiro · MySQL)`) — JUnit vira manifesto via `npm run casos:results` (merge per-UC).
2. **Cadência:** rodar ao fim de toda mexida no Financeiro. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE): UC-F01..03 espelham o RetencaoLoopE2ETest (CU-3→CU-5) no manifesto G-7; RetencaoLoop entrou na allowlist do financeiro-pest + JUnit artifact.
- 2026-06-20 · [CL] **Revalidado (G-6 frescor):** `Index.tsx` mudou em 2026-06-19 (commit `82f005341` — drawer FA-5 R3: prop `hue` no `DrawerLens` + ícones por domínio, mudança puramente visual do drawer). UC-F01/F02/F03 são contratos da CADEIA backend (venda→título→caixa via Observers, provados por `RetencaoLoopE2ETest`, ainda `pass` no manifesto G-7) — a mudança de drawer não toca a cadeia de dados. Casos seguem válidos; `last_run` bumpado.
