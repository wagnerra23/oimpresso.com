# 05 — CI Gate (`charter-gate.yml` GitHub Action)

> **Spec do workflow que valida charters em todo PR.**
> Modo soft (warn-only) em F1; hard em F2 após ratchet baseline aceito.

---

## Trigger

```yaml
on:
  pull_request:
    paths:
      - '**/*.charter.md'
      - 'resources/js/Pages/**/*.tsx'
      - 'memory/sprints/s6-charter-capterra/**'
```

Roda só quando charters ou telas Inertia mudam — sem custo em PRs de outros módulos.

---

## Etapas

```
1. Checkout                                                ~5s
2. Setup PHP 8.4 + Composer                                ~30s
3. Install deps (cache hit típico)                         ~10s
4. Discover *.charter.md (find + filter)                   <1s
5. Tier 1 GUARD (estrutura)                                ~3s
6. Tier 2 GUARD (metrics referenciadas)                    ~30s
7. Drift report (last_validated por tier)                  ~1s
8. Comment no PR                                           ~2s
```

Total alvo: <90s no caminho feliz.

---

## Modos

### Soft (F1 default)
- `continue-on-error: true` no step GUARD
- Sempre exit 0
- Comenta resumo no PR usando `gh pr comment`

### Hard (F2+, decisão Wagner)
- `continue-on-error: false` em Tier A
- Exit 1 se Tier A errors > 0
- Tier B/C ainda soft
- PR fica vermelho → merge bloqueado

---

## Output (PR comment template)

```markdown
## 🛡️ Charter Gate — modo soft

**Charters detectados:** 5 (5 Tier A · 0 Tier B · 0 Tier C)

| Tela | Tier 1 (estrutura) | Tier 2 (metrics) | Stale? |
|---|---|---|---|
| /repair/dashboard | ✅ | ⏭️ skip (specs F2) | ✅ fresh |
| /repair/job-sheet | ✅ | ⏭️ skip | ✅ fresh |
| ... | | | |

**Soft mode:** este check NÃO bloqueia merge. Drift detectado vai pro `charter:health` daily.
```

---

## Métricas que o gate emite

Vai pra `mcp_audit_log`:
- `charter_gate_runs_total{result}` (success / soft_warn / hard_fail)
- `charter_gate_duration_seconds`
- `charter_gate_drift_count`

M5 (Detector latency, F4) lê o tempo entre PR opened e workflow completed.

---

## Ratchet baseline (entra em F2)

Antes de virar hard:
1. Coletar 7d de soft runs
2. Snapshot de erros existentes vai pra `tests/Charter/baseline.json`
3. CI hard só quebra se PR introduz erro NOVO (não em baseline)
4. Wagner remove items do baseline conforme corrige

---

## Critério de aceite F1

- [ ] `.github/workflows/charter-gate.yml` commitado
- [ ] Workflow roda em PRs que tocam `*.charter.md`
- [ ] Modo soft confirmado: comenta mas não falha
- [ ] PR comment formatado conforme template
- [ ] Tempo total < 90s em PR que não toca tudo

---

## Não escopo F1 (fica F2+)

- ❌ Hard mode com baseline
- ❌ Auto-PR `charter-evolve` (skill que propõe v2)
- ❌ Cron daily `charter:health` (vive em F2)
- ❌ Dashboard `/copiloto/admin/qualidade` (F4)
