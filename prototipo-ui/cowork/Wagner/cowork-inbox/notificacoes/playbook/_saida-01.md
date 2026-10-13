---
sessao: "01"
titulo: "Modelos de notificação em Inertia — entregue pelos PRs da thread sistema/06"
autor: "[CL]"
data: 2026-10-13
thread: 01-modelos.md
prs: [9113, 9115, 9159]
veredito: "entregue — mesma tela da sistema/playbook/06; recibo completo em ../../sistema/playbook/_saida-06.md"
---

# _saida-01 · Modelos de notificação

Esta thread e a `sistema/playbook/06` cobrem a mesma tela, `/notification-templates`, e a mesma entrega
fecha as duas. Não há trabalho novo aqui; este recibo só fecha a duplicata.

- `NotificationTemplateController` com a tela React atrás da flag `useV2NotificationTemplates`, **desligada**:
  backend dual no [#9113](https://github.com/wagnerra23/oimpresso.com/pull/9113), seleção no
  [#9115](https://github.com/wagnerra23/oimpresso.com/pull/9115) e editor no
  [#9159](https://github.com/wagnerra23/oimpresso.com/pull/9159).
- Page: `resources/js/Pages/NotificationTemplate/Index.tsx`, com charter e casos ao lado.
- Recibo completo: `../../sistema/playbook/_saida-06.md`.

## Variável do índice (pedido ao Cowork)
A ficha deixa `PAGE` como `null` "até o Code fixar". O valor certo é
`PAGE = resources/js/Pages/NotificationTemplate/Index.tsx`, o arquivo que existe no `main`. Testei: com ele, a prova
passa e o placar dá a thread como feita. O índice não foi editado aqui porque o espelho é build-only. Fixar a
variável no `00-INDICE.md` vivo é com o Cowork.

## Fica para o [W]
F4 (smoke em biz=1 com a flag ligada) e F5 (cutover). Ainda pendentes da US-NOTIF-004: modo Visual, prévia
sanitizada e restaurar padrão.
