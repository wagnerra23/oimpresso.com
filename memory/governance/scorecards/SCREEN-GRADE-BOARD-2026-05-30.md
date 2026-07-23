---
id: governance-scorecards-screen-grade-board-2026-05-30
---

# SCREEN-GRADE BOARD — estado da arte das 223 telas

> **Método:** [SCREEN-GRADE 9.75](../../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) (16 dimensões · nota 0-100 · níveis Beginner→Champion) · **grade ESTÁTICO** (lido do `.tsx`, sem render) · **19 agentes paralelos** (workflow `screen-grade-estado-arte`) · 2026-05-30.
> **Cobertura:** 222 telas reais graduadas (+ 1 alvos Inertia sem .tsx no caminho — ver §Não-localizadas). **Média: 75/100.** Ratchet: nota só sobe (ADR 0236).

---

## Distribuição por nível

| Nível | Faixa | Telas | % |
|---|---|--:|--:|
| 🏆 Champion | 95-100 | 0 | 0% |
| 🥈 Leader | 85-94 | 24 | 11% |
| Advanced | 70-84 | 154 | 69% |
| Developing | 50-69 | 42 | 19% |
| 🥉 Beginner | 0-49 | 2 | 1% |
| **Total** | | **222** | 100% |

## 🔴 Top 30 PRIORIDADES (menor nota — atacar primeiro)

| # | Tela | Arq | Persona | Nota | Nível | Top gap → fix |
|--:|---|---|---|--:|---|---|
| 1 | `NfeBrasil/Transactions/NfceStatus` | detail | eliana | **38** | Beginner | Pre-Flight-conformance — Eliminar todos style={{}} inline e oklch(0.55 0.05 240) azul; reescrever com Tailwind+tokens v4 roxo. |
| 2 | `Produto/StockHistory` | detail | larissa | **47** | Beginner | Speed-to-task — Migrar endpoint pra JSON e renderizar timeline real em vez de link 'ver legacy' |
| 3 | `Financeiro/Advisor/Login` | form | eliana | **50** | Developing | Pre-Flight-conformance — <input> e os-btn crus (só Checkbox/Label do @/ui); cores slate/emerald/red hand-roll, zero token v4/roxo, sem charter |
| 4 | `Manufacturing/Index` | list | misto | **50** | Developing | Pre-Flight-conformance — Cores gray-50/500/900 + green/yellow-100 cruas; sem AppShellV2 nem @/Components/ui (só table raw) |
| 5 | `Financeiro/Advisor/Dashboard` | dashboard | eliana | **52** | Developing | Pre-Flight-conformance — Tudo hand-roll: bg-slate/white/amber/emerald cruas, os-btn legacy em vez de @/ui Button, zero token v4, zero roxo, sem charter |
| 6 | `Financeiro/Unificado/Novo` | form | eliana | **52** | Developing | Speed-to-task — E stub picker (2 cards) que so redireciona pra contas-receber/pagar — entregar form unificado real |
| 7 | `Jana/Brief/Index` | other | misto | **52** | Developing | Speed-to-task — Renderizar brief real (brief-fetch) inline em vez de redirecionar pro chat |
| 8 | `Jana/Regras/Index` | other | wagner | **52** | Developing | Speed-to-task — Listar policies do PolicyEngine (4 outcomes) read-only em vez de só redirecionar |
| 9 | `Repair/JobSheet/Index` | list | tecnico | **52** | Developing | Speed-to-task — Tabela e placeholder (div explicando DataTables legacy); migrar pra TanStack/tabela real com dados |
| 10 | `ComunicacaoVisual/Index` | other | larissa | **54** | Developing | Pre-Flight-conformance — Trocar paleta zinc/amber crua por tokens v4 e montar via AppShellV2 como as demais telas |
| 11 | `Jana/Painel` | dashboard | misto | **55** | Developing | Pre-Flight-conformance — Substituir markup .jc-* hand-rolled por @/Components/ui + tokens v4 |
| 12 | `Site/BlogPost` | detail | misto | **55** | Developing | Error-recovery — content via dangerouslySetInnerHTML sem sanitizacao (risco XSS se CMS aceitar HTML nao-confiavel); sanitizar server/client |
| 13 | `Produto/Unificado/Index` | list | larissa | **56** | Developing | Pre-Flight-conformance — Trocar <select>/<input>/TweaksPanel nativos pelos @/Components/ui; remover sky-700 e stone-900 por tokens roxo |
| 14 | `Auditoria/Index` | list | wagner | **57** | Developing | Pre-Flight-conformance — hover:bg-sky-50, text-sky-700, badges bg-emerald/sky/amber-100 crus; migrar pra tokens semanticos |
| 15 | `Auditoria/Detail` | detail | wagner | **58** | Developing | Pre-Flight-conformance — Links text-sky-700 (azul) + zinc-* cru + sem PageHeader primary roxo; trocar por tokens DS |
| 16 | `Financeiro/AssinaturaAtualizar` | form | eliana | **58** | Developing | Pre-Flight-conformance — header usa os-page-h/fin-page-h/fin-cowork/vendas-aplus legacy em vez de <PageHeader>; sem charter (arquivo se diz 'UI minimal HITL pending') |
| 17 | `Financeiro/Configuracoes/Contador` | config | eliana | **58** | Developing | Pre-Flight-conformance — Inputs CNPJ/email/nome sao <input> hand-rolled com classes cruas; migrar pra @/Components/ui/input + flash bg-blue-300/50 viola zero-blue |
| 18 | `Ponto/Welcome` | other | tecnico | **58** | Developing | Speed-to-task — Pagina piloto so mostra business/usuario; sem navegacao util nem KPIs do modulo |
| 19 | `Settings/PaymentGateways/CnabRetorno` | config | wagner | **58** | Developing | Pre-Flight-conformance — Sem charter (assumido), paleta stone/emerald/rose/amber crua, PageHeader importado de Financeiro/atoms (nao canon shared) |
| 20 | `Site/Page` | other | misto | **58** | Developing | Error-recovery — Sem fallback p/ page null/404 nem estado vazio de content; dangerouslySetInnerHTML sem sanitize visivel |
| 21 | `superadmin/Usuario360/Index` | list | wagner | **58** | Developing | Speed-to-task — Busca exige submit; adicionar debounce + only:['users'] partial reload |
| 22 | `ads/Admin/Graph` | other | wagner | **60** | Developing | Pre-Flight-conformance — nodeStyle/MiniMap/Legend cravam HEX cru (#3b82f6,#fef3c7,#ef4444...) em inline style — violacao dura AP hex; extrair pra tokens CSS vars |
| 23 | `Repair/JobSheet/AddParts` | form | tecnico | **61** | Developing | Affordance — Campo Variation ID e input numerico cru; trocar por autocomplete de produto com nome/SKU |
| 24 | `OficinaAuto/Vehicles/Edit` | form | tecnico | **62** | Developing | Internal-consistency — Edit omite chassi/renavam/ano/motor/combustivel/KM que existem no Create — restaurar paridade de campos |
| 25 | `Repair/Dashboard/Index` | dashboard | tecnico | **62** | Developing | Information-hierarchy — job_sheets_by_status e trending viram <SimpleListCard> de texto; usar gráfico (recharts/sparkbar) e expor trending_devices (hoje voided com FIXME) |
| 26 | `Admin/FeatureFlags/Index` | config | wagner | **64** | Developing | Pre-Flight-conformance — Trocar bg-amber-100/bg-red-100/bg-green por <Alert variant> + tokens DS v4; criar charter |
| 27 | `OficinaAuto/Vehicles/Create` | form | tecnico | **64** | Developing | Pre-Flight-conformance — sem Create.charter.md (irmaos tem) + select/textarea nativos — criar charter e usar Select/Textarea DS |
| 28 | `superadmin/Usuario360/Show` | detail | wagner | **64** | Developing | Error-recovery — submitUnlock usa window.confirm nativo; trocar por Dialog do DS com motivo/nota igual ao lock |
| 29 | `Admin/FeatureFlags/Show` | config | wagner | **66** | Developing | Pre-Flight-conformance — Trocar <select> nativo por @/Components/ui/select e bg-red-100 por Alert variant; criar charter |
| 30 | `governance/Policies` | config | wagner | **66** | Developing | Affordance — Trocar botão toggle artesanal por @/Components/ui/switch com estado pending/disabled durante router.post |

## 🏅 Top 15 GOLDENS (maior nota — candidatas a referência)

| Tela | Arq | Nota | Nível | Resumo |
|---|---|--:|---|---|
| `Financeiro/Cobranca/Index` | list | **94** | Leader | Port Cowork F1.5 96/100: charter live, defer+Deferred, KB-9.75, localStorage, PII mask, primary roxo 295 - referencia de excelencia do modulo. |
| `Atendimento/Inbox/Index` | chat | **91** | Leader | Cockpit 3-paineis estado-da-arte: charter, Deferred+skeletons, J/K/E/A, Centrifugo+polling fallback, tokens primary — falta polish mobile/a11y real-time. |
| `Financeiro/Unificado/Index` | list | **90** | Leader | Cockpit financeiro denso estado-da-arte (KPI hero+sparkline, multi-filtro, bulk, drawer 3-abas, atalhos J/K/space/B, OCR boleto) — top do batch, so peca mistura emoji+oklch cru vs DS. |
| `Sells/Index` | list | **90** | Leader | Cockpit de vendas estado-da-arte (1806 linhas, charter, score 9.75): SLA pills, pipeline FSM, ⌘K, saved views, bulk emit, sparkline deferred; desvia do DS por CSS Cowork scoped. |
| `ProjectMgmt/Board/Index` | kanban | **89** | Leader | Kanban estado-da-arte: drag-drop otimista com optimistic-lock 409/403, atalhos J/K/E/A, polling+on-focus, DetailSheet via URL, charter presente; só leves blue-leaks (border-l-blue-500). |
| `Atendimento/CaixaUnificada/Index` | chat | **88** | Leader | Inbox omnichannel madura: Centrifugo+polling fallback, atalhos J/K/E/A + /, Deferred granular, EmptyState, tokens primary e charter+review; pesa header hand-rolled e 3 acoes topnav disabled. |
| `RecurringBilling/Index` | dashboard | **88** | Leader | Cockpit 3-col de altíssimo craft (defer, ⌘K palette, Jana IA, troubleshooters, timeline, atalhos) — teto puxado por mock client-side, a11y de itens clicáveis e fuga do shell/DS canon. |
| `Sells/Create` | form | **88** | Leader | Form POS denso e maduro (1647 linhas, charter): autosave multi-tenant, atalhos /, Cmd+Enter, error-scroll, NumericInputPtBR; perde por cores cruas nos cards e header fora do canon. |
| `Site/Login` | form | **88** | Leader | Login estado-da-arte: token-puro, floating-label, social IdP, show/hide, flash error, copy PT-BR caloroso; falta wiring a11y de erro de campo. |
| `TransactionPayment/Index` | list | **87** | Leader | Melhor lista do lote: Inertia::defer+Skeleton nos KPIs, filtros persistidos em localStorage, paginacao server real, Badge/token puro; falta PageHeader e date-range UI. |
| `Cliente/Show` | detail | **86** | Leader | Detalhe de cliente maduro: 9 tabs, Deferred em tudo, cowork-primary, CPF mascarado LGPD, frota condicional. |
| `Jana/Admin/Governanca/Index` | dashboard | **86** | Leader | Governanca MCP estado-da-arte interno (3 secoes SubNav+persist LS, KPI taxa-sucesso/p95, chart calls/custo toggle, RBAC denied, EmptyState/StatusBadge shared) — melhor conformance DS do batch. |
| `Ponto/Aprovacoes/Index` | list | **86** | Leader | Lista densa estado-da-arte (KPI-filtro, bulk, dialogs, empty/search, toasts); peca por cores cruas, confirm() nativo e zero charter. |
| `ProjectMgmt/Inbox/Index` | list | **86** | Leader | Caixa de entrada agrupada por tipo com marca-lido otimista, deep-link pro Board, J/K/Enter/R, banner erro role=alert, empty-state e charter; tokens limpos. Marcada DRAFT aguardando screenshot Wagner. |
| `Site/Register` | form | **86** | Leader | Cadastro espelha Login com mesma qualidade: social, gate allowRegistration, copy '30s sem cartao'; falta strength meter e validacao client de match. |

## 📊 Média por módulo (pior → melhor)

| Módulo | Telas | Média |
|---|--:|--:|
| Manufacturing | 1 | 50 |
| ComunicacaoVisual | 1 | 54 |
| Auditoria | 2 | 58 |
| superadmin | 2 | 61 |
| Jana | 11 | 69 |
| Settings | 2 | 69 |
| Financeiro | 19 | 70 |
| Produto | 8 | 71 |
| Repair | 13 | 72 |
| Compras | 1 | 72 |
| NfeBrasil | 6 | 73 |
| ads | 19 | 73 |
| OficinaAuto | 10 | 73 |
| Admin | 8 | 73 |
| MemCofre | 6 | 73 |
| Site | 7 | 74 |
| governance | 6 | 74 |
| ConsultaOs | 1 | 74 |
| Vestuario | 1 | 74 |
| kb | 2 | 76 |
| Cliente | 7 | 77 |
| Essentials | 13 | 77 |
| Purchase | 4 | 77 |
| Ponto | 20 | 78 |
| Fiscal | 7 | 78 |
| Atendimento | 9 | 79 |
| Modules | 1 | 79 |
| Sells | 8 | 80 |
| Whatsapp | 2 | 80 |
| Nfse | 3 | 80 |
| StockAdjustment | 2 | 81 |
| StockTransfer | 2 | 82 |
| ProjectMgmt | 8 | 83 |
| RecurringBilling | 6 | 84 |
| Home | 1 | 85 |
| TransactionPayment | 3 | 86 |

## Ranking completo (222 telas · nota ASC)

| Tela | Nota | Nível | Persona | Resumo |
|---|--:|---|---|---|
| `NfeBrasil/Transactions/NfceStatus` | 38 | Beginner | eliana | Page demo de 1 badge de status com polling; tem charter mas e stub com inline style={{}} e oklch(...240) azul hardcoded — viola Pre-Flight, sem componentes DS nem estados ricos. |
| `Produto/StockHistory` | 47 | Beginner | larissa | Placeholder: filtros variação/local com persist localStorage, mas timeline só linka pro Blade legacy (admite migração Wave 3) — feature incompleta. |
| `Financeiro/Advisor/Login` | 50 | Developing | eliana | Login isolado do portal contador (justificadamente fora do AppShell) com flash success/error; inputs/botões crus, cores hand-roll, zero DS v4 e sem recuperação de senha. |
| `Manufacturing/Index` | 50 | Developing | misto | Esqueleto MWART de produções com tabela hand-rolled, paleta gray/green/yellow-100 fora do DS, sem AppShellV2 nem dark mode e CTA desabilitado. |
| `Financeiro/Advisor/Dashboard` | 52 | Developing | eliana | Portal contador read-only funcional com grid de clientes e aviso LGPD; estética crua (os-btn legacy, cores hand-roll, zero DS v4/roxo) e sem charter. |
| `Financeiro/Unificado/Novo` | 52 | Developing | eliana | Stub-ponte provisorio (2 cards picker receber/pagar) auto-declarado status=stub — funcional mas sem form real nem charter. |
| `Jana/Brief/Index` | 52 | Developing | misto | Stub explícito 'UI em construção' com 2 CTAs e dot oklch azul cru; correto mas sem conteúdo real. |
| `Jana/Regras/Index` | 52 | Developing | wagner | Stub 'UI em construção' apontando pra Governança; correto e a11y básica ok, mas sem conteúdo e cor oklch azul/violet cruas. |
| `Repair/JobSheet/Index` | 52 | Developing | tecnico | Shell React limpo mas e stub — lista real e placeholder de DataTables legacy e cards so contam filtros. |
| `ComunicacaoVisual/Index` | 54 | Developing | larissa | Hub stub honesto de 4 cards 'em breve', mas cores zinc cruas, sem AppShellV2 e sem funcionalidade real. |
| `Jana/Painel` | 55 | Developing | misto | Esqueleto Onda A1 com placeholders '[kind] sub-component virá', classes .jc-* cruas e zero @/Components/ui — HTML cru declarado. |
| `Site/BlogPost` | 55 | Developing | misto | Pagina de post marketing tokenizada (prose, tags, meta-desc); fina e com dangerouslySetInnerHTML sem sanitizacao e img hero sem lazy/dimensoes. |
| `Produto/Unificado/Index` | 56 | Developing | larissa | Catálogo denso 5-sub-telas density-first (bom pra Larissa), mas zero @/Components/ui (native select/input), text-sky-700 blue-leak, bg-stone-900 e TODOs por todo lado. |
| `Auditoria/Index` | 57 | Developing | wagner | Log de atividades legivel mas Developing: cores sky/zinc cruas, filtros sem UI, sem Deferred/skeleton nem header canon. |
| `Auditoria/Detail` | 58 | Developing | wagner | Detalhe de auditoria funcional mas thin: azul sky cru, JSON dump bruto, sem header roxo canon nem links navegaveis. |
| `Financeiro/AssinaturaAtualizar` | 58 | Developing | eliana | Form propositalmente minimal (HITL pending Wagner) p/ alterar cobrança recorrente; @/ui + useMemo ok, mas header legacy, sem tabela de assinaturas, sem charter nem preview de impacto. |
| `Financeiro/Configuracoes/Contador` | 58 | Developing | eliana | MVP F0 stub (charter pendente): consent LGPD bem feito, mas inputs hand-rolled, flash bg-blue cru, confirm() nativo e sem PageHeader/SubNav/defer. |
| `Ponto/Welcome` | 58 | Developing | tecnico | Stub de boas-vindas (business+usuario), copy dev-facing e sem valor operacional — placeholder, nao dashboard. |
| `Settings/PaymentGateways/CnabRetorno` | 58 | Developing | wagner | Fundacao tecnica de upload CNAB retorno (sem charter, declarado): form file + tabela deferred funcional mas plano, paleta stone fora do DS v4. |
| `Site/Page` | 58 | Developing | misto | Renderizador CMS enxuto (prose + dangerouslySetInnerHTML); correto e tokenizado mas sem estados de erro/vazio nem sanitize — baixa maturidade por escopo. |
| `superadmin/Usuario360/Index` | 58 | Developing | wagner | Lista de busca enxuta com tabela responsiva e empty state, mas sem charter, botão submit hand-rolled, sem paginação/loading e busca só por submit (sem debounce). |
| `ads/Admin/Graph` | 60 | Developing | wagner | Knowledge-graph ReactFlow util mas e o pior em Pre-Flight: HEX cru em inline-style, canvas fixo nao-responsivo, zero a11y. |
| `Repair/JobSheet/AddParts` | 61 | Developing | tecnico | Tabela de pecas editavel funcional mas pede Variation ID numerico cru — affordance fraca e sem totais. |
| `OficinaAuto/Vehicles/Edit` | 62 | Developing | tecnico | Edit minimo (so 6 campos) diverge do Create completo, selects nativos e erros parciais — scaffold incompleto. |
| `Repair/Dashboard/Index` | 62 | Developing | tecnico | Port honesto do dashboard Repair com tokens semânticos corretos, mas raso: 2 KPIs, 'gráficos' são listas de texto e um chart foi dropado (FIXME). |
| `Admin/FeatureFlags/Index` | 64 | Developing | wagner | Painel GrowthBook funcional com audit log, mas tabelas hand-rolled e cores cruas amber/red sem token, sem charter nem estados defer. |
| `OficinaAuto/Vehicles/Create` | 64 | Developing | tecnico | Cadastro de veiculo completo porem scaffold: selects nativos, erros so na placa, sem charter e divergente do Edit. |
| `superadmin/Usuario360/Show` | 64 | Developing | wagner | Painel 360 abrangente (roles/perms/tokens/sessions/audit/lockouts) com graceful-degradation de tabelas ausentes, mas sem charter, emoji-heavy, RISK_STYLES em cores cruas e window.confirm nativo. |
| `Admin/FeatureFlags/Show` | 66 | Developing | wagner | Detalhe de flag com mata-switch e CRUD de rule biz via useForm; bom error-recovery por confirm, mas <select> nativo, cores cruas e zero charter. |
| `governance/Policies` | 66 | Developing | wagner | Toggle de rules limpo com KpiGrid/EmptyState e dark-mode, mas toggle hand-rolled (emerald cru), sem loading/optimistic e sem token roxo v4. |
| `OficinaAuto/ServiceOrders/Create` | 66 | Developing | tecnico | Form de OS em Sheet 720 funcional com referer-close, mas selects/textarea nativos e erros parciais o deixam scaffold. |
| `ads/Admin/Learning` | 67 | Developing | wagner | Pipeline-loop didatico com stages clicaveis e diagrama de fluxo; colorMap cru de 9 cores e chart hand-roll sem eixos puxam pra baixo. |
| `Financeiro/Extrato/Index` | 67 | Developing | eliana | Extrato bancario shadcn com 4 cards-resumo e filtro periodo; charter live, mas header os-page-h inline sem PageHeader/SubNav, text-red cru e doc da contraparte exposto sem mascara (PII). |
| `Admin/Index` | 68 | Developing | wagner | Centro de Operacoes agrega 10 widgets em Card grid com charter; mas zero Inertia::defer (tudo eager) e badges de status com cores cruas green/amber/red repetidas inline. |
| `Fiscal/Sped` | 68 | Developing | eliana | SPED MVP parcial (tabela competencias + export EFD-ICMS/IPI .txt funcional, resto placeholder 'em dev') — funcional no core mas imaturo e com hex cru de fallback. |
| `OficinaAuto/Vehicles/Show` | 68 | Developing | tecnico | Detalhe de veiculo limpo (dados + historico OS) porem estatico: sem charter, status plano e sem acao/estado FSM no topo. |
| `Ponto/Relatorios/Index` | 68 | Developing | tecnico | Galeria de relatorios em cards com estado disponivel/em-breve, mas cores cruas (blue/violet/amber proibidos no DS roxo) e zero parametrizacao. |
| `Produto/SellingPrices` | 68 | Developing | larissa | Matriz preço grupo×variação funcional com @/Components/ui e empty-states, mas paleta stone crua + header hand-rolled fora do PageHeader/token roxo. |
| `Repair/JobSheet/Create` | 68 | Developing | tecnico | Create com Deferred options + skeleton e secoes claras, mas cliente por ID numerico e validacao incompleta. |
| `Site/Blogs` | 68 | Developing | misto | Indice de blog marketing tokenizado com cards hover/line-clamp/lazy-img e empty state; faltam paginacao, busca/tags e data nos cards. |
| `Admin/RagQualityDashboard` | 69 | Developing | wagner | Observability RAG com 3 sparklines p99, nDCG/recall e Deferred bem aplicado; perde por cores cruas zinc/emerald/indigo, sem charter e thresholds hardcoded sem token. |
| `ads/Admin/Confidence` | 69 | Developing | wagner | Tabela score×HiTL limpa com KpiGrid e EmptyState pedagogico, mas cor crua como unico sinal e zero charter puxam pra Developing. |
| `governance/DriftAlerts` | 69 | Developing | wagner | Lista de drift funcional com KPIs e historico, mas quase toda em amber cru e divs hand-rolled (sem CardTitle), read-only sem acao e fora do DS roxo. |
| `MemCofre/Modulo` | 69 | Developing | wagner | Hub de requisitos com 12 tabs, KPIs e filtro ADR — completo mas pesado (623 linhas) e despeja conteudo em <pre> font-mono cru sem markdown nem virtualizacao. |
| `Admin/GovernanceV4Dashboard` | 70 | Advanced | wagner | Dashboard intra-bucket com sparkline SVG, drift banner e Deferred; cores cruas zinc/emerald e hex inline (#dc2626) + filtros bg-zinc-900 fora do primary roxo derrubam Pre-Flight. |
| `ads/Admin/Conflicts` | 70 | Advanced | wagner | Detector 3-tipos bem estruturado com KPIs, empty-state e links de drill-down; cor crua e falta de nav inter-secoes limitam. |
| `Atendimento/Macros/Variants` | 70 | Advanced | misto | A/B testing rico (peso/taxa/vencedora) mas falta charter, tem bug filter pre-guard e usa confirm() + cores cruas. |
| `Financeiro/ContasPagar/Index` | 70 | Advanced | eliana | Lista titulos + Sheet de baixa shadcn com toast e validacao; solido, mas status bg-blue-100 nao-token, sem defer/skeleton e empty state minimo. |
| `Financeiro/ContasReceber/Index` | 70 | Advanced | eliana | Gemea de ContasPagar com coluna boleto + emitir-boleto via toast; mesmos pontos fortes e fracos: bg-blue-100 cru, sem defer, sem atalhos. |
| `governance/Audit` | 70 | Advanced | wagner | Audit forense com 4 filtros server-side e charter ao lado, mas selects/tabela hand-rolled com zinc/emerald crus e sem paginacao real. |
| `Jana/Cockpit` | 70 | Advanced | misto | Cockpit Analista IA rico (KPIs, análises donut/spark/bars, 4 kinds de bubble, stream mock, PII detector, atalhos) porém 100% hand-rolled sem @/Components/ui, emoji-ícones e cores cruas — Pre-Flight fraco. |
| `Produto/Show` | 70 | Advanced | larissa | Detalhe com tabs + Deferred/skeleton e bons empty-states, porém tabs e header hand-rolled com bg-stone-900 em vez do PageHeader e token roxo. |
| `ads/Admin/Metricas` | 71 | Advanced | wagner | Metricas de adocao com KPIs duplos, stacked-bar e top-10; boa microcopy (peso 3x modificadas) mas cor crua e sem defer. |
| `ads/Admin/Patterns` | 71 | Advanced | wagner | Padroes com Wilson-Score bem explicado, secoes candidatos/drifts em cards + tabela; cor crua como sinal e a11y de tabela limitam. |
| `ads/Admin/Projects` | 71 | Advanced | wagner | Lista+create inline limpa com progress roxo, mas sem filtro/busca e paleta de status crua (blue presente). |
| `Cliente/Map` | 71 | Advanced | larissa | Split-screen mapa de clientes limpo com tokens, mas mapa via iframe Google hardcoded e lista hand-rolled sem defer. |
| `Financeiro/Conciliacao/Index` | 71 | Advanced | eliana | Fluxo OFX upload->match->aprovar funcional e claro, mas oklch cru inline nos botoes, sem defer, sem charter e file input nao estilizado. |
| `Jana/Memoria` | 71 | Advanced | misto | Lista de fatos LGPD com edição inline e copy clara, reuso DS bom, mas confirm() nativo, categorias com bg cru e label de edição sem a11y. |
| `MemCofre/Memoria` | 71 | Advanced | wagner | Explorador arvore+preview de 3 roots com filtro recursivo e markdown render; bom UX mas usa <input> hand-rolled, fetch manual sem error state e cores raw amber/sky/emerald. |
| `Ponto/Espelho/Index` | 71 | Advanced | tecnico | Seletor de colaborador p/ espelho com filtro de mes ok, mas tem input de busca falso 'em breve', empty cru e sem charter. |
| `Repair/JobSheet/Edit` | 71 | Advanced | tecnico | Edit com tabs e indicador de erro por aba (bom), mas checklist readOnly inativo e cliente por ID. |
| `ads/Admin/MetaSkills` | 72 | Advanced | wagner | Editor de regras SOFT robusto (condition-builder, validate-against-data, versao, switch); select nativo, JSON cru e cores cruas limitam. |
| `ads/Admin/ProjectShow` | 72 | Advanced | wagner | Detalhe de project bem estruturado (KpiGrid+parts ordenadas+deps), mas cores cruas com blue e confirm() nativo derrubam Pre-Flight. |
| `Cliente/Ledger` | 72 | Advanced | eliana | Extrato financeiro denso e legivel (KpiCard, debito/credito/saldo, PDF/Excel) mas usa window.location full-reload, cores cruas e sem Deferred. |
| `Compras/Index` | 72 | Advanced | larissa | Cockpit denso com drawer, KPIs, defer e visibilidade de colunas, mas CSS bundle proprio fora do DS v4 e tabela hand-rolled. |
| `Financeiro/Relatorios/Index` | 72 | Advanced | eliana | Relatorios com tabs Fluxo/Resumo, KPI fin-stats, export CSV e card-alerta de vencidos; solido mas banner DRE oklch cru, sem defer e graficos div artesanais. |
| `MemCofre/Chat` | 72 | Advanced | wagner | Chat do Cofre sólido com reuso DS (Card/Textarea/Button/Badge), envio otimista, estado de erro e sessões recentes, mas select nativo, sem charter e a11y de inputs fraca. |
| `Ponto/Configuracoes/Index` | 72 | Advanced | wagner | Painel CLT bem organizado em 4 cards, mas read-only, usa azul proibido na borda e cores cruas — baixa o Pre-Flight. |
| `Repair/Show` | 72 | Advanced | tecnico | Detalhe de venda-reparo limpo (dl grid, aside pagamentos, defer timeline) mas FSM e placeholder e cores cruas. |
| `Sells/Subscriptions` | 72 | Advanced | larissa | Lista de assinaturas com start/stop inline, badges freq/proxima-fatura/status; fragil em feedback de erro (catch mudo) e a11y. |
| `ads/Admin/DecisaoShow` | 73 | Advanced | wagner | Detalhe rico: drill-down chain, review G-Eval, KV tecnico, StatusBadge canon; window.prompt e cores cruas seguram a nota. |
| `ads/Admin/Policy` | 73 | Advanced | wagner | Firewall read-only claro: aviso imutabilidade, 4 categorias com icone/cor, copy honesta; cor parcialmente crua e sem filtro. |
| `ads/Admin/Tools` | 73 | Advanced | wagner | Catalogo de tools com try-it inline e audit de execucoes; util mas paleta crua (emerald/amber/zinc), confirm() nativo e input JSON manual elevam carga. |
| `Essentials/Settings/Index` | 73 | Advanced | misto | Form de config bem cardificado com Switch/Textarea/@ui; falha em inputs numéricos crus, breadcrumb HRM≠Essentials e ausência de charter. |
| `Financeiro/PlanoContas/Index` | 73 | Advanced | eliana | Plano de contas hierarquico denso com tokens fin-*, filtro radiogroup a11y e indentacao por nivel; mas microcopy de empty vaza comando tinker SSH e hue 240 (blue) no CSS var. |
| `Jana/Chat` | 73 | Advanced | misto | Chat master/detail maduro com busca acento-insensitive, tabs a11y (role/aria/teclado) e charter, mas CSS hand-rolled .cs-/.sb- fora do DS e tabs 'Em breve' são afford. morta. |
| `OficinaAuto/ServiceOrders/Edit` | 73 | Advanced | tecnico | Edit com secao Itens inline (optimistic+toast+roxo) supera Create, mas mantem selects nativos e window.confirm fora do DS. |
| `Sells/Quotations` | 73 | Advanced | larissa | Lista de cotacoes irma de Drafts (badge orcamento, enviar/editar); mesma divida: sem skeleton/paginacao/a11y e CTA 'Enviar' enganoso. |
| `ads/Admin/TeamScopes` | 74 | Advanced | wagner | Matriz user×modulo com Switch granular e explicacao do enforcement server-side; densa e poderosa mas desktop-only e cores de estado cruas. |
| `Atendimento/Channels/Show` | 74 | Advanced | larissa | Detalhe com tabs role-tab acessiveis, Deferred por aba, re-parear com poll e PageHeader; mas sem componente Tabs do DS, confirm() nativo e sem charter. |
| `Atendimento/JanaTemplates` | 74 | Advanced | misto | Form config limpo c/ charter, PageHeader canon e @/Components/ui — simples demais, sem render de erros de validacao inline. |
| `ConsultaOs/Index` | 74 | Advanced | misto | Consulta publica de OS limpa com 3 estados (busca/resultado/nao-encontrado) e @/Components/ui, mas gradiente blue/violet fora do brand. |
| `Financeiro/Caixa/Index` | 74 | Advanced | eliana | Wrapper read-only maduro: charter excelente, PageHeader v3.8, SubNav, KPIs, fmtMoney pt-BR e badge integração; perde em cores cruas/emerald-primary, confirm() nativo e tabela hand-roll. |
| `Financeiro/ContasBancarias/Index` | 74 | Advanced | eliana | Lista shadcn limpa com StatusBadge semantico e Sheet de config boleto; charter live, mas primary oklch inline (TODO Wave5) e sem defer/skeleton. |
| `Jana/Admin/Qualidade/Index` | 74 | Advanced | wagner | Dashboard de métricas IA denso e competente (sparklines SVG, gates, KpiCard reuse, empty state com comando), mas cores cruas hex e sem charter. |
| `Jana/Dashboard` | 74 | Advanced | misto | Dashboard de metas com farol, sparkline e empty states sólidos via DS, mas KpiStrip é mock '—' e badges gradiente violet/fuchsia/pink desviam do roxo primary. |
| `kb/Graph` | 74 | Advanced | wagner | Tri-pane grafo Reactflow com filtros, KPIs, atalhos / e Esc, empty state e a11y — mas roda em modo MOCK (sem backend) e usa amber/muted em vez de roxo v4. |
| `MemCofre/Dashboard` | 74 | Advanced | wagner | Dashboard de cobertura documental rico (KPIs, dots, barras trace/audit/DoD, empty guards) com bom reuso DS, mas tabela densa sem mobile e tons semânticos hardcoded; sem charter. |
| `MemCofre/Ingest` | 74 | Advanced | wagner | Form de captura limpo com tipo-condicional (upload/url/text), progress bar de upload e evidencia opt-in via Switch; falta charter e a11y de erro agregado. |
| `Purchase/Edit` | 74 | Advanced | misto | Clone fiel do Create com PUT e pré-popula; herda as mesmas forças e o mesmo gap de affordance (IDs textuais). |
| `Repair/DeviceModels/Create` | 74 | Advanced | tecnico | Form pequeno e correto (DS primitives, PageHeader, autoFocus, foco max-w-3xl, errors), limitado pelo checklist via '\|' e selects crus pouco touch. |
| `Repair/ProducaoOficina/Index` | 74 | Advanced | tecnico | Kanban F3 com DnD otimista e filtros dinamicos por vertical, mas palette crua fora do DS e drawer totalmente mockado. |
| `Sells/Drafts` | 74 | Advanced | larissa | Lista de rascunhos limpa com badge idade/CTA continuar e atalhos N/Esc; raso em a11y de tabela, sem skeleton nem paginacao server. |
| `Vestuario/Etiquetas/Index` | 74 | Advanced | larissa | Gerador de etiquetas ZPL/PDF funcional: lote multi-item, cópias, config badges, download blob, error inline; trava em entrada manual de Produto ID e grid-12 nao responsivo. |
| `Whatsapp/Templates/Index` | 74 | Advanced | misto | Lista HSM madura com Deferred+skeleton, EmptyState, form criar e alerta orfaos; perde em cores cruas nos badges e ausencia de charter. |
| `Cliente/Create` | 75 | Advanced | larissa | Cadastro limpo c/ useForm + ClienteForm compartilhado + lookup CNPJ BrasilAPI, mas header sem canon roxo e nav <a> full-reload. |
| `Cliente/Edit` | 75 | Advanced | larissa | Espelho do Create (ClienteForm reuso, useForm put), mesma qualidade — falta header canon roxo, Link SPA e dirty-guard. |
| `Essentials/Knowledge/Create` | 75 | Advanced | misto | Form de criacao coeso (tipo derivado do pai, compartilhamento, autoFocus), mas conteudo HTML em textarea cru sem editor. |
| `Essentials/Knowledge/Edit` | 75 | Advanced | misto | Edicao espelha o Create com mesmos campos e DS limpo; mesma lacuna de editor rich-text e charter ausente. |
| `Essentials/Todo/Edit` | 75 | Advanced | misto | Edit consistente com Create (Head dinâmico, @ui, validação); mesmos gaps: sem PageHeader canon, charter, busca no multiselect, nem dirty-guard. |
| `Financeiro/Categorias/Index` | 75 | Advanced | eliana | Lista CRUD com PageHeader canon, FinanceiroPrimaryButton, drawer CategoriaSheet e toasts; arranha em confirm() nativo, cores cruas de tipo e ausência de charter. |
| `Fiscal/Eventos` | 75 | Advanced | eliana | Timeline append-only de eventos fiscais (CC-e/cancel/EPEC/manifesto) com callout janelas legais + Deferred + link cross pra NFe — correta e enxuta, layer fx-* fora do DS. |
| `governance/Dashboard` | 75 | Advanced | wagner | Cockpit de governanca rico (KPIs constituicao+saude, 3 paineis, atalhos, links canon) com charter, mas emojis e paleta crua afastam do DS roxo estado-da-arte. |
| `Nfse/Emitir` | 75 | Advanced | eliana | Form emissao NFSe robusto: calculo ISS/liquido ao vivo, painel venda vinculada, alertas cert/config, contador de chars, PageHeader DS. Usa tokens CSS-var legacy (--accent) nao primary roxo v4 e sem charter. |
| `Sells/Caixa/Index` | 75 | Advanced | larissa | Caixa do dia KB-9.75 com KPI hero e barras por-origem, mas metade e placeholder legacy e usa CSS cru com emoji. |
| `ads/Admin/Decisoes` | 76 | Advanced | wagner | Inbox operacional forte: KPIs-como-filtro, live-polling cortes, rows densas, empty por aba; cor crua e window.prompt limitam. |
| `ads/Admin/Skills/Review` | 76 | Advanced | wagner | Approval queue clara com gating de comentario e cards de draft, mas valida via alert() nativo e cores de status cruas. |
| `Atendimento/Metricas/Index` | 76 | Advanced | wagner | Dashboard metricas solido (KpiCard reuse, Deferred, snapshot pre-agregado) mas chart com hex/blue cru e SVG hand-rolled. |
| `Essentials/Messages/Index` | 76 | Advanced | misto | Mural chat com polling, auto-scroll, Enter-envia e bolhas mine/theirs tokenizadas, mas innerHTML stored-XSS e sem charter. |
| `Essentials/Todo/Create` | 76 | Advanced | misto | Form de criação sólido (@ui, Sonner, validação inline em task/date); falta PageHeader canon, charter e multiselect com busca. |
| `Fiscal/Nfse` | 76 | Advanced | eliana | Lista NFS-e nacional (status+ISS+competencia month-picker, Deferred, chips) — consistente com irmas mas mais rasa: sem drawer detalhe nem acoes por linha. |
| `Jana/Admin/Roadmap` | 76 | Advanced | wagner | Gantt de tasks MCP bem estruturado (Sheet detalhe, useMemo/useCallback, filtros, empty states, charter), porém tons de prioridade hardcoded e a11y fraca no canvas Gantt. |
| `MemCofre/Inbox` | 76 | Advanced | wagner | Inbox de triagem solido com shadcn/ui, busca debounce+Scout, tabs por status, dialog editar e AlertDialog deletar; perde por cores raw e sem charter. |
| `OficinaAuto/ServiceOrders/Show` | 76 | Advanced | tecnico | Detalhe de OS com Itens optimistic + imprimir A4 e CTA roxo; status plano (sem badge canon) e cores slate cruas na lista. |
| `Ponto/Escalas/Form` | 76 | Advanced | tecnico | Form create/edit de escala correto (useForm dual), mas turnos so leitura, checkbox nativo inconsistente com Switch e sem charter. |
| `Ponto/Importacoes/Create` | 76 | Advanced | tecnico | Form de upload AFD enxuto, DS v4 + toast, mas header canon nao estilizado (os-page-h orfao) e sem charter. |
| `ProjectMgmt/Roadmap/Index` | 76 | Advanced | wagner | Roadmap epics×quarter em colunas roláveis com progress-bar e KPIs; bom empty-state, mas várias cores cruas (blue-100, slate, #3b82f6 fallback) e sem charter nem interação de mover epic na UI. |
| `Purchase/Create` | 76 | Advanced | misto | Form linear denso e consistente (Card/PageHeader/AppShellV2 + totais reativos), mas fornecedor/produto como texto livre e <select> cru limitam affordance. |
| `Repair/DeviceModels/Edit` | 76 | Advanced | misto | Form de modelo limpo com useForm/charter/AppShellV2, mas cores cruas rose e selects nativos fora do DS. |
| `ads/Admin/Skills/Test` | 77 | Advanced | wagner | Tela de teste completa (manual vs conversas reais, PII, dry-run badge, runs com metricas), mas mistura inputs nativos hand-rolled e a11y de label fraca. |
| `Essentials/Todo/Index` | 77 | Advanced | misto | Lista com filtros, troca rápida de status e paginação funcionais; perde em cores cruas (sem tokens v4), tabela hand-roll e ausência de defer/skeleton. |
| `NfeBrasil/Tributacao/ImportCsv` | 77 | Advanced | eliana | Fluxo import 2-step (upload->preview->aplicar) exemplar: amostra em tabela sticky, erros em <details>, idempotencia explicita; sem charter e usa confirm() nativo. |
| `Repair/Status/Index` | 77 | Advanced | misto | CRUD de status enxuto e completo pro escopo (swatch cor, EmptyState, a11y) mas create/edit ainda em Blade legacy. |
| `Atendimento/Csat/Index` | 78 | Advanced | larissa | CSAT limpo: KpiGrid 4 cards tonalizados, distribuicao em barras, tabela com Deferred, EmptyState e StarRow a11y; perde por cores cruas emerald/amber, prop 'actions' fora do padrao e sem charter. |
| `Cliente/Import` | 78 | Advanced | larissa | Wizard import bom: 2 passos, progress bar, estado zip-indisponivel, variants cowork — mas cores rose/emerald cruas e sem preview de linhas. |
| `Essentials/Knowledge/Index` | 78 | Advanced | misto | Arvore livro/secao/artigo em cards com expand/collapse e delete confirmado, mas innerHTML cru e botoes-icone minusculos. |
| `Essentials/Knowledge/Show` | 78 | Advanced | misto | Leitor de artigo com nav lateral em arvore, prose e badge de tipo, mas innerHTML cru e sem charter. |
| `Essentials/Reminders/Index` | 78 | Advanced | misto | Lista pessoal limpa com Dialog+AlertDialog, @/ui, charter live; falta PageHeader canon, tokens v4 (usa default) e loading state. |
| `Financeiro/Dre/Index` | 78 | Advanced | eliana | DRE hierarquico denso classe-Cowork com tabs Balanco/Balancete, charter+visual-comparison approved; mas 5 botoes feature sao no-op F1, banner oklch cru e sem defer. |
| `kb/Index` | 78 | Advanced | wagner | Browser KB master-detail denso: j/k/Enter/Esc, debounce, soft-delete LGPD com confirm, markdown rico — mas usa bg-blue-100 (azul proibido), emojis como ícones e prose-blockquote azul. |
| `NfeBrasil/Tributacao/RegraForm` | 78 | Advanced | eliana | Form criar/editar regra NCM limpo: subcomponente FieldDecimal reusado, toggle CSOSN/CST, selects UF, mascaras; consistente com ConfigDefault. Sem charter e microcopy fiscal cru. |
| `OficinaAuto/AprovacaoPublica` | 78 | Advanced | tecnico | Aprovacao publica mobile-first com PIN OTP e lockout; cores cruas green/blue violam Pre-Flight e flash sem aria-live. |
| `Ponto/Importacoes/Index` | 78 | Advanced | tecnico | Lista AFD limpa com StatusBadge/EmptyState/paginacao, falta filtro+busca e header canon nao renderiza estilizado. |
| `Purchase/Index` | 78 | Advanced | misto | Lista densa com filtros sticky, pills de status/pagamento e empty state bom; pena o azul cru no pill 'ordered' e ausência de defer/paginação. |
| `Repair/Index` | 78 | Advanced | misto | Listagem OS rica (KPI toggle, chips status, atalhos Enter/Esc) mas sem charter, cores cruas e sem defer. |
| `Site/Home` | 78 | Advanced | misto | Landing marketing composicional com Inertia::defer per-prop, CTA final em bg-primary roxo e componentes Site reusados; perde so por tipos any e fallbacks que piscam. |
| `ads/Admin/Skills/Index` | 79 | Advanced | wagner | Lista read-only exemplar: busca useMemo, empty contextual, tabela densa com source badge; pequenos gaps a11y e marca roxa ausente. |
| `Atendimento/Channels/Index` | 79 | Advanced | larissa | CRUD de canais rico: cards com health, dialogs DS, fluxo QR/pairing com poll, EmptyState e charter+review; perde por alert()/confirm() nativos, emoji 📥 e cores emerald/amber cruas. |
| `Atendimento/Macros/Index` | 79 | Advanced | misto | Lista de macros completa (Deferred+skeleton+empty+a11y labels), mas table hand-rolled, confirm() nativo e vermelho cru. |
| `Essentials/Todo/Show` | 79 | Advanced | misto | Detalhe rico (tabs comentários/anexos/atividades, upload com progress, fetch async); arranha em GET-deleta, cores cruas e dangerouslySetInnerHTML. |
| `Financeiro/Fluxo/Index` | 79 | Advanced | eliana | Fluxo de caixa projetado+realizado com graficos de barra inline, tabs deep-link, charter+visual-comparison approved e tokens fin consistentes; banner oklch cru e sem defer. |
| `Fiscal/Dfe` | 79 | Advanced | eliana | Manifestacao DF-e bem-feita (callout legal, 4 acoes SEFAZ com modal justificativa min-15char validado, Deferred, historico) — gaps: modal inline-style e bulk pendente. |
| `Modules/Index` | 79 | Advanced | wagner | Tabela admin polida espelhando Cliente/Index: StatCards, FilterDropdown acessivel, chips de filtro ativo, ActionsMenu; perde por confirm() nativo e paleta stone/emerald/rose raw. |
| `OficinaAuto/ServiceOrders/Index` | 79 | Advanced | eliana | Lista de OS rica com KPIs, chips FSM e defer + drawer; tabela/ cores hand-rolled fora do DS e sem atalhos de teclado. |
| `Ponto/Configuracoes/Reps` | 79 | Advanced | wagner | Split form+lista bom para REPs (Portaria 671) mas CRUD incompleto (so create), validacao fraca do identificador e sem charter. |
| `Produto/Edit` | 79 | Advanced | larissa | Edicao espelha Create (consistencia boa), tipo travado bem comunicado; perde em tokens crus, feedback de erro parcial e sem dirty-guard. |
| `Sells/Edit` | 79 | Advanced | larissa | Edit deferred com skeleton, autosave per-venda, FSM-safe e paridade Create; cai por selects nativos, confirm()/alert() nativos e cores cruas. |
| `ads/Admin/Skills/Edit` | 80 | Advanced | wagner | Form de edicao forte: rationale 4-campos obrigatorio, dirty-state visual, tokens majoritariamente semanticos; so falta guard de saida e amber cru. |
| `Financeiro/Dashboard/Index` | 80 | Advanced | eliana | Defer+skeletons com aria em todos os blocos, KPI clicaveis pra filtrar; forte, mas cores bg-blue/purple/green cruas, dangerouslySetInnerHTML na paginacao e 'Novo titulo' no-op. |
| `Fiscal/Config` | 80 | Advanced | eliana | Config fiscal unificada solida (cert A1 upload+ambiente+testar SEFAZ live+series+SPED, 4 tabs, useForm+toast) — UX completa, mas forms 100% inline-style fora do DS. |
| `governance/ModuleGrades/Index` | 80 | Advanced | wagner | Tabela densa 13-col com filtros/KPIs/Deferred/skeletons e charter — mas paleta sky/emerald em vez de primary roxo v4 e header de cores cruas. |
| `NfeBrasil/Tributacao/ConfigDefault` | 80 | Advanced | eliana | Form de defaults fiscais Nivel 4 muito bom: wizard 'Aplicar pelo regime', toggle CSOSN/CST, mascaras numericas, hints por regime; charter presente. Perde em a11y de erro e cascade discoverability. |
| `OficinaAuto/ProducaoOficina/Index` | 80 | Advanced | tecnico | Kanban rico com DnD-FSM, 6 KPIs e confirm dialog; densamente hand-rolled em slate/raw colors fora do DS e DnD sem fallback teclado. |
| `Ponto/BancoHoras/Index` | 80 | Advanced | tecnico | Ledger limpo com KPIs e saldo colorido; falta busca/sort, reflow mobile e tokens no lugar de emerald/destructive crus. |
| `Ponto/Colaboradores/Edit` | 80 | Advanced | tecnico | Form de config de ponto bem estruturado (Switch+Select+useForm) mas pagina vs drawer-PT, sem mascara CPF/PIS e charter. |
| `Ponto/Escalas/Index` | 80 | Advanced | tecnico | Lista de escalas enxuta e conforme DS (primary, empty, tabular-nums); falta filtro/busca e reflow mobile. |
| `Ponto/Importacoes/Show` | 80 | Advanced | tecnico | Detalhe solido com polling de job e bloco de erro, mas header canon nao estilizado e polling sem backoff/aria-live. |
| `Ponto/Intercorrencias/Show` | 80 | Advanced | tecnico | Detalhe com maquina de estados (canEdit/Submit/Cancel) e alerts de aprovacao/rejeicao; usa confirm() nativo e header canon orfao. |
| `Produto/Create` | 80 | Advanced | larissa | Cadastro Cowork com avancado colapsavel persistido + dedup; usa Deferred? nao, e perde em tokens crus + feedback de erro so no nome. |
| `ProjectMgmt/Activity/Index` | 80 | Advanced | wagner | Feed timeline agrupado por dia com PageHeader+KpiGrid, filtros, auto-refresh 30s e i18n PT-BR sólido; falta charter e tem leve dependência de cor emerald hardcoded. |
| `ProjectMgmt/Burndown/Index` | 80 | Advanced | wagner | Burndown SVG ideal-vs-real hand-rolled com KPIs de pace/forecast e empty-state sem-cycle; usa stroke-blue-500 cru e não tem charter nem tooltip/hover no gráfico. |
| `Purchase/Show` | 80 | Advanced | misto | Detalhe completo (fornecedor/empresa/itens/pagamentos/totais) bem hierarquizado e fiscal-aware; falta CTA de pagamento e ajuste do azul cru. |
| `Settings/PaymentGateways/Index` | 80 | Advanced | wagner | Console de gateways (charter, Cowork F1.5 93/100, persona Wagner): KPIs+tabela deferred, J/K, cheat-sheet, drawer; desvia do DS v4 (paleta stone + atoms locais). |
| `StockAdjustment/Create` | 80 | Advanced | eliana | Form denso forte: validacao live recuperado<=total, perda liquida ao vivo, perm-gate preco, empty states; trava em product-picker fake e paleta stone fora do token v4. |
| `Admin/ScreenReviewDashboard` | 81 | Advanced | wagner | Landing PDCA enxuta e limpa: KpiGrid 5 cards tonalizados, alert role=alert com dark mode e charter; escopo pequeno mas conforme DS, so falta tendencia/contexto alem dos numeros. |
| `ads/Admin/Skills/Show` | 81 | Advanced | wagner | Detalhe rico: timeline de versions com acoes contextuais (promover/publish), markdown prose, dl frontmatter; confirm() nativo e zinc-900 cru sao os furos. |
| `Jana/Admin/Custos/Index` | 81 | Advanced | wagner | Dashboard custos IA bem-feito (4 KPI + SVG area chart inline + tabela por-usuario com tfoot total, primary roxo, @/Components/ui) — DS-clean, so falta charter. |
| `NfeBrasil/Tributacao/Index` | 81 | Advanced | eliana | Hub tributacao bem orquestrado: templates por setor, config pendente em destaque, gate emissao-automatica com Switch, tabela regras NCM, diagrama de prioridade cascade; charter ok. |
| `OficinaAuto/Vehicles/Index` | 81 | Advanced | tecnico | Lista de caçambas rica (KPIs, pills c/ contagem, tfoot totais, paginacao, dark-mode); cores cruas e toggle Grade sem efeito real. |
| `Ponto/BancoHoras/Show` | 81 | Advanced | tecnico | Detalhe append-only solido (saldo hero, ajuste, historico, useForm) — limitado por cores cruas e validacao so-toast. |
| `Ponto/Colaboradores/Index` | 81 | Advanced | tecnico | Lista limpa com busca debounced 350ms e empty/search states; falta filtros, reuso de SearchInput e reflow mobile. |
| `Produto/BulkEdit` | 81 | Advanced | larissa | Edicao em massa com banner destrutivo + confirm 2-passos e charter; perde em paleta crua e ausencia de preview/diff de mudancas. |
| `StockTransfer/Create` | 81 | Advanced | eliana | Transfer com guard origem≠destino (bloqueia submit + filtra destino), total+frete ao vivo, charter presente; mesmas pendencias: product-picker fake e cores stone. |
| `Fiscal/Cockpit` | 82 | Advanced | eliana | Cockpit fiscal unificado rico (ribbon KPI, saved-views chips, bulk, drawers NFe/NFSe/Eventos, density) — charter ok, mas layer fx-* fora do DS e dados mock eager. |
| `governance/ModuleGrades/Show` | 82 | Advanced | wagner | Detalhe rico: sparkline a11y, dossier markdown deferred, modal Evoluir, gaps ordenados — porém botão Evoluir verde hardcoded e accents per-dim em cores cruas, não tokens v4. |
| `NfeBrasil/Manifestacao/Index` | 82 | Advanced | eliana | Cockpit fiscal forte: master/detail, atalhos J/K/C/D/R, bulk toolbar sticky, KPIs, PrazoBadge, localStorage; charter+visual-comparison aprovados. Perde por confirm()/prompt() e cores raw. |
| `Nfse/Show` | 82 | Advanced | eliana | Detalhe limpo com AlertDialog de cancelamento (contador+minLength) e aside de venda; falta token roxo e auto-refresh no processando. |
| `Ponto/Espelho/Show` | 82 | Advanced | tecnico | Espelho mensal forte (heatmap clicavel->scroll, totalizadores, imprimir PDF, nav mes) — limitado por paleta crua com azul e cor-unica. |
| `Ponto/Intercorrencias/Index` | 82 | Advanced | tecnico | Lista madura: PageFilters+chips+EmptyState contextual (search vs vazio)+StatusBadge; falta busca textual e header canon orfao. |
| `RecurringBilling/Planos/Create` | 82 | Advanced | eliana | Form de plano limpo com campos condicionais (custom/CFOP/NFS-e), kbd ⌘↵/Esc e validação por campo; pena estar fora do shell/DS e sem máscara de valor. |
| `Repair/DeviceModels/Index` | 82 | Advanced | misto | Lista madura com Deferred+skeleton, persist localStorage e EmptyState; perde por KpiCard duplicado e selects nativos. |
| `StockAdjustment/Index` | 82 | Advanced | eliana | Lista densa exemplar: filtro sticky filial/data, pills tipo, perm-gate preco, empty dual (busca vs zero), tabular-nums; perde em tokens stone crus e confirm() nativo. |
| `StockTransfer/Index` | 82 | Advanced | eliana | Lista transfer solida com filtro status+filial+data, origem→destino inline, pills status, print/ver/excluir; mesmos gaps de tokens stone e ausencia de defer. |
| `Admin/ScreenReview` | 83 | Advanced | wagner | Tri-pane PDCA bem montado: Deferred por pane, localStorage, PageHeaderActions, toast e charter+review; falta dialog real pra notes/initiative e re-smoke ainda e placeholder toast. |
| `Essentials/Documents/Index` | 83 | Developing | misto | CRUD de docs/memos forte: tabs, upload com progresso, share dialog, AlertDialog e toasts, tudo tokenizado. |
| `Ponto/Intercorrencias/Create` | 83 | Advanced | tecnico | Form rico com classificacao IA (texto livre->preenche campos), bom estado loading/erro; perde em checkbox nativo + header canon orfao. |
| `Produto/Index` | 83 | Advanced | larissa | Catalogo Cowork forte: Inertia Deferred+skeletons, KPIs, tabs categoria, localStorage de prefs; perde em tokens crus e densidade card vs tabela p/ balcao. |
| `ProjectMgmt/MyWork/Index` | 83 | Advanced | wagner | Painel duplo My Work + Inbox com foco alternável (Tab), J/K/E/R, defer-guard, ring de foco via primary; falta charter e tem blue-500 cru no dot de não-lida. |
| `RecurringBilling/Configuracoes/Index` | 83 | Advanced | eliana | Config rica (gateways/dunning/NFe/webhooks com copiar+docs+tour) mas majoritariamente read-only/'em breve' e fora do shell canon. |
| `RecurringBilling/Planos/Edit` | 83 | Advanced | eliana | Edit espelha o Create com aviso inteligente de slug alterado; falta guarda em ciclo/valor com assinaturas ativas e alinhamento ao DS/shell. |
| `Admin/GovernanceV4` | 84 | Advanced | wagner | Tri-pane estado-da-arte com ⌘K, localStorage, Deferred granular, KpiGrid e charter+review; mancha sao os window.prompt/confirm pra initiative/override. |
| `Cliente/Index` | 84 | Advanced | larissa | Flagship denso (KB ⌘K/J/K, KPI strip clicavel, 6 filtros, drawer 5 tabs, avatar HSL) — execucao Leader mas paleta inline oklch/sky-rose fora dos tokens e header nao-canon. |
| `Essentials/Holidays/Index` | 84 | Developing | misto | Lista de feriados solida: filtros por localidade/data, dialog create/edit, badge de dias, empty state, full DS. |
| `Fiscal/Nfe` | 84 | Advanced | eliana | Cockpit NF-e/NFC-e exemplar de perf (Inertia Deferred + only:[] partial reload, J/K/Enter nav, chips status server-side, drawer SEFAZ) — melhor padrao tecnico do batch; acoes write ainda stub. |
| `Nfse/Index` | 84 | Advanced | eliana | Lista madura com atalhos J/K/N//, filtros persistidos e tooltips; perde no token roxo e Select value vazio (bug Radix). |
| `ProjectMgmt/Backlog/Index` | 84 | Advanced | wagner | Lista densa com filtros multi + bulk-edit sticky + defer-guard + persist localStorage e badges canônicos; falta charter e atalhos de teclado (J/K) que o resto do módulo tem. |
| `Repair/JobSheet/Show` | 84 | Advanced | tecnico | Melhor da leva: painel FSM completo (fetch, confirm, motivo, toast, side-effect, permissao) mas cores cruas e modal hand-roll. |
| `Site/Pricing` | 84 | Advanced | misto | Landing de preços convincente: billing toggle, tiers/FAQ extraidos, confidence row, CTA final roxo; falha em token success cru e a11y do toggle. |
| `Home/Index` | 85 | Leader | misto | Landing pos-login limpa e WCAG-AA (8 KPI Vendas/Compras agrupados, accent-map JIT-safe, primary roxo, permission gate, fallback legacy) — solido mas KPIs estaticos sem drill. |
| `Ponto/Dashboard/Index` | 85 | Leader | wagner | Cockpit rico (KPIs clicaveis, presenca ao vivo, chart 7d, polling only:[]) — peca skeleton, a11y do chart e charter. |
| `ProjectMgmt/Triage/Index` | 85 | Leader | wagner | Fila de triagem com atribuição inline otimista (owner/prio/cycle/epic), rollback+banner erro, chips de motivo, J/K/Enter, responsivo grid→stack e charter; só falta confirmar gate F3 (DRAFT). |
| `RecurringBilling/Faturas/Index` | 85 | Leader | eliana | Lista de faturas estado-da-arte (KPIs hero+sparkline, filtros pill, defer+skeleton, cancel dialog LGPD-aware), limitada por divergência do shell/DS e criação ainda bloqueada. |
| `RecurringBilling/Planos/Index` | 85 | Leader | eliana | Lista de planos forte (KPIs hero+distribuição de ciclos, defer+skeleton, flash, ⌘K, atalhos N/J/K) limitada por cores cruas, a11y de linha e métricas aproximadas. |
| `Sells/Show` | 85 | Leader | larissa | Detalhe 8/4 deferred com proxima-acao FSM, emit NF-e/NFS-e, timeline unificada, multi-modo print e cheat-sheet; perde por tones crus e alert() nativo. |
| `TransactionPayment/Edit` | 85 | Leader | eliana | Edit token-puro forte: Select DS, campos condicionais por metodo, toast validation+onError, contexto da transacao; falha em mask de cartao e red-600 cru. |
| `TransactionPayment/Show` | 85 | Leader | eliana | Detalhe/recibo token-puro elegante: amount destacado, campos condicionais por metodo, print (no-print), download anexo, badges status; falta sanitize e PageHeader. |
| `Cliente/Show` | 86 | Leader | larissa | Detalhe de cliente maduro: 9 tabs, Deferred em tudo, cowork-primary, CPF mascarado LGPD, frota condicional. |
| `Jana/Admin/Governanca/Index` | 86 | Leader | wagner | Governanca MCP estado-da-arte interno (3 secoes SubNav+persist LS, KPI taxa-sucesso/p95, chart calls/custo toggle, RBAC denied, EmptyState/StatusBadge shared) — melhor conformance DS do batch. |
| `Ponto/Aprovacoes/Index` | 86 | Leader | tecnico | Lista densa estado-da-arte (KPI-filtro, bulk, dialogs, empty/search, toasts); peca por cores cruas, confirm() nativo e zero charter. |
| `ProjectMgmt/Inbox/Index` | 86 | Leader | wagner | Caixa de entrada agrupada por tipo com marca-lido otimista, deep-link pro Board, J/K/Enter/R, banner erro role=alert, empty-state e charter; tokens limpos. Marcada DRAFT aguardando screenshot Wagner. |
| `Site/Register` | 86 | Leader | misto | Cadastro espelha Login com mesma qualidade: social, gate allowRegistration, copy '30s sem cartao'; falta strength meter e validacao client de match. |
| `Whatsapp/Settings` | 86 | Leader | misto | Wizard OAuth Meta exemplar: charter live v2, tokens semanticos, Esc/autofocus/aria, empty-state honesto, error-recovery solido — melhor da leva. |
| `TransactionPayment/Index` | 87 | Leader | eliana | Melhor lista do lote: Inertia::defer+Skeleton nos KPIs, filtros persistidos em localStorage, paginacao server real, Badge/token puro; falta PageHeader e date-range UI. |
| `Atendimento/CaixaUnificada/Index` | 88 | Leader | larissa | Inbox omnichannel madura: Centrifugo+polling fallback, atalhos J/K/E/A + /, Deferred granular, EmptyState, tokens primary e charter+review; pesa header hand-rolled e 3 acoes topnav disabled. |
| `RecurringBilling/Index` | 88 | Leader | eliana | Cockpit 3-col de altíssimo craft (defer, ⌘K palette, Jana IA, troubleshooters, timeline, atalhos) — teto puxado por mock client-side, a11y de itens clicáveis e fuga do shell/DS canon. |
| `Sells/Create` | 88 | Leader | larissa | Form POS denso e maduro (1647 linhas, charter): autosave multi-tenant, atalhos /, Cmd+Enter, error-scroll, NumericInputPtBR; perde por cores cruas nos cards e header fora do canon. |
| `Site/Login` | 88 | Leader | misto | Login estado-da-arte: token-puro, floating-label, social IdP, show/hide, flash error, copy PT-BR caloroso; falta wiring a11y de erro de campo. |
| `ProjectMgmt/Board/Index` | 89 | Leader | wagner | Kanban estado-da-arte: drag-drop otimista com optimistic-lock 409/403, atalhos J/K/E/A, polling+on-focus, DetailSheet via URL, charter presente; só leves blue-leaks (border-l-blue-500). |
| `Financeiro/Unificado/Index` | 90 | Leader | eliana | Cockpit financeiro denso estado-da-arte (KPI hero+sparkline, multi-filtro, bulk, drawer 3-abas, atalhos J/K/space/B, OCR boleto) — top do batch, so peca mistura emoji+oklch cru vs DS. |
| `Sells/Index` | 90 | Leader | larissa | Cockpit de vendas estado-da-arte (1806 linhas, charter, score 9.75): SLA pills, pipeline FSM, ⌘K, saved views, bulk emit, sparkline deferred; desvia do DS por CSS Cowork scoped. |
| `Atendimento/Inbox/Index` | 91 | Leader | misto | Cockpit 3-paineis estado-da-arte: charter, Deferred+skeletons, J/K/E/A, Centrifugo+polling fallback, tokens primary — falta polish mobile/a11y real-time. |
| `Financeiro/Cobranca/Index` | 94 | Leader | eliana | Port Cowork F1.5 96/100: charter live, defer+Deferred, KB-9.75, localStorage, PII mask, primary roxo 295 - referencia de excelencia do modulo. |

## ⚠️ Não-localizadas (1) — alvo Inertia::render sem .tsx no caminho esperado (verificar mapeamento, não é nota baixa real)

- `Financeiro/Boletos/Index` — Não encontrada — não há Financeiro/Boletos/Index.tsx; boletos aparecem só em Cobranca/Index e sheets de ContasBancarias/Unificado.

---

## Próximo passo

Aplicar o método na **#1 prioridade** (`NfeBrasil/Transactions/NfceStatus`, nota 38) ou na de **maior impacto×menor esforço** entre as Top 30. Cada fix cria teste anti-regressão (ADR 0230 Invariante A) e a nota só sobe (ratchet). Este board é o baseline (`screen-grades-baseline-2026-05-30.json`).
