---
sessao: "13"
titulo: Scorecard · superadmin (4 telas) (abrir com `/onda prontidao --thread 13`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/superadmin-assinaturas-index.yaml · memory/governance/scorecards/screens/superadmin-dashboard-index.yaml · memory/governance/scorecards/screens/superadmin-negocios-index.yaml · memory/governance/scorecards/screens/superadmin-pacotes-index.yaml
nao_toca: Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx · Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx · Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx · Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx
---
# 13 · Scorecard · superadmin (4 telas)

## Telas
- `superadmin/Assinaturas/Index` · `Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx` → `memory/governance/scorecards/screens/superadmin-assinaturas-index.yaml`
- `superadmin/Dashboard/Index` · `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` → `memory/governance/scorecards/screens/superadmin-dashboard-index.yaml`
- `superadmin/Negocios/Index` · `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx` → `memory/governance/scorecards/screens/superadmin-negocios-index.yaml`
- `superadmin/Pacotes/Index` · `Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → `memory/governance/scorecards/screens/superadmin-pacotes-index.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/superadmin-assinaturas-index.yaml` contém `screen: superadmin/Assinaturas/Index`
- `memory/governance/scorecards/screens/superadmin-dashboard-index.yaml` contém `screen: superadmin/Dashboard/Index`
- `memory/governance/scorecards/screens/superadmin-negocios-index.yaml` contém `screen: superadmin/Negocios/Index`
- `memory/governance/scorecards/screens/superadmin-pacotes-index.yaml` contém `screen: superadmin/Pacotes/Index`
- `_saida-13.md` com a tabela de notas
