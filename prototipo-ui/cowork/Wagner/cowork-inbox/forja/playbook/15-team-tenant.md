---
sessao: "15"
titulo: Tier 0 — Equipe
dono: "[CL]"
base: 50e23057f1c2
---
# 15 · Tier 0 — Equipe

[W] 07/10: `gerarToken`, `gerarDxt`, `atualizarQuota` e o apagar token conferem que o `userId`/`tokenId` da URL é do negócio da sessão (404 se não). A rota legacy `DELETE /team/token/{token}` sai (contradiz o Anti-hook do charter). Teste primeiro, vermelho no `main`.
