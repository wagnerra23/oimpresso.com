"""
Fase 5 — Importer Python: contas bancárias Delphi WR Comercial → Laravel oimpresso.

Aplica MAPPING.md (memory/dominios/wr-comercial/modulos/financeiro/MAPPING.md).
Lê Firebird via registry HKCU. Escreve em MySQL via 3 modos:
  - --target dry-run (default): só gera SQL, não executa
  - --target local: executa contra oimpresso.test (Herd MySQL local Wagner)
  - --target prod: executa contra Hostinger (Remote MySQL whitelist Wagner IP)

UPSERT idempotente — re-rodar é seguro.

Uso:
    # Dry-run primeiro pra validar SQL gerado
    python import-contas-bancarias.py --alias ServidorWR2 --target-business 1

    # Executar local (Herd) após dry-run validado
    python import-contas-bancarias.py --alias ServidorWR2 --target-business 1 --target local

    # Prod requer --confirm explícito
    python import-contas-bancarias.py --alias ServidorWR2 --target-business 1 --target prod --confirm

Limitações desta versão (0.1.0):
  - Importa apenas CONTAS → accounts + fin_contas_bancarias
  - Não importa BOLETOS / FINANCEIRO_BOLETO_HISTORICO (decisão pendente Wagner)
  - Não importa BANCOS_CONCILIACAO_BANCARIA (decisão pendente Wagner)
  - Não migra segredos bancários (CLIENTSECRET, KEYFILE, CERTFILE) — exige
    Vaultwarden integration (decisão pendente Wagner)
"""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

# Força UTF-8 stdout no Windows
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# Adiciona lib/ ao path
sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect, get_versao_banco, query  # noqa: E402
from lib.mysql_writer import MysqlWriter  # noqa: E402

IMPORTER_VERSION = "0.2.0"
LEGACY_SOURCE = "wr-comercial-delphi"

# Mapping TIPO Delphi → account_types.id (biz=1 default UltimatePOS)
# IDs 1=Banco, 2=Caixa criados em 2021. ID=3 "Cartão de Crédito" criado on-demand pelo importer.
TIPO_TO_ACCOUNT_TYPE_NAME = {
    "BANCO": "Banco",
    "CAIXA": "Caixa",
    "CARTA": "Cartão de Crédito",
}


def query_contas_delphi(
    con, codempresa: int | None = None, only_ativo: bool = False,
) -> list[dict]:
    """Busca CONTAS no Firebird, opcionalmente filtrando por CODEMPRESA e ATIVO.

    Usa SELECT * porque schema reconstruído de UpdateSQL.txt cobre v6+ mas
    CONTAS foi criada antes (provável v6 ou earlier — fora do .txt). Schema
    vivo é a fonte da verdade. .get(col, default) no map_conta_to_oimpresso
    tolera ausência de colunas.
    """
    sql = "SELECT * FROM CONTAS"
    where = []
    params: list = []
    if codempresa:
        where.append("CODEMPRESA = ?")
        params.append(codempresa)
    if only_ativo:
        where.append("ATIVO = 'S'")
    if where:
        sql += " WHERE " + " AND ".join(where)
    sql += " ORDER BY CODIGO"
    return query(con, sql, tuple(params))


def query_empresa_delphi(con, codempresa: int) -> dict | None:
    """Lookup EMPRESA(CODIGO) — retorna {CNPJCPF, RAZAOSOCIAL, ...} ou None.

    Convenção FK ([CONVENCOES.md §1]): CODEMPRESA → EMPRESA(CODIGO).
    """
    rows = query(
        con,
        "SELECT FIRST 1 CODIGO, CNPJCPF, RAZAOSOCIAL FROM EMPRESA WHERE CODIGO = ?",
        (codempresa,),
    )
    return rows[0] if rows else None


def normalize_banco_codigo(codbanco: int | str | None) -> str:
    """CODBANCO Delphi É o código FEBRABAN (validado: 104=Caixa, 341=Itaú).

    Convenção FK ([CONVENCOES.md §1]): CODBANCO → BANCOS(CODIGO).
    BANCOS.CODIGO é PK = código FEBRABAN.

    Formata pra 3 dígitos com leading zeros pra match com fin_contas_bancarias.banco_codigo.
    """
    if codbanco is None:
        return "000"
    try:
        return f"{int(codbanco):03d}"
    except (ValueError, TypeError):
        return "000"


def map_conta_to_oimpresso(
    delphi: dict, business_id: int,
    empresa_cache: dict[int, dict] | None = None,
    account_type_id_map: dict[str, int] | None = None,
) -> tuple[dict, dict, str, dict]:
    """Aplica mapping CONTAS Delphi → (account_data, fin_cb_data, legacy_id, metadata).

    Veja MAPPING.md memory/dominios/wr-comercial/modulos/financeiro/MAPPING.md.
    Usa convenção CONVENCOES.md §1 — COD<TABELA> = FK (CODBANCO→BANCOS,
    CODEMPRESA→EMPRESA).
    """
    legacy_id = str(delphi["CODIGO"])

    # Lookup EMPRESA via CODEMPRESA FK (cache pra evitar N+1)
    empresa: dict | None = None
    if delphi.get("CODEMPRESA") and empresa_cache is not None:
        empresa = empresa_cache.get(int(delphi["CODEMPRESA"]))

    # accounts (core UltimatePOS) — nome amigável: DESCRICAO Delphi (nome curto Wagner) >
    # NOME_CEDENTE (razão social no boleto) > EMPRESA.RAZAOSOCIAL > fallback Legacy {id}
    nome_conta = (
        delphi.get("DESCRICAO")
        or delphi.get("NOME_CEDENTE")
        or (empresa and empresa.get("RAZAOSOCIAL"))
        or f"Conta legacy {delphi.get('CODIGO_CEDENTE') or delphi['CODIGO']}"
    )
    # account_type_id: mapeamento automático TIPO Delphi (CAIXA/BANCO/CARTA) → account_types.id
    tipo_delphi = str(delphi.get("TIPO") or "").strip().upper()
    tipo_name = TIPO_TO_ACCOUNT_TYPE_NAME.get(tipo_delphi)
    account_type_id = (account_type_id_map or {}).get(tipo_name) if tipo_name else None

    account_data = {
        "business_id": business_id,
        "name": str(nome_conta).strip()[:255] if nome_conta else f"Legacy {legacy_id}",
        "account_number": str(
            delphi.get("CONTA") or delphi.get("CODIGO_CEDENTE") or delphi["CODIGO"]
        )[:64],
        "is_closed": delphi.get("ATIVO", "S") != "S",
        "account_type_id": account_type_id,
    }

    # fin_contas_bancarias (módulo Financeiro)
    # CODBANCO Delphi É FEBRABAN — convenção FK + validado em ServidorWR2 (104=Caixa, 341=Itaú)
    banco_codigo = normalize_banco_codigo(delphi.get("CODBANCO"))
    beneficiario_doc = (empresa and empresa.get("CNPJCPF")) or ""
    beneficiario_razao = (
        delphi.get("NOME_CEDENTE")
        or (empresa and empresa.get("RAZAOSOCIAL"))
        or ""
    )

    fin_cb_data = {
        "business_id": business_id,
        "banco_codigo": banco_codigo,
        "agencia": str(delphi.get("AGENCIA") or "")[:10],
        "agencia_dv": (str(delphi.get("DIGITO_AG"))[:2] if delphi.get("DIGITO_AG") else None),
        "conta_dv": (
            str(delphi.get("DIGITO_CC") or delphi.get("AGENCIA_CONTA_DV") or "")[:2]
            or None
        ),
        "carteira": str(delphi.get("CARTEIRA") or "")[:10],
        "convenio": (str(delphi.get("TIPO_CONVENIO") or "")[:30] or None),
        "codigo_cedente": (str(delphi.get("CODIGO_CEDENTE") or "")[:30] or None),
        "beneficiario_documento": str(beneficiario_doc)[:18],
        "beneficiario_razao_social": str(beneficiario_razao)[:150],
        "ativo_para_boleto": delphi.get("ATIVO", "S") == "S",
    }

    # metadata JSON — captura TUDO mais
    legacy_metadata = {
        "delphi_legacy": {
            "codbanco_delphi": delphi.get("CODBANCO"),
            "codbanco_configuracao": delphi.get("CODBANCO_CONFIGURACAO"),
            "codigo_transmissao": delphi.get("CODIGO_TRANSMISSAO"),
            "carteira_gera_remessa": delphi.get("CARTEIRA_GERA_REMESSA"),
            "variacao_gera_remessa": delphi.get("VARIACAO_GERA_REMESSA"),
            "layout_arquivo": delphi.get("LAYOUT_ARQUIVO"),
            "carac_titulo": delphi.get("CARAC_TITULO"),
            "executa_arquivo_retorno": delphi.get("EXECUTA_ARQUIVO_RETORNO"),
            "tipo_carteira_manual": delphi.get("TIPO_CARTEIRA_MANUAL"),
            "responsavel_emissao": delphi.get("RESPONSAVEL_EMISSAO"),
            "tolerancia": delphi.get("TOLERANCIA"),
            "ignorar_retorno_sem_liquidacao": delphi.get("IGNORAR_RETORNO_SEM_LIQUIDACAO"),
            "gera_debito_tarifa": delphi.get("GERA_DEBITO_TARIFA"),
            "baixa_devolucao": delphi.get("BAIXA_DEVOLUCAO"),
            "status": delphi.get("STATUS"),
            "especie": delphi.get("ESPECIE"),
            "codconta_vinculada": delphi.get("CODCONTA_VINCULADA"),
            "codconta_transferencia_auto": delphi.get("CODCONTA_TRANSFERENCIA_AUTO"),
            "variacao_carteira": delphi.get("VARIACAO_CARTEIRA"),
            "multa_dias_tolerancia": delphi.get("MULTA_DIAS_TOLERANCIA"),
            "desconto": float(delphi["DESCONTO"]) if delphi.get("DESCONTO") else None,
            "dia_desconto": delphi.get("DiaDESCONTO"),
        },
        "cooperativa": {
            "is_cooperativa": delphi.get("COOPERATIVA") == "S",
            "agencia": delphi.get("AGENCIA_COOPERATIVA"),
            "conta": delphi.get("CONTA_COOPERATIVA"),
            "digito_ag": delphi.get("DIGITO_AG_COOPERATIVA"),
            "digito_cc": delphi.get("DIGITO_CC_COOPERATIVA"),
            "codigo_cedente": delphi.get("CODIGO_CEDENTE_COOPERATIVA"),
        } if delphi.get("COOPERATIVA") == "S" else None,
        "boleto_email": {
            "assunto": delphi.get("EMAIL_ASSUNTO"),
            "exibir_documento": delphi.get("EMAIL_EXIBIR_DOCUMENTO"),
            "exibir_vencimento": delphi.get("EMAIL_EXIBIR_VENCIMENTO"),
            "exibir_nota": delphi.get("EMAIL_EXIBIR_NOTA"),
            "exibir_valor": delphi.get("EMAIL_EXIBIR_VALOR"),
            "exibir_historico": delphi.get("EMAIL_EXIBIR_HISTORICO"),
            "tipo_exibicao_dados": delphi.get("EMAIL_TIPO_EXIBICAO_DADOS"),
            "codemail_modelo": delphi.get("CODEMAIL_MODELO"),
        },
        "boleto_mensagens": {
            "protesto": delphi.get("MENSAGEM_PROTESTO"),
            "multa": delphi.get("MENSAGEM_MULTA"),
            "juros": delphi.get("MENSAGEM_JUROS"),
            "desconto": delphi.get("MENSAGEM_DESCONTO"),
        },
        "pix": {
            "chave": delphi.get("PIX"),
            "indicador": delphi.get("INDICADORPIX"),
        } if delphi.get("PIX") else None,
        "ws_bancario": {
            "tem_ws": delphi.get("TEM_WS"),
            "scopo": delphi.get("WS_SCOPO"),
            "endereco": delphi.get("ENDERECO"),
            "versao_arquivo": delphi.get("VERSAO_ARQUIVO"),
            "versao_layout": delphi.get("VERSAO_LAYOUT"),
        } if delphi.get("TEM_WS") == "S" else None,
        "credenciais_warning": (
            "APPKEY presente em CONTAS — não migrada nesta versão. "
            "Wagner decide Vaultwarden integration."
            if delphi.get("APPKEY") else None
        ),
        "import_meta": {
            "imported_from_alias": "(passed via --alias)",
            "imported_at_iso": "(set at runtime)",
        },
    }

    return account_data, fin_cb_data, legacy_id, legacy_metadata


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias do banco no registry (ex: ServidorWR2)")
    parser.add_argument("--target-business", type=int, required=True, help="business_id no oimpresso (ex: 1)")
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"),
                        help="Senha Firebird (default 'masterkey' — hardcoded em Principal.pas {$IFDEF WR2})")
    parser.add_argument("--codempresa", type=int, default=None,
                        help="Filtrar CONTAS por CODEMPRESA Delphi (default: todas)")
    parser.add_argument("--only-ativo", action="store_true",
                        help="Só importa CONTAS com ATIVO='S' (default: importa todas)")
    parser.add_argument("--reset-placeholders", action="store_true",
                        help="Soft-delete em accounts pré-existentes da biz alvo SEM bridge legacy_map "
                             "(limpa placeholders manuais antes de importar). Idempotente.")
    parser.add_argument("--limit", type=int, default=None, help="Limitar N primeiras (debug)")
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")),
                        help="users.id pra preencher accounts.created_by (default 1=admin)")
    parser.add_argument("--confirm", action="store_true",
                        help="OBRIGATÓRIO em --target prod. Sem isso, prod aborta.")
    parser.add_argument("--output-dir", default="output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"🚀 Importer v{IMPORTER_VERSION}")
    print(f"   Alias Firebird       : {args.alias}")
    print(f"   business_id alvo     : {args.target_business}")
    print(f"   Target MySQL         : {args.target}")
    if args.codempresa:
        print(f"   Filtro CODEMPRESA    : {args.codempresa}")
    if args.limit:
        print(f"   Limit                : {args.limit}")

    # 1) Conecta Firebird
    print(f"\n🔌 Conectando no Firebird...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        versao = get_versao_banco(fb_con)
        print(f"   Versão schema banco  : {versao or '?'}")

        # 2) Lê CONTAS
        print(f"\n📖 Lendo CONTAS (only_ativo={args.only_ativo})...")
        contas = query_contas_delphi(fb_con, codempresa=args.codempresa, only_ativo=args.only_ativo)
        if args.limit:
            contas = contas[: args.limit]
        print(f"   Encontradas: {len(contas)} contas")

        if not contas:
            print("⚠️  Nada a importar")
            return 0

        # 2.5) Pré-cache EMPRESA pra resolver CODEMPRESA → CNPJ + razão social
        # (convenção FK CONVENCOES.md §1)
        empresa_codigos = {c["CODEMPRESA"] for c in contas if c.get("CODEMPRESA")}
        empresa_cache: dict[int, dict] = {}
        if empresa_codigos:
            print(f"\n🔍 Carregando {len(empresa_codigos)} EMPRESA(s) (lookup FK CODEMPRESA)...")
            for codempresa in empresa_codigos:
                empresa = query_empresa_delphi(fb_con, codempresa)
                if empresa:
                    empresa_cache[int(codempresa)] = empresa
                    print(f"   EMPRESA {codempresa}: {empresa.get('RAZAOSOCIAL')!r} CNPJ={empresa.get('CNPJCPF')!r}")

        # 3) Abre writer MySQL (ou dry-run)
        print(f"\n💾 Abrindo writer ({args.target})...")
        writer = MysqlWriter(
            target=args.target,
            host=args.mysql_host,
            port=args.mysql_port,
            user=args.mysql_user,
            password=args.mysql_password,
            database=args.mysql_database,
            output_dir=Path(args.output_dir),
            importer_version=IMPORTER_VERSION,
        )

        # 4) Itera + UPSERT
        with writer:
            # 4.0) Pre-flight: garante account_types (Banco/Caixa/Cartão de Crédito) na biz alvo
            account_type_id_map = writer.ensure_account_types(
                business_id=args.target_business,
                names=list(set(TIPO_TO_ACCOUNT_TYPE_NAME.values())),
                created_by=args.created_by,
            )
            print(f"\n🏷️  account_types biz={args.target_business}: {account_type_id_map}")

            # 4.0.5) Reset placeholders (soft-delete em accounts SEM bridge legacy_map)
            if args.reset_placeholders:
                n = writer.soft_delete_placeholder_accounts(business_id=args.target_business)
                print(f"🧹 Soft-deleted {n} placeholders sem bridge legacy_map em biz={args.target_business}")

            for i, delphi in enumerate(contas, 1):
                try:
                    account_data, fin_cb_data, legacy_id, metadata = map_conta_to_oimpresso(
                        delphi, args.target_business,
                        empresa_cache=empresa_cache,
                        account_type_id_map=account_type_id_map,
                    )

                    print(f"\n[{i}/{len(contas)}] CODIGO={delphi['CODIGO']} → {account_data['name']}")

                    # account_details: agência/banco/empresa origem em texto curto pra Wagner ver na lista
                    bank_desc = ""
                    if delphi.get("CODBANCO"):
                        bank_desc = f"FEBRABAN {normalize_banco_codigo(delphi.get('CODBANCO'))}"
                    parts = [
                        delphi.get("AGENCIA") and f"Ag {delphi.get('AGENCIA')}" or None,
                        delphi.get("CONTA") and f"CC {delphi.get('CONTA')}" or None,
                        bank_desc or None,
                        (empresa_cache.get(int(delphi['CODEMPRESA'])) or {}).get("RAZAOSOCIAL")
                            if delphi.get("CODEMPRESA") else None,
                    ]
                    account_details = " | ".join(p for p in parts if p)[:500] or None

                    account_id = writer.upsert_account(
                        business_id=args.target_business,
                        name=account_data["name"],
                        account_number=account_data["account_number"],
                        account_details=account_details,
                        is_closed=account_data["is_closed"],
                        account_type_id=account_data["account_type_id"],
                        created_by=args.created_by,
                        legacy_source=LEGACY_SOURCE,
                        legacy_id=legacy_id,
                        legacy_metadata=metadata,
                    )

                    if account_id is None:
                        print(f"   ⚠️  account_id None — pulando fin_contas_bancarias")
                        continue

                    writer.upsert_fin_conta_bancaria(
                        business_id=args.target_business,
                        account_id=account_id,
                        legacy_source=LEGACY_SOURCE,
                        legacy_id=legacy_id,
                        banco_codigo=fin_cb_data["banco_codigo"],
                        agencia=fin_cb_data["agencia"],
                        agencia_dv=fin_cb_data["agencia_dv"],
                        conta_dv=fin_cb_data["conta_dv"],
                        carteira=fin_cb_data["carteira"],
                        convenio=fin_cb_data["convenio"],
                        codigo_cedente=fin_cb_data["codigo_cedente"],
                        beneficiario_documento=fin_cb_data["beneficiario_documento"],
                        beneficiario_razao_social=fin_cb_data["beneficiario_razao_social"],
                        ativo_para_boleto=fin_cb_data["ativo_para_boleto"],
                        metadata=metadata,
                    )

                except Exception as e:
                    writer.stats["errors"] += 1
                    print(f"   ❌ Erro: {e!r}", file=sys.stderr)

        # 5) Relatório final
        print(f"\n📊 Relatório final:")
        print(f"   Inserts : {writer.stats['inserts']}")
        print(f"   Updates : {writer.stats['updates']}")
        print(f"   Erros   : {writer.stats['errors']}")
        if args.target == "dry-run":
            print(f"   SQL     : output/dry-run-*.sql")
        print(f"\n✅ Importer concluído (v{IMPORTER_VERSION}, target={args.target})")

    return 0


if __name__ == "__main__":
    sys.exit(main())
