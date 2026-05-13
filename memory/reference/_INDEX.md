# memory/reference/ — Índice (canon git)

> Knowledge canônico do time oimpresso, migrado do auto-mem privado per [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) + [ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md). Time (Wagner/Felipe/Maiara/Eliana/Luiz) vê tudo aqui via MCP server canônico.

> **Origem da migração:** 2026-05-13 (G1 P0 da auditoria knowledge-architecture). 51 auto-mem privadas → git canônico. Detalhes em [`memory/requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md`](../requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md).

## Cliente & domínio

- [cliente-rotalivre.md](cliente-rotalivre.md) — ROTA LIVRE (biz=4) Larissa loja vestuário Termas do Gravatal/SC; sensibilidades operacionais; histórico incidentes
- [clientes-ativos.md](clientes-ativos.md) — 56 businesses, só 7 com vendas; ROTA LIVRE 99% do volume
- [dominios-verticais-oimpresso.md](dominios-verticais-oimpresso.md) — Vestuario (peça)/ComVis (m²)/OficinaAuto (m³ caçamba/pneu)/Repair (device); 3 erros recorrentes a NÃO repetir
- [concorrentes-com-visual.md](concorrentes-com-visual.md) — Zênite/Mubisys/Alfa/Visua/Calcgraf/Calcme; copy verbo-de-ação; métrica m²

## Infraestrutura

- [hostinger.md](hostinger.md) — SSH credenciais + warm-up + hPanel + API DNS + análise DB via SSH+MySQL (tokens no Vaultwarden)
- [hostinger-remote-mysql.md](hostinger-remote-mysql.md) — srv1818.hstgr.io:3306 direto (autossh rejeitado); pattern oimpresso-mcp + daemon whatsapp-baileys
- [infra-proxmox-ct100.md](infra-proxmox-ct100.md) — Proxmox host (192.168.0.2) + CT 100 (192.168.0.50) + stack Docker + Traefik labels + autossh sidecar (senhas Vaultwarden)
- [infra-rede-empresa.md](infra-rede-empresa.md) — TP-Link 192.168.0.1, IP público 177.74.67.30, DHCP reservas, 16 port forwards, Issabel VoIP CentOS 7 EOL
- [vaultwarden-credenciais.md](vaultwarden-credenciais.md) — vault.oimpresso.com self-hosted, fonte canônica de TODAS senhas/tokens infra
- [local-dev-setup.md](local-dev-setup.md) — Herd 8.4 + MySQL Laragon + worktrees

## Stack & integração

- [ultimatepos-integracao.md](ultimatepos-integracao.md) — DataController hooks, multi-tenant via session, tabelas core, FK business.id é unsignedInteger, DataTables locale, payload SellPosController@store, events
- [financeiro-integracao.md](financeiro-integracao.md) — Hooks DataController + Observer Transaction + retro-vínculo transaction_payment; tela unificada US-FIN-013
- [modules-cms-landing.md](modules-cms-landing.md) — Modules/Cms = landing/blog do oimpresso.com (ausente no worktree, vive em produção)

## MCP & Jana

- [mcp-endpoints.md](mcp-endpoints.md) — `mcp.oimpresso.com` (CT 100/FrankenPHP) canônico; `oimpresso.com/api/mcp` (Hostinger) só CRUD admin

## WhatsApp / Atendimento

- [whatsapp-daemon-ct100.md](whatsapp-daemon-ct100.md) — daemon Baileys 6.7.18 + Fastify TS; endpoints; deploy; anti-QR-fest (PRs #685+#686); Multi-Device unified inbox
- [whatsapp-permissions-spatie.md](whatsapp-permissions-spatie.md) — bug histórico (whatsapp.* nunca registradas em prod) + comando `whatsapp:register-permissions` (PR #665)
- [atendimento-inbox-state-2026-05-12.md](atendimento-inbox-state-2026-05-12.md) — estado funcional pós-CYCLE-05 (o que faz/parcial/falta)
- [meta-whatsapp-tech-provider.md](meta-whatsapp-tech-provider.md) — Tech Provider Meta direto + Embedded Signup pra escalar 4000+ clientes (Agrosys deal)

## Tests & deploy

- [tests-pest-canon.md](tests-pest-canon.md) — workflow Modules Pest, YAML traps, SQLite guard, dual-mode SQLite/MySQL, Event::fake bridge listeners, setup worktree, PowerShell env vars inline
- [deploy-recovery-patterns.md](deploy-recovery-patterns.md) — composer install obrigatório, quick-sync fallback SSH, tela branca Inertia, recovery tabela órfã DDL
- [branch-protection-admin-merge.md](branch-protection-admin-merge.md) — check "ADR frontmatter" só roda se PR toca decisions/, admin merge legítimo

## Legacy & migração

- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — código fonte Delphi WR Comercial (SVN) + 50 bancos Firebird + SYSDBA/masterkey hardcoded {$IFDEF WR2} + fluxo login→registro
- [project-officeimpresso-modulo.md](project-officeimpresso-modulo.md) — módulo Laravel licença desktop (3.7 restaurado→6.7) + tela licenca_log v3 machine-centric

## Projects (estados consolidados de sessão)

- [project-agrosys-deal-2026-05-12.md](project-agrosys-deal-2026-05-12.md) — Deal R$2.65M ano 1, Wagner Tech Provider Meta, vendedor Artur (sobrinho RED FLAG)
- [project-sessao-2026-05-12-23-prs.md](project-sessao-2026-05-12-23-prs.md) — Recorde 23 PRs WhatsApp/omnichannel; CAPTERRA 78%→91%; auth state MySQL LIVE
- [project-mcp-5-prs-2026-05-13.md](project-mcp-5-prs-2026-05-13.md) — 5 PRs MCP fix 4 bugs sync + 2 auditorias estado-da-arte (ADR 0144)
- [project-nfebrasil-2026-05-07.md](project-nfebrasil-2026-05-07.md) — US-NFE-002 fechada server-side, biz=1 pronta smoke SEFAZ-SC
- [project-octane-mcp-prod-deps.md](project-octane-mcp-prod-deps.md) — laravel/octane + laravel/mcp em prod-deps; ADR estrutural pendente
- [project-form-shim-migration.md](project-form-shim-migration.md) — Form:: shim laravelcollective→spatie/laravel-html

## Revenue & comercial

- [revenue-thesis-modulos.md](revenue-thesis-modulos.md) — Pricing tiers + take rate; Financeiro/NfeBrasil/RecurringBilling/LaravelAI/Copiloto

## Feedback (regras de trabalho)

- [feedback-agent-write-verification.md](feedback-agent-write-verification.md) — Agent pode alucinar Write; verificar com `ls -la` + `wc -l`
- [feedback-auto-merge-quando-verde.md](feedback-auto-merge-quando-verde.md) — `gh pr merge --auto` quando CI verde + Wagner aprovou
- [feedback-browser-mcp-smoke.md](feedback-browser-mcp-smoke.md) — Após cada feature UI, smoke via Chrome MCP em prod biz=1
- [feedback-check-main-antes-pr.md](feedback-check-main-antes-pr.md) — `git log origin/main..HEAD` antes de abrir PR
- [feedback-comissao-recurring-vendedor.md](feedback-comissao-recurring-vendedor.md) — Comissão MRR perpétua = suicídio financeiro (caso Artur Agrosys)
- [feedback-daemon-deploy-order.md](feedback-daemon-deploy-order.md) — Deploy daemon ANTES (ou junto) quick-sync Hostinger quando payload muda
- [feedback-daemon-max-deploys-day.md](feedback-daemon-max-deploys-day.md) — Máximo ~3 deploys daemon Baileys/dia (anti-abuse Multi-Device)
- [feedback-daemon-qrfest.md](feedback-daemon-qrfest.md) — Daemon rebuild = QR-fest; PR #685 mitiga após primeiro pair
- [feedback-eloquent-array-cast-inertia.md](feedback-eloquent-array-cast-inertia.md) — `(array) $eloquent` quebra Inertia; usar `->toArray()`
- [feedback-legacy-migration-importer.md](feedback-legacy-migration-importer.md) — Migração Delphi → oimpresso via Python importer canônico
- [feedback-migrate-pos-deploy.md](feedback-migrate-pos-deploy.md) — `php artisan migrate` manual obrigatório pós-quick-sync Hostinger
- [feedback-module-audit-approach.md](feedback-module-audit-approach.md) — Skill `module-completeness-audit` Tier B (8 dims governança)
- [feedback-nunca-publicar-credenciais.md](feedback-nunca-publicar-credenciais.md) — NUNCA ecoar valor literal credencial no chat
- [feedback-outbound-markdown-over-mcp.md](feedback-outbound-markdown-over-mcp.md) — Outbound/sales tracking via markdown, não MCP tasks granulares
- [feedback-pesquisar-versao-lib.md](feedback-pesquisar-versao-lib.md) — Antes reverter lib externa, pesquisar GitHub issues + versões intermediárias
- [feedback-revert-isolar-client.md](feedback-revert-isolar-client.md) — Antes reverter PR em prod, isolar client-side primeiro
- [feedback-tenancy-pest-local.md](feedback-tenancy-pest-local.md) — Mudanças tenancy exigem Pest verde local antes de PR
- [feedback-test-biz-99-cross-tenant.md](feedback-test-biz-99-cross-tenant.md) — biz=1 default; biz=99 cross-tenant (não biz=4 cliente real)

## Workflow & triggers

- [trigger-guarde-no-cofre.md](trigger-guarde-no-cofre.md) — Comando reservado Wagner: classifica decisão/regra/evidência e grava em ADR/SPEC/docs_evidences
- [cursor-collaboration.md](cursor-collaboration.md) — Cursor é outra IA paralela; checa `memory/sessions/` + `git status` antes de começar

## Ideias

- [ideia-chat-ia-contextual.md](ideia-chat-ia-contextual.md) — Chat IA flutuante que sabe a tela/dados/user atual; após Fase 1-3 redesign Ponto

---

## Convenção lifecycle

Documentos em `memory/reference/` são canônicos (visíveis ao time via MCP). Updates: PR com diff explícito. Não há versão "historical" — append novo arquivo se conhecimento estiver obsoleto e linkar com `supersedes`.

Para conhecimento volátil/máquina-local pessoal Wagner: `~/.claude/oimpresso-local/` (gitignored per ADR 0131).
Para segredos: Vaultwarden (`vault.oimpresso.com`) per ADR 0131.
