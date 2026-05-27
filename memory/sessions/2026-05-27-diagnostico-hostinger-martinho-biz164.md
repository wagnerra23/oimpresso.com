---
title: "Diagnóstico Hostinger oimpresso prod + estado real Martinho biz=164 — fim do mito do gargalo de escala"
topic: "Diagnóstico Hostinger oimpresso prod + Martinho biz=164 estado real (pós ADR 0198 Fase 0)"
type: session
date: "2026-05-27"
authors:
  - W
  - C
status: live
prs:
  - 1717
  - 1723
related_adrs:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0093-multi-tenant-isolation-tier-0
  - 0137-modules-oficinaauto-qualificada
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
---

# Diagnóstico Hostinger + Martinho biz=164 — 2026-05-27

## TL;DR

Wagner liberou acesso SSH Hostinger e direcionou "faça o necessário". Claude mergeou PR #1717 (ADRs 0197+0198 + RUNBOOK) e PR #1723 (Bucket A migration contacts) + rodou diagnóstico ADR 0198 §Fase 0. **3 descobertas que mudam o plano:**

1. **Gargalo escala é mito atual** — DB inteiro 594 MB · disco 21 TB total (6.1 TB livre). Plano Hostinger atual aguenta MUITO mais que isso. ADR 0198 vira prospectivo.
2. **Martinho biz=164 já tem 14 anos de dados em prod** (2012-03 → 2026-05). Vendas, financeiro, cadastros, veículos — tudo lá. Execução pré-RUNBOOK divergiu do plano original.
3. **Gap crítico descoberto** — 92.5% das vendas Martinho (40.644/43.951) NÃO têm linhas em `transaction_sell_lines`. Importer Fase 3 migrou cabeçalho mas pulou sub-linhas. US futura pra Felipe.

## Conexão Hostinger

```bash
ssh -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@oimpresso.com
# user u906587222 · porta 65002 · key ed25519_oimpresso (já em ~/.ssh)
# path: /home/u906587222/domains/oimpresso.com/public_html
```

Detalhes técnicos canon em [migracao-officeimpresso-pattern.md §4 Pre-flight](../reference/migracao-officeimpresso-pattern.md).

## Q1 — Tamanho total DB

```sql
SELECT ROUND(SUM(data_length + index_length)/1024/1024, 1) AS size_mb, COUNT(*) AS tables
FROM information_schema.tables WHERE table_schema = 'u906587222_oimpresso';
```

**Resultado:** 593.6 MB · 368 tabelas.

MariaDB 11.8.6 (partitioning suportado).

```bash
df -h ~ →  21T total · 6.1T livre (29% uso)
du -sh storage →  273 MB
du -sh vendor  →  465 MB
du -sh .git    →  1.4 GB   ← candidato a cleanup
```

**Conclusão:** Hostinger atual aguenta 50-100 GB sem suor. Wagner pode adiar upgrade VPS indefinidamente.

## Q2 — Top 12 tabelas mais pesadas

| Tabela | MB | Linhas | Notas |
|---|---:|---:|---|
| `fin_titulos` | 105.3 | 96.313 | Maior tabela do DB — Martinho biz=164 ocupa 83k linhas |
| `transactions` | 103.4 | 66.776 | Cabeçalho de vendas (todas biz) |
| `mcp_dual_brain_decisions` | 72.9 | 22.493 | MCP/IA decisões (não relacionado migração legacy) |
| `messages` | 46.2 | 44.068 | WhatsApp inbound/outbound (Modules/Whatsapp) |
| `fin_titulo_baixas` | 35.1 | 86.175 | Baixas históricas — biz=164 tem 71.675 |
| `activity_log` | 34.1 | 61.387 | Spatie ActivityLog (audit) |
| `jobs` | 25.7 | 26.403 | Queue Horizon |
| `mcp_memory_documents_history` | 18.6 | 902 | MCP knowledge versioning |
| `transaction_sell_lines` | 18.1 | 40.266 | ⚠️ Light — esperado seria 5-10× transactions |
| `contacts` | 12.8 | 16.421 | Multi-biz (biz=164 sozinho tem 9.938) |
| `mcp_memory_documents` | 11.9 | 743 | MCP canon docs |
| `oauth_refresh_tokens` | 10.1 | 22.057 | Passport |

## Q3 — Inventory Martinho biz=164 (real)

```php
$mart = DB::select("SELECT
  (SELECT COUNT(*) FROM transactions WHERE business_id=164) AS vendas,
  (SELECT COUNT(*) FROM transaction_sell_lines tsl JOIN transactions t ON t.id=tsl.transaction_id WHERE t.business_id=164) AS itens,
  ...");
```

| Tabela | Volume | Esperado pelo RUNBOOK original | Drift |
|---|---:|---:|---|
| `transactions` (vendas) | **43.974** | 0 (Fase 3 pendente) | ⚠️ migração pré-RUNBOOK |
| `transaction_sell_lines` | **5.758** | 200k+ (5-10× vendas) | 🔴 **gap crítico 92.5%** |
| `transaction_payments` | 0 | n/d | ✅ esperado (Modules/Financeiro canônico) |
| `fin_titulos` | **83.045** | 0 (Fase 4 pendente) | ⚠️ migração pré-RUNBOOK |
| `fin_titulo_baixas` | 71.675 | 0 | ⚠️ migração pré-RUNBOOK |
| `vehicles` | 91 | 91 | ✅ Fase 2 confirmada |
| `contacts` | **9.938** | ~4 (EMPRESA Fase 1) | ⚠️ PESSOAS migradas |
| `products` | 3.809 | n/d | ✅ catálogo migrado |
| `users` | 12 | n/d | ✅ operadores Martinho |
| `service_orders` | 91 | 91 | ✅ 1 OS/veículo |

**Tabelas Tier 0 com isolation correta** (campo `business_id` filtrado): contacts, products, vehicles, transactions, fin_titulos, fin_titulo_baixas, users, service_orders.

**Tabelas sem `business_id`** (esperado, FK indireta via parent): transaction_sell_lines, variation_location_details, purchase_lines, transaction_payments.

## Q4 — Distribuição vendas Martinho 14 anos

```sql
SELECT YEAR(transaction_date), COUNT(*), ROUND(SUM(final_total),2)
FROM transactions WHERE business_id=164 GROUP BY 1 ORDER BY 1 DESC;
```

| Ano | Vendas | Receita |
|---:|---:|---:|
| 2026 (parcial até 26/05) | 1.923 | R$ 4.669.551,41 |
| 2025 | **4.868** | **R$ 12.594.668,98** |
| 2024 | 5.211 | R$ 12.547.971,59 |
| 2023 | 5.192 | R$ 15.874.207,54 |
| 2022 | 5.221 | R$ 16.595.740,55 |
| 2021 | 5.161 | R$ 14.903.429,02 |
| 2020 | 3.578 | R$ 6.634.731,97 |
| 2019 | 2.534 | R$ 4.518.756,86 |
| 2018 | 3.176 | R$ 5.697.383,91 |
| 2017 | 2.069 | R$ 3.211.584,94 |
| 2016 | 1.695 | R$ 2.723.215,32 |
| 2015 | 1.121 | R$ 2.314.547,33 |
| 2014 | 1.100 | R$ 2.301.856,78 |
| 2013 | 727 | R$ 1.718.332,62 |
| 2012 | 398 | R$ 608.304,73 |

**Cross-validation com [perfil Martinho §5](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md#5-saúde-financeira):** dizia "R$ 6.28M 12m + R$ 1M+/mês Wagner". Real 2025: R$ 12.6 mi/ano (R$ 1.05M/mês) — bate. Perfil estava conservador.

## Gap crítico — sub-linhas Martinho

**92.5% das vendas Martinho biz=164 NÃO têm linhas em `transaction_sell_lines`:**

| Métrica | Valor |
|---|---:|
| Vendas tipo `sell` | 43.951 |
| Vendas COM linhas | **3.307** (7.5%) |
| Vendas SEM linhas | **40.644** (92.5%) |
| Média itens/venda | 0.13 |

Pra contexto: oficinas típicas mecânica pesada têm 1-3 itens por OS (1 peça + 1 serviço + 1 mão-de-obra). Esperado seria ~44k a ~130k linhas; temos 5.758.

**3 hipóteses pra investigar:**

1. **Importer Fase 3 parou no cabeçalho** — loop sub-linhas falhou silenciosamente ou foi interrompido
2. **FK pra `products` faltando** — sub-linhas precisam `product_id`; dos 3.809 produtos migrados, pode não haver match pra SKU antigo
3. **Importer parcial intencional** — só 2023+ migrado completo (mas 3.307 < 5.211 vendas só de 2024 — não explica)

**Recomendação:** US-OFICINA-XXX nova (Felipe owner):
- Script Python `audit-venda-itens-gap.py` compara VENDA_ITEM Firebird vs transaction_sell_lines MySQL biz=164
- Decisão arquitetural pendente: (A) completar backfill retroativo · (B) accept-loss · (C) Modules/Financeiro já cobre

**NÃO BLOQUEIA operação atual** — Martinho ativo 2026-05-26 com vendas novas funcionando normal.

## Reclassificação ADRs

| ADR | Status original | Reclassificação |
|---|---|---|
| 0197 (Bucket A+B contacts) | "schema pendente pra migrar PESSOAS" | **Confirmado relevante** — 9.938 contacts biz=164 vão se beneficiar via importer dedicado a `contact_profile_legacy`. Migration Bucket A já em prod (PR #1723). Próximo: PR Bucket B (satélite + Model) |
| 0198 (hot/cold tiering) | "preocupação Wagner gargalo escala" | **Prospectivo** — Hostinger 594 MB atual ✅. Mitigações aplicar ANTES do 2º cliente (Gold/Vargas/Extreme), não pro Martinho |

## Próximos passos (atualizados)

1. ✅ PR #1717 — ADRs 0197+0198 + RUNBOOK Martinho (mergeado 13:23 BRT)
2. ✅ PR #1723 — Migration Bucket A contacts + Pest (mergeado 13:24 BRT)
3. 🟡 **PR Bucket B** — `contact_profile_legacy` satélite 1:1 + Model novo + hasOne Contact + Pest (~2h IA-pair)
4. 🟡 **PR importer pessoas-from-firebird.py** — backfill contact_profile_legacy nos 9.938 contacts biz=164 (depende Bucket B mergeado)
5. 🟡 **US-OFICINA-XXX gap sub-linhas** — Felipe investiga 92.5% transaction_sell_lines ausentes
6. 🟢 ADR 0198 mitigações **adiar até Gold piloto** — não urgente

## Commands canon pra próximas sessões

```bash
# Conectar Hostinger
ssh -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@oimpresso.com

# Diagnóstico full biz=N
cd /home/u906587222/domains/oimpresso.com/public_html && \
  php artisan tinker --execute='echo DB::table("contacts")->where("business_id", N)->count();'

# Re-rodar Q1+Q2+Q3 ADR 0198 §Fase 0
# (queries documentadas em memory/decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md §Fase 0)
```

## Anti-patterns evitados nesta sessão

| Anti-pattern | Como evitamos |
|---|---|
| Codar sem confirmar estado real | Diagnóstico SSH antes de assumir Fase 3 pendente |
| Apagar RUNBOOK obsoleto | Apender retrospectiva §7 (append-only ADR-style) |
| Tomar decisões sem dado | Q1+Q2+Q3+Q4 fornecem base empírica |
| Inflar prematuramente plano | ADR 0198 reclassificado pra "prospectivo" — economiza esforço |
