# memory/reference/ — Índice (canon git)

> Knowledge canônico do time oimpresso, migrado do auto-mem privado per [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) + [ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md). Time (Wagner/Felipe/Maiara/Eliana/Luiz) vê tudo aqui via MCP server canônico.

> **Origem da migração:** 2026-05-13 (G1 P0 da auditoria knowledge-architecture). 51 auto-mem privadas → git canônico. Detalhes em [`memory/requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md`](../requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md).

## Cliente & domínio

- [cliente-rotalivre.md](cliente-rotalivre.md) — ROTA LIVRE (biz=4) Larissa loja vestuário Termas do Gravatal/SC; sensibilidades operacionais; histórico incidentes
- [cliente-martinho-cacambas.md](cliente-martinho-cacambas.md) — MARTINHO CAÇAMBAS (biz=164) prospect com HiSoft competitor; **migração WR2 Firebird → oimpresso CONCLUÍDA 2026-05-16** (91 caçambas + 9.988 contatos + 44k vendas + 83k títulos); **aging real R$ [redacted Tier 0]k vs R$ [redacted Tier 0]M fóssil pré-2020**
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
- [gotcha-worktree-junction-vendor-rm.md](gotcha-worktree-junction-vendor-rm.md) — **Pegadinha 2026-05-26:** `git worktree remove --force` segue `mklink /J` e deleta vendor real. Prevenção: remover junção antes do worktree remove. Recovery: `composer install` ~5min

## Stack & integração

- [ultimatepos-integracao.md](ultimatepos-integracao.md) — DataController hooks, multi-tenant via session, tabelas core, FK business.id é unsignedInteger, DataTables locale, payload SellPosController@store, events
- [financeiro-integracao.md](financeiro-integracao.md) — Hooks DataController + Observer Transaction + retro-vínculo transaction_payment; tela unificada US-FIN-013
- [modules-cms-landing.md](modules-cms-landing.md) — Modules/Cms = landing/blog do oimpresso.com (ausente no worktree, vive em produção)
- [pattern-sidebar-ghost-no-op-modify-admin-menu.md](pattern-sidebar-ghost-no-op-modify-admin-menu.md) — Pattern emergente Wagner 2026-05-26: módulo X vira ghost de hub Y → DataController.modifyAdminMenu vira NO-OP, hub Y ganha ghost via attribute. 4 aplicações catalogadas (PaymentGateway/ProductCatalogue/Woocommerce/Fiscal cockpit)

## MCP & Jana

- [mcp-endpoints.md](mcp-endpoints.md) — `mcp.oimpresso.com` (CT 100/FrankenPHP) canônico; `oimpresso.com/api/mcp` (Hostinger) só CRUD admin

## LGPD & Privacidade

- [lgpd-mapa-tratamento.md](lgpd-mapa-tratamento.md) — **Registro de Operações Art. 37 LGPD** (canon). Catalogadas Op-01 a Op-07 (ERP, Clarity, WhatsApp, Jana IA, Asaas, NFe, e-mail) + 12 subprocessadores (Hostinger, Proxmox, Microsoft Clarity SCC EUA, OpenAI/Anthropic/Gemini/Cohere/Groq, GitHub, Asaas BR, Mailgun, Meta). Encarregado: Wagner. Revisão trimestral. Origem: gap G1 [ADR 0191](../decisions/0191-microsoft-clarity-session-replay-lgpd.md).

## WhatsApp / Atendimento

- [whatsapp-daemon-ct100.md](whatsapp-daemon-ct100.md) — daemon Baileys 6.7.18 + Fastify TS; endpoints; deploy; anti-QR-fest (PRs #685+#686); Multi-Device unified inbox
- [whatsapp-baileys-messages-canonical.md](whatsapp-baileys-messages-canonical.md) — POR QUÊ Baileys + protocolo retardado + inbox queue pattern + webhook security stack (HMAC+nonce+backpressure+OTel) + anti-ban patterns + 8 gotchas CT 100 deploy
- [whatsapp-permissions-spatie.md](whatsapp-permissions-spatie.md) — bug histórico (whatsapp.* nunca registradas em prod) + comando `whatsapp:register-permissions` (PR #665)
- [atendimento-inbox-state-2026-05-12.md](atendimento-inbox-state-2026-05-12.md) — estado funcional pós-CYCLE-05 (o que faz/parcial/falta)
- [meta-whatsapp-tech-provider.md](meta-whatsapp-tech-provider.md) — Tech Provider Meta direto + Embedded Signup pra escalar 4000+ clientes (Agrosys deal)
- [observability-jaeger-ct100.md](observability-jaeger-ct100.md) — Jaeger all-in-one CT 100 (deploy/OTLP/Traefik/troubleshoot/evolução Tempo) + integração daemon Baileys via network `observability`. US-WA-083 fechado 2026-05-14

## Tests & deploy

- [tests-pest-canon.md](tests-pest-canon.md) — workflow Modules Pest, YAML traps, SQLite guard, dual-mode SQLite/MySQL, Event::fake bridge listeners, setup worktree, PowerShell env vars inline
- [deploy-recovery-patterns.md](deploy-recovery-patterns.md) — composer install obrigatório, quick-sync fallback SSH, tela branca Inertia, recovery tabela órfã DDL, §2.1 checklist "tela nova não aparece pós-merge" (caso 2026-05-14 PRs #838/#839)
- [branch-protection-admin-merge.md](branch-protection-admin-merge.md) — check "ADR frontmatter" só roda se PR toca decisions/, admin merge legítimo
- [sandbox-hostnames.md](sandbox-hostnames.md) — prod=`oimpresso.com`, sem sandbox separado; hostname stale `oi.wr2.com.br` removido do vite.config.js 2026-05-20 (correção Wagner)

## Legacy & migração

- [contrato-delphi-inviolavel.md](contrato-delphi-inviolavel.md) — **TIER 0** wire IRREVOGÁVEL (Delphi não vai recompilar) — endpoints Connector+Officeimpresso+Subscription + 3 níveis enforcement bloqueio (empresa/máquina/validade) + builds prod catalogados + matriz permitido/proibido
- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — código fonte Delphi WR Comercial (SVN) + 50 bancos Firebird + SYSDBA/masterkey hardcoded {$IFDEF WR2} + fluxo login→registro
- [project-officeimpresso-modulo.md](project-officeimpresso-modulo.md) — módulo Laravel licença desktop (3.7 restaurado→6.7) + tela licenca_log v3 machine-centric
- [migracao-officeimpresso-pattern.md](migracao-officeimpresso-pattern.md) — **pattern canônico 4 fases** (Empresas→Vehicles→Vendas→Financeiro) · Python firebird-driver + pymysql · idempotência por legacy_id · audit JSON · anti-patterns Martinho 2026-05-13
- [matriz-conhecimento-clientes-legacy.md](matriz-conhecimento-clientes-legacy.md) — **matriz universo** 50 bancos Firebird × 56 businesses oimpresso × status migração · Tier A (5 perfis: WR2/Vargas/Extreme/Gold/Martinho) · Tier B (45 dormentes) · **VERSAO_BANCO** por cliente (range 1404-1474 = 70 versões drift)

## Projects (estados consolidados de sessão)

- [project-agrosys-deal-2026-05-12.md](project-agrosys-deal-2026-05-12.md) — Deal R$ [redacted Tier 0]M ano 1, Wagner Tech Provider Meta, vendedor Artur (sobrinho RED FLAG)
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
- [feedback-lookup-cnpj-sobrescreve-dados.md](feedback-lookup-cnpj-sobrescreve-dados.md) — Lookup CNPJ/CEP/integrações fonte oficial: sobrescreve dados cadastrais, contatos só se vazio
- [feedback-migrate-pos-deploy.md](feedback-migrate-pos-deploy.md) — `php artisan migrate` manual obrigatório pós-quick-sync Hostinger
- [feedback-module-audit-approach.md](feedback-module-audit-approach.md) — Skill `module-completeness-audit` Tier B (8 dims governança)
- [feedback-nunca-publicar-credenciais.md](feedback-nunca-publicar-credenciais.md) — NUNCA ecoar valor literal credencial no chat
- [feedback-outbound-markdown-over-mcp.md](feedback-outbound-markdown-over-mcp.md) — Outbound/sales tracking via markdown, não MCP tasks granulares
- [feedback-pesquisar-versao-lib.md](feedback-pesquisar-versao-lib.md) — Antes reverter lib externa, pesquisar GitHub issues + versões intermediárias
- [feedback-revert-isolar-client.md](feedback-revert-isolar-client.md) — Antes reverter PR em prod, isolar client-side primeiro
- [feedback-tenancy-pest-local.md](feedback-tenancy-pest-local.md) — Mudanças tenancy exigem Pest verde local antes de PR
- [feedback-test-biz-99-cross-tenant.md](feedback-test-biz-99-cross-tenant.md) — biz=1 default; biz=99 cross-tenant (não biz=4 cliente real)
- [feedback-habilitar-modulo-por-business.md](feedback-habilitar-modulo-por-business.md) — **Tier 0 IRREVOGÁVEL** (lado a lado com `business_id` global scope em [proibicoes.md](../proibicoes.md)). **3 CAMADAS** canônicas pra esconder/liberar feature por business+user: (1) Subscription Package via `/superadmin/packages/{id}/edit` — 24+ chaves `X_module` nWidart; (2) `business.enabled_modules` via `/business/settings` — 13 keys core (pegadinha switch business — Wagner afirma `/superadmin/business/{id}/settings` existe, verificar próxima sessão); (3) Spatie Permissions via `/roles/{id}/edit` — 260+ permissions granulares por feature. Checklist Wagner recorrente + pattern criar gate quando módulo nWidart não tem. NUNCA `if ($business_id === N) return`. Wagner regra 2026-05-18.
- [feedback-cowork-bundle-aplicar-inteiro.md](feedback-cowork-bundle-aplicar-inteiro.md) — **Tier 0 regra design system 2026-05-18 Wagner** (após 3 tentativas falhas no Financeiro PR #1085→#1091→#1092). Pacote Cowork novo de módulo: PRIMEIRA aplicação = COPIAR `styles.css` INTEIRO do bundle, sem cherry-pick. Validado historicamente em Vendas/Pedidos/Cockpit. Cherry-pick incremental erra detalhes (cor, hue, padding) e gasta 3-5 ondas iterando. Bundle copy = 1 PR base + N PRs de customização Inertia/React.

## Workflow & triggers

- [checklist-pos-merge.md](checklist-pos-merge.md) — **Checklist canônico unificado pós-merge** (8 passos consolidando ADR 0070/0130/0164 + skills tela-smoke-pos-merge/brief-update/memory-sync + workflow GHA) — tabela de gatilhos por tipo de PR + estado auditado
- [trigger-guarde-no-cofre.md](trigger-guarde-no-cofre.md) — Comando reservado Wagner: classifica decisão/regra/evidência e grava em ADR/SPEC/docs_evidences
- [cursor-collaboration.md](cursor-collaboration.md) — Cursor é outra IA paralela; checa `memory/sessions/` + `git status` antes de começar

## Ideias

- [ideia-chat-ia-contextual.md](ideia-chat-ia-contextual.md) — Chat IA flutuante que sabe a tela/dados/user atual; após Fase 1-3 redesign Ponto

---

## Convenção lifecycle

Documentos em `memory/reference/` são canônicos (visíveis ao time via MCP). Updates: PR com diff explícito. Não há versão "historical" — append novo arquivo se conhecimento estiver obsoleto e linkar com `supersedes`.

Para conhecimento volátil/máquina-local pessoal Wagner: `~/.claude/oimpresso-local/` (gitignored per ADR 0131).
Para segredos: Vaultwarden (`vault.oimpresso.com`) per ADR 0131.
