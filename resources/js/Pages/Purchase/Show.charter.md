---
page: /purchases/{id}
component: resources/js/Pages/Purchase/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Purchase
related_adrs: [114, 101, 93, 104, 141, 110]
tier: B
charter_version: 1
---

# Page Charter — /purchases/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `app/Http/Controllers/PurchaseController@show` (rota `GET /purchases/{id}`). Detalhe read-only de uma compra — substitui os Blade legacy `show.blade.php` + `show_details.blade.php` (430+ linhas) e mata o bug 500 do barcode. Classificação de PT: SILENCIOSO (detalhe bespoke — usa cards + tabela de itens, sem assinatura de PT-03 tipo FsmActionPanel/Timeline/`<dl`/StatCard).

---

## Mission
Mostrar tudo de uma compra numa página só: fornecedor, empresa/filial, resumo, itens com custo/desconto/imposto/subtotal, pagamentos registrados, quebra de totais (subtotal, desconto, impostos, frete, total, pago, a pagar) e notas adicionais. É a visão de conferência antes de editar, imprimir ou registrar pagamento.

---

## Goals — Features (faz)
- Cabeçalho com ref/data e pills de status e pagamento.
- 3 cards de contexto: Fornecedor (com CNPJ/CPF, contato, link de documento anexo), Empresa/filial (com labels fiscais 1/2) e Resumo.
- Tabela de itens: produto/variação, SKU, qtd+unidade, custo unit., desconto %, imposto (com nome do tributo) e subtotal.
- Bloco de pagamentos (data, método+nota, valor) com empty-state.
- Bloco de totais: subtotal, desconto (fixo ou %), quebra de impostos, frete, total geral, pago e a pagar (com destaque de saldo).
- Notas adicionais quando presentes.
- Ações no header gated por permissão: voltar, imprimir (`/purchases/print/{id}`), editar (`purchase.update`), excluir (`purchase.delete`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita a compra nem lança pagamento nesta tela — só exibe; edição/pagamento são fluxos dedicados (inferência pendente de Wagner).
- ❌ Não mostra timeline/histórico de auditoria da transação (não há FsmActionPanel nem Timeline aqui).
- ❌ Não recalcula totais no cliente — os valores vêm prontos do controller.
- ❌ Não acessa compra de outro tenant — `show` carrega a transação escopada por `business_id` (Tier 0, ADR 0093); IDs de outro negócio devem retornar 404/403.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader ; grid responsivo 1→3 colunas.

---

## Automation hooks (faz)
- Nenhuma automação de servidor disparada por esta tela — é render read-only do payload do `show`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh do detalhe.
- ❌ Exclusão nunca dispara sozinha — exige `confirm()` com ref da compra antes do `router.delete`.
- ❌ Impressão e download de documento abrem só sob clique, nunca automático.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — validar impressão e link de documento anexo
- [ ] Confirmar comportamento esperado para `{id}` de outro tenant (404 vs 403)
