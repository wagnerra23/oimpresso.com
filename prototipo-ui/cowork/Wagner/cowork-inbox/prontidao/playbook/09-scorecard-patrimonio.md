---
sessao: "09"
titulo: Scorecard · Patrimonio (5 telas) (abrir com `/onda prontidao --thread 09`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: memory/governance/scorecards/screens/patrimonio-index.yaml · memory/governance/scorecards/screens/patrimonio-bens.yaml · memory/governance/scorecards/screens/patrimonio-alocacoes.yaml · memory/governance/scorecards/screens/patrimonio-manutencoes.yaml · memory/governance/scorecards/screens/patrimonio-configuracoes.yaml
nao_toca: resources/js/Pages/Patrimonio/Index.tsx · resources/js/Pages/Patrimonio/Bens.tsx · resources/js/Pages/Patrimonio/Alocacoes.tsx · resources/js/Pages/Patrimonio/Manutencoes.tsx · resources/js/Pages/Patrimonio/Configuracoes.tsx
---
# 09 · Scorecard · Patrimonio (5 telas)

## Telas
- `Patrimonio/Index` · `resources/js/Pages/Patrimonio/Index.tsx` → `memory/governance/scorecards/screens/patrimonio-index.yaml`
- `Patrimonio/Bens` · `resources/js/Pages/Patrimonio/Bens.tsx` → `memory/governance/scorecards/screens/patrimonio-bens.yaml`
- `Patrimonio/Alocacoes` · `resources/js/Pages/Patrimonio/Alocacoes.tsx` → `memory/governance/scorecards/screens/patrimonio-alocacoes.yaml`
- `Patrimonio/Manutencoes` · `resources/js/Pages/Patrimonio/Manutencoes.tsx` → `memory/governance/scorecards/screens/patrimonio-manutencoes.yaml`
- `Patrimonio/Configuracoes` · `resources/js/Pages/Patrimonio/Configuracoes.tsx` → `memory/governance/scorecards/screens/patrimonio-configuracoes.yaml`

## O que fazer
Rodar o agente `.claude/agents/screen-qa-specialist.md` **só nos passos 0 (Pré-Flight) e 1 (Nota)** para cada tela. O YAML sai no formato dos vizinhos (ex. `memory/governance/scorecards/screens/essentials-todo-index.yaml`): `screen`, `path`, `archetype`, `persona`, `nota`, `baseline_anterior`, `dimensoes` (16), `gaps` com `best_of_class` + `fix`.

- O nome do arquivo é exatamente o slug acima. O readiness casa por nome, e a thread 01 existe porque um nome escapou.
- `baseline_anterior` = a própria nota (primeira medição).
- E2E, axe e smoke (passos 2 a 4 do agente) ficam **fora** desta thread, porque estourariam o limite de 300 linhas. Os gaps entram no YAML, não viram task sem [W].

## PARAR SE
- Faltar charter para alguma tela (o agente manda parar). Não é o caso em árvore dd380c33a374 (lida 2026-09-23 11:20 UTC), mas conferir.
- A tela não abrir em prod (rota 404/500): registrar no `_saida`, não dar nota de olho no código.

## Prova
- `memory/governance/scorecards/screens/patrimonio-index.yaml` contém `screen: Patrimonio/Index`
- `memory/governance/scorecards/screens/patrimonio-bens.yaml` contém `screen: Patrimonio/Bens`
- `memory/governance/scorecards/screens/patrimonio-alocacoes.yaml` contém `screen: Patrimonio/Alocacoes`
- `memory/governance/scorecards/screens/patrimonio-manutencoes.yaml` contém `screen: Patrimonio/Manutencoes`
- `memory/governance/scorecards/screens/patrimonio-configuracoes.yaml` contém `screen: Patrimonio/Configuracoes`
- `_saida-09.md` com a tabela de notas
