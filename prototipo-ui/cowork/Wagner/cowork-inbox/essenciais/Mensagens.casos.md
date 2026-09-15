# Mensagens — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Publicar aviso da Matriz | escrever → localidade Matriz → Enviar | Mensagem entra no fim do mural com autor e hora |
| UC-02 — Marcar mural como lido | Marcar tudo como lido | Contador da aba zera e as marcas de "nova" saem |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
