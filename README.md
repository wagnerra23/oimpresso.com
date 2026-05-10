# oimpresso.com — ERP gráfico com IA

ERP multi-tenant pra indústria gráfica brasileira, com módulos integrados de financeiro, NFe/NFSe, ponto, CRM e copiloto IA (Jana).

## Stack canônica

- **Laravel 13.6** + PHP 8.4 (Herd local · CT 100 Proxmox · Hostinger prod)
- **Inertia v3** + React 18 + Tailwind 4 (frontend SPA-style sobre Blade)
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
| `Modules/Crm/` · `Modules/Cms/` · `Modules/ADS/` · `Modules/MemCofre/` | Apoio |
| `Modules/Brief/` | Daily Brief MCP — estado consolidado do projeto ([ADR 0091](memory/decisions/0091-brief-mcp-tool-l7.md)) |

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

```bash
# Suite completa (SQLite :memory: — conforme phpunit.xml)
vendor/bin/pest

# Módulo específico (5 módulos com CI automatizado)
vendor/bin/pest Modules/Arquivos/Tests
vendor/bin/pest Modules/ComunicacaoVisual/Tests
vendor/bin/pest Modules/NfeBrasil/Tests
vendor/bin/pest Modules/Repair/Tests
vendor/bin/pest Modules/Vestuario/Tests
```

CI roda automaticamente esses 5 módulos em paralelo (matrix) em PRs que tocam `Modules/<X>/` — ver `.github/workflows/modules-pest.yml`.

## Documentação canônica

- [`CLAUDE.md`](CLAUDE.md) — primer técnico (≤100 linhas + imports)
- [`memory/decisions/`](memory/decisions/) — 95+ ADRs Nygard
- [`memory/requisitos/`](memory/requisitos/) — SPEC/RUNBOOK/CAPTERRA por módulo
- `.claude/skills/` — 30+ skills (Tier A always-on + Tier B/C contextuais)

## Suporte & licença

Software proprietário. Origem UltimatePOS sob [Codecanyon Standard License](https://codecanyon.net/licenses/standard); modificações e módulos novos pertencem à oimpresso.

Contato: wagnerra@gmail.com (Wagner, dono/operador).
