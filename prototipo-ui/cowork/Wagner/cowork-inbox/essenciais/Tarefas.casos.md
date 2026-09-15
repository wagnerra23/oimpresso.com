# Tarefas — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Atribuir tarefa a duas pessoas | Adicionar → título + 2 responsáveis + prazo → salvar | Tarefa aparece no topo com ID TAR2026/nnnn e badge da prioridade |
| UC-02 — Concluir 2 tarefas de uma vez | marcar 2 linhas → BulkBar → Concluir | Situação vira Concluída, histórico ganha a entrada, aviso diz "2 tarefas concluídas" |
| UC-03 — Ver por que a tarefa parou | abrir tarefa → Histórico de situação | Linha do tempo com quem mudou, para quê e quando |
| UC-04 — Colaborador sem assign_todos | trocar papel para Colaborador | Filtro "atribuído a" desaparece e a lista mostra só as tarefas dele — com nota explicando |
| UC-05 — Tarefa atrasada | fim anterior a hoje | Trilho vermelho na linha, badge Atrasada e contador no cabeçalho |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
