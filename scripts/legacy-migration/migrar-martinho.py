"""
Wrapper orchestrator MARTINHO CAÇAMBAS biz=164.

Roda os 3 importers Martinho v1404 em sequência com SSH tunnel Hostinger:
  1. import-contacts-from-venda.py (clientes inline VENDA — CRM órfão)
  2. import-financeiro.py (FIN_RECEBER + FIN_PAGAR + write-off detection)
  3. import-vendas.py (filtro --start-date / --end-date, default últimos 12m)

Skips opcionais: --skip-contacts, --skip-financeiro, --skip-vendas.

Uso:
    python migrar-martinho.py --target dry-run
    python migrar-martinho.py --target prod --confirm
    python migrar-martinho.py --target prod --confirm --skip-financeiro  # só contacts+vendas
    python migrar-martinho.py --target prod --confirm --start-date 2024-05-14  # vendas desde

Pareado com import-vehicles.py (já LIVE 2026-05-13 — 91 caçambas biz=164).
"""

from __future__ import annotations

import argparse
import os
import subprocess
import sys
import time
from datetime import date, timedelta
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
    cmd = [
        "ssh", "-4", "-i", SSH_KEY, "-p", SSH_PORT,
        "-N",
        "-L", f"127.0.0.1:{TUNNEL_PORT}:127.0.0.1:3306",
        "-o", "ConnectTimeout=30",
        "-o", "ServerAliveInterval=10",
        "-o", "ServerAliveCountMax=200",
        "-o", "ExitOnForwardFailure=yes",
        "-o", "AddressFamily=inet",
        SSH_HOST,
    ]
    print(f"[ssh] Abrindo tunnel localhost:{TUNNEL_PORT} -> Hostinger MySQL...")
    p = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    for _ in range(20):
        time.sleep(0.5)
        try:
            import socket
            s = socket.create_connection(("127.0.0.1", TUNNEL_PORT), timeout=1)
            s.close()
            print(f"   [ok] Tunnel ready")
            return p
        except Exception:
            pass
    p.kill()
    raise RuntimeError("SSH tunnel nao abriu em 10s")


def run_script(name: str, args: list[str], env: dict) -> int:
    print(f"\n{'='*70}")
    print(f"[run] {name} {' '.join(a if not _is_secret(a) else '<REDACTED>' for a in args)}")
    print('='*70)
    cmd = [sys.executable, str(HERE / name), *args]
    r = subprocess.run(cmd, env={**os.environ, **env})
    return r.returncode


def _is_secret(arg: str) -> bool:
    return False  # Não passamos senha em args (vai via env)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--target-business", type=int, default=164,
                        help="default 164 = Martinho")
    parser.add_argument("--alias", default="MartinhoServidor",
                        help="default MartinhoServidor")
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--skip-contacts", action="store_true")
    parser.add_argument("--skip-financeiro", action="store_true")
    parser.add_argument("--skip-vendas", action="store_true")
    parser.add_argument("--start-date", default=None,
                        help="default = hoje - 12 meses (vendas)")
    parser.add_argument("--end-date", default=None,
                        help="default = hoje (vendas)")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[err] --target prod requer --confirm explicito", file=sys.stderr)
        return 2

    # Default vendas: ultimos 12m
    today = date.today()
    if not args.start_date:
        args.start_date = (today - timedelta(days=365)).isoformat()
    if not args.end_date:
        args.end_date = today.isoformat()

    env: dict = {}
    tunnel: subprocess.Popen | None = None

    try:
        if args.target == "prod":
            print("[ssh] Lendo DB_PASSWORD via SSH .env Hostinger (sem ecoar)...")
            db_pass = fetch_db_password_via_ssh()
            if not db_pass:
                print("[err] DB_PASSWORD vazio - abortando", file=sys.stderr)
                return 4
            tunnel = open_tunnel()
            env = {
                "MYSQL_HOST": "127.0.0.1",
                "MYSQL_PORT": str(TUNNEL_PORT),
                "MYSQL_USER": "u906587222_oimpresso",
                "MYSQL_PASSWORD": db_pass,
                "MYSQL_DATABASE": "u906587222_oimpresso",
                "FIREBIRD_PASSWORD": "masterkey",
            }
        elif args.target == "local":
            env = {
                "MYSQL_HOST": "127.0.0.1",
                "MYSQL_PORT": "3306",
                "MYSQL_USER": "root",
                "MYSQL_PASSWORD": "",
                "MYSQL_DATABASE": "oimpresso",
                "FIREBIRD_PASSWORD": "masterkey",
            }

        # 1) Contacts (extraidos inline de VENDA)
        if not args.skip_contacts:
            common = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
            ]
            if args.confirm:
                common.append("--confirm")
            rc = run_script("import-contacts-from-venda.py", common, env)
            if rc != 0:
                print(f"[err] contacts falhou rc={rc}", file=sys.stderr)
                return rc

        # 2) Financeiro
        if not args.skip_financeiro:
            common = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
            ]
            if args.confirm:
                common.append("--confirm")
            rc = run_script("import-financeiro.py", common, env)
            if rc != 0:
                print(f"[err] financeiro falhou rc={rc}", file=sys.stderr)
                return rc

        # 3) Vendas (filtro periodo)
        if not args.skip_vendas:
            common = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
                "--start-date", args.start_date,
                "--end-date", args.end_date,
            ]
            if args.confirm:
                common.append("--confirm")
            rc = run_script("import-vendas.py", common, env)
            if rc != 0:
                print(f"[err] vendas falhou rc={rc}", file=sys.stderr)
                return rc

        print(f"\n[ok] Migracao MARTINHO concluida (target={args.target})")
        return 0

    finally:
        if tunnel:
            print("[ssh] Fechando SSH tunnel...")
            try:
                tunnel.terminate()
                tunnel.wait(timeout=5)
            except subprocess.TimeoutExpired:
                tunnel.kill()


if __name__ == "__main__":
    sys.exit(main())
