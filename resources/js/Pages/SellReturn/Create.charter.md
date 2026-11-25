---
page: /sell-return/create
component: resources/js/Pages/SellReturn/Create.tsx
owner: wagner
status: draft
last_validated: "2026-11-24"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/devolucao-v3.html
runbook: memory/requisitos/Sells/RUNBOOK-sell-return-create.md
alcance:
  rota: /sell-return/create
  rota_nome: n/a (Route::resource do núcleo em routes/web.php — o nome sell-return.create é implícito, sem ->name literal)
  permission: n/a (permissão do núcleo access_sell_return / access_own_sell_return, checada no corpo de SellReturnController@create)
  menu_hook: n/a (sem item de menu próprio; abre pelo botão "+ Nova devolução" de SellReturn/Index)
tier: B
charter_version: 1
related_us: [US-SELL-066]
---

# Charter — Nova devolução · origem (`/sell-return/create`)

> Etapa 1 do protótipo Devolução V3 ("Origem da devolução", modo venda). O registro da
> devolução segue em `SellReturn/Add` ([Add.charter.md](Add.charter.md)).

**Permissão:** `access_sell_return · access_own_sell_return` · **Casos:** [Create.casos.md](Create.casos.md)

## Mission

Escolher a venda finalizada que está voltando, sem sair da área de devoluções.

## Goals — Features (faz)

- G1 Lista as vendas finalizadas da empresa, nos locais permitidos ao usuário, a mais recente primeiro.
- G2 Filtra por nota: Todas · Com NF-e · Sem nota.
- G3 Busca por cliente, número da venda ou número da nota.
- G4 "Usar esta venda" abre o registro `/sell-return/add/{venda}` por visita Inertia; venda que já
  tem devolução mostra "Abrir devolução".
- G5 Um clique na linha escolhe a venda e soma o valor dela no totalizador "Fechamento fiscal"; dá
  para escolher várias vendas do MESMO cliente (pedido [W] 2026-11-24). Cada venda vira uma
  devolução própria: o registro abre uma de cada vez, pela `?fila=`. O total mostrado é a soma das
  vendas (o teto); o valor final sai dos itens no registro.
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- ❌ Não grava devolução, não calcula valor e não mexe em estoque — isso é do registro (`store()`).
- ❌ Não mostra consignação nem "Fazer acerto": consignação não existe no repo.
- ❌ Não oferece garantia, conserto ou comodato ("Não é devolução?"): não há fluxo de destino.

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- RUNBOOK: [RUNBOOK-sell-return-create.md](../../../../memory/requisitos/Sells/RUNBOOK-sell-return-create.md)
- ADR 0093 (multi-tenant)
