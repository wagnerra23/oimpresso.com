---
sessao: "11"
titulo: Scorecard · Forja (Aprovacoes, Trabalho, Cockpit) (abrir com `/onda prontidao --thread 11`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/forja-aprovacoes-index.yaml · memory/governance/scorecards/screens/forja-trabalho-index.yaml · memory/governance/scorecards/screens/team-mcp-forja-cockpit.yaml
nao_toca: Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx · Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx · Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx
---
# 11 · Scorecard · Forja (Aprovacoes, Trabalho, Cockpit)

## Telas
- `Forja/Aprovacoes/Index` · `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx` → `memory/governance/scorecards/screens/forja-aprovacoes-index.yaml`
- `Forja/Trabalho/Index` · `Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx` → `memory/governance/scorecards/screens/forja-trabalho-index.yaml`
- `team-mcp/Forja/Cockpit` · `Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx` → `memory/governance/scorecards/screens/team-mcp-forja-cockpit.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/forja-aprovacoes-index.yaml` contém `screen: Forja/Aprovacoes/Index`
- `memory/governance/scorecards/screens/forja-trabalho-index.yaml` contém `screen: Forja/Trabalho/Index`
- `memory/governance/scorecards/screens/team-mcp-forja-cockpit.yaml` contém `screen: team-mcp/Forja/Cockpit`
- `_saida-11.md` com a tabela de notas
