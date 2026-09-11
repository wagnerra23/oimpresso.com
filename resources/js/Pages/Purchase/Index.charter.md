---
id: resources-js-pages-purchase-index-charter
page: /purchases
component: resources/js/Pages/Purchase/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/compras-page.jsx (ComprasPage)
related_visual_comparison: memory/requisitos/Compras/_telas/index-visual-comparison.md
related_us: [US-MWART-008]
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Purchase
related_adrs: [114, 101, 93, 104, 141, 110]
tier: B
charter_version: 1
---

# Page Charter — /purchases (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `app/Http/Controllers/PurchaseController@indexInertia` (dual-path MWART do `index`; rota `GET /purchases`, `Route::resource('purchases')`, permissão `purchase.view`/`view_own_purchase`). Lista densa de compras do tenant migrada de Blade legacy pra Inertia/React.
>
> **Âncora de design promovida em 2026-09-09** (era `n/a (herda PT-01 Lista)`): `prototipo-ui/cowork/Wagner/compras-page.jsx` — a aba **Pedidos** do `ComprasPage` (`:326`; a tabela começa em `:426`). O piloto continua herdando o **PT-01 Lista**; o que muda é que agora existe também uma fonte visual, e ela não contradiz o Padrão.
>
> **Por que este protótipo é fonte, e não porte reverso** (o critério de [§5 2026-08-28](../../../../memory/proibicoes.md)): o cabeçalho do hub (`compras-page.jsx:2`) declara *“Migrado de Compras.html”* — ele nasce de um HTML de design, não do código vivo. E a direção Design→Code é declarada pelo **próprio código**: `Index.tsx:9` diz *“Origem: protótipo Cowork «Compras»”*, apontando `prototipo-ui/cowork/Wagner/legado/compras/visual-source.html` — que **não existe mais** (medido em 2026-09-09: o diretório sumiu do repo; de `prototipos/compras*` sobrou só `compras-grade-matrix/`). Esta promoção troca um ponteiro podre por um vivo.
>
> ⚠️ **Mesma fonte, duas telas vivas — não é duplicação.** O hub também ancora o cockpit `/compras` ([`Compras/Index.charter.md`](../Compras/Index.charter.md), que já o declara). São telas distintas por desenho: o cockpit delega o CRUD pro trilho A `Pages/Purchase/*` (convergência C1 · ADR 0141), e é este charter que descreve o trilho A. O protótipo cobre as duas porque tem as abas Painel/Pedidos/Fornecedores **e** o drawer.

---

## Mission
Dar ao comprador (Wagner/Maiara) uma visão densa e rápida de todas as compras do negócio: quando, de quem, por quanto, situação do recebimento e do pagamento — com filtros por filial, fornecedor, status, situação de pagamento e período, e ações inline (ver, imprimir, etiquetas, editar, excluir) sem sair da lista. É o ponto de entrada do módulo de Compras.

---

## Goals — Features (faz)
- Lista até 200 compras mais recentes do tenant (`getListPurchases`), ordenadas por data desc.
- Filtros server-side via partial reload D-14 (`only: ['rows','filters']`): filial, fornecedor, status da compra, situação de pagamento (inclui `overdue` calculado por pay term), intervalo de datas.
- Busca client-side incremental por ref. nº / fornecedor / nome fantasia (não faz round-trip).
- Pills de status (Recebido/Pendente/Solicitado) e pagamento (Pago/Em aberto/Parcial/Vencido).
- Colunas de valor: total e a pagar (`payment_due` = final_total − amount_paid), com destaque quando há saldo.
- Marca visual de devolução (`return_exists`) na ref.
- Ações por linha gated por permissão: ver (`purchase.view`), imprimir (`/purchases/print/{id}`), etiquetas (`/labels/show`), editar (`purchase.update`), excluir (`purchase.delete`).
- Botão "Nova compra" gated por `purchase.create`; empty-state com CTA para primeira compra.
- Respeita `permitted_locations` do usuário (recorte por filial autorizada).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não pagina server-side além do teto rígido de 200 linhas — lista é um snapshot recente, não um relatório completo (inferência pendente de Wagner).
- ❌ Não registra pagamento nem muda status direto da lista (isso é no Edit/ações dedicadas).
- ❌ Não exporta CSV/PDF nem gera relatório agregado (é lista operacional, não dashboard).
- ❌ Não cruza dados entre tenants — todas as queries são escopadas por `business_id` (Tier 0, ADR 0093) e por `permitted_locations`; não expõe compras de outro negócio.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader ; filtros sticky.

---

## Automation hooks (faz)
- Partial reload D-14: closures `business_locations`/`suppliers`/`order_statuses` são por-business e não executam query no reload de filtro (só `rows`/`filters` re-rodam).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh — recarrega só por ação do usuário (filtro/busca/navegação).
- ❌ Não muta dados em GET — exclusão exige `confirm()` explícito e vai por `router.delete`.
- ❌ Não dispara impressão/etiqueta sem clique (abrem em nova aba sob demanda).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar com Wagner se o teto de 200 linhas + ausência de paginação server-side é o comportamento desejado
