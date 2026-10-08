---
sessao: "10"
titulo: Clientes do Officeimpresso em React
dono: "[CL]"
base: 348b1498bebe
---
# 10 · Clientes do Officeimpresso em React

Hoje: `ClientController@index` → `officeimpresso::clients.index` (Blade). Forma: rota `oi-clientes` (`app.jsx:901`). Tier 0: a tela mostra credencial — conferir escopo por empresa antes de qualquer coisa. Processo MWART (ADR 0104): trio (.tsx + charter + casos) + teste de contrato tenant 98×99; Blade fica como fallback (`?classico=1`) até o cutover. **Não reli o controller neste turno além da assinatura do método** — linhas exatas saem da sua leitura.
