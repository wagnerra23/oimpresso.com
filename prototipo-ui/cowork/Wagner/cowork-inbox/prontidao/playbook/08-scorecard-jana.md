---
sessao: "08"
titulo: Scorecard · Jana (Acoes, Alertas, Plataforma) (abrir com `/onda prontidao --thread 08`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/jana-acoes.yaml · memory/governance/scorecards/screens/jana-alertas.yaml · memory/governance/scorecards/screens/jana-plataforma.yaml
nao_toca: resources/js/Pages/Jana/Acoes.tsx · resources/js/Pages/Jana/Alertas.tsx · resources/js/Pages/Jana/Plataforma.tsx
---
# 08 · Scorecard · Jana (Acoes, Alertas, Plataforma)

## Telas
- `Jana/Acoes` · `resources/js/Pages/Jana/Acoes.tsx` → `memory/governance/scorecards/screens/jana-acoes.yaml`
- `Jana/Alertas` · `resources/js/Pages/Jana/Alertas.tsx` → `memory/governance/scorecards/screens/jana-alertas.yaml`
- `Jana/Plataforma` · `resources/js/Pages/Jana/Plataforma.tsx` → `memory/governance/scorecards/screens/jana-plataforma.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/jana-acoes.yaml` contém `screen: Jana/Acoes`
- `memory/governance/scorecards/screens/jana-alertas.yaml` contém `screen: Jana/Alertas`
- `memory/governance/scorecards/screens/jana-plataforma.yaml` contém `screen: Jana/Plataforma`
- `_saida-08.md` com a tabela de notas
