---
id: requisitos-recurring-billing-index-visual-comparison
---

# Visual canon — Recurring/Index (Cobrança recorrente)

> **Fonte atual (2026-11-19, lote L6 da auditoria RecurringBilling):** `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx`,
> resolvida por `node scripts/design/ancora.mjs RecurringBilling/Index` → `related_prototype (charter)`.
> Papel: **fonte de design** (decisão [W] 2026-11-19, b4 da auditoria). Os arquivos `prototipo-ui/prototipos/recurring/*`
> e `prototipo-ui/cowork-snapshot/` citados abaixo saíram do repo; o texto abaixo é o registro de 2026-05-16 e não
> descreve a fonte de hoje. Gap por parte: `cobranca-recorrente-gap.md`. Veredito de medição: seção
> "Medição 2026-11-23" no fim deste arquivo (L6 PR 2). A medição de 18/09 é inválida e foi substituída:
> os 4 `design.json` eram a mesma aba e a captura de produção pegou o banner de cookies e o tour.

> **Status:** Cowork output renderizado e validado em 2026-05-16. Match exato com 2 screenshots Wagner.
> **Destino visual:** `9,75/10` (Método KB-9.75 alvo final, **NÃO** R#1 intermediário `3,8/10`).
> **Fonte canônica:** `prototipo-ui/prototipos/recurring/recurring-page.jsx` _(removido em 2026-05-20, 1070e3759b7)_ (1.637 linhas IIFE expondo `window.RecurringPage`) + `prototipo-ui/prototipos/recurring/recurring-data.jsx` _(removido em 2026-05-20, 1070e3759b7)_ (mock 18 assinaturas + 5 planos + timelines + troubleshooters) + `prototipo-ui/prototipos/recurring/recurring-icons.jsx` _(removido em 2026-05-20, 1070e3759b7)_
> **Snapshot vivo:** `prototipo-ui/cowork-snapshot/` (rodar `python -m http.server --directory prototipo-ui/cowork-snapshot 5550` → `http://localhost:5550/Oimpresso%20ERP%20-%20Chat.html` → localStorage `oimpresso.route=recurring`). _(alvo não resolve no repo — proveniência não determinada)_

---

## Arquitetura visual (4 sub-rotas)

| Sub-rota | URL alvo | Conteúdo |
|---|---|---|
| `assinaturas` | `/recurring` (default) | 3-col: filtros sidebar · lista 18 assinaturas · drawer detalhe |
| `planos` | `/recurring/plans` | CRUD 5 planos: cardápios/banner/wind/fachada/rótulos |
| `faturas` | `/recurring/invoices` | Lista faturas (open/paid/overdue) + ação cancelar |
| `configuracoes` | `/recurring/settings` | Gateways · régua dunning · NFe auto-emission · webhooks |

Tab control via `tab` prop em `<RecurringPage tab onTab>` — Inertia equivalente: query string `?tab=assinaturas|planos|faturas|configuracoes` ou rotas separadas.

---

## Tela 1 (Assinaturas) — decomposição cirúrgica

### Top header (linha 1)
- Title `Cobrança recorrente` (28px / 700 / -0.02em)
- Subtitle mono `13 ATIVAS · MRR R$ [redacted Tier 0] · CHURN 8.3%` (11px mono uppercase letter-spacing 0.05em)
- Tabs 4 botões com badge contador (`Assinaturas 1`, `Planos 2`, `Faturas 3`, `Configurações 4`) — tab ativa underline azul
- CTA primary `+ Nova assinatura` (texto+ícone) + 6 botões mini-navigation (provavelmente paginar/voltar)

### 4 KPI cards (linha 2 — grid 4col gap=10px)
1. **MRR · RECEITA RECORRENTE** (card escuro `var(--text)` bg + sparkline verde) — `R$ [redacted Tier 0]` + delta `↑ R$ [redacted Tier 0]k vs abr`
2. **CHURN MAIO** — `1 cancelamento` + sub `taxa 8.3%`
3. **PRÓXIMA COBRANÇA** — `amanhã` + sub `R$ [redacted Tier 0] · 4 boletos`
4. **RETENTADO FALHOS** — `2` em `var(--bad)` + sub `requer ação` em bad

### 3-col body (Filtros · Lista · Drawer)

**Coluna esquerda · Filtros (~220px)**
- `★ Mostrar só favoritos` toggle (count badge)
- Seção `PRÓXIMA COBRANÇA`:
  - Qualquer data 18 (selected default · highlight azul)
  - Hoje 1, Amanhã 1, Esta semana 2, Próx. 30 dias 8
- Seção `STATUS`:
  - Todas 18 (selected · highlight azul)
  - Em dia 9 (dot verde), Retentando 2 (dot warn), Falharam 2 (dot bad), Pausadas 1 (dot mute), Canceladas 4 (dot mute strikethrough)
- Seção `PLANO`:
  - Lista 5 planos com `name`, `R$ X,XX`, `N ativ.`
- Rodapé (quando filtra): `MRR FILTRADO R$ X | N ativ. de 18`

**Coluna meio · Lista assinaturas (flex 1)**
- Search input topo `/` shortcut hint + `Buscar (/) — cliente, CNPJ, OS` + contador `18 / 18`
- Cada linha:
  - Avatar circular (28×28, gradient derivado de `hueFor(nome)` — `linear-gradient(135deg, oklch(0.70 0.10 H), oklch(0.50 0.13 H))`) + iniciais 2 letras
  - `★` favorito (toggle ouro/cinza)
  - Nome bold + linha sub `plano · sub-info · desde há Xa/Xm`
  - Lado direito: `RecStatusBadge` (5 estados — em dia/retentando N/M/falhou Nx/pausada/cancelada)
  - Linha 2: ícone método (pix/boleto/card) + valor `R$ X,XX` mono
  - Linha selecionada bg purple soft + border purple

**Coluna direita · Drawer detalhe (~340px)**
- Header: avatar 36px + nome bold + CNPJ formatado + buttons `PDF` ghost + status badge
- Card destacado purple-soft `PRÓXIMA COBRANÇA`: `em N dias` grande + valor mono + data `19 de jun. · ciclo mensal` + ícone método
- Grid 2col key-value:
  - PLANO / CICLO / DESDE / COBRANÇAS PAGAS / FALHAS / LTV / CONTATO / OS RECENTE
- Card amarelo soft `NOTA PINADA` (se `is_pinned`): "5 lojas · cada uma com KV diferente"
- Card NFE row: badge tipo NFe/NFS-e/none + ENVIO (E-mail/WhatsApp/Email+WA) + `Última: NFe 8427` + button `Reenviar`
- 2 buttons: `Editar plano [E]` + `Pausar [P]`
- Card JANA·IA com tabs Sugerir/Resumir/Perguntar + button `+ Resumir saúde da assinatura`
- Card `NOTAS & EVENTOS N itens` + input `Anotar internamente... (use #os4821)` + button `Anotar [E]`
- Lista timeline append-only (event-create/note/event-charge/event-nf/event-retry/event-status/event-plan)
- Card `HISTÓRICO DE PAGAMENTOS`: row 12 cells (1 por mês passado) com cor pago/falhou + legenda `pago (19) · falhou (1) · futuro`

---

## Schema gap vs estado atual (Modules/RecurringBilling)

Existe hoje (✓): `rb_plans`, `rb_subscriptions`, `rb_invoices`, `rb_charge_attempts`, `pg_webhook_events`, `rb_boleto_credentials` (todos com `HasBusinessScope`).

**Tabelas novas:**
- `rb_subscription_notes` — id, subscription_id, business_id, user_id, body TEXT, is_pinned BOOL, created_at, updated_at — INDEX (subscription_id, is_pinned DESC)
- `rb_subscription_favorites` — id, subscription_id, user_id, business_id, created_at — UNIQUE (user_id, subscription_id)
- `rb_subscription_events` — id, subscription_id, business_id, kind ENUM (event-create/event-status/event-plan/event-charge/event-retry/event-nf/note), by_actor VARCHAR(64), body TEXT, occurred_at TIMESTAMP — INDEX (subscription_id, occurred_at DESC)

**Colunas adicionar em `rb_subscriptions`:**
- `payment_method` ENUM('pix','boleto','card') NULL
- `last_jobsheet_id` BIGINT UNSIGNED NULL (soft link Modules/Repair — sem FK constraint pra evitar Tier 0 cross-module hard dep)
- `total_paid_cached` SMALLINT UNSIGNED DEFAULT 0
- `failed_count_cached` SMALLINT UNSIGNED DEFAULT 0
- `total_revenue_cached` DECIMAL(14,2) DEFAULT 0
- `paused_until` DATE NULL
- `churn_reason` VARCHAR(64) NULL
- `contact_phone_cached` VARCHAR(32) NULL (denormalizado pra lista rápida)

**Colunas adicionar em `rb_plans`:**
- `descricao_curta` VARCHAR(200) NULL (mock chamou `items`)
- `fiscal_type` ENUM('nfe','nfse','none') DEFAULT 'none'
- `fiscal_cfop` VARCHAR(8) NULL
- `fiscal_servico` VARCHAR(8) NULL

---

## Plano de ondas (cirúrgico — cada PR ≤300 linhas)

| Onda | Conteúdo | Estimate |
|---|---|---|
| **1** | Migration aditiva (3 tables + 12 cols) + Note/Favorite/Event Models + base Pest | 4h |
| **2** | Observer que recalcula cached cols + comando `rb:backfill-cached-fields` + Pest | 2h |
| **3** | SubscriptionController (index/show/store/cancel/pause) + Policy + AuditLog + Pest | 4h |
| **4** | `Pages/Recurring/Assinaturas/Index.tsx` — header + 4 KPIs + sidebar filtros + lista (read-only) | 5h |
| **5** | `Pages/Recurring/Assinaturas/_components/SubscriptionDrawer.tsx` (drawer detalhe completo) | 4h |
| **6** | PlanController + `Pages/Recurring/Planos/{Index,Create,Edit}.tsx` + FormRequest | 5h |
| **7** | `Pages/Recurring/Faturas/Index.tsx` (reusa InvoiceController existente) | 3h |
| **8** | `Pages/Recurring/Configuracoes/Index.tsx` (gateways · régua · NFe auto · webhooks) | 4h |
| **9** | NotesController + FavoritesController + integração JANA·IA fallback graceful + reenviar NFe wire | 4h |
| **10** | Sidebar entry + redirect 301 /recurringbilling→/recurring + Permissions Spatie + cutover | 2h |

**Total efetivo:** ~37h código. Recalibrado fator 10x ADR 0106 ≈ 4h IA-pair por onda + margem 2x.

---

## Validação realizada na Onda 0 (2026-05-16)

- ✅ `python -m http.server 5550 --directory prototipo-ui/cowork-snapshot` rodando
- ✅ Navegação via `mcp__Claude_Preview__preview_screenshot` confirma rendering pixel-perfect das 2 screenshots Wagner
- ✅ Estrutura DOM mapeada: header, 4 KPIs, 3-col body, drawer com 6 cards
- ✅ Schema gap enumerado: 3 tables + 12 cols
- ✅ Cross-module dependências identificadas: link Repair OS (soft FK), Jana panel (fallback graceful), reenviar NFe (endpoint já existe em `POST /nfe/emissoes/{id}/reenviar-email`)
- ✅ Visual canon `recurring-page.jsx` 1.637ln + `recurring-data.jsx` 220ln + `recurring-icons.jsx` 38ln salvos em `prototipo-ui/prototipos/recurring/` _(removido em 2026-05-20, 1070e3759b7)_

**Próximo:** Onda 1 — migration aditiva + 3 models.

---

## Onda 7 · paridade medida no runtime — 2026-09-08 [CC]

Mesma sonda estrutural · **mesmo tema** (`dark`, medido nos dois) · **mesma viewport** (`2560`) ·
ambos estabilizados. Prod `/recurring-billing` × design rota `recurring`
(`cobranca-recorrente-page.jsx`, servido do espelho).

⚠️ **Ressalva de frescor, honesta:** as âncoras do Financeiro foram provadas SYNC por hash, mas
esta **não** — o `cobranca-recorrente-page.jsx` volta **inline** no `get_file` (abaixo do piso de
persistência), e a rota fiel pra ele depende do bundle, que o painel do protocolo declara **sem
dono nem automação**. A comparação abaixo vale contra o espelho; não está provada contra o vivo.

### Veredito: 1 achado REAL

| Elemento | PROD | DESIGN | |
|---|---|---|---|
| Abas | Assinaturas(162) · Planos(109) · Faturas · Configurações | idem | ✅ |
| KPI MRR | header + "MRR filtrado" | `.cr-stat` hero | ✅ presente |
| KPI churn | "Churn este mês · 0 cancelamentos · taxa 0%" | "Churn este mês · taxa X%" | ✅ presente |
| KPI próximas | "Próxima cobrança" | "Próximas cobranças" | ✅ presente (copy adaptada) |
| KPI recuperar | "Retentado falhos · requer ação" | "A recuperar · N cobranças falhas" | ✅ presente (copy adaptada) |
| h1 | "Cobrança recorrente" | "Assinaturas · Cobrança Recorrente" | ⚠️ difere |
| **Branco puro (`rgb(255,255,255)`)** | **12 elementos** | **0** | ❌ **achado** |

**O achado — `bg-white` hardcoded em tema dark.** Medido: 12 elementos com
`background-color: rgb(255,255,255)` na prod, **todos os 12 dentro do `main`** (zero no shell) —
KPI cards, painel de filtros e a lista de assinaturas. No design, `rgb(255,255,255)` aparece
**zero** vezes: o hero usa token (`oklch(0.94 0.005 90)`) e os demais KPIs são transparentes,
herdando o fundo do tema. É cor **crua**, não tokenizada, na camada de tela — e não responde ao
tema. Hoje é legível **por acaso**, porque os textos dentro também são escuros hardcoded; qualquer
tokenização futura do texto quebraria o contraste.

### Dois falsos achados derrubados na verificação (e a mesma classe de erro, 2×)

1. *"texto `oklch(0.94)` sobre branco = ilegível"* — **falso**. Isso era a cor **herdada no
   container**; os textos-folha são escuros e medem **19–20:1** (passam AA folgado). 17 de 18 ok.
2. *"53 elementos reprovam AA com contraste 1.0"* — **falso**. São iniciais de avatar, e o
   medidor lia o fundo do **card ancestral** ignorando `background-image`: cada avatar tem
   `linear-gradient(135deg, oklch(0.7 0.1 H), oklch(0.5 0.13 H))` próprio.

Os dois são a mesma lápide (§5 2026-07-16, *medir a propriedade errada e chamar de verificado*),
cometida duas vezes na mesma tela. O que separou o achado real dos falsos foi **medir o fundo
efetivo**, subindo a árvore até um `background-color` opaco **ou** um `background-image`.

---

## Medição 2026-11-23 (L6 PR 2) — substitui a de 2026-09-18 [CC]

Mede as **6 telas** do módulo contra `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx`
(sha256 `49104ea2d640…`, último commit `99f169a688`, 2026-11-19 — a versão do L7 #11241). Esta seção
traz o **método e o canário**, que valem para as seis; o veredito de cada tela está no dono dela:

| Tela | Dono do veredito | Veredito |
|---|---|---|
| `RecurringBilling/Index` | esta seção, abaixo | DIVERGE — 3 reais, 2 não conclusivos |
| `RecurringBilling/Faturas/Index` | [`Faturas-Index-visual-comparison.md`](Faturas-Index-visual-comparison.md) | DIVERGE — 5 reais |
| `RecurringBilling/Planos/Index` | [`Planos-Index-visual-comparison.md`](Planos-Index-visual-comparison.md) | DIVERGE — 3 reais, 1 não conclusivo |
| `RecurringBilling/Configuracoes/Index` | [`Configuracoes-Index-visual-comparison.md`](Configuracoes-Index-visual-comparison.md) | DIVERGE — 2 reais |
| `RecurringBilling/Planos/Create` | [`Planos-Create-visual-comparison.md`](Planos-Create-visual-comparison.md) | DIVERGE — página × drawer + 7 grupos de forma |
| `RecurringBilling/Planos/Edit` | [`Planos-Edit-visual-comparison.md`](Planos-Edit-visual-comparison.md) | DIVERGE — igual ao Create |

Snapshots e `resultado.json`: `governance/design/targets/medidas/RecurringBilling--*/`.

### Método

- **Fonte provada pelo git.** O `cowork-mirror-freshness --compare --check` que o pedido citava não existe
  mais: os modos de frescor contra o Cowork saíram em 2026-10-23 (ADR 0438), e o script responde
  `uso: … (os modos de frescor contra o Cowork saíram em 2026-10-23, ADR 0438)`. Desde então a fonte é o
  arquivo no git; a prova é o sha acima. `node scripts/design/protocolo.config.mjs --selftest` → OK.
- **Âncora:** `node scripts/design/ancora.mjs RecurringBilling/<Tela>` — Index declara `related_prototype`;
  Faturas, Planos e Configurações resolvem pelo `bundle_source`; Create e Edit declaram `n/a` no charter e
  foram medidas contra os drawers `novo`/`editar` que o próprio protótipo mapeia para elas (`ROTAS.rb-planos.drawers`).
- **Mesma sonda:** `node scripts/design/design-diff.mjs --probe`, injetada nos dois lados com papéis por lado.
  Os papéis ficaram em `governance/design/targets/roles/RecurringBilling--*.json` (override do lote).
- **Mesmo tema:** claro nos dois (é o tema do usuário em produção). No protótipo, `__setTweak('theme','light')`
  com `prefers-color-scheme: light`; o `_ds/` servido pela regra do `ds-runtime-path.mjs` — sem ele os
  tokens não resolvem e a medida sai errada (aconteceu na primeira tentativa e foi descartada).
- **Mesma viewport:** 1280×900 nos dois. Produção via iframe da mesma origem a 1280px.
- **Produção:** `oimpresso.com`, `business_id=1` (lido dos props da página em cada medida). Sem banner de
  cookies, tour ou diálogo aberto: a medida grava `sobrepostosFixos` e `dialogsAbertos`, vazios nas seis.
- **Vista certa:** D0 pelos contratos `recorrente-*.contract.json` — OK nas 4 Index (Index 19×15 cópias,
  Faturas 23×26, Planos 16×15, Configurações 11×10). Create/Edit não têm contrato; identidade pelo título
  do drawer (`Novo plano`, `Editar plano`) gravado em `__meta.dialogsAbertos`.
- **Drawer depois da animação:** a primeira medida do drawer pegou a animação `cr-slide` no meio
  (`opacity: 0.4`) e foi refeita esperando `getAnimations()` vazio.
- **Texto de produção redigido:** o `assinatura` do `prod.json` versionado guarda só as cópias de UI do
  contrato; nomes de cliente e valores ficaram fora (sha do original em `__meta.assinaturaRedigida`).

### Canário (antes de concluir)

Em produção, Faturas, forcei `h1` a 22px/700 (o valor do protótipo) e removi o último KPI do DOM.
Resultado esperado e obtido: as linhas de título **somem** e aparece `kpi.count prod=3 · design=4`.
A sonda discrimina. Achado lateral: com o KPI só **escondido** (`display:none`), a contagem continua 4 —
a sonda conta elementos, não elementos visíveis.

### Fora do veredito, e por quê

- **Shell (sidebar):** o protótipo renderiza a sidebar recolhida (55px) e a produção expandida; o mapa
  `--shell-roles` não casa no protótipo. Não medido.
- **D6 cor:** sai `IGUAL`, mas a linha de saúde diz que os tokens de cor de produção não resolvem na raiz
  do documento (vivem no `.cockpit`). Leia o `IGUAL` de cor como fraco.
- **D9 texto:** as regiões `data-contract` só existem em produção, então a comparação de formato sai SEM-DADO.
  A exceção é Configurações, onde a sonda achou identificador técnico na copy de produção.
- **Achado de 2026-09-08 (`bg-white` em tema escuro):** não re-medido — esta medição é em tema claro.
  `git grep bg-white resources/js/Pages/RecurringBilling/Index.tsx` não acha nada.

### Veredito — `RecurringBilling/Index`

Reproduz: `node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/RecurringBilling--Index/prod.json governance/design/targets/medidas/RecurringBilling--Index/design.json --check --contrato governance/design/contracts/recorrente-assinaturas.contract.json`

| Dim | Campo | Produção | Protótipo | Leitura |
|---|---|---|---|---|
| D4 | título | 24px / 600 | 22px / 700 | **real** — produção usa o PageHeader compartilhado; mexer muda o título de todos os módulos |
| D4 | valor do KPI | 24px | 22px | **real** |
| — | abas no cabeçalho | Assinaturas · Planos · Faturas · Configurações, com contador | não tem; navega pela sub-nav do shell | **real** (lido no DOM; é a parte "Decidir" do gap) |
| D2 | linhas do filtro | 8 | 5 | não conclusivo — o papel conta blocos da coluna lateral, e as duas colunas têm blocos diferentes |
| D2 | colunas da linha | 4 | 2 | não conclusivo — a linha de produção é favorito + avatar + texto + valor; a do protótipo agrupa em 2 |
| D6 | cor | ok | ok | IGUAL (fraco, ver acima) |
| D8 | alinhamento do KPI | ok | ok | IGUAL |
