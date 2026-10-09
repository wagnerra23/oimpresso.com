---
sessao: "_saida-15"
thread: "15 · Tier 0 · Equipe: conferir negócio em token/DXT/cota + apagar rota legacy (D14)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo-retroativo
entregue_em: "#9060"
base_lida: wagnerra23/oimpresso.com@main b7715dda
---
# _saida-15 (recibo retroativo)

Recibo escrito depois do merge. O PR que entregou a thread não escreveu o `_saida`, e não havia
`_saida-15.md` apagado no histórico para restaurar.

## Entregue
O [#9060](https://github.com/wagnerra23/oimpresso.com/pull/9060) foi mergeado em 2026-10-08 18:43 UTC
(`1e782dfb33`). Ele cumpre a decisão [W] D14.

- `TeamController::gerarToken`, `gerarDxt` e `atualizarQuota` buscam o usuário pelo id da URL **e**
  pelo negócio da sessão. Com id de outro negócio, a resposta é 404.
- A rota legacy `DELETE /team-mcp/team/token/{token}` saiu do `Modules/Forja/Http/routes.php`. Ela
  revogava pelo id do token sem conferir dono nem negócio. Fica só a revogação escopada.
- UC-EQP-07 e UC-EQP-08 entraram no `team-mcp/Team/Index.casos.md`.

## Provas
- `Modules/Forja/Tests/Feature/TeamTenantTest.php` (tenant 98 × 99) nasceu no próprio #9060 —
  o arquivo não existia no `main` antes. O índice ganhou a thread 15 no #9017 (2026-10-07), antes
  do PR. A prova mede a entrega, não um arquivo antigo.
- O teste está na allowlist do `.github/workflows/forja-pest.yml`.
- `node scripts/qa/placar.mjs --indice … --thread 15` passa a dizer `feito` com este recibo.

## Fora do escopo
Nada novo. Este PR só acrescenta o recibo.
