# Pedido para o Code — grupo Usuários (acessos, comissionamento, preferências)

> [CC] 2026-08-19 · destino [CL] (F3) e [W] (aprovação). **Nada aqui está commitado** — as tools de
> GitHub deste projeto são read-only. Ponte: cole o pedido ou abra Issue com o form `cowork-intake`.

- **`PEDIDO-PARA-CODE.md`** — os PRs na ordem, com as decisões que travam cada um. Comece por aqui.
- **`repo/`** — espelho da árvore do `main`: cada arquivo já está no caminho de destino. Copie por cima.
  - `resources/js/Pages/Roles/Index.charter.md` · `Index.casos.md` — trio da tela de funções.
  - `resources/js/Pages/CommissionAgents/Index.casos.md` — trio dos comissionados.
  - `tests/Feature/Roles/RoleControllerTest.php` — 11 casos, cobre os bugs D1 e D2.
  - `tests/Feature/Users/SalesCommissionAgentTest.php` — 7 casos, cobre o hard delete (D5).
  - `tests/Feature/Architecture/TimezoneGuardTest.php` — as guardas G1–G3 do fuso.
  - `prototipo-ui/contrato/funcoes.contract.json` · `comissoes.contract.json` — ADR 0286.
- **Build F1** (protótipo visual, não vai pro `main`): `prototipo-ui/cowork/acessos/`.
- **Trio narrado, com achados e decisões:** `cowork-inbox/ACESSOS-F1-2026-08-19.md`.

⚠️ Os testes foram **escritos, não executados** aqui — o veredito é da lane. Onde o contrato de auth ou
o nome de rota da tela nova ainda não existe, o caso está marcado `skip` com o motivo no próprio teste.
