"""
Gera SQL para criar 109 rb_subscriptions biz=1 (assinaturas mensais WR2)
a partir do TSV base (cliente + plano + venc + conta bancária).

Input  : output/planos-mensalidade/subscriptions-base.tsv
Output : output/planos-mensalidade/rb-subscriptions-insert-2026-06-07.sql

Lança cada cliente com:
  - status=active
  - start_date / next_due_date / billing_anchor_date = 2026-07-XX (1ª cobrança)
  - conta_bancaria_id do título jun/2026 (banco que cliente já paga)
  - payment_method=boleto
  - metadata: cliente, plano, source, valor_origem

Sem invoices criadas — o command rb:generate-invoices (US-RB-003) vai gerar
mensalmente conforme next_due_date <= hoje, a partir de jul/2026.
"""
import csv, json, os, sys

ROOT = os.path.dirname(os.path.abspath(__file__))
TSV  = os.path.join(ROOT, "output", "planos-mensalidade", "subscriptions-base.tsv")
OUT  = os.path.join(ROOT, "output", "planos-mensalidade", "rb-subscriptions-insert-2026-06-07.sql")

def mysql_escape(s):
    if s is None or s == "" or s == "NULL":
        return "NULL"
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

rows = []
with open(TSV, "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, delimiter="\t")
    for r in reader:
        cliente_id = r["cliente_id"].strip()
        nome       = r["cliente_nome"].strip()
        venc_jun   = r["venc_jun"].strip()
        next_due   = r["next_due_jul"].strip()
        valor      = r["valor_titulo"].strip()
        conta      = r["conta_bancaria_id"].strip()
        plan_id    = r["plan_id"].strip()
        plan_valor = r["plan_valor"].strip()

        if not cliente_id or cliente_id == "NULL":
            print(f"WARN sem cliente_id: {nome}")
            continue

        meta = json.dumps({
            "source": "wr-comercial-migracao-2026-06-07",
            "cliente_id": int(cliente_id),
            "cliente_nome": nome,
            "plan_id": int(plan_id),
            "valor_titulo_jun_2026": valor,
            "conta_bancaria_legacy": int(conta) if conta and conta != "NULL" else None,
            "first_cycle_start": next_due,
        }, ensure_ascii=False)

        rows.append({
            "business_id": 1,
            "plan_id": plan_id,
            "contact_id": cliente_id,
            "status": "active",
            "start_date": next_due,
            "next_due_date": next_due,
            "billing_anchor_date": next_due,
            "conta_bancaria_id": conta if conta and conta != "NULL" else "NULL",
            "payment_method": "boleto",
            "metadata": meta,
            "contact_phone_cached": None,
        })

# Gera SQL
sql = []
sql.append("-- ============================================================")
sql.append(f"-- rb_subscriptions biz=1 (WR2): {len(rows)} assinaturas mensais a partir de jul/2026")
sql.append("-- Origem: TSV gerado de fin_titulos jun/2026 + rb_plans criados via slug")
sql.append("-- Gerado: 2026-06-07 (sessao Eliana)")
sql.append("-- O command rb:generate-invoices vai criar rb_invoices conforme next_due_date <= hoje.")
sql.append("-- ============================================================")
sql.append("")
sql.append("START TRANSACTION;")
sql.append("")
sql.append(f"-- {len(rows)} assinaturas:")
for r in rows:
    conta_val = r["conta_bancaria_id"] if r["conta_bancaria_id"] == "NULL" else r["conta_bancaria_id"]
    sql.append(
        "INSERT INTO rb_subscriptions ("
        "business_id, plan_id, contact_id, status, start_date, next_due_date, "
        "billing_anchor_date, conta_bancaria_id, payment_method, metadata, "
        "total_paid_cached, failed_count_cached, total_revenue_cached, created_at, updated_at"
        ") VALUES ("
        f"{r['business_id']}, {r['plan_id']}, {r['contact_id']}, "
        f"{mysql_escape(r['status'])}, {mysql_escape(r['start_date'])}, "
        f"{mysql_escape(r['next_due_date'])}, {mysql_escape(r['billing_anchor_date'])}, "
        f"{conta_val}, {mysql_escape(r['payment_method'])}, {mysql_escape(r['metadata'])}, "
        "0, 0, 0.00, NOW(), NOW());"
    )

sql.append("")
sql.append("-- Verificacao")
sql.append("SELECT COUNT(*) AS total_subs, ROUND(SUM(p.valor),2) AS mrr_total")
sql.append("FROM rb_subscriptions s JOIN rb_plans p ON p.id=s.plan_id")
sql.append("WHERE s.business_id=1;")
sql.append("")
sql.append("COMMIT;")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(sql))

print(f"OK: {len(rows)} subscriptions preparadas.")
print(f"    SQL gerado: {OUT}")
