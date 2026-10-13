---
sessao: "09"
titulo: Usuários — ligar para todas — saída (recibo)
playbook: sistema
thread: "09"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo
base: wagnerra23/oimpresso.com@main d0e6c8b476
---
# _saida-09 · Usuários em React para todas as empresas

A mudança de código entrou no [#9144](https://github.com/wagnerra23/oimpresso.com/pull/9144) (mergeado 2026-10-12):
`config/mwart.php` passou o default de `MWART_SISTEMA_USUARIOS_INDEX` de `false` para `true`, lista de empresas vazia
(vale para todas). Decisão D-USU-LIGAR ([W] 2026-10-07, `_DECISOES-W-2026-10-07.md`). O #9144 deixou o recibo para
depois do deploy; este arquivo é esse recibo. Nenhum código muda aqui.

## Teste na lane
`UsuariosIndexInertiaTest` › **`UC-USUA-01 cutover F5 — sem env, a chave vem LIGADA para todas as e…`**, lane
`PHP / Pest (Acessos · MySQL)` (`acessos-pest.yml`), conferido pelo nome no log (✓), não pelo verde do job:
- run [38254924535](https://github.com/wagnerra23/oimpresso.com/actions/runs/38254924535) — push do próprio #9144 (`b547a39227`), ✓ 0,51s.
- run [38384486541](https://github.com/wagnerra23/oimpresso.com/actions/runs/38384486541) — push de `d0e6c8b476` (main de hoje), ✓ 0,29s.

## Chave no servidor (Hostinger, só leitura, 2026-10-13)
| | antes do #9144 | depois (medido hoje) |
|---|---|---|
| linhas `MWART_SISTEMA_USUARIOS_INDEX*` no `.env` | — (não medido na época) | **0** — o `.env` não fixa a chave |
| `config('mwart.sistema_usuarios_index')` | default `false` → Blade | **`{"enabled":true,"business_ids":[]}`** |
| commit servido | — | `d0e6c8b476` (contém `b547a39227`, o #9144) |

Como o `.env` não tem a linha, o valor de antes era o default do arquivo, `false`. Ninguém precisa mexer no servidor.

## Tela
`GET https://oimpresso.com/users` na empresa WR2 Sistemas (não a empresa 4) devolve `component: "Usuarios/Index"` no
`data-page` e renderiza "Usuários · 8 usuários · 8 ativos · 2 funções". Screenshot tirado pela sessão para a aprovação [W2];
não está no git.

## Prova do índice
`contem` `env('MWART_SISTEMA_USUARIOS_INDEX', true)` em `config/mwart.php` — já passava desde o #9144; com este recibo a
thread sai de `(sem recibo)`.
