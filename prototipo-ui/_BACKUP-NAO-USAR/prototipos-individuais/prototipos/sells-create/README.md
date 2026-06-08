# Protótipo F1 — sells-create

**Status:** 🟢 PRONTO PRA F0 — P0 da fila ([`TELAS_REVIEW_QUEUE.md`](../../TELAS_REVIEW_QUEUE.md)).
**Aprovado por:** [W] 2026-05-09 (Cowork export incluído no zip canon)
**Stories:** US-SELL-007 (sells-create-cockpit-v2) — pendente em SPEC
**Charter:** [`resources/js/Pages/Sells/Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md)

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR. Sells é P0; falha aqui é cliente-facing.

## O que está aqui

3 arquivos de referência visual extraídos do zip Cowork 2026-05-09:

| Arquivo | Tamanho | Função |
|---|---|---|
| `vendas-page.jsx` | 24 KB | Tela principal — Sells/Create + Sells/Index combinados |
| `vendas-extras.jsx` | 56 KB | Componentes auxiliares (split de pagamento, drawer detalhe, atalhos) |
| `data-vendas.jsx` | 4 KB | Mock data |

Canon visual completo (incluindo `os-page.jsx` referência base) em [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/) (ADR [`ui/0012`](../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)).

## Status no loop F0→F4

| Fase | Status |
|---|---|
| F0 BRIEF [W] | ⏳ aguardando Wagner abrir entrada em [`COWORK_NOTES.md`](../../COWORK_NOTES.md) |
| F1 DESIGN [CC] | ✅ feito — material aqui é a entrega F1 |
| F1.5 CRITIQUE [CD] | ⏳ pendente — `design-critique` rodar quando F0 abrir |
| F2 SCREENSHOT [W2] | ⏳ ver `ui_kits/cowork-2026-05-09/screenshot-05-vendas.png` (~127 KB) |
| F3 CODE [CL] | bloqueado por F0+F1.5+F2 |
| F3.5 A11Y [CA] | — |
| F4 MERGE [W2] | — |

## Prioridade visual (charter)

Larissa 1280px — KPIs gigantes, action bar sticky, split pagamento.

Ver [charter](../../../resources/js/Pages/Sells/Create.charter.md) pra goals/non-goals/UX targets/anti-patterns.

## Pré-requisitos pra F3 (quando F0 abrir)

1. `Glob app/Http/Controllers/SellController.php` — UPOS canon (não em `Modules/`)
2. `Read` SellController existente pra padrão middleware + `business_id`
3. Models reais: `App\Transaction` (com `type: sell`), `App\TransactionPayment`, `App\Contact` (cliente), `App\Product`, `App\Variation`
4. **NUNCA inventar** `Sale`/`Order`/`SaleLineItem` — reais são `Transaction`/`TransactionSellLine`
5. Permission: `direct_sell.access` ou `sell.create` (confirmar em `database/seeders/PermissionSeeder.php`)
6. Idempotência obrigatória: `Transaction.idempotency_key`

## Próximo passo

Wagner abre F0 em [`COWORK_NOTES.md`](../../COWORK_NOTES.md) com template canônico:

```markdown
## YYYY-MM-DD HH:MM [W] → [CC]

### Tela: Sells/Create
### Prioridade: P0
### Persona principal: Larissa (balcão ROTA LIVRE 1280×1024)
### Charter existente: resources/js/Pages/Sells/Create.charter.md
### Material F1 disponível: prototipo-ui/prototipos/sells-create/

### Restrições:
- ...

### O que precisa: critique-score F1.5 + screenshot approval F2 → F3 [CL] traduz
```

Daí o loop roda normal. Estimativa F3: ~3-4h (charter completo, material visual pronto, padrão Cockpit V2 conhecido).
