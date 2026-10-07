---
sessao: "_saida-04"
thread: "04 · Scorecards: nota das 4 team-mcp sem nota + 7 órfãos de tela que não existe"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
entregue_em: "PR desta thread (claude/forja-04-scorecards)"
base_lida: wagnerra23/oimpresso.com@main 5546e52656ac
---
# _saida-04

## Entregue

**Parte A — 4 notas novas** em `memory/governance/scorecards/screens/` (slug pela regra `screenSlug`
do `scripts/qa/screen-coverage-map.mjs`):

| tela | arquivo | nota | nível |
|---|---|---|---|
| `team-mcp/Scorecard/Index` | `team-mcp-scorecard-index.yaml` | **76** | Advanced |
| `team-mcp/CcSessions/Index` | `team-mcp-ccsessions-index.yaml` | **75** | Advanced |
| `team-mcp/Tasks/Index` | `team-mcp-tasks-index.yaml` | **75** | Advanced |
| `team-mcp/Team/Index` | `team-mcp-team-index.yaml` | **72** | Advanced |

Como a nota foi gerada: não existe comando que calcule nota de tela (o `screen:grade` da skill é
"automação futura") e o seed `screen-grade-seed.mjs` só materializa o baseline de 2026-05-30, que
não tem nenhuma das 4 telas. Segui o método da skill `screen-grade` no mesmo modo do
`team-mcp-forja-cockpit.yaml` (#7758): **leitura de código**, 16 dimensões, persona wagner com peso
uniforme, nota = média arredondada, cada dimensão cita `arquivo:linha`. **Nada medido em runtime**
(sem browser de prod, sem E2E/axe/smoke) — o cabeçalho de cada YAML diz isso.

Achados que viraram gap e merecem dono fora desta thread:
- **Tasks** — o PATCH de status manda `author: 'wagner'` fixo (`Tasks/Index.tsx:223`) e o
  `TasksAdminController.php:168` grava o que vier no body: todo movimento do time aparece como do
  Wagner no `mcp_task_events`.
- **Team** — os 4 `fetch` leem `r.json()` sem checar `r.ok`; 403/419/500 aparecem como "Erro de rede".
- **CcSessions** — `from`/`to` contam como filtro ativo mas o "Limpar" não os zera.
- **Scorecard** — `checks = []` mostra "0 de 0 checks falhando"; falha do defer fica em "Carregando…".

**Parte B — 7 órfãos apagados** (`git rm`): `forja-{activity,backlog,board,burndown,inbox,mywork,triage}-index.yaml`.
Cada um declarava `path: Modules/Forja/Resources/js/Pages/Forja/<X>/Index.tsx`; nenhum dos 7 `.tsx`
existe em `origin/main` (`git ls-files -- '*Pages/Forja/*'` só lista Aprovacoes, Roadmap e Trabalho).

O `screen-grades-ratchet` lê esses arquivos, mas **não como piso que impede a remoção**: deleção de
scorecard cujo `.tsx` morreu é classificada como legítima por `scripts/lib/delecao-legitima.mjs`
(só acusa quando o `.tsx` continua vivo). Nenhum outro consumidor cita os 7 por nome
(`git grep` fora de `scorecards/screens/` só acha o `00-INDICE.md`). Resíduo declarado: re-rodar
`screen-grade-seed.mjs` recriaria os 7 a partir do baseline de 2026-05-30 (que os lista) — o seed é
materializador de uma vez só, não roda em CI.

## Provas medidas (árvore do PR sobre 5546e52656ac)

```
$ node scripts/qa/screen-grades-ratchet.mjs
Catraca screen-grade · 211 telas · ✅ 207 ok/subiu · ✨ 4 novas · 🔻 0 regrediram · 🗑 7 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.            (exit 0)

$ node scripts/qa/screen-coverage-map.mjs --check
  scorecard   206 →  210  ↑
✓ CATRACA: nenhuma tela viva perdeu cobertura vs origin/main.   (exit 0)
```

Provas do JSON do `00-INDICE.md`: os 4 `team-mcp-*-index.yaml` existem; `forja-triage-index.yaml`
ausente.
