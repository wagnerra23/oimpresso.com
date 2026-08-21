# Configuracoes — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Trocar prefixo da tarefa | editar prefixo → Salvar | Aviso de salvo e a próxima tarefa nasce com o novo prefixo |
| UC-02 — Gestor tenta abrir | trocar papel para Gestor | Bloqueio explicando que precisa de edit_essentials_settings |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
