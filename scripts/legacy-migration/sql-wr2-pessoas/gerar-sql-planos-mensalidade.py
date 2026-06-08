"""
Gera SQL para criar 109 rb_plans biz=1 (mensalidade WR2)
a partir do TSV exportado de fin_titulos jun/2026.

Input  : output/planos-mensalidade/clientes-mensalidade-06-2026.tsv
Output : output/planos-mensalidade/rb-plans-insert-2026-06-07.sql
"""
import csv, json, re, os, sys

ROOT = os.path.dirname(os.path.abspath(__file__))
TSV  = os.path.join(ROOT, "output", "planos-mensalidade", "clientes-mensalidade-06-2026.tsv")
OUT  = os.path.join(ROOT, "output", "planos-mensalidade", "rb-plans-insert-2026-06-07.sql")

def slugify(s):
    s = s.lower()
    s = re.sub(r"[áàâãä]", "a", s)
    s = re.sub(r"[éèêë]", "e", s)
    s = re.sub(r"[íìîï]", "i", s)
    s = re.sub(r"[óòôõö]", "o", s)
    s = re.sub(r"[úùûü]", "u", s)
    s = re.sub(r"ç", "c", s)
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return s

def mysql_escape(s):
    if s is None:
        return "NULL"
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

rows = []
sem_cliente_id = []

with open(TSV, "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, delimiter="\t")
    for r in reader:
        cliente_id = r["cliente_id"].strip()
        nome       = r["cliente_nome"].strip()
        cnpj       = r["cnpj_cpf"].strip() if r["cnpj_cpf"] != "NULL" else None
        valor      = r["valor_mensalidade"].strip()
        legacy     = r["legacy_id"].strip()
        observ     = r["observacoes"].strip()

        if cliente_id == "NULL":
            sem_cliente_id.append((nome, valor, legacy))
            # CYRINO sera reconectada via UPDATE; usa id=31257 fixo
            if "CYRINO" in nome.upper():
                cliente_id = "31257"
            else:
                print(f"WARN sem cliente_id: {nome}")
                continue

        # Nome do plano: 'Mensalidade <RAZAO SOCIAL>' truncado a 150 chars
        name = f"Mensalidade {nome}"[:150]
        # Slug unico per cliente: mensalidade-{cliente_id} (max 80, fixo curto)
        slug = f"mensalidade-cliente-{cliente_id}"[:80]
        # Metadata JSON: linka ao titulo legacy + cliente
        meta = json.dumps({
            "source": "wr-comercial-migracao-2026-06-07",
            "cliente_id": int(cliente_id),
            "cliente_nome": nome,
            "cnpj_cpf": cnpj,
            "titulo_legacy_id": legacy,
            "valor_origem_jun_2026": valor,
        }, ensure_ascii=False)

        rows.append({
            "business_id": 1,
            "name": name,
            "slug": slug,
            "valor": valor,
            "ciclo": "monthly",
            "trial_days": 0,
            "ativo": 1,
            "fiscal_type": "none",
            "metadata": meta,
            "cliente_id": cliente_id,
        })

# Gera SQL
sql = []
sql.append("-- ============================================================")
sql.append("-- rb_plans biz=1 (WR2): 109 mensalidades a partir de jun/2026")
sql.append("-- Origem: fin_titulos plano_conta_id=332 venc 2026-06-*")
sql.append("--         observacoes LIKE 'MENSALIDADE REFERENTE AO MES DE %'")
sql.append("-- Gerado: 2026-06-07 (sessao Eliana)")
sql.append("-- ============================================================")
sql.append("")
sql.append("START TRANSACTION;")
sql.append("")
sql.append("-- (1) Reconecta CYRINO ENGENHARIA E PLOTAGEM EIRELI EPP (cliente_id estava NULL)")
sql.append("UPDATE fin_titulos SET cliente_id=31257")
sql.append("WHERE id=165313 AND business_id=1 AND cliente_id IS NULL;")
sql.append("")
sql.append(f"-- (2) Cria {len(rows)} planos de mensalidade")
for r in rows:
    sql.append(
        "INSERT INTO rb_plans "
        "(business_id, name, slug, valor, ciclo, trial_days, ativo, fiscal_type, metadata, created_at, updated_at) "
        f"VALUES ({r['business_id']}, {mysql_escape(r['name'])}, {mysql_escape(r['slug'])}, "
        f"{r['valor']}, {mysql_escape(r['ciclo'])}, {r['trial_days']}, {r['ativo']}, "
        f"{mysql_escape(r['fiscal_type'])}, {mysql_escape(r['metadata'])}, NOW(), NOW());"
    )
sql.append("")
sql.append("-- (3) Verificacao")
sql.append("SELECT COUNT(*) AS total_planos_criados, SUM(valor) AS soma_valor")
sql.append("FROM rb_plans WHERE business_id=1;")
sql.append("")
sql.append("COMMIT;")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(sql))

print(f"OK: {len(rows)} planos preparados.")
print(f"    sem cliente_id (mas reconectados): {len(sem_cliente_id)} -> {sem_cliente_id}")
print(f"    SQL gerado: {OUT}")
