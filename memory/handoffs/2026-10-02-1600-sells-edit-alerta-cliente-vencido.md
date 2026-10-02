---
date: "2026-10-02"
time: "16:00 BRT"
slug: sells-edit-alerta-cliente-vencido
tldr: "Alerta 'Cliente vencido' do Sells/Edit React mostrava 100x a dívida do Blade (vírgula decimal perdida ao re-parsear texto pt-BR). Fix #8546 mergeado, deploy 18:48 UTC, smoke prod na venda 81965 igual ao Blade."
decided_by: [W]
prs: [8546, 8486]
next_steps:
  - "UC-SEDIT-10 sobe a ✅ quando o manifesto G-7 for regravado de um run verde de main"
  - "Caso com milhar (1.234,56) só coberto no teste — não houve venda com dívida assim à mão no smoke"
---

# Sells/Edit — alerta "Cliente vencido" no valor certo

## O que foi feito
- **#8546** (mergeado 17:41 UTC, squash `93a0d2b2`): `SellController@edit` guarda o número cru de
  `Util::getContactDue` antes da formatação pt-BR e manda em `customer.dues_total`. Antes o
  `preg_replace('/[^\d.]/', …)` sobre o texto formatado descartava a vírgula decimal.
  UC-SEDIT-10 em `resources/js/Pages/Sells/Edit.casos.md` + dataset de 3 casos (comum, milhar,
  zero) em `tests/Feature/Sells/SellsEditContratoTest.php`. Merge de main trouxe o UC-SEDIT-11
  (outra sessão) — conflito resolvido mantendo os dois.
- **#8486** (Pedido de venda em React, outra sessão): mergeado a pedido de [W] às 18:46 UTC,
  aprovação do Felipe no head `1646d7ce05`. Sem smoke meu — fica com a sessão dona.

## Smoke prod (deploy run 37042428259, sucesso 18:48 UTC)
Venda 81965 (OS00134, biz=1): Blade, payload React (`dues_total`) e tela React mostram o
**mesmo** valor no alerta. Valor fora do git (Tier 0). Nenhum save feito.

## Caveats
- A mensagem do squash do #8546 ficou com 2 linhas citando os valores de **exemplo do teste**
  (não de cliente) — [W] decidiu deixar como está.
- O corpo do PR chegou a ter o valor real da venda; foi reescrito, mas o histórico de edição
  do GitHub guarda a versão antiga (só apaga pela UI).
- Code owner (`SellController.php`, `Pages/Sells`) exige aprovação do Felipe; PR aberto pela conta
  do [W] não se auto-aprova.

## Estado MCP no momento do fechamento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas.
