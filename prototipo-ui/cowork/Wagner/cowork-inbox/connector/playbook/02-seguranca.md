---
sessao: "02"
titulo: Segurança da ação
dono: "[CL]"
base: 2fc50fa6dcb8
---
# 02 · CONN-O2

`destroy`: revoga `oauth_access_tokens` do client + apaga a linha em transação; devolve a contagem. `install/uninstall/update` saem de GET. Auditoria de criar/excluir com `client_id`, sem segredo.

## Prova
No JSON do índice + UC-CONN-12 verde.
