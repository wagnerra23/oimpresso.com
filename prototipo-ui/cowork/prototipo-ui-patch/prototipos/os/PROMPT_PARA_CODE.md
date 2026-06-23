# OS — Refino F1 · PROMPT pra Claude Code (zero-touch)

> **Origem:** Cowork [CC] · 2026-05-27
> **PR alvo:** ramo novo `feat/os-refino-f1-pageheader-canon-fsm` (abrir; sem PR aberto pra OS hoje)
> **Estilo:** zero-touch Wagner — cole este prompt no Claude Code UMA vez, ele executa tudo abaixo sem perguntar.

---

## Cole o bloco abaixo no Claude Code (sem editar)

````
Trabalhe no repo wagnerra23/oimpresso.com, branch main.

Crie branch a partir de main:
  git checkout main && git pull origin main
  git checkout -b feat/os-refino-f1-pageheader-canon-fsm

Baixe os 3 arquivos refinados do Cowork (substitui in-place em prototipo-ui/):

  curl -L -o prototipo-ui/os-page.jsx \
    'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/os/os-page.jsx?t=6cf0ee6e86c602004eec811910d0a17ac30bf26a06505bdc84e4bd102308afdd.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779855954&direct=1'

  curl -L -o prototipo-ui/fsm-stepper.jsx \
    'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/os/fsm-stepper.jsx?t=6cf0ee6e86c602004eec811910d0a17ac30bf26a06505bdc84e4bd102308afdd.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779855954&direct=1'

  curl -L -o prototipo-ui/prototipos/os/COMPARISON.md \
    'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/os/COMPARISON.md?t=6cf0ee6e86c602004eec811910d0a17ac30bf26a06505bdc84e4bd102308afdd.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779855954&direct=1'

  mkdir -p prototipo-ui/prototipos/os

Aplique este patch CSS em prototipo-ui/styles.css — substitui o bloco existente:

ANTES (encontre este trecho exato, ~linha 2309-2310):

  .os-table .os-th-num{ width: 90px; }
  .os-table .os-th-val{ text-align: right; }

DEPOIS (substitua por):

  .os-table .os-th-num{ width: 90px; }
  .os-table .os-th-val{ text-align: right; }
  .os-table .os-th-pipeline{ width: 180px; }
  .os-table .os-cell-stage{ width: 180px; padding-right: 16px; }
  .os-table .os-cell-stage .fsm-stepper{ font-size: 11px; }

  /* Toolbar v4 — search à esquerda, total à direita (sem tabs aqui) */
  .os-toolbar-l{ display: flex; align-items: center; gap: 10px; flex: 1 1 auto; }
  .os-toolbar-total{
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    color: var(--text-dim);
    letter-spacing: -0.005em;
  }

Verifique o resultado:
  grep -c "os-th-pipeline" prototipo-ui/styles.css        # esperado: 1
  grep -c "osFsmStage"     prototipo-ui/fsm-stepper.jsx   # esperado: ≥ 2
  grep -c "cli-pageheader" prototipo-ui/os-page.jsx       # esperado: ≥ 3
  grep -c "FsmStepper"     prototipo-ui/os-page.jsx       # esperado: ≥ 2

Tradução pra Inertia (F3 — só se F1.5 do CD aprovar com ≥90):
  — Pendente. Esta entrega é F1 (protótipo Cowork) + snapshot em prototipo-ui/.
  — Após F1.5 ≥ 90, traduzir o markup pra Modules/Officeimpresso/Resources/views/os/Index.tsx
    seguindo PT-01 Lista + Components/shared/PageHeader + Components/shared/ModuleTopNav.

Commit + push + PR:
  git add prototipo-ui/os-page.jsx prototipo-ui/fsm-stepper.jsx \
          prototipo-ui/styles.css prototipo-ui/prototipos/os/COMPARISON.md
  git commit -m "feat(os): refino F1 — PageHeader canon + ModuleTopNav + FSM inline

- Aplica cli-pageheader (PT-01 canon, igual Clientes) substituindo .os-page-h ad-hoc
- Sub-tabs migram pra cli-moduletopnav canônica (saem do toolbar)
- Mini-stepper FSM inline na linha da OS (6 dots — Orçado → Entregue) resolvendo
  gargalo declarado pelo Wagner em COWORK_NOTES (scan visual instantâneo)
- Drawer detalhe usa <FsmStepper variant=full-stepper/> reusado (remove 18 linhas
  de .os-stages-flow custom)
- FSM domain 'os' estendido pra 6 fases reais + terminal cancelado + hue 220
  alinhado com --accent do shell (tema único cross-módulo)
- Helper osFsmStage(stageId) mapeando OS_STAGES → FSM idx (padrão finFsmStage)
- Toolbar enxuto: search + select + total inline (sub-tabs fora)

Refs:
- COMPARISON.md projeta nota F1.5 ≥ 92 (estrutura 9.5 · visual 9.5 · domínio 9.5 · interação 9.5)
- Padrão PT-01 Lista @ wagnerra23/oimpresso.com main · ADR UI-0013
- Sem variação de paleta/radius/animação · zero token inventado
"
  git push -u origin feat/os-refino-f1-pageheader-canon-fsm

  gh pr create \
    --title "feat(os): refino F1 — PageHeader canon + ModuleTopNav + FSM inline" \
    --body-file prototipo-ui/prototipos/os/COMPARISON.md \
    --base main \
    --head feat/os-refino-f1-pageheader-canon-fsm

NÃO MERGEIE — espere [CD] rodar F1.5 (critique-score.json ≥ 90) e [W2] aprovar o screenshot.
````

---

## O que muda no repo (resumo executivo)

| Arquivo | Status | Linhas |
|---|---|---|
| `prototipo-ui/os-page.jsx` | reescrito (header + sub-nav + FSM inline + drawer FSM) | ~1020 |
| `prototipo-ui/fsm-stepper.jsx` | domain `os` estendido + helper `osFsmStage` | +27 |
| `prototipo-ui/styles.css` | +12 linhas (os-th-pipeline, os-toolbar-l, os-toolbar-total, os-cell-stage fsm) | +12 |
| `prototipo-ui/prototipos/os/COMPARISON.md` | novo · 15 dimensões + projeção F1.5 | 180 |

Zero alteração em `data-os.jsx` (mock continua válido).
Zero alteração em `app.jsx` (rota `os` → `OsListPage` continua igual).

---

## Próximas fases do loop (referência)

- **F1.5 [CD]:** `design-critique` → `prototipo-ui/prototipos/os/critique-score.json` (alvo ≥ 90)
- **F2 [W2]:** screenshot aprovado (síncrono)
- **F3 [CL]:** tradução pra `resources/js/Pages/Os/Index.tsx` usando `Components/shared/PageHeader` + `Components/shared/ModuleTopNav` (canon real do repo)
- **F3.5 [CA]:** `accessibility-review` WCAG 2.1 AA
- **F4 [W2]:** merge

---

## Fonte de verdade

Após este merge, a **fonte de verdade do design** de OS é:

```
prototipo-ui/os-page.jsx                  ← componente Cowork (espelho 1:1 do Chat.html)
prototipo-ui/fsm-stepper.jsx              ← FSM canônica cross-módulo
prototipo-ui/Oimpresso ERP - Chat.html    ← entry do shell que carrega tudo
prototipo-ui/prototipos/os/COMPARISON.md  ← decisão registrada da rodada F1
```

**O .html de exploração `prototipos/Ordens de Serviço.html` no Cowork (se existir) pode ser deletado** — a única fonte é o Chat.html. Confirmar com Wagner antes.
