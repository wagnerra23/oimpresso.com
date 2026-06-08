#!/usr/bin/env python3
"""Gera SQL pra importar BOLETOS A RECEBER vivos do Firebird → cobrancas biz=1.

Sem remessa. Idempotente via UNIQUE(idempotency_key).
Liga origem_id ao fin_titulo via legacy_id (formato: {CODEMPRESA}-{CODPEDIDO}-{CODIGO}).

Filtros (Wagner 2026-06-08):
  - BOLETOS.ATIVO='S' (boleto vivo)
  - FINANCEIRO.TIPO='A RECEBER' (a receber)
  - FINANCEIRO.STATUS LIKE 'ATIVO%' (aberto)
  - SITUACAO != PAGO/CANCELADO (boleto ainda na rua)

Output:
  scripts/legacy-migration/sql-wr2-pessoas/output/planos-mensalidade/etapa5-cobrancas-firebird-boletos.sql
"""
import os, json
FB_CLIENT = r"D:\oimpresso.com\.claude\worktrees\fb-client-x64\fbclient.dll"
os.environ["FBCLIENT"] = FB_CLIENT
import firebird.driver as fdb
from firebird.driver import driver_config
driver_config.fb_client_library.value = FB_CLIENT

DB = r"D:\oimpresso.com\scripts\legacy-migration\sql-wr2-pessoas\output\BANCO_VIVO.FDB"
OUT = r"D:\oimpresso.com\.claude\worktrees\wr2-backfill-2026\scripts\legacy-migration\sql-wr2-pessoas\output\planos-mensalidade\etapa5-cobrancas-firebird-boletos.sql"

os.makedirs(os.path.dirname(OUT), exist_ok=True)

con = fdb.connect(database=DB, user="SYSDBA", password="masterkey", charset="WIN1252")
cur = con.cursor()

cur.execute("""
SELECT
  f.CODEMPRESA, f.CODPEDIDO, f.CODIGO,
  f.VALOR, f.VENCTO, f.EMISSAO,
  f.PESSOA_RESPONSAVEL_CODIGO,
  b.CARTEIRA, b.CODBANCO, b.SITUACAO, b.JUROS_MORA, b.MULTA, b.DESCONTO
FROM BOLETOS b
JOIN FINANCEIRO f
  ON f.CODIGO = b.CODFINANCEIRO
 AND f.CODPEDIDO = b.CODPEDIDO
 AND f.CODEMPRESA = b.CODEMPRESA
WHERE b.ATIVO = 'S'
  AND f.TIPO = 'A RECEBER'
  AND f.STATUS STARTING WITH 'ATIVO'
  AND (b.SITUACAO IS NULL OR b.SITUACAO IN ('EMABERTO','EM ABERTO','VENCIDO','EXPIRADO',''))
ORDER BY f.CODEMPRESA, f.CODPEDIDO, f.CODIGO
""")

rows = cur.fetchall()
print(f"BOLETOS A RECEBER vivos: {len(rows)}")

def esc(v):
    if v is None or v == '':
        return "NULL"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"

def n(v):
    return v if v is not None else "NULL"

sql = []
sql.append("-- ============================================================")
sql.append(f"-- ETAPA 5: {len(rows)} BOLETOS A RECEBER Firebird → cobrancas biz=1")
sql.append("-- Gerado: 2026-06-08 — Wagner pediu sem remessa, vinculados a fin_titulos via legacy_id")
sql.append("-- Idempotente: UNIQUE(idempotency_key) — re-rodar = no-op")
sql.append("-- Liga apenas onde fin_titulo já existe em prod (legacy_id match)")
sql.append("-- ============================================================")
sql.append("")
sql.append("START TRANSACTION;")
sql.append("")

# Estatus map (Firebird → cobranca enum)
STATUS_MAP = {
    None: 'aguardando',
    '': 'aguardando',
    'EMABERTO': 'aguardando',
    'EM ABERTO': 'aguardando',
    'VENCIDO': 'vencida',
    'EXPIRADO': 'vencida',
}

count_with_link = 0

for r in rows:
    codemp, codped, codigo, valor, venc, emi, pessoa, carteira, codbanco, situacao, juros, multa, desc = r

    legacy_id = f"{codemp}-{codped}-{codigo}"
    idem_key = f"wr2-boleto-fb-{legacy_id}"
    status = STATUS_MAP.get(situacao, 'aguardando')

    valor_cents = int(round(float(valor or 0) * 100))

    venc_str = venc.isoformat() if venc else "1970-01-01"
    payload = {
        "source": "wr2-backfill-2026",
        "firebird": {
            "codempresa": codemp,
            "codpedido": codped,
            "codigo_financeiro": codigo,
            "situacao_boleto": situacao,
            "carteira": carteira,
            "codbanco": codbanco,
            "juros_mora_pct": float(juros) if juros else None,
            "multa_pct": float(multa) if multa else None,
            "desconto": float(desc) if desc else None,
        }
    }
    payload_json = json.dumps(payload, ensure_ascii=False, default=str)

    # INSERT IGNORE via SELECT FROM fin_titulos (só insere se legacy_id existe + idem_key novo)
    sql.append(f"""INSERT IGNORE INTO cobrancas
  (business_id, tipo, status, valor_centavos, vencimento, contact_id,
   payer_name, payer_cpf_cnpj, descricao, idempotency_key,
   origem_type, origem_id, forma_pagamento, payload_gateway,
   created_at, updated_at)
SELECT 1, 'boleto', {esc(status)}, {valor_cents}, {esc(venc_str)}, t.cliente_id,
       c.name, c.tax_number,
       CONCAT('Boleto legacy WR2 #', {esc(legacy_id)}),
       {esc(idem_key)},
       'avulsa', t.id, 'boleto', {esc(payload_json)},
       NOW(), NOW()
FROM fin_titulos t
LEFT JOIN contacts c ON c.id=t.cliente_id
WHERE t.business_id=1 AND t.legacy_id={esc(legacy_id)}
LIMIT 1;""")
    count_with_link += 1

sql.append("")
sql.append("COMMIT;")
sql.append("")
sql.append("-- Validação")
sql.append("SELECT COUNT(*) AS cobrancas_firebird_inseridas FROM cobrancas")
sql.append("  WHERE business_id=1 AND idempotency_key LIKE 'wr2-boleto-fb-%';")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(sql))

con.close()
print(f"OK: SQL gerado em {OUT}")
print(f"   INSERTs: {count_with_link} (alguns podem virar no-op se fin_titulo não existir em prod biz=1)")
