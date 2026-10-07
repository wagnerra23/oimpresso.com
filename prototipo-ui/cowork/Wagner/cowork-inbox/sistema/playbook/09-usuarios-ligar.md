---
sessao: "09"
titulo: Usuários — ligar para todas
dono: "[CL]"
base: ff43e08461d9
---
# 09 · F5 de Usuários

[W] 07/10: ligar para todas as empresas. A chave é **variável de ambiente** (`config/mwart.php:220-222`: `env('MWART_SISTEMA_USUARIOS_INDEX', false)` + lista de `business_ids`). Duas mudanças: (1) PR troca o default para `true`; (2) no servidor, conferir que o `.env` não fixa `false` — se fixar, quem muda é quem tem acesso ao servidor, e o recibo diz isso. A Page já está no `main` (`resources/js/Pages/Usuarios/Index.tsx`, lido @d452b4dc8db8). Recibo: estado da chave antes/depois e screenshot de `/users` em React (aprovação [W2]).
