---
date: "2026-07-28"
hour: "15:16 BRT"
duration: "1h"
topic: "Planta de IA gerada pela máquina matriz, sem duplicar runtime, container e catálogo"
authors: [W, C]
outcomes:
  - "PLANTA-IA.md passou a ser gerada por system-map.mjs"
  - "22 agentes PHP, 44 tools MCP e compose são derivados do código"
  - "Workflow diário/PR passou a regenerar e versionar a nova página"
prs: []
us:  []
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0048-framework-agentes-laravel-ai-vizra-rejeitada"
  - "0062-separacao-runtime-hostinger-ct100"
---

# Session log 2026-07-28 — planta de IA viva

## TL;DR

A planta deixou de ser inventário manual: `system-map.mjs` agora deriva agentes, tools MCP, tools SQL e serviços declarados em compose, gera `memory/reference/PLANTA-IA.md` e atualiza o painel. Estado de máquina não é copiado como “verde”; a página mantém probes reproduzíveis.

## Contexto

Wagner pediu uma página documental e perguntou como mantê-la sempre viva. A auditoria anterior misturou contagens globais de classes com residência em máquina. A solução já existente no repo era estender a máquina matriz, não criar outro gerador.

## Cronologia

| Quando | Evento |
|---|---|
| 14:20 | Runtime vivo conferido: Hostinger shared + CT 100; 22 containers no CT não equivalem a 22 máquinas |
| 14:45 | `system-map.mjs` estendido com inventário determinístico de IA |
| 15:00 | `PLANTA-IA.md`, painel e workflow de auto-PR integrados |
| 15:10 | Mudança portada da branch local stale para worktree limpa baseada na `main` atual |
| 15:16 | Checks executados no CT 100 |

## Entregas

- **Página gerada** — `memory/reference/PLANTA-IA.md`
- **Fonte única estendida** — `scripts/governance/system-map.mjs`
- **Auto-manutenção** — `.github/workflows/system-map.yml`
- **Canário de paths** — `scripts/governance/onboarding-paths-check.mjs` cobre os três artefatos gerados
- **Executor documental** — refresher `system-map` reconhece `PLANTA-IA.md` como output transacional

## Decisões cinzentas resolvidas

| Pergunta | Decisão | Justificativa |
|---|---|---|
| Gravar “22 containers vivos” na documentação? | Não | uptime é temporal; a página aponta o probe `docker ps` |
| Contar 22 agentes por máquina? | Não | são classes globais no repo, executadas conforme o disparo |
| Criar novo gerador? | Não | `system-map.mjs` já é a máquina matriz canônica |
| Tratar Hostinger e CT 100 como duas máquinas físicas? | Não | são zonas operacionais; o MySQL gerenciado pode estar em host distinto do web shared |

## Aprendizados / pegadinhas

- Uma planta viva separa **topologia versionada**, **inventário derivado** e **estado de runtime**.
- O baseline versionado tinha 33 checks; a medição viva do GitHub pode diferir. A página não promove o número vivo a cânone.
- A primeira busca de referências considerava apenas `Modules/` e marcou falsamente `SaleInsightAgent` como órfão; incluir `app/`, `routes/` e `config/` corrigiu para zero órfãos.
- A pasta original estava 270 commits atrás da `main`; a implementação final foi portada para `codex/planta-ia-documentacao`.

## Validação

- CT 100: `system-map.mjs --check` — verde.
- CT 100: `onboarding-paths-check.mjs` — 3 docs, zero paths mortos.
- CT 100: `document-relocation-executor.mjs --selftest` — 15/15.
- CT 100: `memory-health.mjs --json --warn-only` — zero fails; warnings preexistentes fora do escopo.
- `git diff --check` — verde.

## Próximos passos (não-bloqueante)

- [ ] Revisar a página gerada e decidir se a branch deve virar PR.
- [ ] Se quiser uptime histórico na página, apontar para telemetria própria; não persistir status momentâneo no Markdown.

## Referências

- [PLANTA-IA.md](../reference/PLANTA-IA.md)
- [PAINEL-SISTEMA.md](../reference/PAINEL-SISTEMA.md)
- [ADR 0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)
- [ADR 0048](../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md)
- [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)
