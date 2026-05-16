# PROTOCOL.md — protocolo formal do loop Claude Design ↔ Claude Code

> **Versão:** 1.0
> **Documento mãe:** [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
> **Última revisão:** 2026-05-09

## 1. Os 6 papéis

| Sigla | Quem | Onde vive | O que faz |
|---|---|---|---|
| **[W]** | Wagner | local + chat | escreve pedido em `COWORK_NOTES.md`, aprova screenshot, aprova merge |
| **[CC]** | Claude Cowork | Anthropic Cowork web app | gera protótipo visual `<tela>/page.tsx` |
| **[CD]** | Claude Design | Cowork OU plugin local | roda `design:design-critique`, `design:design-system`, `design:ux-copy` |
| **[CL]** | Claude Code | repo Laravel (este) | traduz protótipo aprovado pra Inertia/React real |
| **[CA]** | Claude Accessibility | plugin local | roda `design:accessibility-review` (WCAG 2.1 AA) |
| **[W2]** | Wagner | local | aprova SCREENSHOT real (não tabela) antes de mergear |

`[CD]` e `[CA]` podem ser a mesma instância de Claude Code rodando skills do plugin Anthropic. `[CC]` é separado (Cowork web app).

## 2. As 5 fases (na verdade 7 com 1.5 e 3.5)

```
F0 BRIEF       [W]   pedido em COWORK_NOTES.md
                     ↓
F1 DESIGN      [CC]  protótipo visual em prototipos/<tela>/page.tsx
                     ↓
F1.5 CRITIQUE  [CD]  score ≥80 ok / 70-79 1 round refator / <70 discussão
                     ↓
F2 SCREENSHOT  [W2]  aprovação visual síncrona (não tabela)
                     ↓
F3 CODE        [CL]  refator/criação Inertia em <Tela>.tsx + .charter.md
                     (detalhe cirúrgico: ver PROTOCOL-F3-COWORK-CODE.md — 7 sub-fases + agente cowork-to-inertia)
                     ↓
F3.5 A11Y      [CA]  accessibility-review WCAG 2.1 AA
                     ↓
F4 MERGE       [W2]  PR merge se F3.5 passou
```

Sem fase pulada. Mesmo princípio do MWART process ([ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).

## 3. Critérios de transição entre fases

| De → Pra | Critério obrigatório |
|---|---|
| F0 → F1 | `COWORK_NOTES.md` tem entrada com tela + prioridade + contexto + restrições |
| F1 → F1.5 | `prototipos/<tela>/page.tsx` existe + commitado |
| F1.5 → F2 | `prototipos/<tela>/critique-score.json` com `score: ≥80` (ou exceção justificada) |
| F2 → F3 | Wagner aprovou screenshot (registrado em `SYNC_LOG.md` com `[W2]: approved`) |
| F3 → F3.5 | `resources/js/Pages/<Mod>/<Tela>.tsx` editado/criado + `npm run build` OK |
| F3.5 → F4 | `prototipos/<tela>/a11y-report.md` sem `severity: critical` |
| F4 → done | PR merged + `SYNC_LOG.md` com `[W2]: merged` + `HANDOFF.md` atualizado |

## 4. Onde cada fase escreve

| Fase | Arquivo escrito | Responsável |
|---|---|---|
| F0 | `COWORK_NOTES.md` (append) | [W] |
| F1 | `prototipos/<tela>/page.tsx`, `prototipos/<tela>/COMPARISON.md` | [CC] |
| F1.5 | `prototipos/<tela>/critique-score.json` | [CD] |
| F2 | `SYNC_LOG.md` (1 linha aprovação) + `HANDOFF.md` (atualizar estado) | [W2] |
| F3 | `resources/js/Pages/<Mod>/<Tela>.tsx`, `*.charter.md`, `CODE_NOTES.md` (append) | [CL] |
| F3.5 | `prototipos/<tela>/a11y-report.md` | [CA] |
| F4 | `SYNC_LOG.md` (1 linha merge) + `HANDOFF.md` (limpar estado, próxima da queue) | [W2] |

## 5. Override autorizado

Wagner pode pular gates via comentário em `COWORK_NOTES.md`:

| Comando | Pula | Quando usar |
|---|---|---|
| `/design-override <razão>` | F1.5 critique | Tela trivial, copy-only, fix de bug |
| `/screenshot-override <razão>` | F2 aprovação | Wagner já viu protótipo Cowork direto |
| `/a11y-override <razão>` | F3.5 a11y | Protótipo interno superadmin (não-cliente-facing) |

**Formato canônico:** `/X-override <razão curta> --tela=<nome-kebab>`
**Exemplo:** `/screenshot-override Wagner aprovou no Cowork --tela=sells-create`

Cada override gera ADR per-tela `lifecycle: historical` em `memory/decisions/<NNNN>-design-excecao-<tela>.md`.

Sem override, gates não cedem. Mesmo loop pra todos: `[W]`, `[F]`, `[M]`, `[L]`, `[E]`.

## 6. Métricas de saúde do loop

A adicionar em `php artisan jana:health-check`:

| Check | SQL/lógica | Falha quando |
|---|---|---|
| `design_loop_stuck` | conta telas em F3 há mais de 7 dias | ≥1 |
| `design_critique_skipped` | conta `prototipos/*/critique-score.json` ausentes | ≥1 protótipo sem critique |
| `design_a11y_skipped` | conta merges sem `a11y-report.md` no path | ≥1 merge sem a11y |
| `design_loop_active_count` | conta entradas `HANDOFF.md` em fase ≠ done | informativo |

Falha → ALERT em `storage/logs/laravel.log`.

## 7. Como lidar com batch (várias telas relacionadas)

Telas que fazem sentido juntas (ex: `Repair/Dashboard` + `JobSheet` + `Status`) podem rodar em **batch single F0**:

- 1 entrada em `COWORK_NOTES.md` listando as 3 telas
- 1 protótipo Cowork por tela em `prototipos/<tela1>/`, `<tela2>/`, `<tela3>/`
- 1 critique score por tela
- 1 screenshot approval por tela (Wagner aprova as 3 em sequência)
- 3 PRs separados em F3 (1 por tela) — preserva commit-discipline (1 PR = 1 intent)

## 8. Anti-padrões

- ❌ Editar `prototipos/<tela>/page.tsx` direto no repo — re-exporta do Cowork
- ❌ Pular F1.5 sem `/design-override` — quebra disciplina
- ❌ `[CL]` mergeia sem `[W]` aprovar screenshot — quebra confiança
- ❌ Tabela markdown vira "screenshot" — F2 exige imagem real
- ❌ Loopar F1 → F1.5 → F1 mais de 3x sem ADR — sinal que protocolo é insuficiente, abrir reflexão
- ❌ Mais de 2 telas simultâneas em F3 (`[CL]` perde foco) — backpressure obrigatório
- ❌ Editar `SYNC_LOG.md` no meio (não-append) — log é imutável, só append

## 9. Ciclo de vida de um arquivo `prototipos/<tela>/`

```
[CC] export zip → unzipa em prototipos/<tela>/
                  ↓
[CL] commit em PR de F1 (label: cowork-export, sem build)
                  ↓
[CD] adiciona critique-score.json em PR de F1.5
                  ↓
[W2] aprova screenshot (PR mergeado em main)
                  ↓
[CL] consome em F3 — translate pra <Tela>.tsx (PR separado)
                  ↓
[CA] adiciona a11y-report.md em PR de F3.5
                  ↓
PR de F3 mergeia → tela ativa em prod
                  ↓
prototipos/<tela>/ permanece read-only como histórico
```

## 10. Links

- [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — mãe
- [ADR 0107](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5 visual
- [ADR 0109](../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md) — Claude Design plugin
- [ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART process
- [ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit V2
- [Skill `mwart-comparative` V4](../.claude/skills/mwart-comparative/SKILL.md) — orquestrador
