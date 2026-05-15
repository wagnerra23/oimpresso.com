---
name: Cross-business contamination em importer Python — bug família catalogado 2026-05-14
description: Padrão de bug onde importer Python pra business_id=X termina atualizando rows de business_id=Y indevidamente. Causa raiz SELECT/UPDATE em tabelas filhas (VLD/purchase_lines) sem JOIN+WHERE business_id explícito. Mitigação pattern cinto-suspensório aplicado em 3 importers.
type: feedback
---
# Cross-business bug em importer — pattern e mitigação

## Incidente real 2026-05-14

Durante migração Wave A Martinho (biz=164), `import-estoque.py` rodando em prod **atualizou 5 VLDs do ROTA LIVRE (biz=4)** indevidamente:

| VLD | Produto · variation ROTA LIVRE | Qty pre-bug | Qty pós-bug | Perda |
|---|---|---:|---:|---:|
| 13758 | CARDIGAN MODAL LONGO L8030 · M | 3 | 3 | ✅ 0 (coincidência) |
| 13759 | CARDIGAN MODAL LONGO L8030 · G | 8 | 8 | ✅ 0 (coincidência) |
| 14705 | JAQUETA MODAL CAPUZ 168 · P/M | **12** | 11 | -1 |
| 14733 | BLUSA MC TRICOT MODAL P130 · P | **8** | 7 | -1 |
| 14735 | BLUSA MC TRICOT MODAL P130 · G | **7** | 6 | -1 |

Total: **3 unidades perdidas**. Recovery via backup Hostinger `~/.cagefs/tmp/oimpresso-dump-20260513-195514.sql.gz` (13/maio 19:55 BRT · 12h pre-bug) + 3 UPDATEs surgical (id=14705,14733,14735). **Larissa não viu nada.**

## Padrão do bug (anti-pattern)

```python
# 1. Lookup CORRETO — retorna product_id apenas biz=164
product_lookup = query(
    "SELECT p.id, v.id FROM products p JOIN variations v ON v.product_id=p.id "
    "WHERE p.business_id=%s AND p.officeimpresso_codigo=%s",
    (164, cod_produto)
)
product_id = lookup["product_id"]  # ✅ scope biz=164
variation_id = lookup["variation_id"]  # ✅ scope biz=164

# 2. SELECT defensivo VLD — VULNERÁVEL (sem JOIN + WHERE business_id)
existing_vld = query(
    "SELECT id FROM variation_location_details "
    "WHERE product_id=%s AND variation_id=%s AND location_id=%s LIMIT 1",
    (product_id, variation_id, 1)
)

# 3. UPDATE VLD — VULNERÁVEL (só usa vld.id sem qualificar business)
query("UPDATE variation_location_details SET qty_available=%s WHERE id=%s", (qty, vld_id))
```

### Por que vaza cross-business?

Embora `product_id` venha de lookup escopado biz=164, a tabela `variation_location_details` é GLOBAL (não tem column `business_id` direta · scope vem via `products.business_id` indireto). Qualquer cenário onde:
- Race condition / autoincrement coincidência cria id colidindo
- Variation criada por importer anterior aponta product errado
- Dados corrompidos historicamente

→ UPDATE bate em row de outro tenant. **Wagner pediu: confiar SÓ no Python não basta. SQL precisa ter cinto-suspensório.**

## Pattern correto (cinto-suspensório aplicado 14/maio)

```python
# SELECT defensivo qualificado
existing_vld = query(
    "SELECT vld.id FROM variation_location_details vld "
    "INNER JOIN products p ON p.id = vld.product_id "
    "WHERE p.business_id = %s "                  # ⭐ CINTO
    "AND vld.product_id=%s AND vld.variation_id=%s AND vld.location_id=%s "
    "LIMIT 1",
    (args.target_business, product_id, variation_id, args.location_default)
)

# UPDATE qualificado com JOIN + double-check business_id
result = execute(
    "UPDATE variation_location_details vld "
    "INNER JOIN products p ON p.id = vld.product_id "
    "SET vld.qty_available=%s, vld.updated_at=NOW() "
    "WHERE vld.id=%s AND p.business_id=%s",       # ⭐ SUSPENSÓRIO
    (qty, vld_id, args.target_business)
)
if result.rowcount == 0:
    # Defesa final: UPDATE não pegou (vld pertence outro biz). Log + skip.
    stats["skipped_cross_business_guard"] += 1
    log_warn(f"BLOCKED cross-business: vld={vld_id} not in biz={target_business}")
    continue
```

## Aplicabilidade do pattern

Todo SELECT/UPDATE em tabela "filha" (não tem `business_id` próprio, depende de JOIN com parent que tem) deve aplicar este pattern. Lista provável no oimpresso/UltimatePOS:

| Tabela filha | Parent com business_id | Pattern cinto-suspensório |
|---|---|---|
| `variation_location_details` | `products.business_id` | ✅ aplicado 14/maio |
| `purchase_lines` | `transactions.business_id` | ⚠️ revisar |
| `transaction_sell_lines` | `transactions.business_id` | ⚠️ revisar |
| `variations` | `products.business_id` | ✅ implícito · lookup já filtra |
| `product_variations` | `products.business_id` | ✅ implícito |
| `fin_titulo_baixas` | `fin_titulos.business_id` | ⚠️ revisar |
| `service_order_items` | `service_orders.business_id` | ⚠️ revisar (se existir) |

## Pest test cross-tenant obrigatório

Por importer, mínimo 2 casos:
- `biz=1 NÃO toca biz=99` (sandbox · classical cross-tenant)
- `biz=164 NÃO toca biz=4 ROTA LIVRE` (regressão real 14/maio)

NUNCA usar biz=164 em smoke (ADR 0101 — biz cliente em prod ≠ test).

## Importers corrigidos (14/maio 17h)

| Importer | Mudança | Status |
|---|---|---|
| `scripts/legacy-migration/import-estoque.py` | linhas 359-388 — SELECT/UPDATE cinto-suspensório + rowcount guard | ✅ aplicado |
| `scripts/legacy-migration/import-produtos.py` | linhas 560-571 — SELECT defensivo cinto-suspensório | ✅ aplicado |
| `scripts/legacy-migration/import-compras.py` | linhas 624-632 — UPDATE transactions com `AND business_id=%s` | ✅ aplicado |

Daemon Fase 1 MVP (BG `a13a132de0c4217f1`) **herda pattern** + adiciona delta-flag + chunks + retry.

## Detecção precoce em prod

Daemon dual-sync deve INCREMENTAR stat `skipped_cross_business_guard` E alertar Wagner WhatsApp se >0 (sinal vermelho — sempre deveria ser 0).

```python
if stats["skipped_cross_business_guard"] > 0:
    send_alert(f"⚠️ Daemon detectou {stats['skipped_cross_business_guard']} tentativas cross-business. Investigar variation_id colidindo entre biz.")
```

## Recovery procedure (se acontecer de novo)

1. Identificar rows tocadas: `SELECT * FROM variation_location_details vld JOIN products p ON p.id=vld.product_id WHERE vld.updated_at > 'INCIDENT_TIME' AND p.business_id != 'TARGET_BIZ'`
2. Buscar backup pre-incident: `ls ~/.cagefs/tmp/oimpresso-dump-*.sql.gz`
3. Extrair valores originais via grep regex: `zcat dump.sql.gz | grep -oE '\([targetIds],...)'`
4. Aplicar UPDATEs surgical um por um · validar com SELECT pós
5. Documentar em [cliente-{X}.md](.) histórico de incidentes

## Refs

- [Session log 2026-05-14](../sessions/2026-05-14-martinho-canary-prep-massive.md) — incidente narrativo
- [Cliente Martinho](cliente-martinho.md) — perfil canary
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) — Pest biz=1 nunca cliente
- [ADR proposal dual-sync §6.1 Lição 3](../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md) — bug família catalogado
