---
runbook: cliente-drawer-760px
module: Crm/Cliente
adr: 0179
charter: Pages/Cliente/Index.charter.md v3
last_updated: 2026-05-21
status: ready-for-execution
waves: 7 (+ Z fechamento)
estimate_total: ~70h elapsed (~35h IA-pair + margem 2x ADR 0106)
---

# RUNBOOK — Cliente drawer lateral 760px substitui Show.tsx full-page

> **Tipo:** runbook reproduzível (cockpit-runbook 11 seções)
> **Refs canônicas:** [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md), [Charter Index v3](../../../resources/js/Pages/Cliente/Index.charter.md), [visual-comparison](cliente-drawer-760-visual-comparison.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md), [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md), [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md), [ADR 0177](../../decisions/0177-mwart-excecao-cliente-show-wave-paralela.md)
> **Validado em:** Wagner 2026-05-21 (aprovação opção A dossiê wagner-understand)

## 1. Objetivo

Migrar paradigma da tela `/cliente` — deixar de abrir `Show.tsx` em rota dedicada (`/cliente/{id}` full-page com 8 tabs operacionais) e passar a abrir **drawer lateral 760px** sobre `Index.tsx` com **8 tabs cadastrais** (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria). Origem: protótipo Cowork `prototipo-ui/prototipos/clientes/` aprovado por Wagner com score KB-9.75 9,4/10. Show.tsx full-page é **deletado no mesmo PR** (Q1 zero-sunset). Tab "OSs" wrapping das 8 sub-tabs Wave Final 2026-05-21 (`_show/LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `ActivitiesTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab`). Larissa biz=4 ROTA LIVRE em 1280×1024 é alvo de UX; biz=1 WR2 SC é canary de prod. Estimate ~35h IA-pair × margem 2x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) = **~70h elapsed**.

## 2. Decisões Q1-Q4 finais Wagner (2026-05-21)

| # | Pergunta | Decisão | Implicação |
|---|---|---|---|
| Q1 | Show.tsx sunset 30d ou delete agora? | **DELETA AGORA** (mesmo PR drawer, sem modo "ficha completa" paralela) | Os 8 `_show/*Tab.tsx` são reposicionados como sub-tabs aninhadas dentro da tab "OSs" do drawer 760. Rota `/cliente/{id}` faz redirect 302 → `/cliente?contact_id={id}&tab=identificacao`. |
| Q2 | Edit.tsx separado ou autosave inline? | **INLINE + autosave on blur** (5 endpoints PATCH `/cliente/{id}/{identificacao,contato,endereco,comercial,classificacao}`, debounce 800ms, optimistic UI + rollback em 4xx/5xx) | Edit.tsx removido. 5 endpoints servidor novos. Sem botão "Salvar" explícito — UX inline shadcn `<Input onBlur>` + toast confirmação. |
| Q3 | Tabela `clientes` paralela ou ALTER `contacts`? | **ALTER TABLE `contacts` aditivo** (~16 colunas NULL reversíveis: `tipo`, `fantasia`, `ie`, `rg`, `nascimento`, `cargo`, `tel2`, `canal`, `tabela_preco`, `pgto`, `obs_comercial`, `segmento`, `tags` JSON, `vip`, `favorito_users` JSON, `site`) | Não toca core UPOS. Migration reversível (down() restaura). Sem tabela `clientes` paralela. Compatível com legacy `/contacts/{id}` Blade. |
| Q4 | Tab IA gate quota ou Default ON? | **DEFAULT ON pra todos** (sem gate `copiloto.admin.custos` inicial) | 3 endpoints LLM (`/cliente/{id}/ia/{resumo,segmento,proxima-acao}`) consomem `Modules/Jana/Services/Ai/LaravelAiSdkDriver`. Telemetria `CustosService::log()` ativa desde dia 1. Wagner regride pra gate se custo/dia ultrapassar baseline. Score risco é determinístico (NÃO chama LLM). |

## 3. Pré-flight obrigatório

Antes de Edit/Write em `Modules/Crm/` ou `resources/js/Pages/Cliente/`:

- [ ] Ler [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) inteira (162 linhas — paradigma + Q1-Q4 + pegadinhas Tier 0)
- [ ] Ler [Charter Index v3](../../../resources/js/Pages/Cliente/Index.charter.md) (113 linhas — Mission/Goals/Non-Goals/Anti-hooks)
- [ ] Ler [visual-comparison](cliente-drawer-760-visual-comparison.md) (310 linhas — 15 dimensões + gate F1.5)
- [ ] Ler [`prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`](../../../prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md) (381 linhas — schema BR + 4 endpoints IA + checklist KB-9.75 9,4/10)
- [ ] Ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) (6 meta-anti-padrões + 15 técnicos — Wave Financeiro rejeitada 2026-05-09)
- [ ] Skill `multi-tenant-patterns` Tier A ativa (ADR 0093 IRREVOGÁVEL — `business_id` global scope obrigatório em 10 endpoints novos)
- [ ] Skill `mwart-process` Tier A ativa (5 fases obrigatórias — PLAN → BACKEND BASELINE → FRONTEND INCREMENTAL → QA → CUTOVER)
- [ ] Worktree confirmado: `D:/oimpresso.com/.claude/worktrees/frosty-greider-83ab2f` (lição R8 — PR #1032 quase perdeu 4h por path errado)

## 4. Waves A-G + Z (sequência canônica)

### Wave A — Charter v3 + ADR 0179 + RUNBOOK + visual-comparison (~2.5h) — **JÁ EXECUTANDO AGORA**

Entregáveis Wave A:

- [x] `memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md` (ADR `proposed` — 162 linhas)
- [x] `resources/js/Pages/Cliente/Index.charter.md` v3 (`status: live` — 113 linhas, drawer_pattern 760px-lateral + 8 tabs)
- [x] `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md` (310 linhas — gate F1.5 status draft)
- [x] **ESTE RUNBOOK** `memory/requisitos/Crm/RUNBOOK-Cliente-drawer-760px.md`
- [ ] Wagner aprova ADR 0179 `proposed` → `accepted` no PR Wave A (libera Wave B)
- [ ] `Pages/Cliente/Show.charter.md` v2 atualizado pra `status: superseded` + `superseded_by: [Pages/Cliente/Index.charter.md v3]` (NÃO deletar; manter como histórico)
- [ ] Commit conventional `docs(crm): ADR 0179 + Charter v3 + RUNBOOK + visual-comparison drawer 760` refs `Refs: SPRINT-N PASSO 1`

### Wave B — Migration aditiva + esqueleto drawer 760 + deeplink (~4h)

Bloqueia C-F. Entregáveis:

- [ ] Migration aditiva `database/migrations/YYYY_MM_DD_HHMMSS_add_drawer_columns_to_contacts_table.php` — adiciona 16 colunas NULL reversíveis (`tipo`, `fantasia`, `ie`, `rg`, `nascimento`, `cargo`, `tel2`, `canal`, `tabela_preco`, `pgto`, `obs_comercial`, `segmento`, `tags` JSON, `vip`, `favorito_users` JSON, `site`). `down()` dropa todas.
- [ ] Migration nova `database/migrations/YYYY_MM_DD_HHMMSS_create_anotacoes_table.php` — `id`, `business_id` (FK + global scope), `contact_id` (FK), `user_id`, `corpo` text, `pinned` boolean, timestamps. Pra tab Auditoria notes anexadas.
- [ ] Expandir `resources/js/Pages/Cliente/Index.tsx` ClienteSheet `w-[480px]` → `w-[760px] sm:max-w-[760px]`
- [ ] Header drawer refactor — avatar 56px HSL determinístico + toggle PF/PJ + badge Ativo/Inativo/Bloqueado + 2 CTAs ("Imprimir ficha" outline + "Falar com Copiloto →" primary → `/jana/chat?context=cliente:{id}`)
- [ ] HistoricoStrip component (4 KPIs cadastrais — OSs total · Ticket médio · Saldo aberto · Última compra + FrescorPill inline) acima das tabs
- [ ] Esqueleto 8 tabs vazias (`role=tablist` + `role=tab` + `role=tabpanel` + `aria-selected` + `aria-controls` + atalho teclado `1-8` quando drawer aberto)
- [ ] `_drawer/IdentificacaoTab.tsx` skeleton (vazio) · `ContatoTab.tsx` · `EnderecoTab.tsx` · `ComercialTab.tsx` · `ClassificacaoTab.tsx` · `OssTab.tsx` · `IATab.tsx` · `AuditoriaTab.tsx`
- [ ] `<Deferred data="tabIdentificacao">` wrapper em cada tab + handler `onValueChange` chama `router.reload({ only: ['tabContato'] })` lazy
- [ ] Deeplink — rota `/cliente/{id}` GET redirect 302 → `/cliente?contact_id={id}&tab=identificacao` (em `app/Http/Controllers/ContactController.php::show` ou novo middleware)
- [ ] Rota legacy `/contacts/{id}` Blade dual-render preservado via `config('mwart.cliente_show.enabled')` (fallback emergencial)
- [ ] `@media print` CSS no drawer (pra botão "Imprimir ficha" gerar A4 com brand Oimpresso)
- [ ] `DrawerSkeleton` loading state por tab
- [ ] Pest `ClienteShowSupersededTest` — `/cliente/{id}` redirect 302; `/contacts/{id}` legacy intacto; viewport 1280×1024 sem scroll horizontal
- [ ] Commit conventional `feat(crm): drawer 760 esqueleto + migration aditiva contacts + deeplink redirect`

### Wave C — 5 tabs cadastrais inline autosave + BrLookupService (~11h)

Maior wave (paralelizável com G se Felipe paralelizar). Entregáveis:

- [ ] `resources/js/Lib/br-mask.ts` — máscaras CPF `000.000.000-00` / CNPJ `00.000.000/0000-00` / tel `(00) 0 0000-0000` / CEP `00000-000`
- [ ] `resources/js/Lib/br-validate.ts` — mod 11 (CPF/CNPJ) + regex email/site
- [ ] `resources/js/Lib/avatar.ts` — `avatarFor(id)` HSL hash determinístico (`id % 360` · `hsl(hue, 60%, 55%)`)
- [ ] `resources/js/Lib/relDate.ts` — "há Xd/Xh/Xm" formatter relativo
- [ ] `Modules/Crm/Services/BrLookupService.php` — proxy server-side ViaCEP + BrasilAPI com cache Redis (CEP 90d, CNPJ 30d). **OBRIGATÓRIO** antes de Wave C ir pra prod — Larissa biz=4 ~30 cadastros/dia fura rate limit ViaCEP/IP sem cache.
- [ ] `Modules/Crm/Http/Controllers/ClienteLookupController.php` — endpoints `GET /api/cliente/lookup/cep/{cep}` + `GET /api/cliente/lookup/cnpj/{cnpj}` (multi-tenant scope explícito)
- [ ] 5 endpoints PATCH autosave em `app/Http/Controllers/ContactController.php` (ou `Modules/Crm/Http/Controllers/ClienteCadastroController.php` NOVO) — `PATCH /cliente/{id}/{identificacao,contato,endereco,comercial,classificacao}` com debounce 800ms client-side + optimistic UI + rollback em 4xx/5xx + toast feedback
- [ ] **Tab Identificação** — Razão social/Nome, Fantasia (PJ), CNPJ + botão "Buscar CNPJ" (chama BrLookupService autopreenche razão), IE (PJ), Contato principal (PJ), Cargo (PJ), CPF/Nascimento/RG (PF). Toggle PF/PJ no header esconde/mostra campos correspondentes.
- [ ] **Tab Contato** — tel/tel2 (máscara), email (regex), site, canal preferido (radio whatsapp/email/telefone/presencial)
- [ ] **Tab Endereço** — CEP + ViaCEP no blur autopreenche logradouro/bairro/cidade/UF (via BrLookupService cache), endereço, número, complemento, bairro, cidade, UF
- [ ] **Tab Comercial** — limite crédito, prazo padrão (dias), tabela preço (radio padrao/varejo/atacado/parceiro), pgto padrão (radio pix/boleto/cartão/dinheiro/transferência), obs comercial textarea
- [ ] **Tab Classificação** — segmento (radio varejo/atacado/agência/corporativo/evento/governo), tags multi-select (9 valores semânticos), status (ativo/inativo/bloqueado), VIP toggle
- [ ] Pest `ClienteDrawerCadastroAutosaveTest` — 5 endpoints autosave + mod 11 CPF/CNPJ + cross-tenant blocked (ADR 0093) + rollback 4xx
- [ ] Pest `ClienteLookupCnpjCepTest` — BrLookupService cache hit/miss + 404 graceful + rate limit ViaCEP graceful
- [ ] Commit conventional `feat(crm): 5 tabs cadastrais autosave + BrLookupService cache Redis`

### Wave D — Tab OSs wrapper das 8 sub-tabs Wave Final 2026-05-21 (~3h)

Entregáveis:

- [ ] `resources/js/Pages/Cliente/_drawer/OssTab.tsx` wrapper component
- [ ] Decisão final layout (validar com Wagner em revisão Wave D — ADR 0179 ponto de re-revisão):
  - **Opção 1** sub-tabs aninhadas verticais (pills left 120px + content 640px)
  - **Opção 2** dropdown header "Ver: [SalesTab ▼]" + content full 760px
- [ ] Reusar literalmente os 8 sub-componentes Wave Final 2026-05-21 (preservar paridade Blade):
  - `resources/js/Pages/Cliente/_show/LedgerTab.tsx`
  - `_show/SalesTab.tsx` (default ao abrir tab OSs)
  - `_show/PaymentsTab.tsx`
  - `_show/DocumentsTab.tsx`
  - `_show/ActivitiesTab.tsx`
  - `_show/PessoasContatoTab.tsx`
  - `_show/SubscriptionsTab.tsx`
  - `_show/RewardPointsTab.tsx`
- [ ] Cada sub-tab carrega via `Inertia::defer` + `<Deferred data="ossSalesTab">` (lazy partial reload)
- [ ] Pest charter test viewport 1280×1024 — sub-tabs aninhadas couberam sem scroll horizontal (se Opção 1 falhar layout, ADR 0179 emenda formal pra Opção 2)
- [ ] Commit conventional `feat(crm): tab OSs wrapper 8 sub-tabs Wave Final 2026-05-21`

### Wave E — Tab IA 4 cards via Modules/Jana (~6h)

Entregáveis:

- [ ] `Modules/Crm/Http/Controllers/ClienteIaController.php` — 3 endpoints LLM + 1 determinístico
- [ ] `POST /cliente/{id}/ia/resumo` — consome `Modules/Jana/Services/Ai/LaravelAiSdkDriver` Sonnet/Haiku, gera resumo relacionamento editável antes de aplicar
- [ ] `POST /cliente/{id}/ia/segmento` — sugere mudanças de segmento + tags aplicáveis em 1-click
- [ ] `POST /cliente/{id}/ia/proxima-acao` — sugere próxima ação ex "Cliente sem compra há 187 dias — sugerir reativação WhatsApp"
- [ ] `GET /cliente/{id}/ia/risco` — score risco **DETERMINÍSTICO** (NÃO chama LLM — handoff §5.4, port `clientes-tabs.jsx::RiscoCliente`). NÃO conta quota `cliente_ia`.
- [ ] `resources/js/Pages/Cliente/_drawer/IATab.tsx` — 4 cards Copiloto com spinner + erro graceful 8s timeout + `aria-live=polite` ("Carregando resumo…" → "Resumo gerado")
- [ ] Telemetria `Modules/Jana/Services/CustosService::log()` ativa desde dia 1 (Wagner monitora custo Brain B/dia vs baseline 30d pós-Wave E em prod)
- [ ] Tab IA **Default ON pra todos** (Q4 sem gate quota inicial)
- [ ] Pest `ClienteIaQuotaTest` — 3 endpoints IA mock LLM + telemetria custo + risco determinístico zero LLM
- [ ] Commit conventional `feat(crm): tab IA 4 cards via Modules/Jana LaravelAiSdkDriver`

### Wave F — Tab Auditoria timeline Spatie ActivityLog v4.8 LGPD (~3.5h)

Entregáveis:

- [ ] `app/Models/Contact.php` adiciona trait `Spatie\Activitylog\Traits\LogsActivity` + `getActivitylogOptions()` config (eventos `created`, `updated`, `deleted` + status_changed customizado)
- [ ] `Modules/Crm/Http/Controllers/ClienteAuditoriaController.php` — `GET /cliente/{id}/auditoria` lista timeline (paginate 20) + `GET /cliente/{id}/auditoria/export.{csv,pdf}` LGPD Art. 18 direito de acesso
- [ ] Reusar `Modules/Auditoria/Services/AuditEntryService::forSubject(Contact $contact)` (multi-tenant scope embutido) — **NÃO** criar audit_log paralelo
- [ ] `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` — timeline vertical 6+ tipos eventos (created · field_changed · status_changed · view · os_created · note_added) + avatar+nome user + timestamp absoluto + relativo "há Xm/h/d" + botão "Exportar log" CSV/PDF
- [ ] Banner LGPD topo da tab — "Você pode exportar todos os seus dados (Art. 18 LGPD)" brand promise
- [ ] Permission `cliente.audit_view` em `DataController::user_permissions()` (Modules/Crm)
- [ ] Tabela `anotacoes` (criada Wave B) integrada na timeline como tipo `note_added`
- [ ] Pest `ClienteAuditoriaSpatieTest` — timeline 6 tipos + Exportar log CSV/PDF + LGPD Art. 18 + multi-tenant scope (cross-tenant blocked ADR 0093)
- [ ] Commit conventional `feat(crm): tab Auditoria timeline Spatie ActivityLog LGPD Art. 18`

### Wave G — Listagem turbinada (~7h)

Paralelizável com C se Felipe paralelizar. Entregáveis:

- [ ] `resources/js/Components/clientes/Avatar.tsx` — avatar colorido-hash HSL determinístico via `avatarFor(id)` (Wave C Lib/avatar.ts)
- [ ] `resources/js/Components/clientes/Pills.tsx` — StatusPill, TipoPill, TagChip (9 cores semânticas), FrescorPill (4 estados: fresco verde 0-30d, recente azul 31-90d, distante âmbar 91-180d, frio cinza 180d+)
- [ ] Substituir 4 pílulas radio (Todos/Ativos/Atrasados/Sem OS) por **6 FilterDropdown**: Tipo (PF/PJ) · Status (ativo/inativo/bloqueado) · UF (27) · Tags (multi) · Sem compra há (15d/30d/90d/180d/365d) · Com saldo (sim/não/devedor)
- [ ] ActiveChip horizontal scrollable — remove filtro individual; sync URL via `router.get('/cliente', { ...filters })` debounced
- [ ] Colunas tabela finais: [Avatar HSL · Nome+sub-nome (fantasia/cidade/UF) · TipoPill · Documento mascarado · Cidade/UF · FrescorPill+ÚltimaOS · Saldo colorido (`text-red-700 tabular-nums font-semibold` se devedor) · OS · Tags chips + Star pessoal localStorage · Ações ⋯]
- [ ] **MANTER** colunas operacionais (OS / Abertas / ValorAberto / UltimaOS) como secundárias toggleable — Larissa biz=4 ROTA LIVRE depende
- [ ] Header: contador inline `{total} cadastrados · {ativos} ativos · {com_saldo} com saldo` (encurta subtítulo verbose atual)
- [ ] Empty-state IA card "Não encontrei. Quer que eu pesquise no Brasil.io?" (sem resultado busca)
- [ ] Export CSV/XLSX/PDF
- [ ] Star pessoal localStorage (`favorito_users` JSON column adicionada Wave B)
- [ ] Atalho `1-8` troca tab quando drawer aberto (depende Wave B tabs existirem)
- [ ] Pest `ClienteIndexDrawer760CharterTest` — 11 GUARDs Charter v3 + Non-Goal violations + cross-tenant + viewport 1280×1024 sem scroll horizontal
- [ ] Commit conventional `feat(crm): listagem turbinada avatar HSL FrescorPill 6 filtros tag chips Star Export`

### Wave Z — Smoke Brave prod biz=1 + brief-update + handoff (~1.5h)

Critério aceitação. Entregáveis:

- [ ] Deploy SSH Hostinger `composer install --no-dev=false && composer dump-autoload --no-scripts && php artisan migrate --force`
- [ ] `npm run build:inertia` local + commit `public/build-inertia/` (NÃO `npm run build` — config errado)
- [ ] Feature flag `MWART_CLIENTE_INDEX=true` ativada biz=1 primeiro (canary)
- [ ] Brave smoke prod biz=1 — `https://oimpresso.com/cliente` carrega → clica linha → drawer abre → troca **cada uma das 8 tabs** (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria) → screenshot salvo em `prototipo-ui/SYNC_LOG.md` (R1 PROTOCOLO)
- [ ] `curl -sv` canônicos (skill `smoke-prod-evidence`) — ver §10
- [ ] `php artisan jana:health-check` verde (5 checks SQL: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift)
- [ ] Pós 48h verde biz=1 → ativar biz=4 Larissa ROTA LIVRE
- [ ] Skill `brief-update` — atualiza `memory/requisitos/Crm/BRIEFING.md` com capacidade nova drawer 760 + score Capterra recalculado
- [ ] `cliente-drawer-760-visual-comparison.md` muda `status: draft` → `status: approved` + `approved_by: wagner` + notas REAIS pós-merge (substituir estimativas calibradas por medições)
- [ ] ADR 0179 muda `status: proposed` → `status: accepted` (Wagner aprova manualmente)
- [ ] `Pages/Cliente/Show.charter.md` v2 `status: superseded` + `superseded_by: [Pages/Cliente/Index.charter.md v3]`
- [ ] HANDOFF append `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` — entrada final "Wave A-Z concluída 2026-05-XX, drawer 760 em prod biz=1+biz=4"
- [ ] Commit conventional `chore(crm): smoke prod drawer 760 biz=1 + brief-update + handoff Wave Z` refs `Refs: SPRINT-N PASSO Z`

## 5. Estrutura de arquivos

### Frontend (Inertia/React)

```
resources/js/Pages/Cliente/
├── Index.tsx                              ← expandido drawer 480→760 (Wave B+G)
├── Index.charter.md                       ← v3 live (Wave A)
├── Show.charter.md                        ← v2 SUPERSEDED (Wave Z)
├── _drawer/                               ← NOVO (Wave B skeleton + Wave C/D/E/F forms)
│   ├── IdentificacaoTab.tsx               (Wave C)
│   ├── ContatoTab.tsx                     (Wave C)
│   ├── EnderecoTab.tsx                    (Wave C)
│   ├── ComercialTab.tsx                   (Wave C)
│   ├── ClassificacaoTab.tsx               (Wave C)
│   ├── OssTab.tsx                         (Wave D wrapper)
│   ├── IATab.tsx                          (Wave E)
│   └── AuditoriaTab.tsx                   (Wave F)
└── _show/                                 ← Wave Final 2026-05-21 (reusados Wave D)
    ├── LedgerTab.tsx
    ├── SalesTab.tsx
    ├── PaymentsTab.tsx
    ├── DocumentsTab.tsx
    ├── ActivitiesTab.tsx
    ├── PessoasContatoTab.tsx
    ├── SubscriptionsTab.tsx
    └── RewardPointsTab.tsx

resources/js/Components/clientes/          ← NOVO (Wave G)
├── Avatar.tsx                             (HSL hash determinístico)
└── Pills.tsx                              (StatusPill/TipoPill/TagChip/FrescorPill)

resources/js/Lib/                          ← NOVO (Wave C+G)
├── br-mask.ts                             (CPF/CNPJ/tel/CEP)
├── br-validate.ts                         (mod 11 + regex)
├── avatar.ts                              (avatarFor(id) HSL)
└── relDate.ts                             (há Xd/Xh/Xm)
```

### Backend (Laravel/Modules)

```
app/Http/Controllers/
├── ContactController.php                  ← edita: show redirect 302, 5 endpoints PATCH autosave (Wave B+C)
└── Install/ModulesController.php          ← intacto

Modules/Crm/
├── Http/Controllers/
│   ├── ClienteLookupController.php        ← NOVO (Wave C — CEP/CNPJ proxy)
│   ├── ClienteIaController.php            ← NOVO (Wave E — 3 LLM + 1 risco determinístico)
│   ├── ClienteAuditoriaController.php     ← NOVO (Wave F — timeline + export LGPD)
│   └── ClienteCadastroController.php      ← opcional (Wave C — se preferir não inflar ContactController)
└── Services/
    └── BrLookupService.php                ← NOVO (Wave C — proxy ViaCEP/BrasilAPI cache Redis)

Modules/Auditoria/Services/
└── AuditEntryService.php                  ← REUSA (Wave F forSubject(Contact))

Modules/Jana/Services/
├── Ai/LaravelAiSdkDriver.php              ← REUSA (Wave E)
└── CustosService.php                      ← REUSA (Wave E telemetria)

app/Models/
└── Contact.php                            ← adiciona trait LogsActivity (Wave F)

database/migrations/
├── YYYY_MM_DD_HHMMSS_add_drawer_columns_to_contacts_table.php  ← NOVO (Wave B)
└── YYYY_MM_DD_HHMMSS_create_anotacoes_table.php                ← NOVO (Wave B)

routes/web.php                             ← edita: redirect /cliente/{id} → /cliente?contact_id (Wave B)
```

### Tests (Pest)

```
tests/Feature/Cliente/
├── ClienteIndexDrawer760CharterTest.php          (Wave G — 11 GUARDs charter v3)
├── ClienteDrawerCadastroAutosaveTest.php         (Wave C — 5 endpoints PATCH)
├── ClienteLookupCnpjCepTest.php                  (Wave C — BrLookupService cache)
├── ClienteIaQuotaTest.php                        (Wave E — 3 LLM + risco)
├── ClienteAuditoriaSpatieTest.php                (Wave F — timeline LGPD Art. 18)
└── ClienteShowSupersededTest.php                 (Wave B — redirect 302 + legacy intacto)
```

## 6. Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)

`business_id` global scope obrigatório em TODA query nova:

- **5 endpoints PATCH autosave** (Wave C) — `Contact::where('id', $id)->firstOrFail()` (global scope filtra biz automaticamente; `where('business_id', auth()->user()->business_id)` explícito redundante mas defensivo)
- **2 endpoints lookup** (Wave C) — cache Redis namespace por `business_id` (`crm:lookup:cep:{biz}:{cep}`) pra evitar cross-tenant cache poisoning
- **3 endpoints IA** (Wave E) — `ClienteIaController` valida `$contact->business_id === auth()->user()->business_id` antes de chamar Jana
- **1 endpoint risco determinístico** (Wave E) — idem acima
- **2 endpoints Auditoria** (Wave F) — `AuditEntryService::forSubject($contact)` já filtra biz internamente
- **6 dropdowns filtro listagem** (Wave G) — query principal em `ContactController::index` já tem global scope

`withoutGlobalScopes()` **PROIBIDO sem comentário** `// SUPERADMIN: <razão>`. Pest cross-tenant test obrigatório em **toda** Wave (cria 2 contacts em biz=1 e biz=2, autentica como biz=1, tenta acessar contact biz=2 → 404 esperado).

Skill `multi-tenant-patterns` Tier A always-on enforça via hook.

## 7. PII + LGPD

- **`tax_number_masked`** server-side em todo defer payload (`****.***.000-XX` PJ; `***.***.000-XX` PF) — Larissa biz=4 ROTA LIVRE tem clientes finais com CPF/CNPJ visíveis nos contracts; vazamento chain Larissa→cliente final do cliente é P0.
- **Telefone mascarado** server-side em payloads compartilhados (`(00) 0 ****-1234`).
- **Spatie ActivityLog v4.8** já instalado (`composer.json:47 spatie/laravel-activitylog ^4.8`) — Wave F reusa.
- **LGPD Art. 18 direito de acesso** atendido via tab Auditoria — botão "Exportar log" CSV/PDF (`GET /cliente/{id}/auditoria/export.csv` e `.pdf`). Banner topo da tab "Você pode exportar todos os seus dados (Art. 18 LGPD)" brand promise.
- **NÃO emitir activity log "view"** — Spatie logga só em mutate (`created`/`updated`/`deleted`/`status_changed`/`field_changed`/`note_added`/`os_created`). View tracking viola LGPD princípio minimização.
- **Endpoints IA Wave E** — `ClienteIaController` chama `LaravelAiSdkDriver` server-side; NÃO usar `window.claude.complete` client-side (Tier 0 segurança — leak prompt + custo cliente).
- **Score risco determinístico** (Wave E) — calcula em PHP via `RiscoController` (NÃO chama LLM, NÃO conta quota `cliente_ia`).

## 8. Performance budgets

| Métrica | p95 target | Cache/strategy |
|---|---:|---|
| First-paint Index `/cliente` (50 customers) | < 1200ms | `Inertia::defer` em customers + kpis (skill `inertia-defer-default`) |
| Drawer abrir (in-memory, sem fetch) | < 100ms | `setOpenContactId` instantâneo |
| Tab change (router.reload only:[tab]) | < 500ms | `<Deferred>` lazy partial reload |
| Autosave round-trip (PATCH 5 tabs) | < 400ms | optimistic UI + rollback 4xx/5xx + debounce 800ms |
| ViaCEP/BrasilAPI proxy hit cache Redis | < 200ms | TTL 90d CEP / 30d CNPJ |
| IA card resposta LLM (Brain B Sonnet/Haiku) | < 4000ms | spinner + erro graceful 8s timeout |

## 9. Rollback strategy

Procedimento 4 passos se bug crítico aparecer pós-merge:

1. **Feature flag desliga** — `php artisan tinker --execute="config(['mwart.cliente_index.enabled' => false]); cache:clear"` (dual-render volta pra `/contacts/{id}` Blade legacy intacto). **<5min**.
2. **Rota legacy `/contacts/{id}`** continua respondendo Blade — Wagner+Larissa+Eliana operam normal via URL legada. Sem regressão funcional.
3. **Migration aditiva reversível** — `php artisan migrate:rollback --step=2` dropa 16 colunas `contacts` + tabela `anotacoes`. Dados perdidos = só os preenchidos pós-Wave C (Wagner avalia se vale rollback completo ou só feature flag).
4. **Charter rollback** — `Pages/Cliente/Show.charter.md` v2 volta `status: live`; `Index.charter.md` v3 → `status: draft`; ADR 0179 → `status: rejected` + ADR nova `0180-cliente-drawer-rollback-pos-mortem.md` documentando aprendizados.

Wagner toma decisão entre passos 1+2 (rápido + seguro) ou 1+2+3+4 (completo). Default conservador: parar em 1+2 e investigar 48h antes de rollback completo.

## 10. Tests smoke pós-merge

Skill `smoke-prod-evidence` Tier B — `curl -sv` canônicos (NÃO declarar "funcionando" sem evidência):

```bash
# 1. Index drawer 760 renderizando biz=1
curl -sv -b "laravel_session=<cookie-wagner-biz1>" https://oimpresso.com/cliente \
  | grep -E "HTTP/|x-inertia-version|w-\[760px\]" \
  | head -20

# 2. Deeplink /cliente/{id} redirect 302
curl -sv -b "laravel_session=<cookie-wagner-biz1>" https://oimpresso.com/cliente/123 \
  | grep -E "HTTP/|Location:"
# Esperado: HTTP/2 302 + Location: /cliente?contact_id=123&tab=identificacao

# 3. Legacy /contacts/{id} Blade dual-render intacto
curl -sv -b "laravel_session=<cookie-wagner-biz1>" https://oimpresso.com/contacts/123 \
  | grep -E "HTTP/|<title>"
# Esperado: HTTP/2 200 + <title>Cliente — </title> (Blade renderizado)

# 4. Endpoint lookup CEP cache hit Redis
curl -sv -b "laravel_session=<cookie>" https://oimpresso.com/api/cliente/lookup/cep/01310100 \
  | grep -E "HTTP/|x-cache-status"
# Esperado: HTTP/2 200 + x-cache-status: HIT (após segunda chamada)

# 5. Endpoint autosave PATCH identificacao
curl -sv -X PATCH -b "laravel_session=<cookie>" \
  -H "X-Inertia: true" -H "Content-Type: application/json" \
  -d '{"name":"Teste Drawer","tipo":"PJ"}' \
  https://oimpresso.com/cliente/123/identificacao \
  | grep -E "HTTP/|X-Inertia"
# Esperado: HTTP/2 200 + X-Inertia: true

# 6. Endpoint IA resumo (Wave E)
curl -sv -X POST -b "laravel_session=<cookie>" \
  -H "X-Inertia: true" \
  https://oimpresso.com/cliente/123/ia/resumo \
  | grep -E "HTTP/|x-jana-cost-cents"
# Esperado: HTTP/2 200 + x-jana-cost-cents: <valor> (telemetria CustosService)

# 7. Endpoint Auditoria export CSV LGPD
curl -sv -b "laravel_session=<cookie>" https://oimpresso.com/cliente/123/auditoria/export.csv \
  | grep -E "HTTP/|Content-Disposition"
# Esperado: HTTP/2 200 + Content-Disposition: attachment; filename="auditoria-cliente-123.csv"
```

**Comandos artisan** (rodar local após deploy):

```bash
php artisan migrate --force                        # Wave B aplica migration
php artisan jana:health-check                       # Wave Z aceitação (5 checks SQL verdes)
php artisan route:list --path=cliente                # valida 5 PATCH + 2 lookup + 4 IA + 2 audit
php artisan route:list --path=api/cliente/lookup    # valida 2 lookup
php artisan module:enable Crm                       # garante módulo ativo
composer dump-autoload --no-scripts                 # se mexeu psr-4
npm run build:inertia                               # NÃO npm run build
```

**Brave smoke MCP** — `mcp__Claude_in_Chrome__navigate` + `mcp__Claude_in_Chrome__find` cada uma das 8 tabs + `mcp__Claude_in_Chrome__get_page_text` screenshot salvo em `prototipo-ui/SYNC_LOG.md`.

## 11. Refs

- [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) — paradigma drawer 760 substitui Show.tsx full-page (162 linhas)
- [Charter Index v3](../../../resources/js/Pages/Cliente/Index.charter.md) — drawer_pattern 760px-lateral + 8 tabs
- [visual-comparison drawer 760](cliente-drawer-760-visual-comparison.md) — 15 dimensões + gate F1.5 (310 linhas)
- [Dossiê wagner-understand](../../sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md) — decodificação completa opção A
- [HANDOFF_CLIENTES.md](../../../prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md) — spec protótipo KB-9.75 9,4/10 (381 linhas — schema BR + 4 endpoints IA + checklist)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight Wave C-F (6 meta-anti-padrões + 15 técnicos)
- [PROTOCOL.md Cowork loop](../../../prototipo-ui/PROTOCOL.md) — loop formalizado ADR 0114
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART 5 fases
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x IA-pair + margem 2x
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5 visual (Wagner aprova SCREENSHOT)
- [ADR 0110](../../decisions/0110-cockpit-pattern-v2.md) — Cockpit Pattern V2
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — prototipo-ui Cowork loop
- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md) — pattern reuse blueprint
- [ADR 0177](../../decisions/0177-mwart-excecao-cliente-show-wave-paralela.md) — exceção MWART Wave paralela Show (paradigma supersedido por 0179)
- [Sessão coord Wave Final 5waves](../../sessions/2026-05-21-coord-cliente-show-paridade-5waves.md) — contexto PRs #1298-1307 (8 sub-componentes `_show/*` reusados Wave D)
- [SPEC Crm](SPEC.md) — US-CRM-068 drawer 760 + US-CRM-069 listagem turbinada + US-CRM-070/071/072 (Wagner criar)
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md) — template cockpit-runbook origem
- Skill `multi-tenant-patterns` Tier A · `mwart-process` Tier A · `mwart-comparative V4` Tier A · `commit-discipline` Tier A · `smoke-prod-evidence` Tier B · `inertia-defer-default` Tier B · `brief-update` Tier B

---

_RUNBOOK criado 2026-05-21 (Wave A) — formato cockpit-runbook 11 seções canônicas. Estimate total ~70h elapsed (~35h IA-pair × margem 2x ADR 0106). Critério aceitação Wave Z: screenshot drawer 8 tabs em prod biz=1 + Pest 6 arquivos verdes + `php artisan jana:health-check` verde + brief-update rodado + handoff append. Próxima atualização: pós-merge Wave Z, substituir estimativas por medições reais + marcar ADR 0179 `accepted`._
