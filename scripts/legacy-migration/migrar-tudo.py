"""
Wrapper orchestrator — migra Delphi WR Comercial → oimpresso (pipeline completo).

Ordem canônica (dependências FK):
  1. CONTAS         → fin_contas_bancarias + accounts_legacy_map
  2. EMPRESA        → business + business_locations
  3. CONTACTS       → contacts (extraído de VENDA, dedup CNPJ)
  4. VENDAS         → transactions (ref_no = CODPEDIDO Delphi)
  5. FINANCEIRO     → fin_titulos + fin_titulo_baixas (lookup transactions + accounts)
  6. NOTAS_FISCAIS  → nfe_emissoes (lookup transactions via CODVENDA)

Fluxo de conexão:
  - target=prod: abre SSH tunnel 127.0.0.1:33069 → Hostinger MySQL, lê
                 DB_PASSWORD do .env remoto via SSH (sem ecoar)
  - target=local: usa MySQL local (Herd default ou env vars)
  - target=dry-run: não conecta MySQL (só Firebird leitura + audit JSON)

Uso:
    python migrar-tudo.py --target dry-run --target-business 164 --alias MartinhoServidor
    python migrar-tudo.py --target local --target-business 164 --alias MartinhoServidor
    python migrar-tudo.py --target prod --target-business 164 --alias MartinhoServidor --confirm

    # Skip steps específicos
    python migrar-tudo.py --target local --target-business 164 \\
        --skip-contas --skip-empresas  # já rodadas em sessão anterior

    # Filtro temporal (para todos os steps que aceitam)
    python migrar-tudo.py --target dry-run --target-business 164 \\
        --start-date 2024-12-01 --end-date 2024-12-31
"""

from __future__ import annotations

import argparse
import os
import signal
import subprocess
import sys
import time
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

HERE = Path(__file__).parent
TUNNEL_PORT = 33069
SSH_HOST = "u906587222@148.135.133.115"
SSH_PORT = "65002"
SSH_KEY = os.path.expanduser("~/.ssh/id_ed25519_oimpresso")


def fetch_db_password_via_ssh() -> str:
    """Lê DB_PASSWORD do .env Hostinger e retorna em memória (sem stdout)."""
    cmd = [
        "ssh", "-4", "-i", SSH_KEY, "-p", SSH_PORT,
        "-o", "ConnectTimeout=900",
        "-o", "ServerAliveInterval=3",
        "-o", "ServerAliveCountMax=200",
        SSH_HOST,
        "cd domains/oimpresso.com/public_html && grep '^DB_PASSWORD=' .env | cut -d'=' -f2- | tr -d '\"'",
    ]
    r = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return r.stdout.strip()


def open_tunnel() -> subprocess.Popen:
    """Abre SSH tunnel local em background. Retorna Popen pra kill depois."""
    cmd = [
        "ssh", "-4", "-i", SSH_KEY, "-p", SSH_PORT,
        "-N",  # no command, só tunnel
        # Bind local IPv4 explícito (127.0.0.1) — server MySQL Hostinger não tem GRANT pra ::1
        # Remote alvo 127.0.0.1 (não "localhost" — em alguns hosts resolve pra ::1)
        "-L", f"127.0.0.1:{TUNNEL_PORT}:127.0.0.1:3306",
        "-o", "ConnectTimeout=30",
        "-o", "ServerAliveInterval=10",
        "-o", "ServerAliveCountMax=200",
        "-o", "ExitOnForwardFailure=yes",
        "-o", "AddressFamily=inet",  # força IPv4 nas conexões SSH
        SSH_HOST,
    ]
    print(f"🔌 Abrindo SSH tunnel localhost:{TUNNEL_PORT} → Hostinger MySQL...")
    p = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    # Wait for tunnel to be ready (max 10s)
    for _ in range(20):
        time.sleep(0.5)
        try:
            import socket
            s = socket.create_connection(("127.0.0.1", TUNNEL_PORT), timeout=1)
            s.close()
            print(f"   ✅ Tunnel ready")
            return p
        except Exception:
            pass
    p.kill()
    raise RuntimeError("SSH tunnel não abriu em 10s")


def run_script(name: str, args: list[str], env: dict) -> int:
    """Roda script Python filho com env vars MySQL preenchidas."""
    print(f"\n{'='*70}")
    print(f"▶️  {name} {' '.join(args)}")
    print('='*70)
    cmd = [sys.executable, str(HERE / name), *args]
    r = subprocess.run(cmd, env={**os.environ, **env})
    return r.returncode


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--target-business", type=int, default=1)
    parser.add_argument("--alias", default="ServidorWR2")
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--skip-contas", action="store_true")
    parser.add_argument("--skip-empresas", action="store_true")
    parser.add_argument("--skip-contacts", action="store_true")
    parser.add_argument("--skip-produtos", action="store_true")
    parser.add_argument("--skip-vendas", action="store_true")
    parser.add_argument("--skip-venda-itens", action="store_true")
    parser.add_argument("--skip-financeiro", action="store_true")
    parser.add_argument("--skip-notas-fiscais", action="store_true")
    parser.add_argument("--reset-placeholders", action="store_true", default=True,
                        help="default True — soft-delete placeholders biz alvo (Wagner '2 a')")
    parser.add_argument("--only-ativo", action="store_true", default=True,
                        help="default True — só CONTAS ATIVO='S' (Wagner '1 c' = 19 contas)")
    parser.add_argument("--start-date", help="Filtro EMISSAO >= YYYY-MM-DD (vendas/financeiro/notas)")
    parser.add_argument("--end-date", help="Filtro EMISSAO <= YYYY-MM-DD (vendas/financeiro/notas)")
    parser.add_argument("--limit", type=int, default=0, help="Limita rows por step (0=sem limite)")
    parser.add_argument("--include-rejeitadas", action="store_true",
                        help="Notas fiscais: inclui rejeitadas (cstat=217)")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito", file=sys.stderr)
        return 2

    env: dict = {}
    tunnel: subprocess.Popen | None = None

    try:
        if args.target == "prod":
            print("🔐 Lendo DB_PASSWORD via SSH .env Hostinger (sem ecoar)...")
            db_pass = fetch_db_password_via_ssh()
            if not db_pass:
                print("❌ DB_PASSWORD vazio — abortando", file=sys.stderr)
                return 4
            tunnel = open_tunnel()
            # Bind IPv4 explícito 127.0.0.1 — alguns MySQL GRANTs negam ::1
            env = {
                "MYSQL_HOST": "127.0.0.1",
                "MYSQL_PORT": str(TUNNEL_PORT),
                "MYSQL_USER": "u906587222_oimpresso",
                "MYSQL_PASSWORD": db_pass,
                "MYSQL_DATABASE": "u906587222_oimpresso",
                "FIREBIRD_PASSWORD": "masterkey",
            }
        elif args.target == "local":
            # Herd default
            env = {
                "MYSQL_HOST": "127.0.0.1",
                "MYSQL_PORT": "3306",
                "MYSQL_USER": "root",
                "MYSQL_PASSWORD": "",
                "MYSQL_DATABASE": "oimpresso",
                "FIREBIRD_PASSWORD": "masterkey",
            }

        # Args comuns a todos os steps
        def base_args() -> list[str]:
            a = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
            ]
            if args.confirm:
                a.append("--confirm")
            return a

        def date_args() -> list[str]:
            a = []
            if args.start_date:
                a += ["--start-date", args.start_date]
            if args.end_date:
                a += ["--end-date", args.end_date]
            return a

        def limit_arg() -> list[str]:
            return ["--limit", str(args.limit)] if args.limit > 0 else []

        # 1) Contas bancárias → fin_contas_bancarias + accounts_legacy_map
        if not args.skip_contas:
            cmd = base_args()
            if args.only_ativo:
                cmd.append("--only-ativo")
            if args.reset_placeholders:
                cmd.append("--reset-placeholders")
            rc = run_script("import-contas-bancarias.py", cmd, env)
            if rc != 0:
                print(f"❌ contas falhou rc={rc}", file=sys.stderr)
                return rc

        # 2) Empresas → business + business_locations
        if not args.skip_empresas:
            rc = run_script("import-empresas.py", base_args(), env)
            if rc != 0:
                print(f"❌ empresas falhou rc={rc}", file=sys.stderr)
                return rc

        # 3) Contacts → contacts (dedup CNPJ extraído de VENDA)
        if not args.skip_contacts:
            rc = run_script("import-contacts-from-venda.py", base_args() + date_args(), env)
            if rc != 0:
                print(f"❌ contacts falhou rc={rc}", file=sys.stderr)
                return rc

        # 4) Produtos → products + variations (sem filtro de data — catálogo é global)
        if not args.skip_produtos:
            rc = run_script("import-produtos.py", base_args(), env)
            if rc != 0:
                print(f"❌ produtos falhou rc={rc}", file=sys.stderr)
                return rc

        # 5) Vendas → transactions (ref_no=CODPEDIDO — usado por financeiro/nfe/itens lookups)
        if not args.skip_vendas:
            rc = run_script("import-vendas.py", base_args() + date_args() + limit_arg(), env)
            if rc != 0:
                print(f"❌ vendas falhou rc={rc}", file=sys.stderr)
                return rc

        # 6) Venda itens → transaction_sell_lines (precisa de vendas + produtos)
        if not args.skip_venda_itens:
            rc = run_script("import-venda-itens.py", base_args() + date_args() + limit_arg(), env)
            if rc != 0:
                print(f"❌ venda-itens falhou rc={rc}", file=sys.stderr)
                return rc

        # 7) Financeiro → fin_titulos + fin_titulo_baixas
        if not args.skip_financeiro:
            rc = run_script("import-financeiro.py", base_args() + date_args() + limit_arg(), env)
            if rc != 0:
                print(f"❌ financeiro falhou rc={rc}", file=sys.stderr)
                return rc

        # 8) Notas Fiscais → nfe_emissoes
        if not args.skip_notas_fiscais:
            cmd = base_args() + date_args() + limit_arg()
            if args.include_rejeitadas:
                cmd.append("--include-rejeitadas")
            rc = run_script("import-notas-fiscais.py", cmd, env)
            if rc != 0:
                print(f"❌ notas-fiscais falhou rc={rc}", file=sys.stderr)
                return rc

        print(f"\n🎉 Migração concluída (target={args.target}, biz={args.target_business})")
        return 0

    finally:
        if tunnel:
            print("🔌 Fechando SSH tunnel...")
            try:
                tunnel.terminate()
                tunnel.wait(timeout=5)
            except subprocess.TimeoutExpired:
                tunnel.kill()


if __name__ == "__main__":
    sys.exit(main())
