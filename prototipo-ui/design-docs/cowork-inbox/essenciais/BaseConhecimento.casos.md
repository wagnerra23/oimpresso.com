# BaseConhecimento — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Achar a regra de arquivo | buscar "PDF" | Árvore filtra e o artigo abre com a lista do que aceitar |
| UC-02 — Descer até o artigo | categoria → seção → artigo | Trilha de 3 níveis com o conteúdo rico do main |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
