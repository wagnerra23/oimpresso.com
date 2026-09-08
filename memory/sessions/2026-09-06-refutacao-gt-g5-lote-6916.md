---
date: "2026-09-06"
topic: "Refutação GT-G5 do lote PR #6916 — design-code-map lote 2 (Governance ×5 · Superadmin ×4 · RecurringBilling ×3)"
authors: ["C"]
prs: [6916]
---

# Refutação GT-G5 — lote #6916 (`claude/design-code-map-lote2`)

Refutador em sessão fresca (Fable 5.1), sem contexto do gerador. Tipo `anchors`, amostra 100%. Prompt canônico: *"Prove que este item está ERRADO. Busque evidência no código real (disco = origin/main + o lote), não no texto do PR."*

Base medida: `HEAD = 65e86705c0` (lote) · `origin/main = 4fbab283a7`. Zero git ops de estado.

## Checklist §3 (PROTOCOLO-REFUTADOR-BACKFILL)

- [x] Sessão fresca (sem nenhum contexto do gerador — não abri `*refutacao*` de outros lotes nem transcripts)
- [x] Modelo de tier SUPERIOR ao gerador (refutador Fable 5.1; gerador informado como Opus/Sonnet pelo parent — igualdade só no tier máximo)
- [x] Amostra: 100% anchors (tipo `anchors`; sem prosa amostrada, sem seed necessária)
- [x] Cada item verificado contra o código real em disco (origin/main + lote), não contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff — linhas `+` de `memory/requisitos`, com controle positivo sintético por padrão
- [x] `error_rate_pct` calculado
- [ ] Entry no ledger — NÃO é deste artefato (parent escreve)

## Escopo medido (`git diff --name-status origin/main...HEAD`)

| Status | Arquivos |
|---|---|
| A | 12 `*-gap.md` — Governance ×5 (`audit`, `dashboard`, `drift-alerts`, `module-grades`, `policies`) · Superadmin ×4 (`assinaturas`, `dashboard`, `negocios`, `pacotes`) · RecurringBilling ×3 (`cobranca-recorrente-{configuracoes,faturas,planos}`) |
| A | 12 `*.map.json` homônimos |
| M | `memory/requisitos/Cliente/clientes.map.json` |
| M | `scripts/design-sync/state/application-report.json` · `scripts/design-sync/state/applications.json` |

Total: 27 arquivos, batendo com o escopo anunciado.

## Regra de contagem adotada

- **Item** = 1 linha da tabela `| Parte | Estado no vivo | Ação |` (cada gap.md), + 4 itens de frontmatter por gap.md (`tela_viva` existe · `prototipo` existe · `tela:` resolve pela âncora · charter sem REVOGADA), + 6 itens por `map.json` (`prototipo.arquivo` · `vivo.arquivo` · `linhas` batem com a célula · `vivo.ancora` presente · `prototipo_sha` recomputado · `acao` == célula), + 1 item por máquina (4), + 2 itens do `clientes.map.json`.
- **Tolerância de linha:** citação `arquivo:NNN` é CONFIRMADA se `sed -n NNNp` cai **dentro do mesmo elemento/statement** que a célula descreve (ex.: tag de fechamento do `<Badge>` citado); é REFUTADA se cai em código não relacionado ou se o range não contém o que a célula afirma.
- **`Decidir.`** só passa se: mockup desenha capacidade concreta (não harness de mock), ausente no vivo (grep reproduzido), e nenhum charter Non-Goal/Anti-hook, RUNBOOK, SPEC, ADR ou comentário no `.tsx` já a proíbe ou decide. Os 3 protótipos deste lote se declaram **porte reverso** (`governance-page.jsx:1-3` "Espelha as telas vivas"; `superadmin-page.jsx:1-2` "Traduz o Blade legado"; `cobranca-recorrente-page.jsx:2` "Reescreve a RecurringBilling do git") — cada `Decidir.` foi julgado contra isso: o que o retrato **acrescenta** ao vivo conta; o que só espelha, não.

## Grupo 1 — Frontmatter dos 12 gap.md (48 itens)

| Verificação | Como mediu | Resultado |
|---|---|---|
| `tela_viva` existe | `[ -f ]` nos 12 paths | 12/12 OK (211…1052 ln) |
| `prototipo` existe | `[ -f ]` em `governance-page.jsx` (442) · `governance-telas.jsx` (321) · `superadmin-page.jsx` (1446) · `cobranca-recorrente-page.jsx` (378) | 4/4 OK — os 3 gap.md de Governance que citam `+ governance-telas.jsx` batem com `governance-page.jsx:5` ("Auditoria, drift e notas vivem em governance-telas.jsx") |
| `tela:` resolve pela âncora | `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` ×12 | 12/12 `âncora ✓` — Governance ×5 via `bundle_source: governance-page.jsx` (charter L4); Superadmin ×4 via `related_prototype: prototipo-ui/cowork/superadmin-page.jsx` (charter L5); RecurringBilling ×3 via `-page.jsx` do bundle (`cobranca-recorrente-page.jsx`; charter só tem `visual_source`, e o resolvedor aceita) |
| Charter sem âncora REVOGADA | `grep -n REVOG` nos 12 charters | 0 hits em todos (o único `IRREVOGÁVEL` em `Audit.charter.md:40` é Non-Goal de append-only, não âncora) |

**48 itens · 48 confirmados · 0 refutados.**

## Grupo 2 — Linhas da tabela (por gap.md)

### Governance/Audit — 10 linhas · 10 confirmadas
Vivo: `Audit.tsx:78` SubNav · `:79-83` PageHeader (`:82` texto append-only) · `:85-89` 3 KpiCard · `:92-156` 4 Select (`:100-103` 1h/24h/7d/30d · `:148-150` só `ok`/`error`) · `:65-74` `router.get` com `only:['entries','kpis','filters']` · `:161-164` EmptyState · `:166-197` tabela · `:202-204` rodapé teto 200 · `:209` AppShellV2 · `:17-26` `Entry` com `user_id` · `:33-48` `Props` sem total do período · `AuditController.php:72` `kpisFor($entries)`. Mockup: `governance-page.jsx:24-30` VIEWS · `:403-418` header · `:420-424` tabs · `governance-telas.jsx:63-64` `limpo()`/`temFiltro` · `:71-73` Nota imutável · `:106` botão Limpar · `:107` hint 30d · `:111` sub "de N no período" · `:120` botão no vazio · `:147-149` "o período tem N" · `governance-data.jsx:122` `RES_LABEL` 4 valores · `:128-134` gerador com `denied`/`quota_exceeded`. Charter: `Audit.charter.md:22,30` "status ok/error" · `:53` hint do limite. Recibos: os 4 greps reproduzidos → 0/0/0/0. Os 2 `Decidir.` (Limpar filtros · contagem além do teto) são capacidade concreta do retrato, ausente no vivo, sem Non-Goal contrário (`:43` só veda histórico >30d em distinct; `:60` veda eager-load sem limit, não `count()`).

### Governance/Dashboard — 17 linhas · 17 confirmadas
Vivo: todas as 60+ citações sondadas (`:134 complianceColor` · `:141 modeBadge` · `:170 severityBadgeClass` · `:204 MCP_PRESET_LABEL` · `:238-260 McpCallsChart` c/ EmptyState `:254` · `:364 aba` · `:372-400` 4 KPIs · `:403-419 SubNav` · `:448-596` 4 blocos c/ EmptyState `:457/:489/:526/:565` · `:616-624 only:['mcp','mcp_filters']` · `:656-693` De/Até `type="date"` + Aplicar · `:698-701` "Atualizando" · `:702-718 <Deferred data="mcp">` · `:743 SubNav` · `:745-753 PageHeader` · `:797-803` card Compliance · `:806-844` SDD c/ `:838-842` "Sem snapshot" · `:846-882` saúde c/ null-guards · `:884-916` ADRs c/ links · `:918-957` highlights (`slice(0,10)` em `:934`) · `:959-996` narrativas · `:992-995 mcp_enabled &&` · `:998-1027` 4 atalhos (hrefs contados: policies/audit/drift/adr proposto) · `:1030-1047` 8 links (contados: 8 `href=`) · `:1050 layout`). `DashboardController.php:65` e `:268` = `$compliancePct = (7 * 10) + (2 * 5) + 0`. Mockup: `governance-page.jsx:74-102 Conformidade` · `:104-122 CardSdd` · `:129-146` Saúde+narrativas · `:157-180` Decisões+Ocorrências · `:196-259` seção MCP · `:273-275` KPIS_CONST · `:284-289 semPermissao` · `:383-387 CENARIOS` · `:410-414` segmento; `governance-data.jsx:11-22 ARTIGOS` · `:24-30` · `:55-59` · `:95-99`. Charter v3: Non-Goals `:56` histórico longo · `:62` drill-down por chamada; Anti-patterns `:81` tablist; Anti-hooks `:89-90` Deferred + gate herdado; Goals `:48` degradação graciosa. Recibos: 8 greps reproduzidos com os mesmos números (0 · 1 em `:748` · 1 em `:81` · 0 · 2 em `:98`/`:325` · 0 · 0 · 0). O único `Decidir.` (régua por artigo + selo "auto-declarado") é acréscimo do retrato sobre um vivo de card único com valor literal no controller — sem Non-Goal contrário.
- Observação (não conta): `:936` e `:940` na linha "Ocorrências 24h" caem 2 ln abaixo do `slice(0,10)` (`:934`) e no fechamento do `<Badge>` (`:937-940`) — dentro do mesmo elemento; tolerância aplicada.

### Governance/DriftAlerts — 10 linhas · 10 confirmadas
Vivo: `DriftAlerts.tsx:33-43 Props` · `:45 GH_BLOB` · `:48-53 severityVariant` · `:55-60` props síncronas · `:63-64` SubNav (comentário key `drift`) · `:65-69 PageHeader` · `:71-98` 4 KPIs (`:74/:80/:87` tone) · `:100-158` card drift (`:104-106` Badge · `:109-110` EmptyState · `:117-119` Badge · `:122-128` ul · `:129-131` hint · `:132-151` botões-link) · `:160-184` sem SCOPE (`:165` · `:170-179` Button asChild) · `:186-220` histórico (`:190-192` · `:195-200` vazio · `:202-217` ul) · `:225 layout`. `DriftAlertService.php:154` `\Log::error('DriftAlertService: YAML parse falhou em SCOPE.md'`. Mockup `governance-telas.jsx:159-223` DriftView (`:162-163` setTimeout 800 · `:169-173` KPIs · `:176-197` lista · `:178` Esqueleto · `:179-180` Vazio done · `:198-203` YAML_ILEGIVEL · `:207-212` sem SCOPE · `:214-217` histórico). Charter: `:42-43` Auto-fix/Suprimir · `:53` YAML "log estruturado, UI não quebra" · `:59` "atualmente síncrono" · `:60` ignorar. Recibos: 4 greps → 0/0/0/0. O `Decidir.` (expor módulo com YAML ilegível na tela) — o charter decide o log, e o retrato acrescenta a superfície de UI; não é reabertura.

### Governance/ModuleGrades/Index — 12 linhas · 12 confirmadas
Vivo: `Index.tsx:126 scoreColorClass` · `:136-137 useState` (sem localStorage) · `:150-159 Head+PageHeader` · `:151 SubNav` · `:161-165` toggle ViewTab · `:174-176 <Deferred data="kpis">` · `:178-202` filtros · `:204-272` tabela (`:208-209 overflow/min-w-[1100px]` · `:221-224 th title` · `:229-234` vazio colSpan · `:237 hover` · `:239-241 Link` · `:253-256 —` · `:257-263` Ações) · `:274-298` banner (**4 `href=` contados**) · `:300-306` rodapé (slugs 0153/0154/0155 contados) · `:313-342 FilterChip` · `:344-360 KpiBar` · `:362-380` skeletons · `:384-397 ViewTab` · `:401-511 CatalogSignalsView` · `:513 layout`; `:134-311` sem `<form|router.post` (0). Mockup `governance-telas.jsx:224-320 NotasView` (`:230` setTimeout 950 · `:252-255` KPIs · `:259-268` chips+busca · `:274` Esqueleto 8 · `:275-278` Vazio + `:277` Limpar · `:279-306` tabela · `:285` title · `:298` tooltip · `:307` p travessão · `:310-315` gate texto sem `href`); `governance-data.jsx:186-192 FAIXAS` (Médio min 50). Charter: Goals `:20,:23,:25,:26,:27` · Non-Goals `:31-33` · UX `:41` empty state · Anti-hooks `:49` localStorage. Recibos: 6 greps → todos 0. O `Decidir.` (Limpar no vazio) é acréscimo concreto; charter `:41` pede só o empty state.

### Governance/Policies — 9 linhas · 9 confirmadas
Vivo: `Policies.tsx:47 overrides` · `:52-66 toggle` (`:55` otimista · `:60-63 onError` c/ `:62 toast.error`) · `:70 SubNav` · `:71-75 PageHeader` · `:77-82` 4 KPIs · `:84-85 EmptyState` · `:84-118 map` · `:94-96 Switch` (disabled pendente) · `:102-110` linha · `:123 layout`. `PoliciesController.php:65` `back()->with('status', "Policy #{$id} ativada/desativada")` · `app.tsx:55-60 showFlashToast` · `HandleInertiaRequests.php:99-113` lê `status.msg`/`status.success` — o hedge da célula ("se chega ao React é smoke") é honesto: o controller manda string, o middleware espera array. Mockup `governance-page.jsx:302-306 filtradas` · `:317-320 alternar` (`:319 toast`) · `:324-329` KPIs · `:331-335` aviso histórico · `:337-342` busca · `:346-348` Vazio + Limpar busca · `:350-351 h3` c/ contador · `:354-371` linha · `:436` toast. Charter: `:30` ordenação · `:53` flash · `:61` esconder desligadas · `:62` TODO history. Recibos: 5 greps → 0. Os 2 `Decidir.` — aviso de "sem rastro" (charter `:62` decide que histórico é TODO, não decide sobre avisar) e busca local (charter `:61` proíbe esconder desligadas, não busca) — não reabrem decisão.

**Governance: 58 linhas · 58 confirmadas · 0 refutadas.**

### Superadmin/Dashboard/Index — 19 linhas · 19 confirmadas
Vivo `Dashboard/Index.tsx`: `:12-14` comentário (funil/churn/receita-por-pacote fora de propósito, UC-SADASH-05) · `:97-107 KpiEsqueleto` · `:112-115 trocarPeriodo` · `:119 PageHeader` descrição fixa · `:121-141` segmented + `:140 janela.rotulo` · `:144-166 <Deferred>` · `:179-186 KpisDoPeriodo` · `:191-194 KpiSemAssinatura` · `:197-213 KpiMrr` (`:200/:210` canceladas) · `:216-253 Tendencia` (`:225` copy travada · `:232-235` vazio) · `:261-264` header recentes · `:266-267` vazio · `:269-291` tabela 3 colunas. Fontes `SuperadminDashboardService.php:48 countNotSubscribedBusinesses` · `:63 buildMonthlyRevenueChart` · `:106 statsForPeriod` · `:170 calcularMrr` (as `(:N)` da célula apontam pro arquivo da fonte nomeada) · `SuperadminController.php:129 recentesPayload` · `:144-146` só nome/criado/assinatura · `SuperadminDashboardContratoTest.php:188-190` `missing('funil'|'churn'|'receitaPorPacote')` · `PageHeader.tsx:46-50 moduleNav`. Mockup `superadmin-page.jsx:599-759 ViewVisao` (`:609` sub computado · `:611-612 __selectRoute` · `:621` "encerra em 18/08/2026" literal · `:625-628` KPIs (`:628` "+8,4%" fixo) · `:696` Cobrar/Converter · `:706` "de vendas" · `:721-727` fazer primeiro · `:735` Ver todos · `:739` 6 `<th>` · `:743` cidade · `:748` mrr). Contrato `superadmin-dashboard.contract.json` = 4 seções (`periodo/kpis/tendencia/recentes`, contadas). Charter: `:71` "Coluna dono" sob "Alvo do F1 ainda não entregue" · Non-Goals `:73-83` · Anti-hook `:135` "Não inventa número" · `:138` MRR saiu 2026-08-20 · `:152-197` Pendências/REGRA MESTRE. RUNBOOK-dashboard §1 `:51-56` (funil ❌ SA-O1b · churn ✅ canceladas · motivos `churn_reason` · receita ❌ SA-O1b · fila 🟡 `findOverdueApproved()` · fazer-primeiro ❌ sem onda). Recibos: 11 greps → todos 0 (funil/churn só no comentário `:12`). Os 2 `Decidir.` (subtítulo com contagens · colunas extras dos recentes): nenhum charter/RUNBOOK/SPEC decide sobre eles (grep `subtítulo|Dono.*Pacote|cidade` → 0 decisões); "coluna dono" é alvo declarado não entregue, compatível com "construir ou rejeitar".

### Superadmin/Pacotes/Index — 16 linhas · 16 confirmadas
Vivo `Pacotes/Index.tsx`: `:20-21` comentário LEITURA/SA-O4d · `:59-82` moeda/ciclo · `:91-93 limite` · `:95-107 PacotesIndex` (`:98 PageHeader` fixo · `:100 data-contract` · `:101 <Deferred>`) · `:111-119 GridEsqueleto` · `:121-144 Grid` (`:124` vazio · `:138` grid 2/3) · `:146-201 CartaoPacote` (`:148 opacity-60` · `:150-158` header · `:161-168` preço · `:174-180` ul · `:183-191` módulos · `:193-198` footer); `:134-311`-equivalente sem `<form|router.post` (0). `PackagesController.php:90` `$assinantes = DB::table('subscriptions')` · `:110 map` · `:141 'assinantes'`. `_components/assinatura.tsx:25 plural`. Mockup `:1176-1249 ViewPacotes` (`:1178 useToast` · `:1180 lim` · `:1183 per` · `:1186` sub computado · `:1192-1194` grid/off · `:1204-1209 Kebab` · `:1213-1236` seções) · `:13 BRL`. Charter: Non-Goal `:64` · Anti-hooks `:83,:85` · §Divergências `:103-105` (FormDrawer SA-O4d · kebab · migre §5.3). RUNBOOK-pacotes `§5.1 :72` · `§5.3 :98` · `§8 :143-147`. SPEC `US-SUPER-002 :49` "LEITURA … SA-O4d". Contrato `superadmin.pacotes.grid` + `_nota_recorte`. Recibos: 8 greps → 0 (FormDrawer só no comentário `:20`). O `Decidir.` (subtítulo) não tem decisão registrada em lugar nenhum.

### Superadmin/Assinaturas/Index — 21 linhas · 21 confirmadas
Vivo `Assinaturas/Index.tsx`: `:123-130 COLUNAS` (`:124 id:null`) · `:134 moeda` · `:137-152 irPara` · `:154-156` ordenação · `:167 PageHeader` fixo · `:175/:181/:221-229 <Deferred>` · `:180-218` filtros · `:255-263 KpisEsqueleto` · `:277-296 KpiLinha` (`:284`, `:290-294`) · `:299-311 FiltroPacote` · `:313-338 Cabecalho` · `:358-372` vazio duplo · `:382-437` tabela (`:424 moeda.format`) · `:440-467` paginação · `:476-479` comentário comprovante · `:481-499 Kebab` (`:490-492` Ver negócio) · `:521-537 ACOES` · `:543-551 GavetaDeAcao` · `:560 put` · `:562-563 post` · `:577-623` modo status (`:591-622` cancelar) · `:625-665` vigência. Controller `SuperadminSubscriptionsController.php:59 opcaoValida` · `:76 ORDENS` · `:124 kpisPayload` · `:208 orderBy(self::ORDENS…)` · `:212 paginate(20)` · `:250 create` · `:267 store` (+`can('subscribe')` em `:269`) · `:414-417` `status` c/ `success`+`msg` · `:519-521`. `app.tsx:55 showFlashToast` · `:75 router.on('success')`. Mockup `:557-590 AssinaturaForm` · `:1044-1168 ViewAssinaturas`. Charter: Goals `:50,:51,:55,:60,:63` · Anti-hooks `:85` Bloqueada · `:90 orderBy` · `:91 status direto` · `:96` 5 status · §Divergências `:119-123`. RUNBOOK-assinaturas `§3 :79` · `§7 :149-156`. Contrato 5 seções + `_papel` + `_nota_recorte`. `Index.casos.md` tem UC-SAASS-08/09/11/12 + UC-SA-009. Recibos: 9 greps → 0 (comprovante só em `:476`, comentário). 0 `Decidir.` — coerente: as divergências estão todas decididas em charter §Divergências / RUNBOOK §7.

### Superadmin/Negocios/Index — 28 linhas · 28 confirmadas
Vivo `Negocios/Index.tsx`: `:45-55 NegocioLinha` sem `mrr` · `:134-146` atalho `/` · `:148-159` debounce · `:162-168 abrir` · `:179-186 esc` · `:196-210 irPara` · `:217 PageHeader` fixo · `:220-226 Input` · `:228-234` filtros · `:236-248` Limpar · `:252/:258 <Deferred>` · `:268-280 FiltroPacote` · `:298-315` vazio duplo · `:324-366` tabela (`:326-333` th estáticos · `:337-341` linha clicável) · `:369-381` paginação · `:407-418 DrawerEsqueleto` · `:438-462 Uso` (`:442` UC-SA-006) · `:465-473` não encontrado · `:478-548` drawer (`:484-487` só data · `:495-500` Assinatura · `:501-505` nota · `:508-514` Uso · `:516-525` Dono · `:527-545` Histórico). `BusinessController.php:188 orderByDesc('business.id')->paginate(20)` · `:228-300 detalheDoNegocio` (0 `created_by`) · `:362 create` · `:401 store` · `:499-501 created_by` no `show()` legado. Mockup `:762-832` drawer (`:778` "por {n.criador}" · `:792` Recorrência · `:826`) · `:844-857 Uso` · `:859 NEG_PAGE=6` · `:861-873` estado local · `:919-1035 ViewNegocios` (`:921` Exportar→toast · `:932 kbd` · `:960-964 SortTh` · `:983/:987` sub-linhas · `:1022-1035 Confirm`). Charter: Non-Goals `:59` lote · `:60-61` "entrar como … D1 em aberto" · §Contrato visual `:93-98` · Anti-hooks `:114,:116` · Pendências `:129-133` (4 de 109 · ordenação em aberto). RUNBOOK-negocios `§2 :44` · `§6 :95-102` · `§7 :104-130` (create/store migração · edit/update decisão [W] · destroy/toggleActive GET). Contrato 3 seções + `_nota_recorte` (1 `exportar`, na BulkBar). Recibos: 13 greps → 0. Os 4 `Decidir.`: subtítulo/Exportar/criador — grep `Exportar|CSV|criador|created_by|subtítulo` nos 4 charters + 4 RUNBOOKs + SPEC + BRIEFING do Superadmin → só decisões sobre **Assinaturas** (RUNBOOK-assinaturas §7) e Usuario360, nada sobre Negocios; ordenação — charter `:133` e RUNBOOK `:102` dizem "em aberto"/estado atual, sem veredito.

**Superadmin: 84 linhas · 84 confirmadas · 0 refutadas.**

### RecurringBilling/Planos/Index — 11 linhas · 11 confirmadas
Protótipo: `cobranca-recorrente-page.jsx:332-342 Placeholder` · `:344-348 TABS` · `:367 window.PageHeaderNav` · `:370` desc "CRUD … + distribuição por ciclo … drawer lateral … Espelha /recurring-billing/planos do git" · `.css` `var(--accent)` 10 / zinc-violet 0. Tela-mãe `cobranca-recorrente-gap.md` existe (versionada). Vivo `Planos/Index.tsx`: `:50 trial_days` no tipo · `:77 per_page` · `:172-203 CicloDistribuicao` · `:205-217 StatusBadge` (`:207 bg-success-soft`) · `:219-226 FiscalBadge` · `:228-243 FlashBanner` (`:236`) · `:277-313` atalhos (`:282-284 /` · `:287-299 j/k` · `:300-302 n`) · `:331-337 handleSearch` (`:334 per_page`) · `:339-353 handleDelete` (`:344 confirm(` · `:346 router.delete` · `:350 reload flash`) · `:361-402` header (`:364-366 h1` · `:367-375` sub mono · `:378-383 Link` · `:384-391 ?` · `:392-399 Link bg-primary`) · `:404 FlashBanner` · `:407-445` KPIs (`:411-422` c/ sparkline `:417-421`) · `:447-449` ciclo · `:457-481 form` busca · `:483 Deferred plans` · `:488-560` tabela (`:502-509` linha ativa `:507 bg-primary/50` · `:511-529` colunas · `:536-556` ações · `:538-545 Link`) · `:566-575` rodapé · `:580-603` overlays · `:612-631 EmptyState` (`:615 bg-primary/10`) · `:633-658` skeletons. `PlanController.php:303 buildKpisPayload` · `:330 $distribuicao`. `web.php:94-109` rotas planos (index/novo/store/editar/update/destroy). `Create.tsx:19,52` + `Edit.tsx:26` `trial_days` (5/5). `Index.tsx:533-539` da mãe faz `router.visit` por aba. Charter: `:56-62` Non-Goals (drag/clonar/histórico/toggle/filtros/CSV/confirm) · `:79` Anti-pattern Modal pra Create/Edit. Recibos: 11 greps reproduzidos com os mesmos números (0/47/0/0/0/0/0/`:344`/0/0/1). 0 `Decidir.` — coerente com placeholder atrás do vivo.

### RecurringBilling/Faturas/Index — 12 linhas · 12 confirmadas
Vivo `Faturas/Index.tsx`: `:38` 5 status · `:102-109 dueLabel` · `:111-132 STATUS_STYLES` (`:128 refunded`) · `:134-138 GATEWAY_STYLES` · `:175-189 GatewayBadge` · `:208-213 heroTone` · `:251-277` skeletons · `:283-356 CancelDialog` (`:303 bg-destructive…` · `:315-318` aviso · `:322-332` motivo · `:341 Voltar` do dialog) · `:379 installPrintStyles` · `:387-405` atalhos (`:392-394 /`) · `:407-417 applyFilters` · `:419-435 handleCancel` (`:431 reload`) · `:445-476` header (`:448-450 h1` · `:451-459` sub · `:462-473` botão disabled) · `:479-519` KPIs (`:483 "Pago este mês"` · `:489-494` sparkline) · `:522-607` filtros (`:525-544` pills · `:547-562` gateway · `:565-580` período · `:583-599` busca · `:601-605` contador) · `:611 Deferred invoices` · `:612-621` vazio · `:624-694` tabela 8 colunas (`:663 text-destructive-fg` · `:676-686` Cancelar · `:687-689` traço) · `:700-736` paginação · `:740-744` nota · `:758-759` overlays. `InvoiceController.php:60-63 kpis defer` · `:92 is_cancelavel open/overdue` · `:113 cancel()`. `web.php:128-129 rb-invoices.cancel`. `C6Driver.php:80 cancelar()` · `:85 BadMethodCallException`. `SPEC.md:549-554` US-RB-042 `_parcial_`. Charter Faturas: `:35,:47` "zinc" (2) · `:59-66` 8 Non-Goals. Recibos: 11 greps → 0/65/2/0/0/0/0/0/0/`:80,:85`/0-10. 0 `Decidir.`.

### RecurringBilling/Configuracoes/Index — 8 linhas · 8 confirmadas
Vivo `Configuracoes/Index.tsx`: `:4` comentário aponta `prototipo-ui/prototipos/recurring/recurring-page.jsx` (`ls` → No such file, confirmado) · `:97-122 SEVERITY_TOKENS` · `:154-169` atalhos · `:177-203` header (`:180 bg-primary/10` · `:184-186 h1` · `:187-189` sub · `:193-201 ?`) · `:208-216 SectionCard` gateways (`:213 Deferred gateways`) · `:219-257` dunning (`:224-226` · `:229-251` map · `:243-245` selo · `:254-256 editavel_em`) · `:260-297` NFe (`:279-281` rótulo · `:282-284 us_ref` · `:287-289` Em breve · `:292-296`) · `:300-310` webhooks · `:316 CheatSheet` · `:327-441 TOUR_STEPS_CONFIG` (`:360-369` ←/→ · `:383,:422 bg-primary`) · `:474-551 GatewaysContent` (`:475-494` vazio · `:504-509` sigla · `:510-524` · `:525-535` badge · `:540-548 a` CTA) · `:553-616 WebhookCard` (`:556-568 handleCopy` · `:572-578` · `:579-587 a` docs · `:590` rótulo · `:592-609` URL+Copiar · `:611-613` auth) · `:622-637 GatewaysSkeleton`. `ConfiguracoesController.php:18` comentário +3d/+7d/+15d · `:36-38` eager · `:49-76 regua_dunning` · `:81` comentário · `:83-84 nfe_auto ativo=false` · `:87` "fiscal_type = nfe ou nfse" · `:96-114 webhooks` · `:117-137` gateways defer. `SPEC.md:588` US-RB-044 NFe55 · `:766-771` US-RECURRINGBILLING-004 `_parcial_`. Charter: `:43` "Tailwind 4 puro … (zinc" (grep → `:43`) · `:49-54` 6 Non-Goals · `:72 config_json`. Recibos: 14 greps → 0/54/1/0/0/0/`:87`/0/0/0/0/0/0/No such file. 0 `Decidir.`.

**RecurringBilling: 31 linhas · 31 confirmadas · 0 refutadas.**

**Grupo 2 total: 173 linhas · 173 confirmadas · 0 refutadas.**

## Grupo 3 — map.json ×12 (72 itens = 6 × 12)

| Verificação | Como mediu | Resultado |
|---|---|---|
| `prototipo.arquivo` / `vivo.arquivo` existem | loop `existsSync` sobre o set de arquivos de todas as partes dos 12 maps | 0 MISSING. Governance: as partes de auditoria/drift/notas apontam corretamente pra `governance-telas.jsx` (8+8+9 partes) e as de header/abas pra `governance-page.jsx` |
| `prototipo.linhas` / `vivo.linhas` batem com a célula homônima | dump id→P/V dos 12 maps (173 partes) cruzado com as células lidas no Grupo 2 | 173/173 batem. Convenção observada e coerente: parte cujo mockup a célula declara **ausente** recebe o range do componente-raiz (`governance-page.jsx:389-442` = `function GovernancePage` … `Object.assign(window,…)`; `superadmin-page.jsx:599-759/860-1043/1044-1175/1176-1249` = as 4 views; `governance-telas.jsx:224-320` = `NotasView`) e vivo `n/a (…)` quando a célula é recibo de ausência |
| `vivo.ancora` string ⇒ `data-contract` presente no `.tsx` | `includes` de `data-contract="<id>"` por parte | 11/11 presentes (`superadmin.assinaturas.{kpis,filtros,tabela,form,paginacao}` · `superadmin.dashboard.{periodo,tendencia,recentes}` · `superadmin.negocios.{tabela,paginacao}` · `superadmin.pacotes.grid`) |
| `prototipo_sha` recomputado | `node prototipo-ui/gerar-map.mjs <gap.md>` (stdout, sem escrita) × committed | 12/12 IGUAL (`5ee328e84ed4` ×3 page+telas · `58c7fc89b9ba` ×2 page · `c3ba0e10c548` ×4 · `40e51d3a1e6a` ×3) |
| `acao` == célula Ação sem `**` | dump `acao` × coluna Ação | 173/173 (os `Decidir.` aparecem sem asteriscos) |
| `gap_fonte` aponta pro gap.md homônimo | dump | 12/12 |

**72 itens · 72 confirmados · 0 refutados.**

## Grupo 4 — Máquinas + estado do design-sync (5 itens)

| Item | Comando | Resultado |
|---|---|---|
| M1 | `node scripts/governance/design-code-map-check.mjs --check --strict` | rc=0 — "[OK] nenhum map.json com âncora quebrada ou sha stale. 2 âncora(s) TODO pendente(s)" (as TODO são de maps fora do lote) |
| M2 | `node scripts/governance/doc-id-index.mjs --check-collisions` | rc=0 — 0 colisão em 2608 ids |
| M3 | `node scripts/governance/requisitos-status.mjs RecurringBilling --check` | rc=0 — `_STATUS-GENERATED.md` em dia |
| M4 | `node scripts/design-sync/status.mjs --check-mapping` | rc=0 — as 12 telas listadas em `[COMPARED]` com o `mapa:` correto (contadas: 12) |
| S1 | `scripts/design-sync/state/*.json` coerentes com `origin/main` | **REFUTADO** — ver lista abaixo |

**5 itens · 4 confirmados · 1 refutado.**

## Grupo 5 — `Cliente/clientes.map.json` (2 itens)

- `git diff origin/main -- memory/requisitos/Cliente/clientes.map.json` → **1 linha**: `prototipo_sha: sha256:8f284ad79fb3 → sha256:2be4c00c452a`; nada mais mudou. ✓
- Recomputo por `node prototipo-ui/gerar-map.mjs memory/requisitos/Cliente/clientes-gap.md` (stdout) → `sha256:2be4c00c452a` = committed. ✓ (não rodei `--atualizar` porque escreve; o sha do esqueleto é o mesmo cálculo `computeProtoHash`)

**2 itens · 2 confirmados · 0 refutados.**

## Lista completa dos REFUTADOS

1. **[Grupo 4 · S1] `scripts/design-sync/state/application-report.json` foi regenerado de uma base que não contém o `origin/main` atual, contradiz o main e CONFLITA no merge.**
   - Evidência: `git merge-base origin/main HEAD` = `80bc4ef8b9`; `git merge-base --is-ancestor 29fdc3e0f0 HEAD` → **NÃO** (o #6903, que gravou o recibo CI de `arquivos-page.jsx → resources/js/Pages/Arquivos/Index.tsx` às 11:25:39Z, não está na branch). O único commit do lote que toca o state é `65e86705c0` (09:08, `status.mjs --mark-compared`).
   - `git diff origin/main -- scripts/design-sync/state/application-report.json`: `"tested": 4 → 3`, `Arquivos/Index lifecycleState "tested" → "applied"`, `tested: true → false` — números derivados de uma foto anterior ao #6903.
   - `git merge-tree --write-tree origin/main HEAD` → rc=1, **`CONFLICT (content): Merge conflict in scripts/design-sync/state/application-report.json`** (2 hunks, marcadores em `:3-7` e `:21-32` da árvore simulada `28720f05c6`). `applications.json` auto-mergeia e o recibo do Arquivos **sobrevive** (`tests=1`, 20 entries) — o dano é só no relatório derivado, mas ele bloqueia o merge como está.
   - Porquê é erro (e não só observação): o protocolo mede contra `origin/main`; o artefato committed afirma `tested: 3` quando o main tem 4, e o PR não mergeia sem intervenção. Conserto: `gh pr update-branch` (ou rebase) + re-rodar `node scripts/design-sync/status.mjs --mark-compared` / regenerar o report — o conteúdo dos 12 gap.md/map.json não muda. Família §5 2026-08-03 ("despachar escrita sobre base que envelheceu sozinha").

## Observações que NÃO contam como erro

- Dashboard (Governance) linha "Ocorrências em 24 h": `:936` e `:940` caem 2 ln abaixo do `audit_highlights.slice(0, 10)` (`:934`) e no fechamento do `<Badge>` (`:937-940`) — dentro do mesmo elemento; tolerância aplicada.
- Superadmin: três `data-contract` existem no `.tsx` e o map deixou `ancora: false` na parte correspondente — `superadmin.dashboard.kpis`, `superadmin.negocios.busca-filtros`, `superadmin.negocios.drawer`. Âncora não-declarada é fragilidade (linha-only), não drift; o gerador nasce `false` por desenho e a promoção é preenchimento humano.
- Os 3 protótipos são porte reverso; todos os 11 `Decidir.` do lote foram julgados como **acréscimo do retrato** sobre o vivo (Limpar filtros ×2 · contagem além do teto · régua por artigo · nota YAML ilegível · aviso sem rastro · busca local · subtítulo com contagens ×3 · colunas extras · Exportar · ordenação · criador) e nenhum reabre Non-Goal/Anti-hook/RUNBOOK/SPEC — grep de decisão feito nos 4 charters + 4 RUNBOOKs + SPEC + BRIEFING do Superadmin e nos charters do Governance.
- Os gap.md do RB registram "Base medida: origin/main 80bc4ef8b9" — é o merge-base real; fato datado honesto, e todas as linhas seguem batendo no HEAD do lote.
- Policies "Feedback do toggle": o controller manda `status` como **string** e `HandleInertiaRequests.php:99-113` lê `status.msg`/`status.success` — o hedge da célula ("se chega ao React é smoke") está correto e é honesto; não é achado deste gap.

## Scan PII (linhas `+` do diff em `memory/requisitos`, 3.291 linhas)

| Padrão | Regex | diff | controle positivo (sintético) |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 0 | 1 |
| CPF cru (11 dígitos) | `(^|[^0-9])[0-9]{11}([^0-9]|$)` | 0 | 1 |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 0 | 1 |
| Telefone | `\(?[0-9]{2}\)? ?9?[0-9]{4}-[0-9]{4}` | 0 | 1 |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 0 | 1 |
| `R$`+dígito | `R\$ ?[0-9]` | 0 | 1 |

`pii_hits = 0`, cada padrão provado vivo no controle (6/6).

## Comandos reproduzíveis

```bash
git diff --name-status origin/main...HEAD
node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork          # x12
grep -n REVOG <charter>                                                          # x12 -> 0
sed -n '<range>p' <arquivo>                                                      # cada citacao do Grupo 2
node prototipo-ui/gerar-map.mjs <gap.md> | grep prototipo_sha                    # x13, stdout, sem escrita
node scripts/governance/design-code-map-check.mjs --check --strict               # rc 0
node scripts/governance/doc-id-index.mjs --check-collisions                      # rc 0
node scripts/governance/requisitos-status.mjs RecurringBilling --check           # rc 0
node scripts/design-sync/status.mjs --check-mapping | grep -A1 COMPARED          # 12 telas
git merge-base origin/main HEAD; git merge-base --is-ancestor 29fdc3e0f0 HEAD    # NAO
git merge-tree --write-tree origin/main HEAD                                     # CONFLICT application-report.json
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' | grep -cE <regex>   # PII x6 + controle
```

## Veredito

```json
{"itens_verificados": 300, "erros_confirmados": 1, "error_rate_pct": 0.33, "pii_hits": 0, "veredito": "aprovado"}
```

Aprovado pelo critério (<2%), **com um bloqueador de merge que não é de conteúdo**: a branch está atrás de `origin/main` (falta o #6903) e o `application-report.json` regenerado conflita. `gh pr update-branch` + regenerar o report resolve sem tocar nos 12 gap.md/map.json.
