---
sessao: "03"
titulo: Ninguém lê o segredo
dono: "[CL]"
base: 2fc50fa6dcb8
---
# 03 · CONN-O2b

Tirar `makeVisible('secret')` (`ClientController:41`). Segredo só na resposta do POST de criação, uma vez. ⛔ `Passport::hashClientSecrets()`, migrar a coluna, rotação, validade — proibidos (WR Comercial em campo).

Testes: nenhuma rota do painel devolve `secret` · client pré-existente ainda obtém token em `POST /oauth/token`.

## Prova
No JSON do índice.
