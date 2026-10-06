---
sessao: "A-LOTE"
titulo: Lote mede por tela
dono: "[CL]"
base: aacb74f4df18
---
# A-LOTE · Defeito na origem

Três módulos mediram várias telas com o **mesmo** `design.json`: Repair (6), Officeimpresso Logs (2), Superadmin (4). Causa medida pelo Code em `superadmin/_saida-A1`: o lote resolve a tela pela rota do **módulo**, não da tela, e grava os dois lados juntos (sem flag de lado único).

1. Mapa tela → rota do protótipo vindo do próprio protótipo (`ROTAS` de cada `*-page.jsx` ou um `data-page` na vista), nunca a rota do grupo.
2. `--lado design|prod|ambos` (default ambos).
3. **Sanidade:** duas telas diferentes do mesmo módulo têm de dar `design.json` com hash diferente; se der igual, sai exit 2 NÃO MEDI.
