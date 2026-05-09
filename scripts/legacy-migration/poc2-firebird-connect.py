"""
POC 2 — Conexão Firebird via registry HKCU\\Software\\Rocha.

Resolve um alias registrado pela ferramenta WR2 Office Comercial v3.5
(`Editor de Registros de Bancos de Dados`), conecta no Firebird, e
executa 2 queries-sonda:

  1. SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'
     → versão atual do schema (deve casar com algum UPDATE N; do POC 1)

  2. SELECT count(*) FROM RDB$RELATIONS WHERE RDB$SYSTEM_FLAG=0
     → total de tabelas usuárias

E lista as 20 primeiras tabelas pra calibrar a fase 3 (schema baseline).

Crítica:
- Roda APENAS no Windows do Wagner (registry HKCU + LAN servidor-crm)
- Exige fbclient.dll instalado (Firebird client) — Wagner já tem
- User SYSDBA herdado do Delphi (configurável via FIREBIRD_USER no .env)

Uso:
    python poc2-firebird-connect.py --alias ServidorWR2
    python poc2-firebird-connect.py --alias TechPressLocal
"""

from __future__ import annotations

import argparse
import os
import sys
import winreg
from pathlib import Path

# Força stdout/stderr UTF-8 — Windows console default é cp1252 e crasha em emojis
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

try:
    import firebird.driver as fb
except ImportError:
    print(
        "❌ firebird-driver não instalado. Rode:\n"
        "   pip install -r requirements.txt",
        file=sys.stderr,
    )
    sys.exit(2)


def read_registry_alias(app_title: str, alias: str) -> tuple[str, str]:
    """Lê path+senha do registry pra um alias do WR Office Comercial.

    Estrutura confirmada em backup\\backup2\\Principal.pas:3482:
        HKCU\\Software\\Rocha\\<APP_TITLE>\\Banco\\Caminhos       (props = aliases)
        HKCU\\Software\\Rocha\\<APP_TITLE>\\Banco\\Caminhos\\Senhas (props = paths)
    """
    caminhos_key = rf"Software\Rocha\{app_title}\Banco\Caminhos"
    senhas_key = rf"Software\Rocha\{app_title}\Banco\Caminhos\Senhas"

    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, caminhos_key) as k:
            path, _ = winreg.QueryValueEx(k, alias)
    except FileNotFoundError as e:
        raise RuntimeError(
            f"Alias '{alias}' não encontrado em {caminhos_key}. "
            f"Verifique no app Editor de Registros de Bancos de Dados."
        ) from e

    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, senhas_key) as k:
            password, _ = winreg.QueryValueEx(k, path)
    except FileNotFoundError:
        # Alguns aliases podem não ter senha cadastrada (banco sem auth)
        password = ""

    return path, password


def parse_firebird_path(raw_path: str) -> str:
    """Converte path-do-registry pro formato firebird-driver.

    Registry guarda paths em 2 formatos:
      - Local:  D:\\DadosClientes\\Techpress\\BANCO.FDB
      - Remoto: servidor-crm:D:\\DadosClientes\\X\\BANCO.FDB
              ou apenas: servidor-crm:Banco (instância raiz, alias FB)

    firebird-driver aceita esses formatos diretamente — apenas retornamos.
    """
    return raw_path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--alias",
        default="ServidorWR2",
        help="Alias do banco no registry (default: ServidorWR2 = banco do Wagner)",
    )
    parser.add_argument(
        "--app-title",
        default=os.environ.get("WR_APP_TITLE", "Office Comercial"),
        help="ApplicationTitle do app WR (default: Office Comercial)",
    )
    parser.add_argument(
        "--user",
        default=os.environ.get("FIREBIRD_USER", "SYSDBA"),
        help="User Firebird (default: SYSDBA)",
    )
    parser.add_argument(
        "--charset",
        default=os.environ.get("FIREBIRD_CHARSET", "WIN1252"),
        help="Charset Firebird (default: WIN1252)",
    )
    parser.add_argument(
        "--password",
        default=os.environ.get("FIREBIRD_PASSWORD"),
        help=(
            "Override da senha do registry. Backup\\Principal.pas:3448 hardcoda "
            "'masterkey' sob {$IFDEF WR2} — use --password masterkey se a senha do "
            "registry for placeholder de 1 caractere."
        ),
    )
    parser.add_argument(
        "--list-tables",
        type=int,
        default=20,
        help="Quantas tabelas listar (default: 20)",
    )
    args = parser.parse_args()

    # Passo 1: registry
    print(f"📖 Resolvendo alias '{args.alias}' no registry...")
    try:
        raw_path, password = read_registry_alias(args.app_title, args.alias)
    except RuntimeError as e:
        print(f"❌ {e}", file=sys.stderr)
        return 1

    fb_path = parse_firebird_path(raw_path)
    print(f"   Path  : {fb_path}")

    # Override de senha (CLI > registry)
    if args.password:
        print(f"   Senha : *** (override via --password/FIREBIRD_PASSWORD)")
        password = args.password
    else:
        print(f"   Senha : {'*' * len(password) if password else '(vazia)'} (do registry)")

    # Passo 2: conecta
    print(f"\n🔌 Conectando como {args.user}@{fb_path} (charset={args.charset})...")
    try:
        con = fb.connect(
            database=fb_path,
            user=args.user,
            password=password,
            charset=args.charset,
        )
    except Exception as e:
        print(f"❌ Falha na conexão: {e!r}", file=sys.stderr)
        print(
            "\nDicas:\n"
            "  - fbclient.dll está no PATH? (deve estar instalado pelo Delphi)\n"
            "  - servidor-crm é resolvível na sua LAN? (ping servidor-crm)\n"
            "  - User/senha corretos? (cf. registry HKCU\\Software\\Rocha\\Office Comercial\\Banco\\Caminhos\\Senhas)\n"
            "  - Charset alternativo? Tenta --charset NONE ou --charset UTF8",
            file=sys.stderr,
        )
        return 1

    print("✅ Conectado")

    # Passo 3: VERSAO_BANCO
    print("\n=== Sonda 1 — versão do schema ===")
    cur = con.cursor()
    try:
        cur.execute(
            "SELECT VALOR FROM CONFIGURACOES WHERE CONFIG = 'VERSAO_BANCO'"
        )
        row = cur.fetchone()
        if row is None:
            print("⚠️  Nenhuma linha CONFIG='VERSAO_BANCO' em CONFIGURACOES")
            versao_banco = None
        else:
            versao_banco = row[0]
            print(f"   VERSAO_BANCO = {versao_banco}")
    except Exception as e:
        print(f"❌ Falha sonda 1: {e!r}")
        versao_banco = None

    # Passo 4: contagem de tabelas
    print("\n=== Sonda 2 — tabelas usuárias ===")
    try:
        cur.execute(
            "SELECT COUNT(*) FROM RDB$RELATIONS WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NULL"
        )
        total_tabelas = cur.fetchone()[0]
        print(f"   Total de tabelas (não-views, não-sistema): {total_tabelas}")

        cur.execute(
            "SELECT COUNT(*) FROM RDB$RELATIONS WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NOT NULL"
        )
        total_views = cur.fetchone()[0]
        print(f"   Total de views                            : {total_views}")
    except Exception as e:
        print(f"❌ Falha sonda 2: {e!r}")
        total_tabelas = 0
        total_views = 0

    # Passo 5: lista N tabelas
    if args.list_tables > 0:
        print(f"\n=== Primeiras {args.list_tables} tabelas (alfabética) ===")
        try:
            cur.execute(
                f"""
                SELECT FIRST {args.list_tables}
                       TRIM(RDB$RELATION_NAME)
                FROM RDB$RELATIONS
                WHERE RDB$SYSTEM_FLAG = 0
                  AND RDB$VIEW_BLR IS NULL
                ORDER BY RDB$RELATION_NAME
                """
            )
            for (name,) in cur.fetchall():
                print(f"  {name}")
        except Exception as e:
            print(f"❌ Falha listagem: {e!r}")

    # Passo 6: validação cruzada (se POC 1 já rodou)
    out_dir = Path(os.environ.get("OUTPUT_DIR", "output"))
    parsed_json = out_dir / "updatesql-parsed.json"
    if parsed_json.is_file() and versao_banco is not None:
        import json
        with parsed_json.open(encoding="utf-8") as f:
            data = json.load(f)
        max_version = max(int(k) for k in data.keys() if int(k) < 1999)
        print(f"\n=== Validação cruzada com POC 1 ===")
        print(f"   Schema do banco       : {versao_banco}")
        print(f"   Schema máximo no .txt : {max_version}")
        try:
            v = int(versao_banco)
            if v > max_version:
                print(
                    f"   ⚠️  Banco tem versão {v} maior que o .txt local ({max_version}) — "
                    "seu UpdateSQL.txt está desatualizado vs. o que rodou no banco"
                )
            elif v < max_version:
                print(
                    f"   ℹ️  Banco em {v}, .txt em {max_version} — "
                    f"{max_version - v} updates pendentes (normal pra cliente sem upgrade recente)"
                )
            else:
                print(f"   ✅ Banco e .txt sincronizados em {v}")
        except (ValueError, TypeError):
            print(f"   ⚠️  VERSAO_BANCO não é integer: {versao_banco!r}")

    cur.close()
    con.close()
    print("\n✅ POC 2 concluído sem erros")
    return 0


if __name__ == "__main__":
    sys.exit(main())
