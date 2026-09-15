# Lembretes — casos de uso

| UC | Ação | Resultado esperado |
| --- | --- | --- |
| UC-01 — Criar lembrete semanal | Adicionar → nome + Toda semana + data → Enviar | Evento aparece no mesmo dia da semana em todas as semanas do mês |
| UC-02 — Vencimento do Financeiro | clicar evento âmbar | Detalhe com valor e botão Abrir no módulo — sem opção de excluir aqui |
| UC-03 — Navegar sem mouse | setas do teclado na grade + Enter | Foco anda dia a dia e Enter abre o primeiro lembrete do dia |

## A11y (onda E8)
- Drawer e modal: foco entra no painel, Tab fica preso dentro, esc fecha e o foco volta pro elemento que abriu.
- Avisos em região `aria-live="polite"`; contador da BulkBar anunciado.
- Calendário: setas percorrem os dias, Enter abre o primeiro lembrete, foco visível.
- Nenhuma cor sozinha carrega significado (situação e origem têm rótulo em texto).
