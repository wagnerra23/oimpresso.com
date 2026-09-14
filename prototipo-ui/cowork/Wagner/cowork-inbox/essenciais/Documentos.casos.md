# Documentos — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Subir tabela de preço | Adicionar → escolher arquivo → descrição → Enviar | Item entra no topo com tipo, tamanho e autor |
| UC-02 — Recusar arquivo inválido | escolher .exe | Erro dizendo os tipos aceitos, sem enviar |
| UC-03 — Compartilhar por função | Compartilhar → Por função → Comercial → Salvar | Quem entra na função passa a ver sem refazer a lista |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
