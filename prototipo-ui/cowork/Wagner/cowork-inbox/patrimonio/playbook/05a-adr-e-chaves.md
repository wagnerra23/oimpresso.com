---
sessao: "05a"
titulo: ADR sucessora + chaves do retention.php
dono: "[CL]"
base: e1dea70d4710
---
# 05a · Reabrir a retenção LGPD direito

[W] 2026-10-07, no Cowork: o descarte da 05 (lápide §5 de 27/07, *"num ERP não se apaga PII"*) **está errado**. A 05 não apaga — **anonimiza** campo pessoal, preserva a linha e nunca toca a trilha de auditoria (ver B da `05-retencao-lgpd.md`). A lápide misturou as duas coisas.

1. **ADR sucessora** (`supersedes` a decisão de 27/07): retenção = anonimização de campo pessoal; `activity_log` e `asset_transactions` nunca perdem linha; nasce `enabled=false`; ligar é D-CANARY-LGPD. Corpo do PR cita esta ficha. Merge = [W] (R10).
2. **`retention.php`**: o `_saida-04.md` mediu **4 de 4 chaves apontando para tabelas que não existem**. Corrigir para as tabelas reais do módulo (`am_*`, conferir no schema) — sem inventar coluna (C7). Se alguma janela não tiver campo pessoal correspondente, declare e tire a janela.
3. **Não** escrever o comando aqui — é a 05.

Sem prova de arquivo (nome da ADR é do Code): o recibo cita o número da ADR e o diff das chaves.
