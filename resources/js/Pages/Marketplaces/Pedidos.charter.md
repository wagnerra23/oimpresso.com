---
page: /marketplaces/pedidos
component: resources/js/Pages/Marketplaces/Pedidos.tsx
owner: wagner
status: draft
parent_module: Marketplaces
related_prototype: n/a (módulo novo sem protótipo; gerado do PT-01 pelo designer-agente (ADR 0282/0438))
alcance:
  rota: /marketplaces/pedidos
  rota_nome: marketplaces.pedidos        # name() da rota — é o que o guard procura
  permission: marketplaces.access      # declarada em DataController::user_permissions
  menu_hook: Modules/Marketplaces/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: marketplaces_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Marketplaces/Pedidos (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> Preencha os TODO antes de subir de `draft` → `live` (exige screenshot aprovado por Wagner).

## Mission

Mostrar à empresa os pedidos que chegaram do Mercado Livre e se já viraram venda, e deixar
escolher em que local de venda cada conta conectada joga as vendas (US-MKT-008 · ADR 0439).

## Goals — Features (faz)

- Lista os pedidos do marketplace da empresa: número, marketplace, data, situação no marketplace, total e a venda gerada (link)
- Filtra por número do pedido e por "com venda / ainda sem venda"
- Mostra as contas conectadas e o local de venda de cada uma; quem tem `marketplaces.account.connect` escolhe o local ali mesmo (decisão [W] 2026-10-26)
- Botão "Conectar Mercado Livre" para quem pode conectar contas
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- A definir por [W] (o `charter-write` não infere Non-Goals). Fora do escopo desta entrega, por ser de outras US: emitir NF-e (US-MKT-009), marcar enviado em lote e imprimir etiqueta (US-MKT-019), disputas (US-MKT-014).

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- Blocos deferidos com Skeleton; a lista mostra até 200 pedidos, do mais novo para o mais antigo

## Casos

- [Pedidos.casos.md](Pedidos.casos.md)

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
