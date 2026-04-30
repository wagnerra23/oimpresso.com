---
slug: 0062-separacao-runtime-hostinger-ct100
number: 62
title: "Separação dura de runtime: Hostinger ≠ CT 100 Proxmox"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-04-30
module: infra
quarter: 2026-Q2
tags: [ambiente, hostinger, proxmox, octane, mcp, regras, governanca]
supersedes: []
superseded_by: []
related:
  - 0053-mcp-server-governanca-como-produto
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0059-governanca-memoria-estilo-anthropic-team
  - 0060-tudo-rede-interna-proxmox-bye-hostinger
pii: false
review_triggers:
  - hostinger_migrar_pra_dedicado
  - mcp_server_voltar_pro_hostinger
  - composer_json_split_em_dois
---

# ADR 0062 — Separação dura de runtime: Hostinger ≠ CT 100 Proxmox

## Contexto

O monorepo `oimpresso.com` serve dois runtimes distintos com o mesmo `composer.json`/`composer.lock`:

| Runtime | Onde | Stack | Path |
|---|---|---|---|
| **Hostinger** (app web) | `~/domains/oimpresso.com/public_html` em 148.135.133.115 | L13.6 + PHP 8.4 shared hosting (sem daemon) | gerenciado por git pull + `composer install` |
| **CT 100 Proxmox** (MCP server) | `/opt/oimpresso-mcp/code` em 100.99.207.66 (Tailscale) | Docker + FrankenPHP + Octane + Traefik | clone separado, deploy via docker compose |

Pacotes específicos do CT 100 (`laravel/mcp`, `laravel/octane`) ESTÃO no `composer.json` raiz porque ambos os runtimes usam o mesmo arquivo. Isso cria armadilha real: rodar `composer install` no Hostinger instalaria laravel/mcp + laravel/octane lá também, mesmo que o Hostinger nunca os use em runtime.

Em 2026-04-30 Claude tentou criar worktree no Hostinger pra rodar Pest do MEM-KB-3 e disparou `composer update laravel/mcp laravel/octane` — Wagner reagiu duro: *"na hotinger? mcp? tais maluco? não é permitido"*. Confirmou regra nunca antes formalizada por escrito no canônico.

## Decisão

**Cada runtime tem responsabilidade exclusiva. Cruzar runtime é incidente.**

### Hostinger (shared hosting do app web)

**Pode:**
- Servir oimpresso.com (Inertia/React/L13.6/UltimatePOS).
- `composer install` do app web — mas **sem** instalar `laravel/mcp` ou `laravel/octane` (fix futuro: `composer.json` split ou allowlist via custom installer; até lá disciplina humana).
- `php artisan migrate` no DB Hostinger.
- Deploy via `git pull` + composer + cache clear (ver INFRA.md §3).

**Não pode:**
- Rodar Pest da suite Copiloto/MCP.
- Instalar `laravel/mcp`, `laravel/octane`, `laravel/reverb` ou qualquer pacote de daemon.
- Criar worktree pra testar branch que envolva os 3 acima.
- Rodar daemon persistente (sem supervisord, sem Horizon supervised, sem autossh).
- Editar arquivo direto via SSH (drift mata governança — já queimou Eliana no 3.7→6.7).

### CT 100 Proxmox (MCP server + daemons)

**Pode:**
- Servir mcp.oimpresso.com (FrankenPHP + Octane).
- Rodar `laravel/mcp`, `laravel/octane`, Centrifugo, Meilisearch, Horizon workers.
- Conectar MySQL Hostinger via Remote MySQL whitelist (read-only operacional pelo MCP server).
- Pest da suite Copiloto/MCP (CT 100 tem o ambiente completo).
- Worktree de qualquer branch (ambiente dev-friendly).

**Não pode:**
- Servir oimpresso.com (não tem rota nem cert pro domínio principal).
- Escrever no DB Hostinger sem passar pelo app web (audit/RBAC obrigatório).
- Bind no IP público sem Traefik na frente.

### Local Wagner (`D:\oimpresso.com`)

**Pode tudo** — Herd PHP 8.4 + Laragon MySQL + worktrees em `.claude/worktrees/`. Único lugar onde Claude pode rodar `composer install` completo (com mcp/octane) sem incidente.

### CI GH Actions

**Pode:** Pest, lint, sync de lock (`composer-lock-sync.yml`), deploy (`deploy.yml`), ADR linter (`adr-lint.yml`).
**Não pode:** rodar daemons; deploy direto pro Hostinger sem PR aprovado.

## Justificativa

1. **Hostinger é shared hosting** — sem permissão de root, sem supervisord, sem controle do Nginx/PHP-FPM. Daemons morrem ou são killados. Octane sem worker manager + restart é inviável.
2. **MCP server precisa de auth + audit + DB próprio** (ADR 0053). Rodar no Hostinger acopla audit log ao app web e quebra LGPD (cliente externo via Claude Desktop não pode acessar app web no mesmo runtime).
3. **Drift = bug** — já existem 3 incidentes históricos (CLAUDE.md §10) de mexer direto no servidor sem git. Worktree é a fronteira que protege a branch ativa do site.
4. **Custo de erro é alto** — instalar Octane no Hostinger pode consumir cota de inodes (vendor/ tem ~2k arquivos a mais), travar `composer install` pra futuros deploys, ou ser flagged pelo provedor como abuso de recurso.

## Consequências

**Positivas:**
- Regra escrita resolve ambiguidade. Claude tem onde apontar.
- Linter futuro pode validar (ex.: hook pre-deploy que `grep` composer.lock no servidor errado).
- Onboarding de Felipe/Maíra/Luiz/Eliana fica claro.

**Negativas / Trade-offs:**
- `composer.json` continua misturado até split formal (gap conhecido).
- Disciplina humana até hoje (Hostinger nunca rodou `composer install` raw após 0062 — sempre `--no-dev` ou similar? — falta verificar).

**Riscos mitigados:**
- Wagner não vai mais ter que reagir tarde quando agente tenta worktree no Hostinger.
- ADR consultável via `decisions-search query:"runtime hostinger ct 100"`.

## Próximos passos (não-bloqueantes pra esta ADR)

1. **`composer.json` split** — extrair `laravel/mcp` + `laravel/octane` pra `composer.json` separado em `docker/oimpresso-mcp/` ou usar `replace`/`provide`. Discutir em ADR 0063.
2. **Hook pre-deploy** — script que aborta `composer install` no Hostinger se vendor/ for ter `laravel/mcp` ou `laravel/octane`. Issue em backlog.
3. **Atualizar CLAUDE.md §4** — bullets curtos apontando pra esta ADR pra detalhe.

## Referências

- ADR 0053 — MCP server governança como produto
- ADR 0058 — Reverb substituído por Centrifugo+FrankenPHP no CT 100
- ADR 0060 — Tudo rede interna Proxmox
- INFRA.md §6 — Mapa de ativos da empresa
- Incidente 2026-04-30 — Tentativa de worktree mcp/octane no Hostinger (corrigida em <30min)
