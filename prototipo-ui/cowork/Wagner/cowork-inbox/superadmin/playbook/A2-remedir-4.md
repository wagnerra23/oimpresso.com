---
sessao: "A2"
titulo: Remedir as 4
dono: "[CL]"
base: aacb74f4df18
---
# A2 · Remedir as 4 do Superadmin

Saiu da A1 (`_saida-A1` §"Remedir as 4 medidas — NÃO MEDI"). Duas causas medidas pelo Code:
1. O lote resolve as 4 telas pra rota `superadmin` (`view="visao"`). O protótipo agora tem `sa-negocios`, `sa-pacotes`, `sa-assinaturas` (`_saida-00` do Cowork) — usar uma rota por tela.
2. `design-diff-lote.mjs` grava `design.json`, `prod.json` e `resultado.json` juntos, sem flag de lado único; o lado prod precisa do `VISREG_LOGIN_TOKEN` do staging. Ver a thread de ferramenta no roteiro `placar` (A-LOTE).

Se o token não estiver disponível: **pare** com NÃO MEDI.
