# RUNBOOK: Design-to-Code Synchronization

> **⚠️ Este runbook é DESIGN → CÓDIGO** (mockup/canvas do claude.ai/design → Pages React `resources/js/Pages/…`). Para sincronizar **TOKENS** do Design System (cor/tipo/espaço) entre git e espelho, use os companheiros:
> - [`design-sync-pull.md`](design-sync-pull.md) — espelho (claude.ai/design) → tokens git (autoria + triagem).
> - [`design-sync-push.md`](design-sync-push.md) — tokens git → espelho (re-espelho, incremental).
> - Sentinela de drift: [`ds-mirror-drift`](../../scripts/governance/ds-mirror-drift.mjs). Governança do loop: proposta [`2026-07-08-profissionalizar-ds-sync-git-espelho.md`](../../memory/decisions/proposals/2026-07-08-profissionalizar-ds-sync-git-espelho.md) (+ ADR de transição a ser numerada por [W]).

> **Quando usar:** Wagner (ou outro designer) trabalha mockup no **Claude Design canvas** (claude.ai/design), recebe um zip/screenshot/HTML, e precisa portar pro repo (`resources/js/Pages/...`) preservando intenção visual.
>
> **Quem orquestra:** Claude Code (sessão desktop). Esse runbook é o roteiro passo-a-passo.
>
> **Referencia:** [Design.md §2](../../Design.md) (Workflow Wagner), [ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) (visual gate F1.5), [ADR 0109](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md) (Claude Design plugin).

---

## Gatilhos

| Gatilho | Ação inicial |
|---|---|
| Wagner cola screenshot/zip de claude.ai/design | Iniciar Fluxo A (handoff bundle) |
| PR adicionou arquivos em `docs/handoff/[bundle-name]/` | Iniciar Fluxo B (commit hook futuro) |
| Wagner pede "porta o design pro Sells/Index" | Fluxo A |
| ADR canon (0107/0109) muda regra visual | Fluxo C (back-port retroativo) |

---

## Fluxo A — Handoff via canvas Claude Design

### A.1. Receber artefatos

Wagner traz um ou mais de:
- Screenshot PNG/JPG (mockup gerado no canvas)
- Arquivo HTML (export do canvas, ex: `os-page-v2.html`)
- Zip com múltiplos HTMLs por módulo (UI Kit Cowork pattern)
- Texto descritivo + URL do canvas
- Figma URL (raro — preferimos Claude Design)

**Salvar bundle em** `docs/handoff/[YYYY-MM-DD]-[modulo]-[escopo]/`:

```
docs/handoff/2026-05-08-sells-drawer-redesign/
├── README.md           ← gerar, descreve a sessão
├── original.html       ← export Claude Design canvas
├── screenshot-1.png    ← mockup principal
├── screenshot-2.png    ← variações/estados
└── notas-wagner.md     ← decisões durante a sessão (timezone, copy, etc)
```

### A.2. Identificar contexto

Em ordem:

1. **`Design.md`** raiz — princípios não-negociáveis (§3) + Cockpit V2 spec (§16)
2. **[ADR 0110](../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)** — anatomia/tipografia/cores semânticas
3. **`*.charter.md`** da Page parent (se existir) — Mission/Goals/Non-Goals
4. **Pages canon vivos** (Sells/Index, Sells/Create, governance/Dashboard, ProjectMgmt/Board) — comparar pattern

### A.3. Visual gate F1.5 (ADR 0107) — OBRIGATÓRIO

Antes de tocar código:

1. **Criar `<tela>-visual-comparison.md`** em `memory/requisitos/[Modulo]/`:
   - 15 dimensões (cobertura V2)
   - Screenshot mockup vs canon vs proposta
   - Score critique (target ≥80) via skill `mwart-comparative` V3
2. **Invocar skill `mwart-comparative` V3** que orquestra:
   - `design:research-synthesis` (persona context)
   - `design:design-system` (audit consistency)
   - `design:ux-copy` (microcopy review)
   - `design:design-critique` (5-categoria estruturada)
   - `design:accessibility-review` (WCAG 2.1 AA)
3. **Wagner aprova SCREENSHOT** (não tabela). Sem aprovação → não codar.

### A.4. Implementação (skill `ui-component-creator`)

Invocar com:

```
component_name = [nome do componente novo/alterado]
target_path    = resources/js/Pages/[Modulo]/...
purpose        = [extraído da Mission do charter ou do bundle README]
page_charter_path = resources/js/Pages/[Modulo]/[Page].charter.md
design_spec    = docs/handoff/[bundle]/README.md + screenshots
```

Skill segue Cockpit Pattern V2 obrigatório (anatomia + cores + tipografia + componentes shared).

### A.5. Tests anti-regressão

Suite Pest deve passar:

```bash
./vendor/bin/pest tests/Feature/Design/ tests/Feature/[Modulo]/
```

Se for Page nova, adicionar test estrutural espelho de [SellsIndexPageTest.php](../../tests/Feature/Sells/SellsIndexPageTest.php).

### A.6. Build + smoke visual

```bash
npm run build:inertia          # bundle Inertia gerado
git diff --stat                # conferir escopo
```

Smoke obrigatório:
- Abrir tela em prod (`https://oimpresso.com/[rota]`) ou Herd local (`oimpresso.test/[rota]`)
- Screenshot side-by-side com mockup original
- Validar: AppShellV2 + h1 canon + KPIs/pills/drawer conforme spec

### A.7. Commit + PR

Branch: `claude/[modulo]-[escopo]-design-sync`.

```bash
git add resources/js/Pages/[Modulo]/ tests/Feature/[Modulo]/ docs/handoff/[bundle]/
git commit -m "feat([modulo]): port design [escopo] (handoff bundle [bundle-name])

Refs: docs/handoff/[bundle-name]/, ADR 0110, ADR 0107
Bundle URL: [claude.ai/design URL se houver]

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
git push -u origin claude/[modulo]-[escopo]-design-sync
gh pr create --title "..." --body "..."
```

PR body deve referenciar:
- Bundle handoff path
- Pages canon comparados
- Tests Pest passing count
- Screenshot before/after

### A.8. Pós-merge

- Atualizar `*.charter.md` da Page com `last_validated: [hoje]`
- Deploy Hostinger (build + opcache reset)
- Smoke visual final em prod

---

## Fluxo B — Commit hook (futuro automático)

Quando workflow GitHub Actions detectar push em `docs/handoff/**`, dispara Claude Code com:

```yaml
- uses: anthropics/claude-code-action@v1
  with:
    prompt: "Sync design from docs/handoff/${{ github.event.head_commit.modified[0] }}"
    skills: ui-component-creator
```

**Hoje:** disparo manual via prompt. Automatização fica pra S6+ quando hooks GitHub estiverem validados.

---

## Fluxo C — Back-port retroativo de mudança canon

Quando ADR 0110 (ou 0107/0109) for emendado com nova regra visual:

1. Identificar Pages afetadas (test `CockpitPatternConformanceTest` revela offenders)
2. Priorizar por tráfego (Sells > Repair > Officeimpresso > resto)
3. Aplicar mudança em batch (PR único cobre múltiplas Pages)
4. Atualizar tests Pest pra cobrir nova regra

---

## Anti-padrões

- ❌ Pular F1.5 visual gate ("vou direto codar e Wagner aprova screenshot depois")
- ❌ Misturar handoff bundle com código (handoff é transitório, fica em `docs/handoff/`)
- ❌ Commit zip Claude Design no repo de código (use `docs/handoff/[bundle]/original.html` extraído)
- ❌ Esquecer de atualizar charter `last_validated`
- ❌ Não rodar tests Pest antes de PR
- ❌ Pular skill `mwart-comparative` V3 (orquestração Claude Design plugin é canon — ADR 0109)

---

## Refs

- [Design.md §2 Workflow Wagner usa hoje](../../Design.md)
- [ADR 0104 Processo MWART](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 Visual gate F1.5](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0109 Claude Design plugin](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- [ADR 0110 Cockpit Pattern V2](../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- Skill `ui-component-creator` (.claude/skills/)
- Skill `mwart-comparative` V3 (.claude/skills/)
- Skill `mwart-process` (.claude/skills/) — fases F1-F5

---

**Última atualização:** 2026-05-08 — runbook criado integrando Cockpit V2 + plugin Claude Design + MWART F1.5.
