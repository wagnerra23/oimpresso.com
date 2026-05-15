# Sugestão PR-split — sessão 2026-05-14 maratona Martinho canary

> **Contexto:** branch `claude/doc-armadilha-tz-multitenant` acumulou ~71 arquivos não-commitados durante maratona ~12h. Esta doc propõe split em 5 PRs lógicos pra revisão viável.
>
> **Origem:** Wagner pediu organização pré-consolidação git (2026-05-14 noite).
>
> **NÃO executado** — Wagner consolida manual segunda (commit-discipline + ADR 0040 publication-policy).

## Estado git no momento (2026-05-14 ~21h)

- Branch: `claude/doc-armadilha-tz-multitenant`
- 15 modificados + 56 untracked = ~71 arquivos
- 0 staged
- 2 agents BG ainda rodando (A fornecedores · B MWART /sells/edit) — pode subir +10-15 arquivos pré-consolidação

## Proposta 5 PRs (lógica + revisão viável)

### PR #1 — Hotfix canary Martinho biz=164 + dual-mode controllers

**Tema:** liberar biz=164 (canary 19/maio) usar Inertia/React em telas-chave · preservar Blade legacy 100% pra demais businesses.

**Arquivos (~6):**
- `app/Http/Controllers/SellController.php` (whitelist biz=164 em `create()` linhas 805-816)
- `app/Http/Controllers/ContactController.php` (dual-mode Inertia branches em `index/create/edit/show` + método `listJson()`)
- `app/Http/Controllers/ProductController.php` (dual-mode similar)
- `app/Services/LegacyMenuAdapter.php` (sidebar filter + cache per-request)
- `database/seeders/DatabaseSeeder.php` (registrar BusinessSidebarConfigSeeder)
- `routes/web.php` (adicionar `/contacts/list-json` + `/products/list-json` antes Route::resource)

**Commit message sugerido:**
```
feat(canary): biz=164 Martinho Inertia branches + sidebar filter per-business

- SellController: whitelist canary biz=164 em /sells/create (preserva guard biz=4 ROTA LIVRE)
- ContactController + ProductController: dual-mode (Inertia via X-Inertia header · Blade fallback)
- LegacyMenuAdapter: filter sidebar por business.sidebar_hidden_groups
- routes: /contacts/list-json + /products/list-json (REST JSON paginado escopado business_id)

Refs: ADR 0093 multi-tenant Tier 0 · ADR 0104 MWART canônico §F5
TODO: remover whitelist hardcoded quando GrowthBook rule useV2SellsCreate ativada via UI
```

### PR #2 — Migrations + Seeder + Tests (Sidebar + sync_checkpoint)

**Tema:** schema additions pra sidebar customizada + daemon dual-sync.

**Arquivos (~6):**
- `database/migrations/2026_05_14_120000_add_sidebar_hidden_groups_to_business.php`
- `database/migrations/2026_05_14_180000_create_sync_checkpoint.php`
- `database/seeders/BusinessSidebarConfigSeeder.php` (Martinho config + futuros)
- `tests/Feature/Sidebar/SidebarPerBusinessTest.php` (26 Pest)
- `tests/Feature/Daemon/DaemonSyncDualBusinessTest.php` (6 Pest)
- `memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md`

**Commit message sugerido:**
```
feat(infra): migrations sidebar_hidden_groups + sync_checkpoint + seeders Martinho

- business.sidebar_hidden_groups JSON nullable (filter Sidebar per biz)
- sync_checkpoint table (daemon dual-sync rastreabilidade per type)
- BusinessSidebarConfigSeeder seed Martinho config (estoque visível Lara)
- Pest cross-tenant biz=1 vs biz=99 verde

Refs: ADR 0093 · ADR proposal dual-sync
Migrate prod: php artisan migrate · php artisan db:seed --class=BusinessSidebarConfigSeeder
```

### PR #3 — MWART Pages /contacts + /products (Inertia React + Pest)

**Tema:** 2 telas Blade legacy migradas pra Inertia React canary biz=164.

**Arquivos (~30):**
- `resources/js/Pages/Crm/Contacts/{Index,Create,Edit,Show}.tsx`
- `resources/js/Pages/Crm/Contacts/{Index,Create}.charter.md`
- `resources/js/Pages/Crm/Contacts/_components/*.tsx`
- `resources/js/Pages/Products/{Index,Create,Edit,Show,StockHistory}.tsx`
- `resources/js/Pages/Products/{Index,Create}.charter.md`
- `resources/js/Pages/Products/_components/*.tsx`
- `tests/Feature/Crm/ContactsInertiaTest.php` (21 Pest)
- `tests/Feature/Products/ProductsInertiaTest.php` (29 Pest)
- `memory/requisitos/Crm/RUNBOOK-contacts.md`
- `memory/requisitos/Products/RUNBOOK-products.md`

**Commit message sugerido:**
```
feat(mwart): /contacts + /products Blade -> Inertia React (dual-mode F3)

- Crm/Contacts: Index/Create/Edit/Show Pages + listJson endpoint REST
- Products: Index/Create/Edit/Show/StockHistory + listJson + KPIs estoque
- Cockpit V2 pattern (pills rounded-full · search debounce 300ms · atalhos Cmd/S Esc)
- Charters + RUNBOOKs MWART 11 seções
- Pest cross-tenant biz=1 vs biz=99 verde (50 casos totais)
- Dual-mode: header X-Inertia carrega React · sem header Blade legacy intacto

Refs: ADR 0104 MWART canônico · ADR 0107 visual comparison gate
Próximo: F4 QA Wagner smoke biz=1 · F5 cutover 30d
```

### PR #4 — Daemon dual-sync Fase 1 MVP + importers fortalecidos

**Tema:** infraestrutura sync near-realtime Delphi master → oimpresso viewer (insight Kamila WhatsApp 14/maio).

**Arquivos (~17):**
- `scripts/legacy-migration/daemon-sync-martinho.py` (NOVO · wrapper loop infinito + SSH tunnel persistent + heartbeat)
- `scripts/legacy-migration/lib/sync_checkpoint.py` (NOVO · helpers checkpoint table)
- `scripts/legacy-migration/lib/firebird_reader.py` (modificado · `has_column` + `read_chunk_with_retry`)
- `scripts/legacy-migration/migrar-martinho.py` (NOVO · wrapper SSH tunnel + 3 importers Martinho)
- `scripts/legacy-migration/import-{contacts-from-venda,financeiro,vendas,produtos,estoque,compras}.py` (modificados · `--delta-since-last-sync` + JSON_MERGE_PATCH + cinto-suspensório)
- `scripts/legacy-migration/import-{produtos,estoque,compras}.py` (NOVOS pelo agent F2 3-importers)
- `scripts/legacy-migration/output/RUNBOOK-importers-produtos-estoque-compras.md`
- `memory/requisitos/Crm/RUNBOOK-daemon-sync-officeimpresso.md`

**Commit message sugerido:**
```
feat(daemon): dual-sync Fase 1 MVP + 6 importers fortalecidos cinto-suspensório

- daemon-sync-martinho.py loop infinito + SSH tunnel persistent + heartbeat 60s + reconnect
- sync_checkpoint helpers (per business · per sync_type)
- firebird_reader: read_chunk_with_retry exponencial backoff [5s/15s/45s]
- 6 importers: --delta-since-last-sync + JSON_MERGE_PATCH metadata (preserva user_* namespace) + cinto-suspensório cross-business
- 3 importers NOVOS: import-produtos.py + import-estoque.py + import-compras.py (4.378 + 4.581 + 15.617 rows)
- RUNBOOK setup PC Wagner

Lições incidente 14/maio ROTA LIVRE biz=4 incorporadas:
- L1 Firebird connection instável → chunks + retry + checkpoint per chunk
- L2 metadata user-added → JSON_MERGE_PATCH (não overwrite)
- L3 cross-business bug família → JOIN+business_id explícito + rowcount guard
- L4 SSH tunnel zombie → python -u + supervisord-style reconnect

Refs: ADR proposal dual-system-delphi-oimpresso-sync-realtime
```

### PR #5 — Memory canon + ADR proposals + Skill Tier A + session log + handoff

**Tema:** documentação e governança · zero código exec · ZERO impacto runtime.

**Arquivos (~25):**
- `memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md` (11 seções + 4 lições incidente)
- `memory/decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md` (15 seções)
- `memory/reference/clientes/{_INDEX,_TEMPLATE,rotalivre,martinho-cacambas}.md`
- `memory/reference/funcionarios/{_INDEX,_TEMPLATE}.md` + `rotalivre/larissa.md` + `martinho-cacambas/{jair,martinho,kamila,lara,dani,rodrigo,eduardo}.md`
- `memory/reference/cliente-rotalivre.md` + `memory/reference/cliente-martinho.md` (stubs redirect 90d)
- `memory/reference/feedback-importer-cross-business-bug.md`
- `memory/reference/feedback-firebird-batch-instavel.md`
- `memory/reference/feedback-nao-projetar-cansaco.md` (NOVO 14/maio noite)
- `memory/reference/dominios-verticais-oimpresso.md` (modificado · sub-vertical 3 → piloto ativo)
- `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md` (modificado · status piloto-ativo)
- `memory/sprints/s3-constituicao/03-skills-audit.md` (modificado · A.9 cliente-funcionario-collector)
- `memory/sessions/2026-05-14-martinho-canary-prep-massive.md`
- `memory/sessions/2026-05-14-pr-split-suggestion.md` (este arquivo)
- `memory/handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md`
- `memory/08-handoff.md` (modificado · linha topo)
- `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md`
- `memory/requisitos/_Skills/RUNBOOK-cliente-funcionario-collector.md`
- `.claude/skills/cliente-funcionario-collector/SKILL.md`
- `tests/Feature/Skills/ClienteFuncionarioCollectorMatcherTest.php`
- `tests/Feature/Skills/smoke-cliente-funcionario-collector-matcher.php`

**Commit message sugerido:**
```
docs(memory): canon perfis cliente+funcionário Tier A + ADR proposals + lições 14/maio

- ADR proposals (proposed segunda):
  * dual-system Delphi master + oimpresso viewer (insight Kamila WhatsApp)
  * cliente-funcionario-perfis-coleta-sistematica (skill Tier A · ADR 0144)
- memory/reference/{clientes,funcionarios}/ padronizado (4 clientes + 8 funcionários)
  * hierarquia familiar Martinho corrigida: Kamila esposa Jair · Lara filha do Martinho
- Feedbacks catalogados:
  * importer cross-business bug (pattern cinto-suspensório)
  * Firebird batch instável (chunks + retry)
  * NÃO projetar cansaço no Wagner (anti-pattern comportamental Claude)
- Skill Tier A cliente-funcionario-collector (proposed · status live após ADR accepted)
- Session log + handoff dia 14/maio (índice 08-handoff atualizado)

Refs: ADR 0061 conhecimento canônico git · ADR 0093 multi-tenant · ADR 0131 tiering memória
```

## Ordem sugerida de merge

1. PR #2 (migrations primeiro · sem essas tabelas, sidebar+daemon não funcionam)
2. PR #1 (controllers + sidebar filter — depende migration #2)
3. PR #3 (MWART Pages — depende controllers #1)
4. PR #4 (daemon — depende migration sync_checkpoint #2)
5. PR #5 (memory canon · independente · pode mergear paralelo a qualquer momento)

## Atalho de revisão pra Wagner

Cada PR tem **commit message pronto** acima. Body do PR pode citar:
- Session log: `memory/sessions/2026-05-14-martinho-canary-prep-massive.md`
- Handoff: `memory/handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md`
- CHECKLIST: `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md`

## Refs

- Session log narrativo (este dia): [2026-05-14-martinho-canary-prep-massive.md](2026-05-14-martinho-canary-prep-massive.md)
- Handoff (estado pra próxima sessão): [2026-05-14-1800-martinho-canary-prep-jair-endossou.md](../handoffs/2026-05-14-1800-martinho-canary-prep-jair-endossou.md)
- Skill canônica commit-discipline (Tier A): `.claude/skills/commit-discipline/SKILL.md`
- ADR 0040 publication-policy: Wagner sempre aprova git ops

---

**Criado:** 2026-05-14 ~21h BRT
**Status:** sugestão · Wagner consolida manual segunda 19/maio
