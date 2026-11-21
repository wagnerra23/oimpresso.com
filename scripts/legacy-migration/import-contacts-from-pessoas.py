"""
Fase 2-ter — Importer CONTACTS a partir do cadastro PESSOAS do Delphi WR Comercial.

Por que existe (gap medido em 2026-11-21, Sabor Brasil biz=199):
- `import-contacts-from-venda.py` só cria o cliente que aparece na VENDA **com
  CPF/CNPJ** (chave de dedup = documento). Quem foi cadastrado no Delphi sem
  documento fica de fora: no Sabor Brasil eram 134 dos 146 clientes que faltavam,
  e as vendas deles caíram no cliente padrão ("consumidor final").
- `import-contacts-from-nfe.py` só cria fornecedor de nota de entrada.
- Nenhum importer lia o cadastro PESSOAS diretamente.

Lógica:
- Lê PESSOAS (+ CIDADES para o nome da cidade).
- Papel → `contacts.type`: IS_CLI → customer · IS_FOR → supplier · os dois → both.
  Representante, funcionário e pessoa sem papel NÃO viram contato (o representante
  vira vendedor/usuário — outra fase). Ficam contados no relatório.
- Chave natural (`contacts.legacy_id`, varchar 32):
    * documento válido (11 ou 14 dígitos) → os dígitos, igual aos outros importers
      (então quem já entrou por eles é achado e NÃO duplica);
    * sem documento → `PES:<CODIGO>` (o CODIGO é a PK de PESSOAS). O prefixo impede
      colisão com documento e com o CODIGO da EMPRESA que o import-empresas grava.
- Idempotente: SELECT por (business_id, legacy_id) — e, com documento, também por
  tax_number. Achou → não sobrescreve cadastro; só promove o tipo
  (customer + supplier → both). Não achou → INSERT.
- Inativo no Delphi (ATIVO='N') entra como contact_status='inactive'.

FORA DE ESCOPO, de propósito (mexem em VALOR — regra mestre de proibicoes.md, exigem
dupla prova + antes→depois + ok [W] em PR próprio): % de comissão do representante,
% de desconto de pontualidade no boleto e tabela de preço do cliente. Também fora:
religar as vendas que hoje estão no cliente padrão (outro PR, depende deste).

Multi-tenant Tier 0 (ADR 0093): business_id em todo INSERT/UPDATE e em todo WHERE.
PII (LGPD): o SQL preview e o audit JSON não levam documento, telefone nem e-mail —
só o tamanho do documento e os 4 primeiros dígitos. A saída vai para output/
(gitignored).

Uso:
    # Dry-run: lê só o Firebird, gera SQL preview + audit, não toca o MySQL
    python import-contacts-from-pessoas.py --alias Camargo --target-business 199

    # Máquina sem o alias do Delphi no registry: caminho direto do banco
    python import-contacts-from-pessoas.py --target-business 199 \\
        --firebird-path "servidor-crm:D:\\DadosClientes\\Sabor Brasil\\Dados\\BANCO.FDB"

    # Produção (exige --confirm e aprovação [W] do dry-run)
    python import-contacts-from-pessoas.py --alias Camargo --target-business 199 \\
        --target prod --confirm

    # Teste das regras puras (sem banco)
    python import-contacts-from-pessoas.py --selftest

Refs:
    - import-contacts-from-venda.py / import-contacts-from-nfe.py — mesmo contrato de upsert
    - enrich-contacts-v2.py — mesmo mapeamento de endereço (address_line_1, CIDADES)
    - memory/research/clientes-legacy-officeimpresso/06-sabor-brasil/02-plano-migracao.md
    - ADR 0093 — Multi-tenant Tier 0
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

IMPORTER_VERSION = "0.1.0"
LEGACY_PREFIX_SEM_DOC = "PES:"

# Mascarados no SQL preview: pessoa física tem nome, documento, IE/RG e endereço como
# dado pessoal, e o Delphi às vezes grava o CPF dentro da razão social.
CAMPOS_PII = {
    "name", "supplier_business_name", "cpf_cnpj", "tax_number", "ie_rg",
    "address_line_1", "rua", "numero", "address_line_2", "bairro", "zip_code",
    "email", "mobile", "landline",
}

# Colunas lidas de PESSOAS. As ausentes nesta versão do banco viram NULL (adapter).
COLS_PESSOAS = [
    "CODIGO", "TIPO", "RAZAOSOCIAL", "FANTASIA", "CNPJCPF", "INSCIDENT",
    "ENDERECO", "NUMERO", "COMPLEMENTO", "BAIRRO", "CEP", "CODCIDADE", "UF",
    "EMAIL", "FONE1", "FONE2", "ATIVO", "IS_CLI", "IS_FOR", "IS_REP", "IS_FUN",
]


# ---------------------------------------------------------------- regras puras

def digits(raw) -> str:
    return re.sub(r"\D", "", str(raw or ""))


def documento_valido(raw) -> str | None:
    d = digits(raw)
    return d if len(d) in (11, 14) else None


def norm(raw, max_len: int | None = None) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    return s[:max_len] if max_len else s


def sim(v) -> bool:
    return str(v or "").strip().upper() == "S"


def papel(row: dict) -> str:
    """Papel da pessoa → 'customer' | 'supplier' | 'both' | motivo de pular."""
    cli, forn = sim(row.get("IS_CLI")), sim(row.get("IS_FOR"))
    if cli and forn:
        return "both"
    if cli:
        return "customer"
    if forn:
        return "supplier"
    if sim(row.get("IS_REP")):
        return "skip:representante"
    if sim(row.get("IS_FUN")):
        return "skip:funcionario"
    return "skip:sem_papel"


def chave_legada(row: dict) -> tuple[str, str | None]:
    """(legacy_id, documento|None). Sem documento: PES:<CODIGO>."""
    doc = documento_valido(row.get("CNPJCPF"))
    if doc:
        return doc[:32], doc
    codigo = norm(row.get("CODIGO"))
    if not codigo:
        raise ValueError("PESSOAS sem CODIGO")
    return f"{LEGACY_PREFIX_SEM_DOC}{codigo}"[:32], None


def tipo_tela(doc: str | None, tipo_delphi) -> str:
    """contact_type: CPF → person; CNPJ → business; sem documento → TIPO F/J do Delphi."""
    if doc:
        return "person" if len(doc) == 11 else "business"
    return "person" if str(tipo_delphi or "").strip().upper() == "F" else "business"


def tipo_combinado(atual: str | None, novo: str) -> str:
    """Promove o tipo sem nunca rebaixar: customer + supplier → both."""
    if not atual or atual == novo:
        return novo
    if "both" in (atual, novo):
        return "both"
    if {atual, novo} == {"customer", "supplier"}:
        return "both"
    return atual


def mapear(row: dict, business_id: int, cidade_nome: str | None) -> dict:
    legacy_id, doc = chave_legada(row)
    razao = norm(row.get("RAZAOSOCIAL"), 191)
    fantasia = norm(row.get("FANTASIA"), 191)
    nome = razao or fantasia or f"Cadastro Delphi {norm(row.get('CODIGO'))}"
    endereco = norm(row.get("ENDERECO"), 191)
    return {
        "business_id": business_id,
        "type": papel(row),
        "contact_type": tipo_tela(doc, row.get("TIPO")),
        "name": nome[:191],
        "supplier_business_name": fantasia or razao,
        "cpf_cnpj": doc,
        "tax_number": doc,
        "ie_rg": norm(row.get("INSCIDENT"), 18),
        "address_line_1": endereco,
        "rua": norm(row.get("ENDERECO"), 80),
        "numero": norm(row.get("NUMERO"), 10),
        "address_line_2": norm(row.get("COMPLEMENTO"), 191),
        "bairro": norm(row.get("BAIRRO"), 40),
        "zip_code": norm(row.get("CEP"), 20),
        "city": norm(cidade_nome, 191),
        "city_code": norm(row.get("CODCIDADE"), 7),
        "state": norm(row.get("UF"), 191),
        "country": "BRASIL",
        "email": norm(row.get("EMAIL"), 191),
        "mobile": norm(row.get("FONE1"), 191) or "",  # NOT NULL no schema
        "landline": norm(row.get("FONE2"), 191),
        "contact_status": "inactive" if str(row.get("ATIVO") or "").strip().upper() == "N" else "active",
        "legacy_id": legacy_id,
    }


def redigir_chave(legacy_id: str) -> str:
    if legacy_id.startswith(LEGACY_PREFIX_SEM_DOC):
        return legacy_id
    return f"[DOC{len(legacy_id)}-{legacy_id[:4]}...]"


# ---------------------------------------------------------------- selftest

def selftest() -> int:
    falhas = []

    def ok(cond, msg):
        if not cond:
            falhas.append(msg)

    base = {"CODIGO": "12-1", "TIPO": "O", "RAZAOSOCIAL": "MERCADO X", "ATIVO": "S"}
    ok(papel({**base, "IS_CLI": "S"}) == "customer", "cliente")
    ok(papel({**base, "IS_FOR": "S"}) == "supplier", "fornecedor")
    ok(papel({**base, "IS_CLI": "S", "IS_FOR": "S"}) == "both", "cliente e fornecedor")
    ok(papel({**base, "IS_REP": "S"}) == "skip:representante", "representante não vira contato")
    ok(papel({**base, "IS_FUN": "S"}) == "skip:funcionario", "funcionário não vira contato")
    ok(papel(base) == "skip:sem_papel", "sem papel")
    # IS_CLI nulo (cadastro antigo) não é cliente
    ok(papel({**base, "IS_CLI": None}) == "skip:sem_papel", "IS_CLI nulo")

    ok(chave_legada({**base, "CNPJCPF": "12.345.678/0001-90"}) == ("12345678000190", "12345678000190"), "CNPJ vira dígitos")
    ok(chave_legada({**base, "CNPJCPF": ""}) == ("PES:12-1", None), "sem documento vira PES:<CODIGO>")
    ok(chave_legada({**base, "CNPJCPF": "123"}) == ("PES:12-1", None), "documento inválido conta como sem documento")
    try:
        chave_legada({"CODIGO": None, "CNPJCPF": None})
        falhas.append("sem CODIGO e sem documento deveria falhar")
    except ValueError:
        pass

    ok(tipo_tela("12345678901", "J") == "person", "CPF é pessoa física")
    ok(tipo_tela("12345678000190", "F") == "business", "CNPJ é empresa")
    ok(tipo_tela(None, "F") == "person", "sem doc usa TIPO F")
    ok(tipo_tela(None, "O") == "business", "sem doc e TIPO O vira empresa")

    ok(tipo_combinado("customer", "supplier") == "both", "promove para both")
    ok(tipo_combinado("both", "customer") == "both", "não rebaixa both")
    ok(tipo_combinado("customer", "customer") == "customer", "mesmo tipo")

    m = mapear({**base, "IS_CLI": "S", "ATIVO": "N", "FONE1": None}, 199, "SÃO PAULO")
    ok(m["contact_status"] == "inactive", "inativo no Delphi")
    ok(m["mobile"] == "", "mobile NOT NULL vira vazio")
    ok(m["business_id"] == 199, "business_id do alvo")
    ok(m["legacy_id"] == "PES:12-1" and m["tax_number"] is None, "sem documento não grava documento")
    ok(m["name"] == "MERCADO X", "nome pela razão social")
    ok(mapear({"CODIGO": "9", "IS_CLI": "S"}, 199, None)["name"] == "Cadastro Delphi 9", "nome nunca vazio")

    ok(redigir_chave("12345678000190") == "[DOC14-1234...]", "documento redigido no audit")
    ok(redigir_chave("PES:12-1") == "PES:12-1", "código do Delphi não é PII")

    if falhas:
        print("SELFTEST FALHOU:", *falhas, sep="\n  - ")
        return 1
    print("SELFTEST OK (26 verificações)")
    return 0


# ---------------------------------------------------------------- execução

def sql_value(v) -> str:
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--selftest", action="store_true")
    p.add_argument("--alias", help="alias do Delphi no registry (HKCU)")
    p.add_argument("--firebird-path", help="caminho do banco, quando a máquina não tem o alias")
    p.add_argument("--target-business", type=int)
    p.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    p.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    p.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    p.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    p.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    p.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    p.add_argument("--confirm", action="store_true")
    p.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = p.parse_args()

    if args.selftest:
        return selftest()
    if not args.target_business:
        p.error("--target-business é obrigatório")
    if not (args.alias or args.firebird_path):
        p.error("informe --alias ou --firebird-path")
    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm explícito", file=sys.stderr)
        return 2

    from lib.firebird_reader import firebird_connect  # import tardio: o selftest não precisa do driver

    print(f"== Importer Contacts a partir de PESSOAS v{IMPORTER_VERSION} ==")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")

    linhas = []
    with firebird_connect(
        args.alias or "-", password_override=args.firebird_password, path_override=args.firebird_path,
    ) as fb:
        cur = fb.cursor()
        cur.execute("SELECT FIRST 1 * FROM PESSOAS")
        presentes = {d[0].strip() for d in cur.description or []}
        cur.close()
        ausentes = [c for c in COLS_PESSOAS if c not in presentes]
        if "CODIGO" in ausentes:
            print("[ERRO] PESSOAS sem a coluna CODIGO — versão de banco desconhecida", file=sys.stderr)
            return 4
        sel = ", ".join(f"P.{c}" if c in presentes else f"CAST(NULL AS VARCHAR(1)) AS {c}" for c in COLS_PESSOAS)

        cidade_join, cidade_sel = "", "CAST(NULL AS VARCHAR(1)) AS CIDADE_NOME"
        try:
            c2 = fb.cursor()
            c2.execute("SELECT FIRST 1 1 FROM CIDADES")
            c2.close()
            cidade_join = "LEFT JOIN CIDADES CID ON CID.CODIGO = P.CODCIDADE"
            cidade_sel = "CID.DESCRICAO AS CIDADE_NOME"
        except Exception:
            pass

        cur = fb.cursor()
        cur.execute(f"SELECT {sel}, {cidade_sel} FROM PESSOAS P {cidade_join} ORDER BY P.CODIGO")
        nomes = [d[0].strip() for d in cur.description]
        for t in cur:
            linhas.append(dict(zip(nomes, t)))
        cur.close()

    stats = Counter()
    registros, preview = [], [
        f"-- import-contacts-from-pessoas v{IMPORTER_VERSION} · {datetime.now(timezone.utc).isoformat()}",
        f"-- business={args.target_business} target={args.target}",
        "-- Dry-run: lista o que seria gravado. Não consulta o MySQL, então quem já existe",
        "-- na empresa aparece aqui e vira no-op (ou promoção de tipo) na execução real.",
        "",
    ]
    vistos = set()
    for row in linhas:
        stats["pessoas_lidas"] += 1
        tp = papel(row)
        if tp.startswith("skip:"):
            stats[tp] += 1
            continue
        try:
            data = mapear(row, args.target_business, row.get("CIDADE_NOME"))
        except ValueError:
            stats["skip:sem_codigo"] += 1
            continue
        if data["legacy_id"] in vistos:
            stats["skip:documento_repetido_no_delphi"] += 1
            continue
        vistos.add(data["legacy_id"])
        stats[f"tipo:{data['type']}"] += 1
        stats["sem_documento" if data["tax_number"] is None else "com_documento"] += 1
        stats[f"status:{data['contact_status']}"] += 1
        registros.append(data)

    con = None
    if args.target in ("local", "prod"):
        import pymysql
        import pymysql.cursors
        con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port, user=args.mysql_user,
            password=args.mysql_password, database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor, autocommit=False,
        )

    audit = []
    try:
        for data in registros:
            chave = redigir_chave(data["legacy_id"])
            if con is None:
                cols = [c for c, v in data.items() if v is not None]
                preview.append(f"-- {chave} tipo={data['type']} status={data['contact_status']}")
                preview.append(
                    f"INSERT INTO contacts ({', '.join(cols)}, created_by, created_at, updated_at) VALUES ("
                    + ", ".join(sql_value(chave) if c == "legacy_id"
                                else "'[PII]'" if c in CAMPOS_PII and data[c]
                                else sql_value(data[c]) for c in cols)
                    + f", {args.created_by}, NOW(), NOW());"
                )
                audit.append({"chave": chave, "acao": "insert_ou_existente", "tipo": data["type"]})
                continue
            with con.cursor() as w:
                if data["tax_number"]:
                    w.execute(
                        "SELECT id, type FROM contacts WHERE business_id=%s AND (legacy_id=%s OR tax_number=%s) "
                        "ORDER BY legacy_id=%s DESC LIMIT 1",
                        (args.target_business, data["legacy_id"], data["tax_number"], data["legacy_id"]),
                    )
                else:
                    w.execute(
                        "SELECT id, type FROM contacts WHERE business_id=%s AND legacy_id=%s LIMIT 1",
                        (args.target_business, data["legacy_id"]),
                    )
                existente = w.fetchone()
                if existente:
                    novo = tipo_combinado(existente["type"], data["type"])
                    if novo != existente["type"]:
                        w.execute(
                            "UPDATE contacts SET type=%s, updated_at=NOW() WHERE id=%s AND business_id=%s",
                            (novo, existente["id"], args.target_business),
                        )
                        stats["promovido_para_both"] += 1
                        audit.append({"chave": chave, "acao": "tipo", "de": existente["type"], "para": novo})
                    else:
                        stats["ja_existia"] += 1
                    continue
                d = {k: v for k, v in data.items() if v is not None}
                cols = list(d)
                w.execute(
                    f"INSERT INTO contacts ({', '.join(cols)}, created_by, created_at, updated_at) "
                    f"VALUES ({', '.join(['%s'] * len(cols))}, %s, NOW(), NOW())",
                    (*d.values(), args.created_by),
                )
                stats["inseridos"] += 1
                audit.append({"chave": chave, "acao": "insert", "tipo": data["type"]})
        if con:
            con.commit()
    except Exception:
        if con:
            con.rollback()
            print("[ERRO] rollback MySQL — nada foi gravado", file=sys.stderr)
        raise
    finally:
        if con:
            con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    suf = f"biz{args.target_business}-{ts}"
    if con is None:
        (out / f"dry-run-contacts-from-pessoas-{suf}.sql").write_text("\n".join(preview), encoding="utf-8")
    (out / f"audit-contacts-from-pessoas-{suf}.json").write_text(json.dumps({
        "importer_version": IMPORTER_VERSION, "business_id": args.target_business,
        "target": args.target, "colunas_ausentes": ausentes, "stats": dict(stats), "registros": audit,
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    print("\n== Relatório ==")
    for k in sorted(stats):
        print(f"  {k:40s} {stats[k]}")
    print(f"  saída em {out}/*-{suf}.*")
    return 0


if __name__ == "__main__":
    sys.exit(main())
