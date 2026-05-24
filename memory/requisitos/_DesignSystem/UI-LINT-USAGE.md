---
doc: UI-LINT-USAGE
camada: meta-protocolo
status: ativo
created: 2026-05-24
parent_adr: UI-0013
related: [AUTOMATION-ROADMAP Onda 1.2]
---

# `php artisan ui:lint` · uso

> **Onda 1 Item 1.2** do [AUTOMATION-ROADMAP](AUTOMATION-ROADMAP.md). Detecta anti-padrões UI da [Constituição UI v2 · UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md) em `resources/js/Pages/` e `resources/js/Components/`.

## Regras ativas

| ID | Detecta | Como |
|---|---|---|
| **R1** | Cor crua — `#hex` literal (exceto `#fff`/`#000`) ou `bg-COR-NNN` Tailwind literal | regex em arquivos `.tsx`/`.jsx` |
| **R2** | FontAwesome (lucide-only · [UI-0003](adr/ui/0003-lucide-react-como-unica-iconografia.md)) | grep `@fortawesome`, `FontAwesomeIcon` |
| **R3** | Emoji em UI de produto · use lucide icon | Unicode ranges `1F300-1F9FF`, `1F600-1F64F`, `1F1E6-1F1FF`, `1FA70-1FAFF` (exclui ✓ ✗ ⚠ text-style) |
| **R4** | PT-01 Lista · `Pages/<X>/Index.tsx` sem `<PageHeader>` (Slot 1) OU sem `<DataTable>` (Slot 5) shared | regex em imports `@/Components/shared/` + presença de `<PageHeader>` / `<DataTable>` JSX |
| **R5** | Origens canon · CSS canon (`cockpit.css`, `inertia.css`) tem só 5 origins (OS/CRM/FIN/PNT/MFG) | grep `--origin-<X>-` em arquivos CSS canon |

## Comandos típicos

```bash
# Scan rápido (warning, exit 0)
php artisan ui:lint

# Detalhe completo (path:linha:match pra cada hit)
php artisan ui:lint --detail

# CI mode (exit 1 se hit > 0) — sem baseline, vai falhar SEMPRE em projeto atual
php artisan ui:lint --strict

# Modo ratchet (CI realista): só falha se regressão vs baseline
php artisan ui:lint --baseline=config/ui-lint-baseline.json --strict

# Pre-commit hook (só arquivos modificados vs origin/main)
php artisan ui:lint --changed-only --baseline=config/ui-lint-baseline.json --strict

# Regra específica
php artisan ui:lint --rule=R1
php artisan ui:lint --rule=R2,R3

# Path específico
php artisan ui:lint --path=resources/js/Pages/Cliente

# Atualizar baseline (depois de aceitar estado novo — ex: refator que reduziu hits)
php artisan ui:lint --write-baseline=config/ui-lint-baseline.json
```

## Baseline (2026-05-24)

Estado inicial registrado em `config/ui-lint-baseline.json`:
- **R1** (cor crua): ~6859 hits em ~309 arquivos
- **R2** (FontAwesome): 0 hits ✓
- **R3** (emoji): ~119 hits em ~37 arquivos
- **Total:** ~7280 violações · 317 arquivos

> Esse não é um "fail" — é o estado atual aceito. Modo ratchet (`--baseline=...`) **só falha em regressão**. Pra reduzir baseline, refatorar arquivo + rodar `--write-baseline=...` pra registrar novo estado aceito.

## Workflow CI (Onda 2.1 · entregue)

Workflow ativo em [`.github/workflows/ui-lint.yml`](../../../.github/workflows/ui-lint.yml). Dispara em PRs que tocam `resources/js/`, `resources/css/`, `app/Console/Commands/UiLintCommand.php`, ou `config/ui-lint-baseline.json`. Roda `ui:lint --baseline --strict` e falha PR em regressão. Step "Diagnóstico em caso de falha" mostra próximos passos quando vermelho.

## Pre-commit hook (Onda 2.2 · entregue)

Hook apendado em [`.githooks/pre-commit`](../../../.githooks/pre-commit) (GUARDA Anti-Drift). Roda `ui:lint --changed-only --baseline=config/ui-lint-baseline.json` em arquivos staged. **Modo default = warning** (não bloqueia). Pra bloquear em regressão UI:

```bash
# Uma vez no clone:
git config core.hooksPath .githooks

# Habilitar strict UI lint (opt-in):
export OIMPRESSO_UI_LINT_STRICT=1

# Daí qualquer git commit roda ui:lint pre-commit
# Se hits aumentaram vs baseline, commit é BLOQUEADO
```

Pular hook em emergência: `git commit --no-verify`.

## Smoke tests validados (2026-05-24)

### Smoke 1 · `ui:lint` baseline ratchet (Onda 1/2)

**Status:** ✅ **CONFIÁVEL**

Validado por regressão sintética:
1. Adicionado `bg-blue-500 text-red-700` em `Pages/Home/Index.tsx`
2. `php artisan ui:lint --baseline=config/ui-lint-baseline.json --strict`
3. Output: `Baseline 7307 · Atual 7309 · Delta +2 · REGRESSÃO · exit 1` ✓
4. Reverter regressão → Delta +0 · exit 0 ✓

**CI gate L3 vai bloquear** PR de regressão sintática de verdade.

### Smoke 2 · `ui:judge-pr` LLM real (Onda 4) — **FUNCIONOU**

**Status:** ✅ **CONFIRMADO FUNCIONAL** com OpenAI gpt-4o-mini

PR #1437 (1 arquivo .tsx, 2043 bytes diff):
1. `php artisan ui:judge-pr 1437 --save-to=storage/smoke-judge-1437-openai.json`
2. **Score: 30/100 · Verdict: request_changes**
3. 9 dimensões avaliadas
4. **1 violação real detectada:** emoji 🔥 em `SheetNovoGateway.tsx:203`
   - Linha real era 207 (±4 imprecisão · arquivo correto)
   - Cross-confirmado por `ui:lint --rule=R3` (mesmo hit em linha 207)
   - **LLM adicionou contexto semântico:** sugestão substituir por lucide icon + revisar copy
5. 2 sugestões + 2 lembretes
6. JSON output em `storage/smoke-judge-1437-openai.json` (gitignored)
7. Custo real: ~$0.001-0.002 (gpt-4o-mini)

**Path evolutivo da configuração:**
- ✅ **OpenAI gpt-4o-mini** (atual default) — `OPENAI_API_KEY` em `.env` (já estava)
- ⚠️ **Anthropic Claude Sonnet 4.5** (upgrade opcional) — adicionar `ANTHROPIC_API_KEY` em `.env` + editar `@Provider('anthropic') @Model('claude-sonnet-4-5-20250929')` em `PrUiJudgeAgent.php`. Custo ~15x maior ($0.034/PR vs $0.002) mas qualidade superior em raciocínio multi-passos.

**Discrepância LLM vs lint:** LLM pega o mesmo emoji mas adiciona "sugestão de refactor". Onda 4 é **complementar** ao L3 sintático, não substituto. Pra emoji simples, `ui:lint` resolve. Pra "drawer modal sobre modal" ou "layout PT-01 quebrado em essência", só LLM.

## Limites conhecidos (Onda 1.2)

- ❌ Não detecta `<style>` inline com cor crua (raro em React, mas existe)
- ❌ Não detecta `style={{ background: '#fff' }}` quando arquivo tem só essa única ocorrência (regex pega apenas color literals em strings)
- ❌ Não detecta AP2 (componente reinventado) · AP5 (gradient decorativo) · AP7 (status badge bg-fill) · AP8 (copy não-PT-BR) — futuras regras Onda 2/3
- ✅ Detecta R1 cor crua em Tailwind classes string
- ✅ Detecta R2 FontAwesome import
- ✅ Detecta R3 emoji decorativo (exclui ✓ ✗ ⚠ text-style)

## Refs

- **ADR-mãe:** [UI-0013 Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md)
- **PRE-MERGE-UI camada 4 (AP1-AP8):** [PRE-MERGE-UI.md](PRE-MERGE-UI.md)
- **Roadmap:** [AUTOMATION-ROADMAP.md Onda 1.2](AUTOMATION-ROADMAP.md)
- **Command:** [`app/Console/Commands/UiLintCommand.php`](../../../app/Console/Commands/UiLintCommand.php)
- **Skill correlata Tier A:** [`constituicao-ui-aware`](../../../.claude/skills/constituicao-ui-aware/SKILL.md) (gancho de atenção, carrega Constituição antes de codar)

---

**Última revisão:** 2026-05-24 · v1.0 · Onda 1 entregue.
