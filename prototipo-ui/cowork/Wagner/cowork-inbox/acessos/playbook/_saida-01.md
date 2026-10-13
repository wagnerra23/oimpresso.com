---
sessao: "01"
titulo: "/roles em Inertia — entregue pela thread sistema/02"
autor: "[CL]"
data: 2026-10-13
thread: 01-roles.md
veredito: "entregue — mesma tela da sistema/playbook/02; recibos em ../../sistema/playbook/_saida-02.md e _saida-02b.md"
---

# _saida-01 · /roles em Inertia

Esta thread e a `sistema/playbook/02` cobrem a mesma tela, `/roles` (`RoleController`), e a mesma entrega fecha
as duas. Não há trabalho novo aqui; este recibo só fecha a duplicata.

- Page `resources/js/Pages/Funcoes/Index.tsx` com charter e casos ao lado, atrás da flag `useV2SistemaFuncoes`.
  `RoleController::index` responde `Inertia::render('Funcoes/Index')`.
- A lista (F3-1) está no `../../sistema/playbook/_saida-02.md`. O drawer de cadastrar e editar (F3-2, #9170) está no
  `../../sistema/playbook/_saida-02b.md`.
- O índice deixava `ROLES_PAGE` como `null` porque o caminho era decisão do Code. Fixado em
  `resources/js/Pages/Funcoes/Index.tsx`, o arquivo que existe no `main`.
