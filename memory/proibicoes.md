# Proibições (Tier 0 — sem ADR mãe nova é proibido)

## Ambiente

- ⛔ **Nunca instalar `laravel/mcp` ou `laravel/octane` no Hostinger** (nem em worktree, nem em `/tmp`). Esses pacotes só vivem em CT 100 Proxmox e local. Hostinger é shared hosting; daemons lá violam contrato ([ADR 0062](decisions/0062-separacao-runtime-hostinger-ct100.md))
- ⛔ **Nunca expor rota `Mcp::web()` (laravel/mcp) sem condicional `if (config('mcp.tools_exposed'))`.** MCP server tools são exposed APENAS no CT 100 Proxmox (`mcp.oimpresso.com`); Hostinger NÃO suporta MCP (lento + crasheia — Wagner regra 2026-05-07). Schema + service backend (cron `brief:generate` etc) podem ficar em Hostinger, mas tool MCP exposed nunca. Default `MCP_TOOLS_EXPOSED=false`. CT 100 .env tem `MCP_TOOLS_EXPOSED=true`
- ⛔ **Nunca rodar Pest da suite Jana/MCP no Hostinger** — usar CT 100 (via Tailscale) ou local
- ⛔ **Nunca rodar `composer update` (sem `--lock`) em servidor de produção** sem PR aprovado
- ⛔ **Nunca alterar branch ativa em produção pra "testar"** (Hostinger ou CT 100) — usar worktree e limpar depois
- ⛔ **Nunca editar arquivo direto via SSH** sem commit no git — drift mata governança
- ⛔ **DDL direto em prod** (`ALTER TABLE`, `CREATE/REPLACE PROCEDURE` via SQL prompt ou phpMyAdmin) sem migration — o check `procedure_drift` em `jana:health-check` detecta e alerta; o `ProcedureDriftSnapshotTest` quebra em CI (US-COPI-092, ADR 0094 §5 SoC brutal)
- ⛔ **Nunca rodar daemons no Hostinger** (Reverb, Centrifugo, Horizon, autossh, Meilisearch). Pra daemons → CT 100

## Código

- ⛔ **Não modificar tabelas core UltimatePOS** (`users`, `business`, `employees`) sem bridge table
- ⛔ **Não fazer UPDATE/DELETE em `ponto_marcacoes`** — append-only por força de lei (Portaria 671/2021). Use `Marcacao::anular()`
- ⛔ **Não remover triggers MySQL de imutabilidade** sem abrir ADR justificando
- ⛔ **Não criar nova tecnologia/dependência** sem registrar ADR
- ⛔ **Não responder em inglês** — Wagner+Eliana são brasileiros, preferem PT-BR
- ⛔ **Não assumir completude** — Wagner valoriza economia de crédito; confirme escopo com perguntas curtas antes de implementar massivamente
- ⛔ **Não remover shim `App\View\Helpers\Form`** sem antes migrar ~6.4k chamadas Blade `Form::`
- ⛔ **Identificadores MySQL >64 chars** — sempre passar nome explícito em índices compostos
- ⛔ **Não suba código sem alertar pré-requisitos e riscos**. Histórico de crashes:
  - 2026-04-18: scaffold incompatível
  - 2026-04-19: PHP 8 em servidor PHP 7.1
  - 2026-04-21: módulo desativado após upgrade 6.7
- ⛔ **Não criar `Modules/X/Tests/` sem registrar em `phpunit.xml`** — testes ficam no repo mas CI nunca roda → falsa cobertura

## Memória/governança

- ⛔ **ZERO auto-mem privada** ([ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)). Hook `block-automem.ps1` BLOQUEIA `Write/Edit` em `~/.claude/projects/*/memory/*.md`
- ⛔ **Não duplicar info entre sistemas.** Git é canônico; MCP é cache governado
- ⛔ **ADRs CANON são append-only.** NUNCA editar accepted records — criar nova com `supersedes: [N]`
- ⛔ **Tasks NÃO em markdown.** Estado vivo via tools MCP (`cycles-active`, `tasks-list`) — CURRENT.md/TASKS.md REMOVIDOS ([ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md))

## Processo MWART canônico — único caminho ([ADR 0104](decisions/0104-processo-mwart-canonico-unico-caminho.md))

- ⛔ **Caminho alternativo de MWART** — Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` existir. Hook `block-mwart-violation.ps1` bloqueia em runtime + CI workflow `mwart-gate.yml` bloqueia no merge. Override: comentar `/mwart-override <razão>` em PR (vira ADR per-tela `lifecycle: historical`)
- ⛔ **F2 BACKEND BASELINE sem Pest 5+ fixtures** do `store()` antes de mexer — gera regressão silenciosa
- ⛔ **F4 QA sem smoke biz=1** ([ADR 0101](decisions/0101-tests-business-id-1-nunca-cliente.md)) — usar biz=4 (cliente) em smoke = grave
- ⛔ **F5 CUTOVER sem aviso prévio cliente + canary 7d** — ROTA LIVRE 99% volume, surprise = perda

## Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md))

- ⛔ **`business_id` global scope obrigatório** em toda Eloquent Model que toca dados de negócio
- ⛔ **Não usar `withoutGlobalScopes`** sem comentário `// SUPERADMIN: <razão>`
- ⛔ **Job assíncrono SEMPRE passa `$businessId`** no constructor — `session()` não funciona em fila
- ⛔ **Tabela de negócio nova obrigatoriamente** tem `business_id` indexado + FK
- ⛔ **PII reais (CPF/CNPJ cliente) NUNCA em PR/commit/log** — use `[REDACTED]` ou `PiiRedactor`

## Sempre fazer

- ✅ **PT-BR em tudo** — texto, commit, comentário, label. Código em inglês ok; domínio negócio em PT (`Marcacao`, `Intercorrencia`, `BancoHoras`)
- ✅ **Cite a lei quando aplicável** — *Art. 66 CLT*, *Portaria 671/2021 Anexo I*, *LGPD Art. 7º*
- ✅ **Preserve imutabilidade** de marcações e movimentos de banco de horas
- ✅ **Mantenha `business_id` scopado** em todas queries (skill `multi-tenant-patterns` Tier A)
- ✅ **Escreva tests Pest** ao menos pra regras CLT (tolerâncias, intra/interjornada, HE) e isolamento multi-tenant
- ✅ **Antes de criar/mudar módulo, abra `Modules/Jana/`/`Repair/`/`Project/`** e imite. Se quiser divergir — registre ADR
- ✅ **Use stack de middlewares UltimatePOS** pra rotas web: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`
