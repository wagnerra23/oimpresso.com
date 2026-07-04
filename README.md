# oimpresso.com — ERP gráfico com IA

ERP multi-tenant pra indústria gráfica brasileira, com módulos integrados de financeiro, NFe/NFSe, ponto, CRM e copiloto IA (Jana).

> 🗺️ **Novo por aqui?** Comece pelo **[Guia do Sistema](memory/GUIA-DO-SISTEMA.md)** — mapa do produto + como operar com Claude Code, numa página.

## Stack canônica

- **Laravel 13.6** + PHP 8.4 (Herd local · CT 100 Proxmox · Hostinger prod)
- **Inertia v3** + React 19 + Tailwind 4 (frontend SPA-style sobre Blade)
- **MySQL 8** multi-tenant via `business_id` ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- **nWidart Modules** — `Modules/<Nome>/` arquitetura modular
- **Pest v4** + PHPUnit v12 — suite de testes
- **MCP server** (`oimpresso-mcp` no CT 100, `laravel/mcp` ^0.7) — knowledge & task management
- **Centrifugo + FrankenPHP** — realtime canônico ([ADR 0058](memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md))

## Módulos principais

| Módulo | Função |
|---|---|
| `Modules/Jana/` | Copiloto IA + memória + MCP server (rename ex-Copiloto via [ADR 0088](memory/decisions/0088-module-rename-php-only.md)) |
| `Modules/NfeBrasil/` | Emissão NFe55 + DANFE + import CSV regras tributárias |
| `Modules/Repair/` | Ordens de serviço (assistência técnica) — MWART pattern |
| `Modules/Financeiro/` | Contas a pagar/receber + integração Asaas |
| `Modules/RecurringBilling/` | Cobrança recorrente + boleto |
| `Modules/Ponto/` | Marcação de ponto (rename ex-PontoWr2) |
| `Modules/Crm/` · `Modules/Cms/` · `Modules/ADS/` · `Modules/SRS/` | Apoio |
| `Modules/Brief/` | Daily Brief MCP — estado consolidado do projeto ([ADR 0091](memory/decisions/0091-daily-brief.md)) |

## Origem

Fork do **UltimatePOS** (Codecanyon) na linha 6.7, evoluído de 2026-04 em diante com:
- Migração Laravel 9 → 13.6 (in-line `knox`/`pesapal` fix)
- Inertia v2 → v3
- Modular split em 30+ módulos
- Constituição V2 — 7 camadas + 8 princípios duros ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md))

## Como começar

**Dev local (Wagner setup):**
```bash
# Herd + Laragon MySQL (root, sem senha, DB 'oimpresso')
herd link  # se primeira vez
composer install
npm install && npm run build
php artisan migrate --seed
# acesse http://oimpresso.test
```

**Time externo (Felipe/Maiara/Eliana/Luiz):**
1. Setup MCP server da empresa via skill `oimpresso-team-onboarding`
2. Ler [`CLAUDE.md`](CLAUDE.md) — primer Claude Code
3. Brief inicial: tool MCP `brief-fetch` (camada L7)

## Rodando testes

> ⛔ **Testes NÃO rodam local nem no Hostinger — só no CT 100** (MySQL real, biz=1 dogfooding). Regra Tier 0 ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md)); o hook `block-test-fora-ct100.ps1` bloqueia `vendor/bin/pest`/`php artisan test` fora do CT 100.

```bash
# Canônico — via Tailscale no container de staging do CT 100:
tailscale ssh root@ct100-mcp \
  "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=NomeDoTeste"
```

O **gate de merge é o CI GitHub Actions** (roda a suite + os módulos em paralelo). Não confie em verde local.

## Documentação canônica

- [`CLAUDE.md`](CLAUDE.md) — primer técnico (≤100 linhas + imports)
- [`memory/decisions/`](memory/decisions/) — ADRs Nygard (índice vivo: [`_INDEX-GENERATED.md`](memory/decisions/_INDEX-GENERATED.md))
- [`memory/requisitos/`](memory/requisitos/) — SPEC/RUNBOOK/CAPTERRA por módulo
- `.claude/skills/` — 30+ skills (Tier A always-on + Tier B/C contextuais)

## Suporte & licença

Software proprietário. Origem UltimatePOS sob [Codecanyon Standard License](https://codecanyon.net/licenses/standard); modificações e módulos novos pertencem à oimpresso.

Contato: wagnerra@gmail.com (Wagner, dono/operador).
