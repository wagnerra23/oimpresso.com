---
sessao: "12"
titulo: Scorecard · Officeimpresso (Logs) (abrir com `/onda prontidao --thread 12`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/officeimpresso-logs-index.yaml · memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml
nao_toca: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx · Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx
---
# 12 · Scorecard · Officeimpresso (Logs)

## Telas
- `Officeimpresso/Logs/Index` · `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx` → `memory/governance/scorecards/screens/officeimpresso-logs-index.yaml`
- `Officeimpresso/Logs/Timeline` · `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx` → `memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/officeimpresso-logs-index.yaml` contém `screen: Officeimpresso/Logs/Index`
- `memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml` contém `screen: Officeimpresso/Logs/Timeline`
- `_saida-12.md` com a tabela de notas
