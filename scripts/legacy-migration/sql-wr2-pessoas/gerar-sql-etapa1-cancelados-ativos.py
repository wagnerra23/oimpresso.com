"""
ETAPA 1 retroatividade RecurringBilling:
- 52 INSERT rb_plans (cancelados, ativo=0)
- 52 INSERT rb_subscriptions (status=canceled, canceled_at, conta do ultimo titulo)
- 109 UPDATE rb_subscriptions (start_date + billing_anchor_date = 1a mensalidade real)

Inputs:
  output/planos-mensalidade/cancelados-base.tsv         (52 linhas)
  output/planos-mensalidade/ativos-primeira-venc.tsv    (109 linhas)

Output:
  output/planos-mensalidade/etapa1-cancelados-ativos.sql
"""
import csv, json, os
from datetime import datetime
from dateutil.relativedelta import relativedelta

# evita dependencia externa: parse manual
def add_one_month(date_str):
    y, m, d = [int(x) for x in date_str.split("-")]
    if m == 12:
        return f"{y+1:04d}-01-{min(d, 31):02d}"
    next_m = m + 1
    # dia max do mes seguinte
    days_in_month = [31, 29 if (y % 4 == 0 and (y % 100 != 0 or y % 400 == 0)) else 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    max_d = days_in_month[next_m - 1]
    return f"{y:04d}-{next_m:02d}-{min(d, max_d):02d}"

ROOT = os.path.dirname(os.path.abspath(__file__))
CANC = os.path.join(ROOT, "output", "planos-mensalidade", "cancelados-base.tsv")
ATIV = os.path.join(ROOT, "output", "planos-mensalidade", "ativos-primeira-venc.tsv")
OUT  = os.path.join(ROOT, "output", "planos-mensalidade", "etapa1-cancelados-ativos.sql")

def mysql_escape(s):
    if s is None or s == "" or s == "NULL":
        return "NULL"
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

def slugify_short(s):
    return s

# ===== 52 CANCELADOS =====
cancelados = []
with open(CANC, "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, delimiter="\t")
    for r in reader:
        cliente_id = r["cliente_id"].strip()
        nome       = r["cliente_nome"].strip()
        primeira   = r["primeira_venc"].strip()
        ultima     = r["ultima_venc"].strip()
        valor      = float(r["valor_plano"].strip())
        conta      = r["conta_bancaria_id"].strip()

        canceled_at_date = add_one_month(ultima)
        meta_plan = json.dumps({
            "source": "wr-comercial-migracao-2026-06-07-etapa1",
            "cliente_id": int(cliente_id),
            "cliente_nome": nome,
            "status_inicial": "canceled",
            "primeira_mensalidade": primeira,
            "ultima_mensalidade": ultima,
            "ultimo_valor": f"{valor:.2f}",
        }, ensure_ascii=False)
        meta_sub = json.dumps({
            "source": "wr-comercial-migracao-2026-06-07-etapa1",
            "cliente_id": int(cliente_id),
            "cliente_nome": nome,
            "primeira_mensalidade": primeira,
            "ultima_mensalidade": ultima,
            "conta_bancaria_legacy": int(conta) if conta and conta != "NULL" else None,
            "canceled_after_no_more_invoices": True,
        }, ensure_ascii=False)

        cancelados.append({
            "cliente_id": cliente_id,
            "nome": nome[:140],
            "slug": f"mensalidade-cliente-{cliente_id}",
            "valor": f"{valor:.2f}",
            "ciclo": "monthly",
            "ativo": 0,
            "fiscal_type": "none",
            "meta_plan": meta_plan,
            "meta_sub": meta_sub,
            "primeira": primeira,
            "ultima": ultima,
            "canceled_at": f"{canceled_at_date} 00:00:00",
            "conta_bancaria_id": conta if conta and conta != "NULL" else "NULL",
        })

# ===== 109 ATIVOS =====
ativos = []
with open(ATIV, "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, delimiter="\t")
    for r in reader:
        sub_id   = r["sub_id"].strip()
        contact  = r["contact_id"].strip()
        primeira = r["primeira_venc"].strip()
        if not primeira or primeira == "NULL":
            continue
        ativos.append({"sub_id": sub_id, "primeira": primeira})

# ===== GERA SQL =====
sql = []
sql.append("-- ============================================================")
sql.append(f"-- ETAPA 1: {len(cancelados)} cancelados + {len(ativos)} ajuste start_date ativos")
sql.append("-- Gerado: 2026-06-07 (sessao Eliana - retroatividade RecurringBilling)")
sql.append("-- ============================================================")
sql.append("")
sql.append("START TRANSACTION;")
sql.append("")
sql.append(f"-- (A) {len(cancelados)} planos novos pros clientes cancelados (ativo=0)")
for c in cancelados:
    sql.append(
        "INSERT INTO rb_plans "
        "(business_id, name, slug, valor, ciclo, trial_days, ativo, fiscal_type, metadata, created_at, updated_at) "
        f"VALUES (1, {mysql_escape('Mensalidade ' + c['nome'])}, {mysql_escape(c['slug'])}, "
        f"{c['valor']}, {mysql_escape(c['ciclo'])}, 0, {c['ativo']}, "
        f"{mysql_escape(c['fiscal_type'])}, {mysql_escape(c['meta_plan'])}, NOW(), NOW());"
    )

sql.append("")
sql.append(f"-- (B) {len(cancelados)} subs novas (status=canceled, lookup plan_id via slug)")
for c in cancelados:
    conta = c["conta_bancaria_id"]
    sql.append(
        "INSERT INTO rb_subscriptions ("
        "business_id, plan_id, contact_id, status, start_date, next_due_date, "
        "billing_anchor_date, canceled_at, conta_bancaria_id, payment_method, metadata, "
        "total_paid_cached, failed_count_cached, total_revenue_cached, created_at, updated_at"
        ") VALUES ("
        f"1, (SELECT id FROM rb_plans WHERE business_id=1 AND slug={mysql_escape(c['slug'])}), "
        f"{c['cliente_id']}, 'canceled', {mysql_escape(c['primeira'])}, {mysql_escape(c['ultima'])}, "
        f"{mysql_escape(c['primeira'])}, {mysql_escape(c['canceled_at'])}, "
        f"{conta}, 'boleto', {mysql_escape(c['meta_sub'])}, "
        "0, 0, 0.00, NOW(), NOW());"
    )

sql.append("")
sql.append(f"-- (C) UPDATE {len(ativos)} subs ativas: start_date + billing_anchor_date = 1a mensalidade real")
for a in ativos:
    sql.append(
        f"UPDATE rb_subscriptions SET start_date={mysql_escape(a['primeira'])}, "
        f"billing_anchor_date={mysql_escape(a['primeira'])} "
        f"WHERE id={a['sub_id']} AND business_id=1;"
    )

sql.append("")
sql.append("-- Verificacao")
sql.append("SELECT '=== Subs por status biz=1 ===' AS x;")
sql.append("SELECT status, COUNT(*) AS qtd FROM rb_subscriptions WHERE business_id=1 GROUP BY status;")
sql.append("SELECT '=== Planos por ativo biz=1 ===' AS x;")
sql.append("SELECT ativo, COUNT(*) AS qtd FROM rb_plans WHERE business_id=1 GROUP BY ativo;")
sql.append("SELECT '=== start_date distribuicao por ano ===' AS x;")
sql.append("SELECT YEAR(start_date) AS ano, COUNT(*) AS qtd FROM rb_subscriptions WHERE business_id=1 GROUP BY YEAR(start_date) ORDER BY ano;")
sql.append("")
sql.append("COMMIT;")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(sql))

print(f"OK: {len(cancelados)} cancelados + {len(ativos)} ativos (UPDATE)")
print(f"    SQL gerado: {OUT}")
print(f"    Linhas SQL: {len(sql)}")
