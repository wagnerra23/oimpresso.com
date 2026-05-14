# 5 Relatórios canônicos Jana · piloto Martinho biz=164

> Calibrado pós-import FINANCEIRO 2026-05-14 (103.423 fin_titulos + 81.484 fin_titulo_baixas em prod Hostinger).
> SQL portável pros 50 clientes legacy (`$business_id` parametrizável).
> Multi-tenant Tier 0 ADR 0093.

---

## 1. 🚨 Inadimplência (impacto comercial imediato)

**Decisão:** identificar top devedores + disparar régua WhatsApp + flag write-off candidates.

```sql
-- Top 20 maiores devedores ativos
SELECT
  COALESCE(c.name, ft.cliente_descricao) AS cliente,
  c.cpf_cnpj,
  COUNT(*) AS parcelas_vencidas,
  ROUND(SUM(ft.valor_aberto), 2) AS divida_total,
  MAX(DATEDIFF(NOW(), ft.vencimento)) AS dias_atraso_max,
  SUM(CASE WHEN ft.metadata->>'$.is_write_off_candidate' = 'true' THEN 1 ELSE 0 END) AS write_off
FROM fin_titulos ft
LEFT JOIN contacts c ON c.business_id=ft.business_id AND c.legacy_id=JSON_EXTRACT(ft.metadata, '$.delphi_codpedido')
WHERE ft.business_id = ?
  AND ft.tipo = 'receber'
  AND ft.status = 'aberto'
  AND ft.vencimento < CURDATE()
  AND ft.cliente_descricao IS NOT NULL
GROUP BY cliente, c.cpf_cnpj
HAVING divida_total > 1000
ORDER BY divida_total DESC LIMIT 20

-- Buckets de atraso (régua WhatsApp threshold)
SELECT
  CASE
    WHEN DATEDIFF(NOW(), vencimento) <= 30 THEN '1_ate_30d'
    WHEN DATEDIFF(NOW(), vencimento) <= 90 THEN '2_30_90d'
    WHEN DATEDIFF(NOW(), vencimento) <= 180 THEN '3_90_180d'
    WHEN DATEDIFF(NOW(), vencimento) <= 365 THEN '4_180_365d'
    ELSE '5_mais_365d'
  END AS bucket,
  COUNT(*) AS qty,
  ROUND(SUM(valor_aberto), 2) AS total_brl
FROM fin_titulos WHERE business_id=? AND tipo='receber' AND status='aberto' AND vencimento < CURDATE()
GROUP BY bucket ORDER BY bucket
```

**Sample real Martinho biz=164:**
- 5.651 títulos vencidos · R$ 6.052.397,38
- Top 5: VARGAS LEANDRO R$ 439k · HIDROPOL R$ 239k · JF CAÇAMBAS R$ 163k · AMS SOLDAS R$ 155k · TORK COMERCIO R$ 150k
- Top 5 = R$ 1.148.493 (19% dívida total)

**Jana brief diário:**
```
📉 INADIMPLÊNCIA Martinho:
- R$ 6.052.397 em 5.651 títulos vencidos
- Top 20 devedores = R$ X (Y% concentrado)
- N candidatos régua WhatsApp >30d <90d (HOJE?)
- Z candidatos write-off >365d sem boleto
```

**Integração:** Page `/financeiro/inadimplencia` + Modules/RecurringBilling/ReguaDispatcher (futuro) + permissão `financeiro.inadimplencia.view`.

---

## 2. 💸 Faturamento histórico + sazonalidade (estratégico)

**Decisão:** identificar curva crescimento/queda + picos sazonais (caçamba = sazonalidade obras).

```sql
-- Curva mensal últimos 24m + MoM%
SELECT
  DATE_FORMAT(transaction_date, '%Y-%m') AS mes,
  COUNT(*) AS vendas,
  ROUND(SUM(final_total), 2) AS receita,
  LAG(SUM(final_total)) OVER (ORDER BY DATE_FORMAT(transaction_date, '%Y-%m')) AS mes_anterior,
  ROUND(((SUM(final_total) - LAG(SUM(final_total)) OVER (ORDER BY DATE_FORMAT(transaction_date, '%Y-%m')))
    / NULLIF(LAG(SUM(final_total)) OVER (ORDER BY DATE_FORMAT(transaction_date, '%Y-%m')), 0) * 100), 2) AS mom_pct
FROM transactions WHERE business_id=? AND type='sell' AND status='final'
  AND transaction_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
GROUP BY mes ORDER BY mes

-- Sazonalidade (média mês do ano)
SELECT MONTH(transaction_date) AS mes_ano,
       ROUND(AVG(monthly_total), 2) AS media_brl,
       COUNT(DISTINCT YEAR(transaction_date)) AS anos_amostra
FROM (
  SELECT YEAR(transaction_date) AS y, MONTH(transaction_date) AS m, SUM(final_total) AS monthly_total, transaction_date
  FROM transactions WHERE business_id=? AND type='sell' AND status='final'
  GROUP BY y, m, transaction_date
) sub GROUP BY mes_ano ORDER BY media_brl DESC
```

**Sample Martinho:** R$ 107M total 11 anos · ticket médio R$ 2.430 · provável pico setembro-fevereiro (obras).

**Jana brief:**
```
📈 FATURAMENTO Martinho:
- Maio/26 até hoje: R$ X (Y vendas) · MoM Z%
- Melhor mês 12m: <Mês> R$ A
- Pico sazonal próximo mês: <Mês> média R$ B
```

**Integração:** Dashboard `/dashboard?biz=164` Recharts + JanaContextoNegocio (ADR 0052 3 ângulos).

---

## 3. 🎯 Concentração de clientes (risco estratégico)

**Decisão:** % top N clientes · Pareto 80/20 · alertar se top 5 > 40% (risco churn alto).

```sql
WITH ranked AS (
  SELECT c.id, c.name, c.cpf_cnpj,
         SUM(t.final_total) AS ltv_total,
         COUNT(*) AS qtd_vendas,
         RANK() OVER (ORDER BY SUM(t.final_total) DESC) AS rk
  FROM transactions t JOIN contacts c ON c.id = t.contact_id
  WHERE t.business_id=? AND t.status='final'
  GROUP BY c.id
)
SELECT
  ROUND(SUM(CASE WHEN rk <= 10  THEN ltv_total ELSE 0 END) / SUM(ltv_total) * 100, 2) AS pct_top10,
  ROUND(SUM(CASE WHEN rk <= 50  THEN ltv_total ELSE 0 END) / SUM(ltv_total) * 100, 2) AS pct_top50,
  ROUND(SUM(CASE WHEN rk <= 100 THEN ltv_total ELSE 0 END) / SUM(ltv_total) * 100, 2) AS pct_top100
FROM ranked
```

**Jana brief:**
```
👥 CONCENTRAÇÃO Martinho:
- Top 10 = X% faturamento (risco baixo/médio/alto)
- Top 50 = Y%
- One-shot = Z% (esperado caçamba avulsa)
```

---

## 4. ⏰ Churn — clientes ouro que pararam de comprar

**Decisão:** identificar clientes LTV alto inativos >90d · disparar WhatsApp "oferta retorno".

```sql
SELECT c.name, c.cpf_cnpj, c.mobile,
       MAX(t.transaction_date) AS ultima_compra,
       DATEDIFF(NOW(), MAX(t.transaction_date)) AS dias_inativo,
       COUNT(*) AS total_vendas_historicas,
       ROUND(SUM(t.final_total), 2) AS ltv_total
FROM transactions t JOIN contacts c ON c.id = t.contact_id
WHERE t.business_id=? AND t.status='final'
GROUP BY c.id
HAVING dias_inativo > 90 AND ltv_total > 50000 AND total_vendas_historicas >= 3
ORDER BY ltv_total DESC LIMIT 30
```

**Jana brief:**
```
🚨 CHURN Martinho:
- N clientes ouro inativos >90d (LTV >R$ 50k cada)
- Score reativação alto = M
- Cohort retention 12m: X%
```

---

## 5. 🚛 Operação caçambas (gestão frota)

**Decisão:** identificar caçambas paradas + overdue + taxa utilização baixa.

```sql
-- Status agora
SELECT current_status, COUNT(*) AS qtd
FROM vehicles WHERE business_id=? AND vehicle_type='cacamba_avulsa'
GROUP BY current_status

-- Taxa utilização 30d por caçamba
SELECT v.plate, v.legacy_id,
       COUNT(DISTINCT DATE(so.transaction_date)) AS dias_locada_30d,
       ROUND(COUNT(DISTINCT DATE(so.transaction_date)) / 30 * 100, 2) AS pct_util
FROM vehicles v
LEFT JOIN service_orders so ON so.vehicle_id=v.id
  AND so.business_id=v.business_id
  AND so.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
WHERE v.business_id=? AND v.vehicle_type='cacamba_avulsa'
GROUP BY v.id ORDER BY pct_util DESC

-- Caçambas paradas >7d (oportunidade outbound)
SELECT v.plate, DATEDIFF(NOW(), MAX(so.transaction_date)) AS dias_parada
FROM vehicles v
LEFT JOIN service_orders so ON so.vehicle_id=v.id AND so.business_id=v.business_id
WHERE v.business_id=? AND v.current_status='disponivel'
GROUP BY v.id HAVING dias_parada > 7 OR dias_parada IS NULL
ORDER BY dias_parada DESC
```

**Jana brief:**
```
🚛 FROTA Martinho:
- X/91 locadas (Y% util) · Z overdue HOJE
- W paradas há >7d
- Taxa util 30d: A% (target 70%)
```

---

## Integração Jana — caminhos

| Caminho | Como funciona |
|---|---|
| **Brief diário 06:00** | `BriefDiarioAgent` chama as 5 SQL · gera linha resumo por categoria · WhatsApp/email Wagner |
| **Chat conversacional** | "quais os top 10 devedores Martinho?" · Jana usa `JanaContextoNegocio` faz query SQL · retorna tabela |
| **Telas Inertia** | `/financeiro/inadimplencia`, `/dashboard`, `/crm/reativacao`, `/oficina-auto/dashboard-frota` |
| **Agentes proativos** | `JanaReativacaoAgent` (Trust L1 sugere · L2 rascunha WhatsApp · L3 HITL aprovação) |

## Multi-tenant + LGPD

- `business_id = ?` obrigatório em TODAS queries (ADR 0093 Tier 0)
- CPF/CNPJ redacted em audit log (`PiiRedactor::redact()` antes de json_encode metadata)
- Hard-delete LGPD via `php artisan copiloto:lgpd:esquecer --user-email=...`

## Próximos passos

1. Criar Page Inertia `/financeiro/inadimplencia` com top 20 + buckets (US-FIN-XXX)
2. Estender `BriefDiarioAgent` com 5 linhas de cada relatório
3. Criar `JanaReativacaoAgent` (Trust L1 inicial)
4. Pest tests cobrindo Tier 0 isolation por relatório
5. Reaplicar pros próximos clientes legacy (Vargas v1468 next)

---
**Calibrado em:** 2026-05-14 pós-import financeiro Martinho · PR #812 (em review).
