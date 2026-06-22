---
date: "2026-06-22"
time: "12:23 BRT"
slug: "charter-refs-catraca-promovida-required"
tldr: "jana:health-check expôs charter_refs_broken apodrecendo (advisory subia 279→282 sozinho); virou catraca completa (279→2 + checker case-exato + gate require-safe + fix da fonte) e foi PROMOVIDA a required no main (flip protection 19→20)."
cycle: null
prs: [3190, 3195, 3199]
us: ["US-GOV-043"]
decided_by: ["W"]
topic: "charter_refs_broken — de advisory que apodrecia a gate REQUIRED no main (279→2 + catraca + fonte + flip)"
duration: "~5h"
authors: ["claude-code-wagner-laptop", "wagner"]
---

## Estado MCP no momento
- Cycle **CYCLE-08** "Receita — Onda A" · 79% decorrido · 6 dias restantes. **Off-cycle**: este trabalho é governança (não receita), nasceu de `jana:health-check`.
- Task criada: **US-GOV-043** (Governance) — owner claude, p2 — registra as 4 camadas da catraca.

## O que aconteceu
Começou em "qual o comando de saúde do sistema?" → `php artisan jana:health-check`. Rodado **ao vivo no Hostinger** (warm-up curl 443 ×5 + SSH canon `hostinger.md`; confirmado que o scheduler/health-check vive no **Hostinger**, não no CT 100 — comentário `Kernel.php:191` "Hostinger ≠ CT 100 · só artisan + schedule"). Veredito: sistema saudável, 5 advisory de charter/governança. O que mais cheirava a regressão — `charter_refs_broken` (subia **279→282 sozinho**) — virou o trabalho.

**Causa-raiz:** bug latente do template de charter — todo link pra raiz do repo nascia com **um `../` a menos** (off-by-one de profundidade). 215 links mortos por isso, não 215 problemas. Depois, triagem dos 64 restantes (ADR renomeadas/renumeradas → repath; `CAPTERRA-FICHA`→`INVENTARIO`; `runbook` Inventory inexistente → remove; artefatos prototipo deletados + testes/sessions mortos → delinkify) → **64→2**. Os 2 restantes = charters órfãos (`fm:component` sem `.tsx`: `OficinaAuto/Os/Create` dup stale, `Orcamento/Index` sem página).

Wagner pediu **catraca** ("vai ser esquecido ou não automatizado?"), depois **"promover"** (a required). Construído em **worktree isolado** (3 sessões paralelas ativas no `onda-0` — zero race). Extraído limpo pro **main** via cherry-pick (conflito só no `baseline-tamper-guard` que o main evoluiu — resolvi reusando o `detectCountRatchet` do main). CI Linux pegou **4 links `Design.md`** que meu Windows (case-insensitive) mascarava → checker virou **case-exato** (`realpathSync.native`). memory-health [G] pegou o gate fora do censo → registrei. Pest falhou em flake do Centrifugo (re-run verde). **Merge no main + flip da protection viva (19→20 required) + baseline sync.**

## Artefatos gerados
- **`scripts/governance/charter-refs.mjs`** (~180l, `--check`/`--fix`/`--list`, case-exato) + **`charter-refs.test.mjs`** (self-test 8/8) + **`governance/charter-refs-baseline.json`** (ceiling 2).
- **`.github/workflows/charter-refs-gate.yml`** (require-safe: sem paths-filter) + wiring no `baseline-tamper-guard` (.mjs+.yml) + registro no `gates-registry.json`.
- **`charter-write` SKILL.md §4b** (fonte: novo charter não nasce quebrado).
- **57 charters** corrigidos (off-by-one + triagem + case).

## Persistência
- **git**: PR **#3190** (onda-0, catraca inicial, MERGED) · **#3195** (main, catraca+fixes, MERGED sha `9902de9e39`) · **#3199** (main, baseline sync, auto-merge squash armado) · onda-0 tem cópia idempotente.
- **protection viva main**: `charter_refs_broken <= teto` agora REQUIRED (20 contexts; flip via `gh api PATCH`).
- **MCP**: US-GOV-043 no SPEC Governance (push feito; webhook propaga).

## Próximos passos pra retomar
- Confirmar #3199 mergeou (fecha o 🟡 do protection-drift). Worktree `charter-refs-main` removível depois.
- **2 charters órfãos** (US-GOV-043 residual): deletar `Os/Create` stale + decidir `Orcamento` → baseline 2→0.

## Lições catalogadas
- **`existsSync` mente no Windows** (case-insensitive) vs CI Linux/Hostinger — gate que valida paths PRECISA `realpathSync.native` senão dá veredito diferente local×CI. Foi o que escondeu os 4 `Design.md`.
- **Promover gate a required tem ordem dura**: o workflow precisa existir no **main** antes do flip, senão required-que-não-reporta trava TODO PR. E path-filtered + required = trava quem não toca o path → gate tem que ser **require-safe** (sem paths-filter, sempre roda+reporta).
- **`onda-0` estava atrás do main** — `main..onda-0` mostra "deleções" que são só staleness; cherry-pick pro main atual é mais limpo que merge do branch stale.
- memory-health **Check [G]** (censo `gates-registry`) pega todo workflow novo no MESMO PR — registrar junto (3ª vez que bate nessa).
- `/tmp` no MSYS/Windows quebra `node fs` → usar path do worktree; env-var pra `node -e` precisa prefixo `VAR=x cmd`, não sufixo.

## Pointers detalhados
- Catraca/ratchet canon: [ADR 0256](../decisions/0256-knowledge-survival-catraca-sentinela-gate-cadencia.md) · anti-teatro: `contrato-de-tela.yml` · promoção required: [ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5 + `required-checks-baseline.json`.
- Checker espelha o PHP: `Modules/Jana/Services/CharterHealthChecker.php` (check `charter_refs_broken`).
