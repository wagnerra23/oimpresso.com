---
tela: purchase/show
modulo: Purchase
tipo: DETAIL
generated_at: 2026-05-11
generated_by: [CL] (skill migracao-blade-react v0.1.0 PR2)
status: medido
snapshot: memory/mwart-inventory/purchase/show.snapshot.md
draft_tsx: resources/js/Pages/Purchase/Show.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@showInertia
bug_fix: "Mata bug 500 em prod (show_details.blade.php:430 DNS1D barcode)"
---

# Visual Comparison — `purchase/show` (DETAIL)

> STEP 4 do pipeline [migracao-blade-react](../../../.claude/skills/migracao-blade-react/SKILL.md).
> **Skill PARA aqui aguardando Wagner aprovar SCREENSHOT** antes do merge.

## Estado atual

| Item | Status |
|------|--------|
| Snapshot paridade | ✅ [show.snapshot.md](../../mwart-inventory/purchase/show.snapshot.md) |
| Draft Inertia/TSX | ✅ [Show.tsx](../../../resources/js/Pages/Purchase/Show.tsx) (354 linhas) |
| Adaptação Controller | ✅ dual path (`PurchaseController@show` → delega `showInertia()`) |
| Pest fixtures | 🟡 pendente STEP 5 |
| **Bug 500 prod** | ✅ **fixed por substituição** — Inertia path não chama DNS1D |
| Screenshot Wagner | 🔴 TODO smoke local `/purchases/24796` |

## 15 dimensões comparativas (Cowork framework)

### 1. Hierarquia

| | Blade legacy (quebrado) | Draft Inertia |
|--|--|--|
| Modal/page | modal AJAX `modal-xl` | full page Inertia |
| Header | "Detalhe de compra #ref" | PageHeader + pills + 4 botões ação |

### 2. Layout

| | Blade | Draft Inertia |
|--|--|--|
| Cards info | 3 cols `col-sm-4` (Supplier/Business/Resumo) | grid-cols-3 Cards shared |
| Tabela items | `<table class="table bg-gray">` 12+ cols | tabela densa 8 cols |
| Pagamentos | `<table>` separada | Card lateral grid-cols-2 |
| Totais | `<table>` lateral | Card lateral com border-top no Total |

### 3. Hierarquia de cores

| Token | Blade | Draft Inertia |
|-------|-------|---------------|
| Header tabela | `bg-green` (✗ contra Cockpit V2) | `bg-stone-50/40` stone neutro |
| Status pills | `<span class="label bg-*">` | `bg-{tone}-50 text-{tone}-700 border-{tone}-200` shared canon |
| Total destaque | `<th class="text-right">` plain | font-semibold + border-top + 15px |

### 4-15. Outras dimensões

| Dim | Blade | Draft |
|-----|-------|-------|
| Tipografia | bootstrap default | text-[13px] denso + tabular-nums BRL |
| Espaçamento | padding bootstrap | py-3/px-4 consistente |
| Status overdue | `bg-red` | rose-50/700/200 ADR 0110 |
| Empty state items | (sem) | `<FileText>` + msg |
| Empty state pagamentos | linha "no_payments" | "Sem pagamentos registrados" |
| Botões ação | inline header HTML+JS modal | shared Button (Voltar/Imprimir/Editar/Excluir) |
| Print | `printThis()` inline | `window.open('/purchases/print/{id}', '_blank')` |
| Multi-tenant | preservado | preservado (`business_id` scope no eager-load) |
| Permissions | comentada (BUG!) | `purchase.view` re-adicionada |
| i18n | `@lang('lang_v1.*')` | hardcoded PT-BR MVP (v0.2 `__()`) |
| Acessibilidade | bootstrap defaults | tailwind defaults |
| Activity log | inline `@includeIf` | omitido MVP (v0.2) |
| Barcode | DNS1D **QUEBRADO** | omitido (v0.2 — não-crítico) |

## Critérios pra Wagner aprovar

- [ ] `/purchases/24796` renderiza sem erro (mata 500 atual)
- [ ] 3 cards (Fornecedor / Empresa / Resumo) preenchidos com dados reais
- [ ] Tabela items mostra produtos com SKU + qtd + preço + subtotal
- [ ] Pagamentos lista (se existem)
- [ ] Totais batem com Blade legacy quando bemfunciona (smoke em compra que NÃO crashava)
- [ ] Status/Payment pills coloridas
- [ ] Botões "Editar/Excluir" só visíveis com permissão
- [ ] Tier 0 OK (acessar `/purchases/{id}` de outro tenant → 404)
- [ ] Documento anexo (se existe) tem link download
- [ ] Notas adicionais aparecem (se preenchidas)

## Critérios de bloqueio (Wagner reprova)

- ❌ Tier 0 quebrado (ver compras de outro tenant)
- ❌ Crash 500 em qualquer compra
- ❌ Falta campo crítico (ref_no, fornecedor, valor total, items)
- ❌ Permissão vazada (botão visível sem permission)

## Como testar local

```bash
npm run build
# Acessar: http://oimpresso.test/purchases/24796 (compra que crashava antes)
# Verificar:
#   - Renderiza sem erro
#   - 3 cards top
#   - Tabela items
#   - Totais
#   - Pagamentos
#   - Botões ação
```

## Limitações MVP (v0.2)

- Activity log (audit) ainda não migrado
- Barcode (quebrado) não substituído — relevância questionável
- Custom fields ainda não migrados
- Shipping detail (apenas purchase_order) ainda não migrado
- Additional expenses ainda não migrados
- Path AJAX legacy preservado (mas Blade está 500 — eventualmente remover)

---

**Refs:** [ADR 0141](../../decisions/0141-skill-migracao-blade-react.md) · [runbook-DETAIL](../../../.claude/skills/migracao-blade-react/runbook-DETAIL.template.md) · [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

---

## Medição de 2026-11-09 (lote 6 da auditoria de Compras)

> Substitui como estado atual o "aguardando screenshot" acima, que fica como registro de 2026-05-11. Nada foi corrigido neste lote.

Run [42621542024](https://github.com/wagnerra23/oimpresso.com/actions/runs/42621542024), artifact `paridade-lote-42621542024`, branch descartável `claude/compras-lote6-medir-nao-mergear` (não mergeada). App efêmero do `design-smoke-ci` sobre o `main` em `d1826f8357`, biz=1 fictício, 1280×900, tema escuro real nos dois lados. Em produção não deu: o Chrome não tinha sessão e o staging está 26 dias atrás.

A âncora é a gaveta `DrawerView` de `compras-page.jsx`, e a produção é uma página inteira em `/purchases/{id}`. Por isso a medida que vale é a `--check-drawer` (`medidas/Purchase--Show/gaveta/`), com o protótipo clicado na primeira linha da aba Pedidos. A medida da página fechada compara a lista do protótipo com o detalhe da produção e **não conta**.

⚠️ **T00 sem prova por contrato:** a identidade vem do charter (`related_prototype: compras-page.jsx (DrawerView)`), então os vereditos são indicativos. O comparador saiu `NÃO MEDI` porque a produção não tem rodapé e o papel `rodape` ficou sem par; a gaveta tem medida por campo, sem veredito geral da máquina.

| # | Item | Vivo | Protótipo | Veredito | Data |
|---|---|---|---|---|---|
| T00 | Identidade da view | sem contrato | sem contrato | NÃO MEDIDO | 2026-11-09 |
| 1 | Forma de apresentação | página de 1224px | gaveta de 560px com sombra e borda | DÍVIDA A FECHAR | 2026-11-09 |
| 2 | Título | 22px, com ícone | 18px, sem ícone | DÍVIDA A FECHAR | 2026-11-09 |
| 3 | Cabeçalho | sem fundo nem padding | fundo próprio, padding 18×20 | DÍVIDA A FECHAR | 2026-11-09 |
| 4 | Corpo | cartões (raio 12, borda, sombra) | seções lisas, padding 14×18 | DÍVIDA A FECHAR | 2026-11-09 |
| 5 | Rodapé de ações | não existe, ações no cabeçalho | rodapé com Fechar e ação do estágio | DÍVIDA A FECHAR | 2026-11-09 |
| 6 | Botões de ação | 32px, fonte 14px, com ícone | 24px, fonte 11,5px, sem ícone | DÍVIDA A FECHAR | 2026-11-09 |
| 7 | Títulos de seção | 11px, peso 500 | 10,5px, peso 700 | DÍVIDA A FECHAR | 2026-11-09 |
| 8 | Abas Resumo/Itens e faixa de estágio | não existem | presentes | DÍVIDA A FECHAR | 2026-11-09 |
| 9 | Rede | — | — | NÃO MEDIDO (tela sem filtro) | 2026-11-09 |

Comparador: 88 linhas, 4 iguais, 1 `DIVERGE (bug)` (título) e o resto `a classificar` ou `dado?`. Os itens acima agrupam as linhas por região.

**Declaração:** PARCIALMENTE PUBLICADA. Levar o detalhe para gaveta é decisão de produto, porque hoje a página é o caminho canônico do `/purchases/{id}`. A dívida fica registrada e a decisão é de [W].
