---
id: resources-js-pages-financeiro-unificado-index-casos
casos: Financeiro Unificado · /financeiro/unificado
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — UC-FUNI-01..04 nascem neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
sdd: memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md
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

## UC-F05 · Pagamento sem vinculação bancária sinaliza "Conta indefinida" (US-FIN-038)
- **Persona:** Eliana [E] — precisa VER quais recebimentos/pagamentos foram baixados sem conta bancária (ADR 0175 permite), pra organizar o caixa depois — sem caçar linha a linha.
- **Aceite:** Dado um título com baixa cuja `conta_bancaria_id` é NULL · Quando a linha/drawer renderiza na Visão Unificada · Então a coluna Conta mostra o pill **"Conta indefinida"** (warning leve, CTA pra cadastro de conta) em vez de "—"; título com baixa vinculada ou sem baixa NÃO mostra o pill. Não altera valor/estoque.
- **Teste:** `UnificadoContaIndefinidaGuardTest` ("GUARD G1..G3") — DB MySQL real, lane `financeiro-pest.yml`.
- **Status: 🧪** (vira ✅ com veredito `pass` no manifesto G-7)

---

## Rastreabilidade UC → CU → US

> **Âncora:** `CU-FIN-01` · `CU-FIN-02` · `CU-FIN-03` · `CU-FIN-04` · `CU-FIN-05` · `CU-FIN-06` · `CU-FIN-07` · `CU-FIN-08` ([SDD do Financeiro](../../../../../memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md) §6.1)
>
> **Cobre:** `US-FIN-003` (baixa) · `US-FIN-013` (visão unificada) · `US-FIN-031` (lote, via UC-F04) · `US-FIN-038` (conta indefinida, via UC-F05)

| UC | Comportamento | Força | Âncora de contrato | Teste | Status |
|---|---|---|---|---|---|
| UC-FUNI-01 | Baixa parcial faz SPLIT e conserva o valor | must `[V0]` | `CU-FIN-02` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-02 | Baixa acima do aberto quita exatamente o aberto | must `[V0]` | `CU-FIN-03` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-03 | Quitado/cancelado recusa baixa | must | `CU-FIN-04` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-04 | Conta de outro business recusada | must `[T0]` | `CU-FIN-05` + ADR 0093 | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-F01 (legado) | Venda a prazo gera título a receber | must `[V0]` | `CU-FIN-01` + `US-FIN-013` | `RetencaoLoopE2ETest` | ✅ |
| UC-F02 (legado) | Recebimento baixa o título e entra no caixa | must `[V0]` | `CU-FIN-08` + `US-FIN-003` | `RetencaoLoopE2ETest` | ✅ |
| UC-F04 (legado) | Ações em lote respeitam tenant/limite/contabilidade | must `[V0][T0]` | `CU-FIN-07` + `US-FIN-031` | `UnificadoBulkGuardTest` | 🧪 |
| UC-F05 (legado) | Pill "Conta indefinida" na baixa sem conta | should | `CU-FIN-06` + `US-FIN-038` | `UnificadoContaIndefinidaGuardTest` | 🧪 |

> ⚠️ Os ids legados `UC-F0N` **não casam** com a régua estrita do `requisitos-status.mjs`
> (`UC-[A-Z0-9]{2,10}-\d{2,3}` exige o 2º hífen). Por isso esta tabela os cita **nominalmente**
> e a linha `> **Âncora:**` acima é o que liga os CU — renomeá-los exigiria editar
> `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`, fora da área deste chip.

---

## Contrato da BAIXA (SDD §6.1 · onda `sdd-from-source` 2026-07-27)

> Estes 4 UC derivam do **SDD `§6.1 CU-FIN-02..05`** (`memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md`),
> que por sua vez deriva de `US-FIN-003` + charter v12 + a decisão [W] 2026-06-04 (*"baixa parcial
> vira SPLIT"*) — **não** da leitura do `.tsx` nem do corpo do teste.
>
> ⚠️ **Todos são `[V0]`/`[T0]`** (REGRA MESTRE valor · multi-tenant). O assert é da **invariante de
> conservação**, não do nome do campo: `Σ(filhos.valor_total) + pai.valor_aberto == valor original`.
> Um assert em `status == 'parcial'` — o que o contrato antigo pedia — **já estaria falso hoje**.
>
> **Lane:** `PHP / Pest (Financeiro · MySQL)` — **required** (consta em
> `governance/required-checks-baseline.json`): reprovar aqui **bloqueia merge**.
> **Veredito:** 🧪 em todos — o trio nasce neste PR e quem dá o veredito é a lane, não esta leitura (G-7).

## UC-FUNI-01 · Baixa parcial faz SPLIT e conserva o valor `[V0]` `[must]`
- **Persona:** Eliana [E] — recebeu metade hoje; o que falta tem que continuar cobrável, ao centavo.
- **Aceite:** Dado título aberto de R$ V · Quando entra baixa de R$ B < V · Então nasce um título **FILHO** quitado de B (`titulo_pai_id` = pai), o **pai reduz** para `V − B` e **segue aberto**; e o valor fecha por **DOIS caminhos independentes**: (a) `Σ(filhos.valor_total) + pai.valor_aberto == V` e (b) `Σ(fin_titulo_baixas.valor_baixa) == B`. Não existe mais `status='parcial'`.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-01 · baixa parcial conserva o valor no split") — MySQL real, com **pré-condição anti-vácuo** (`cvExigeQueTenhaBaixado`: sem linha de baixa gravada o caso FALHA em vez de passar no vácuo).
- **Regressão que defende:** baixa parcial que "some" com centavo no arredondamento do split, ou split que duplica o valor (pai não reduz).
- **Status: 🧪**

## UC-FUNI-02 · Baixa acima do aberto quita exatamente o aberto `[V0]` `[must]`
- **Persona:** Larissa — digitou o valor errado no balcão; o sistema não pode inventar crédito.
- **Aceite:** Dado título aberto de R$ V · Quando a baixa pedida é > V · Então o registrado é **exatamente V** (`clamp` superior), `valor_aberto = 0`, **nunca negativo**, e o excesso **não** vira título-filho de crédito.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-02 · baixa acima do aberto quita exatamente o aberto").
- **Regressão que defende:** excesso virando crédito fantasma ou `valor_aberto` negativo contaminando KPI/DRE.
- **Status: 🧪**

## UC-FUNI-03 · Título quitado/cancelado recusa baixa `[must]`
- **Persona:** Eliana [E] — o histórico contábil é append-only; título fechado não se reabre por baixa.
- **Aceite:** Dado título `cancelado` (idem `quitado`) · Quando chega POST de baixa · Então **nenhuma** `fin_titulo_baixas` é criada, o `status` permanece, o `valor_aberto` **não muda**, e o usuário recebe flash `error`.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-03 · título cancelado recusa baixa").
- **Regressão que defende:** dupla-baixa do mesmo título inflando o recebido do mês.
- **Status: 🧪**

## UC-FUNI-04 · Conta bancária de outro business é recusada `[T0]` `[must]`
- **Persona:** qualquer — é invariante de plataforma, não de usuário ([ADR 0093]).
- **Aceite:** Dado `conta_bancaria_id` que pertence a **outro** business · Quando a baixa é submetida · Então a operação é recusada **fail-closed**: zero `fin_titulo_baixas`, `valor_aberto` e `status` intactos.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-04 · Tier 0: conta bancária de outro business é recusada") — precisa de 2 businesses semeados (a lane semeia biz=1 + biz=2).
- **Regressão que defende:** dinheiro de um tenant caindo na conta de outro.
- **Status: 🧪**

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
- 2026-07-27 · [CC] onda `sdd-from-source` (passo 5 · Onda 2): nasce o **SDD do módulo**
  (`memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md` — o Financeiro tinha 0 CU) e os UC de
  contrato da baixa **UC-FUNI-01..04** (`[V0]`/`[T0]`), derivados de `§6.1 CU-FIN-02..05`, com teste novo
  `BaixaConservacaoValorContratoTest` na allowlist da lane. Nenhum status promovido a ✅ — o veredito é
  da lane (G-7). **2 drifts registrados, não escondidos:** (a) os `it()` dos UC legados usam id no formato
  `UC-F0N`, que a régua estrita do `requisitos-status.mjs` (`UC-[A-Z0-9]{2,10}-\d{2,3}`) **não enxerga** —
  por isso a porta imprimia *"casos.md existe mas não declara nenhum UC"* para esta tela e acusava
  US-FIN-031/038 como "entregue sem contrato"; **não renomeei** porque UC-F01..03 são citados por
  `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`, fora da área deste chip. (b) o `it()` do
  `UnificadoBaixaDialogGuardTest` **G3** ainda se chama *"reduz valor_aberto e marca parcial"* embora o
  corpo asserte o SPLIT correto — descrição stale, corpo certo.
- 2026-07-13 · [Codex] revalidado (bump `last_run`): criação por empty state e por Cmd+K deixam de navegar para a rota legada e abrem o mesmo `TituloCreateSheet` do menu “Novo título”. Os UCs F01..F04 continuam sendo provas backend (venda→título→caixa e lote); a nova prova de intenção da tela é o contrato com cinco fluxos, validado pelos auditores estático e adversarial. Nenhum status ✅ foi promovido sem teste verde.
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
- 2026-07-14 · [CC] US-FIN-038 PR1/3 (Visão Unificada): novo **UC-F05** — pill "Conta indefinida" na coluna Conta (linha + drawer) quando a baixa tem `conta_bancaria_id NULL` (ADR 0175), CTA pra cadastro de conta. Backend `shapeTitulo.conta_indefinida` (booleano de exibição; zero valor/estoque — REGRA MESTRE n/a). GUARD real `UnificadoContaIndefinidaGuardTest` (G1 null→pill · G2 vinculada→sem pill · G3 sem baixa→sem pill) na allowlist financeiro-pest. ContasReceber/Cobranca herdam o componente nas PRs 2/3 (US-FIN-038 segue `doing`). UC-F01..04 intocados.
