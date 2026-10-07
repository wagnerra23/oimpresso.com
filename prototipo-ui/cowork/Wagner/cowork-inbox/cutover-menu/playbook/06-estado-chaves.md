---
sessao: "06"
titulo: Ler o estado das chaves MWART em produção
dono: "[CL]"
base: 50e23057f1c2
---
# 06 · O que o cliente vê hoje

As chaves por empresa são **env** (`config/mwart.php`, `MWART_*` + `*_BIZ`), e algumas telas usam `FeatureFlagService` (banco: `useV2SellsCreate`, `FLAG_V2` de Locais/Código de barras/Impressoras/Logs). O repo não diz o valor em produção.

Entregar no recibo uma tabela: chave → ligada? → para quais `business_id` — lida no servidor (`php artisan tinker` / `config('mwart')`, e a tabela de flags), **sem copiar segredo**. Nenhum código muda nesta thread.
