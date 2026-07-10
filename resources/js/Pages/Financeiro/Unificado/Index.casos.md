---
casos: Financeiro Unificado · /financeiro/unificado
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-10"
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

## UC-F04 · Ações em lote respeitam o tenant e a contabilidade (US-FIN-031)
- **Persona:** Eliana [E] — fechamento do mês com 200+ títulos sem 200 cliques; e NUNCA um lote pode vazar pra outro negócio nem apagar registro contábil.
- **Aceite:** Dado títulos selecionados na Visão Unificada · Quando uma ação em lote roda (`POST /financeiro/unificado/bulk` — baixar/categoria/plano/cancelar/exportar) · Então (a) 1 id de outro business no lote = 422 e NADA aplica; (b) baixar em lote quita com a soma exata provada por 2 caminhos (baixas criadas × total do audit trail — REGRA MESTRE valor); (c) cancelar é `status='cancelado'` append-only, pula quitado, e o modal apresenta "N títulos totalizando R$ X" ANTES de aplicar; (d) limite 500 por chamada; (e) audit trail grava user+ids+count+total.
- **Teste:** `UnificadoBulkGuardTest` ("UC-F04 GUARD G1..G3" + G4/G5/G6) — DB MySQL real, lane `financeiro-pest.yml`.
- **Status: 🧪** (vira ✅ com veredito `pass` no manifesto G-7)

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
- 2026-06-16 · [CL] revalidado (bump last_run) na onda "Financeiro adversário Wave 1": mudança é só de UI no hero/audit trail (% pt-BR + cor de saldo negativo); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-06-18 · [CL] revalidado (bump last_run): migração do header pro `<PageHeader>` canon (#2947) — mudança só de chrome do header (Zona R preservada: 3 lentes + divisor + FinanceiroSubNav + dropdown "Novo título"); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): fix de cor do primary "Novo título" (style inline roxo 295 canon, ADR 0190 — corrige botão ghost/magenta pego no diff prod×protótipo). Mudança puramente cosmética (1 `style` no botão do header); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): filtro de campo de data `<select>` → segmentado (fidelidade protótipo [W] — "iguale o filtro de data ao segmented"). Contrato backend `data_campo` INTACTO (mesmo `aplicar({data_campo})`), só troca o controle visual; some 1 `<select>` nativo (ds/no-native-select). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest. A cobertura do `data_campo` é o `UnificadoDataCampoTest` (Pest GUARD), inalterado.
- 2026-07-06 · [CL] revalidado (bump last_run): rótulo da ação de baixa "Receber/Pagar" → "✓ Recebi/Paguei" (1ª pessoa, fidelidade protótipo [W] — "sim eu quero o recebi/paguei"). 2 lugares (tabela por linha + footer do drawer), só o texto do botão; ação `onBaixar`/`openBaixa` inalterada (abre a FinBaixaSheet). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): pacote fidelidade+D-14 ([W] "arrume"): (a) `aplicar()` ganha `only:[kpis,lancamentos,pagination,filters,periodLabel]` + controller lazy-fica contas/categorias/planosConta/agingBreakdown em closures — PARTIAL reload de verdade, mata o "recarrega a página inteira" (anti-padrão do sistema); Pest full-request avalia closures normal (mesma resposta), `UnificadoDataCampoTest`/`UnificadoLentesGuardTest` inalterados. (b) setas do mês viram SVG lucide ChevronLeft/Right (proto). (c) barra de filtro em 2 LINHAS (linha 1 = Filtrar-por+PeriodBar; linha 2 = chips/contas/plano/busca — ordem do proto). Medido: título da linha (12.5px/500) e formato do footer JÁ eram idênticos ao proto (a diferença percebida era dado real em CAPS + valores reais). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-06 · [CC] US-FIN-031 bulk actions ENTREGUE: novo UC-F04 (ações em lote) com GUARD real `UnificadoBulkGuardTest` (entrou na allowlist do financeiro-pest). Endpoint genérico `POST /unificado/bulk` (baixar/categoria/plano_conta/cancelar/exportar_csv) — ownership Tier 0 de TODOS os ids (422 fail-closed), limite 500, audit trail Activity `bulk_*` com {action,ids,count,total}. Footer bulk ganha Plano lote + Cancelar lote (Sheet destrutivo com "N títulos totalizando R$ X" ANTES de aplicar — REGRA MESTRE valor) + Exportar CSV; "Marcar pago/recebido" migra do loop de N POSTs pro endpoint bulk (1 request). Categorizar lote migrado pro mesmo endpoint (rota antiga preservada back-compat). UC-F01..03 (fluxo backend venda→título→caixa) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-07 · [CC] revalidado (bump last_run): dark-mode legível — ~99 classes de tema-claro fixas (`text-stone-*`/`bg-stone-*`/`border-stone-*`/`bg-white`) → tokens shadcn dark-aware (`text-foreground`/`text-muted-foreground`/`border-border`/`bg-muted`/`bg-card`). SÓ cor (diff toca apenas strings de `className`; zero lógica/valor — REGRA MESTRE n/a). Prova ao vivo browser MCP: remap nas classes reais de 1312 elementos → tabela inteira legível no dark (antes: thead/colunas invisíveis, escuro-no-escuro). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
- 2026-07-10 · [CC] revalidado (bump last_run): pacote fidelidade proto (mandato [W] "zerar diferenças", charter v19) — (a) rodapé/toggles dark theme-aware (`white`→`var(--surface)` em fin-cowork.css; emoji 🗄→lucide Archive); (b) ícones opt-in nas abas da subnav; (c) segmented 12.5px/500/600 + sombra do ativo; (d) IBM Plex Mono nos números (KPI/Valor/rodapé); (e) doc-chip inline NFe/Doc (dado do payload, zero backend). SÓ apresentação (className/CSS/ícone; zero lógica/valor/rota — REGRA MESTRE n/a). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
- 2026-07-09 · [CC] revalidado (bump last_run): fix "duas cores" no header/footer da lista ([W] screenshot anotado) — remove o tint `bg-muted/30` do `<thead>` e `bg-muted/40` do rodapé de paginação, que no dark liam mais escuros que o card (2ª cor emoldurando topo/fim). Agora header/footer herdam a cor do card (1 cor), separados só pela régua `border-b`/`border-t`. SÓ cor/className (diff = 2 classes removidas; zero lógica/valor — REGRA MESTRE n/a). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
