"""
Wrapper orchestrator — migra CONTAS + EMPRESAS Delphi → oimpresso.

Fluxo:
  1. Abre SSH tunnel local 127.0.0.1:33069 → Hostinger MySQL localhost:3306
  2. Lê DB_PASSWORD do .env Hostinger remoto (uma vez, no env, sem ecoar)
  3. Roda import-contas-bancarias.py (com --reset-placeholders + --only-ativo)
  4. Roda import-empresas.py
  5. Fecha tunnel

Uso:
    python migrar-tudo.py --target dry-run
    python migrar-tudo.py --target prod --confirm
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
    parser.add_argument("--reset-placeholders", action="store_true", default=True,
                        help="default True — soft-delete placeholders biz alvo (Wagner '2 a')")
    parser.add_argument("--only-ativo", action="store_true", default=True,
                        help="default True — só CONTAS ATIVO='S' (Wagner '1 c' = 19 contas)")
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

        # 1) Contas
        if not args.skip_contas:
            common = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
            ]
            if args.only_ativo:
                common.append("--only-ativo")
            if args.reset_placeholders:
                common.append("--reset-placeholders")
            if args.confirm:
                common.append("--confirm")
            rc = run_script("import-contas-bancarias.py", common, env)
            if rc != 0:
                print(f"❌ contas falhou rc={rc}", file=sys.stderr)
                return rc

        # 2) Empresas
        if not args.skip_empresas:
            common = [
                "--alias", args.alias,
                "--target-business", str(args.target_business),
                "--target", args.target,
            ]
            if args.confirm:
                common.append("--confirm")
            rc = run_script("import-empresas.py", common, env)
            if rc != 0:
                print(f"❌ empresas falhou rc={rc}", file=sys.stderr)
                return rc

        print(f"\n🎉 Migração concluída (target={args.target})")
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
