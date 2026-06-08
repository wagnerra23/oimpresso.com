"""Probe multiple alias variants for client banks."""
import sys
import firebird.driver as fb

CANDIDATES = [
    # Aliases simples (configurados em databases.conf do Firebird)
    "Vargas",
    "VargasAcessorios",
    "Extreme",
    "Gold",
    # Paths absolutos no servidor (caso aliases.conf nao tenha)
    r"D:\DadosClientes\Vargas\Dados\BANCO.FDB",
    r"D:\DadosClientes\Vargas\BANCO.FDB",
    r"D:\DadosClientes\VargasAcessorios\Dados\BANCO.FDB",
    r"D:\DadosClientes\Vargas Acessorios\Dados\BANCO.FDB",
    r"D:\DadosClientes\Extreme\Dados\BANCO.FDB",
    r"D:\DadosClientes\Gold\Dados\BANCO.FDB",
]

for cand in CANDIDATES:
    alias = f"192.168.0.55:{cand}"
    try:
        con = fb.connect(alias, user="SYSDBA", password="masterkey")
        cur = con.cursor()
        cur.execute("SELECT COUNT(*) FROM RDB$RELATIONS")
        n = cur.fetchone()[0]
        cur.execute("SELECT FIRST 1 RAZAOSOCIAL FROM PESSOAS WHERE TIPO='C' AND RAZAOSOCIAL IS NOT NULL")
        sample = cur.fetchone()
        sample_str = sample[0][:40] if sample and sample[0] else "(sem clientes)"
        print(f"OK   {cand!r:60s} tables={n:4d}  sample_cliente={sample_str}")
        con.close()
    except Exception as e:
        msg = str(e)[:80].replace("\n", " ")
        print(f"FAIL {cand!r:60s} {msg}")
