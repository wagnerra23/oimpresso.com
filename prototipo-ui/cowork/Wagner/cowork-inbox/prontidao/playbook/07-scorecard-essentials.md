---
sessao: "07"
titulo: Scorecard · Essentials (Metas, Tipos) (abrir com `/onda prontidao --thread 07`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/essentials-metas.yaml · memory/governance/scorecards/screens/essentials-tipos.yaml
nao_toca: resources/js/Pages/Essentials/Metas.tsx · resources/js/Pages/Essentials/Tipos.tsx
---
# 07 · Scorecard · Essentials (Metas, Tipos)

## Telas
- `Essentials/Metas` · `resources/js/Pages/Essentials/Metas.tsx` → `memory/governance/scorecards/screens/essentials-metas.yaml`
- `Essentials/Tipos` · `resources/js/Pages/Essentials/Tipos.tsx` → `memory/governance/scorecards/screens/essentials-tipos.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/essentials-metas.yaml` contém `screen: Essentials/Metas`
- `memory/governance/scorecards/screens/essentials-tipos.yaml` contém `screen: Essentials/Tipos`
- `_saida-07.md` com a tabela de notas
