---
sessao: "A3"
titulo: ALVO do lote 3 do CRM — portal do contato
dono: "[CL]"
base: 93ff8a960b74
---
# A3 · ALVO do lote 3 (portal do contato)

Rotas do protótipo: `crm-portal-painel`, `crm-portal-perfil`, `crm-portal-compras`, `crm-portal-vendas`, `crm-portal-extrato`, `crm-portal-agendamentos`, `crm-portal-pedidos` (`app.jsx`, `crm-portal.jsx` → `window.CrmPortalPage`, `view` = sufixo da rota). Molde da A1/A2: tema escuro, após `__oiLazyDone` + duas leituras iguais de `querySelectorAll('*').length`, `getComputedStyle`, caso de sanidade antes; um `design.json` por tela.

A view `novo` (Nova solicitação de pedido) é medida dentro de `crm-portal-pedidos` (botão "Novo pedido"), não tem rota própria.

**Casca (D6):** o portal abre em casca própria, por cima do shell do ERP (`.cp-stage`). Medir `.cp-frame` em dois tamanhos: tela cheia e "Celular 390" (tira de prévia). A tira `.cp-preview` é controle do protótipo: fica **fora** do alvo e não vai para a Page.
