"""
Daemon dual-sync — orchestrator persistente Martinho Caçambas biz=164 (Fase 1 MVP).

Roda os 6 importers Martinho v1404 em loop infinito + sleep adaptativo,
mantém SSH tunnel persistent pra Hostinger MySQL, escreve heartbeat,
faz reconnect on tunnel death, e usa sync_checkpoint pra delta-only sync.

Base reusada: migrar-martinho.py (one-shot wrapper).
Diferenças daemon:
  - Loop infinito com sleep 300s default
  - Per-type interval (contacts/produtos 900s · vendas/fin/estoque 300s · vehicles/compras 600s)
  - Off-hours (22h-6h BRT) → 30min sleep
  - Reconnect logic SSH tunnel — kill + reopen
  - Heartbeat file `output/daemon-heartbeat.json` (60s)
  - Alert webhook se 3 ciclos falham consecutivos
  - --once pra rodar 1 ciclo + termina (testes/cron)
  - --types pra subset de importers

Uso:
    # Loop infinito (Wagner deixa em janela cmd)
    python daemon-sync-martinho.py

    # Outro alias/business
    python daemon-sync-martinho.py --target-business 164 --alias MartinhoServidor

    # Roda 1 ciclo só (cron/teste)
    python daemon-sync-martinho.py --once

    # Sub-set
    python daemon-sync-martinho.py --types contacts,financeiro

Refs:
    - memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md §3
    - memory/requisitos/Crm/RUNBOOK-daemon-sync-officeimpresso.md (Wagner roda)
    - migrar-martinho.py (one-shot wrapper original)
"""

from __future__ import annotations

import argparse
import json
import os
import socket
import subprocess
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

HERE = Path(__file__).parent
OUTPUT_DIR = HERE / "output"
HEARTBEAT_PATH = OUTPUT_DIR / "daemon-heartbeat.json"
TUNNEL_PORT = 33069
SSH_HOST = "u906587222@148.135.133.115"
SSH_PORT = "65002"
SSH_KEY = os.path.expanduser("~/.ssh/id_ed25519_oimpresso")

# Per-type intervals (segundos) — alta freq pra dados quentes, baixa pra cold
# Wagner pode ajustar via env var DAEMON_INTERVAL_<TYPE>
DEFAULT_INTERVALS = {
    "contacts":                  900,   # 15min — clientes inline VENDA (pouca mudança)
    "contacts-fornecedores-nfe": 900,   # 15min — fornecedores via NFe entrada (gap detectado 2026-05-14)
    "financeiro":                300,   # 5min — Dani opera diariamente (canary 19/maio)
    "vendas":                    300,   # 5min — Lara opera diariamente
    "produtos":                  900,   # 15min — catálogo estável
    "estoque":                   300,   # 5min — movimentação chão de oficina
    "compras":                   600,   # 10min — fluxo médio (depende fornecedores resolvidos)
    "vehicles":                  1800,  # 30min — caçambas raramente mudam
}

# Off-hours: 22h-6h BRT → multiplicador 6x (5min → 30min)
OFF_HOURS_MULTIPLIER = 6

# 3 falhas consecutivas dispara alerta
ALERT_THRESHOLD_CONSECUTIVE_FAILURES = 3

# Tempos pra checagem tunnel saúde
TUNNEL_HEALTH_CHECK_TIMEOUT = 2  # segundos


# ----------------------------------------------------------------------------
# SSH tunnel — reusa pattern migrar-martinho.py
# ----------------------------------------------------------------------------


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
    print(f"[ssh] Abrindo tunnel localhost:{TUNNEL_PORT} -> Hostinger MySQL...", flush=True)
    p = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    for _ in range(40):  # 20s timeout
        time.sleep(0.5)
        if is_tunnel_alive():
            print("   [ok] Tunnel ready", flush=True)
            return p
    p.kill()
    raise RuntimeError("SSH tunnel não abriu em 20s")


def is_tunnel_alive() -> bool:
    """Ping 127.0.0.1:33069 — se conectar, tunnel vivo."""
    try:
        s = socket.create_connection(
            ("127.0.0.1", TUNNEL_PORT), timeout=TUNNEL_HEALTH_CHECK_TIMEOUT
        )
        s.close()
        return True
    except (OSError, socket.timeout):
        return False


def reconnect_tunnel(old_tunnel: subprocess.Popen | None) -> subprocess.Popen:
    """Kill old + open new. Retry safe."""
    if old_tunnel is not None:
        try:
            old_tunnel.terminate()
            old_tunnel.wait(timeout=5)
        except Exception:
            try:
                old_tunnel.kill()
            except Exception:
                pass
    return open_tunnel()


# ----------------------------------------------------------------------------
# Importer dispatcher
# ----------------------------------------------------------------------------


# Mapping sync_type → script name + args extras.
# Ordem natural FK (importer execution order):
#   contacts (clientes inline VENDA) →
#   contacts-fornecedores-nfe (fornecedores inline NFE entrada — gap 2026-05-14) →
#   produtos →
#   estoque →
#   vendas →
#   compras (depende contact_id resolvido pra fornecedores) →
#   financeiro
IMPORTER_MAPPING = {
    "contacts":                  {"script": "import-contacts-from-venda.py", "extra_args": []},
    "contacts-fornecedores-nfe": {"script": "import-contacts-from-nfe.py",   "extra_args": []},
    "financeiro":                {"script": "import-financeiro.py",          "extra_args": []},
    "vendas":                    {"script": "import-vendas.py",              "extra_args": []},
    "produtos":                  {"script": "import-produtos.py",            "extra_args": []},
    "estoque":                   {"script": "import-estoque.py",             "extra_args": []},
    "compras":                   {"script": "import-compras.py",             "extra_args": []},
    # vehicles intencionalmente fora — Fase 3 (vehicles) é one-shot (caçambas raramente mudam)
    # Wagner roda manual via migrar-martinho.py se precisar
}


def run_importer(
    sync_type: str,
    business_id: int,
    alias: str,
    target: str,
    confirm: bool,
    env: dict,
    log_dir: Path,
) -> tuple[int, str]:
    """Roda 1 importer + captura output. Retorna (rc, log_path_str).

    target='dry-run' pra teste · 'prod' pra Hostinger real.
    """
    mapping = IMPORTER_MAPPING.get(sync_type)
    if mapping is None:
        return (2, f"sync_type desconhecido: {sync_type}")

    script = mapping["script"]
    extra_args = mapping["extra_args"]

    args = [
        "--alias", alias,
        "--target-business", str(business_id),
        "--target", target,
        "--delta-since-last-sync",
        "--sync-type", sync_type,
        *extra_args,
    ]
    if confirm and target == "prod":
        args.append("--confirm")

    log_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    log_path = log_dir / f"daemon-{sync_type}-biz{business_id}-{ts}.log"

    cmd = [sys.executable, str(HERE / script), *args]
    print(f"[run] {sync_type} ({script}) target={target}", flush=True)
    with open(log_path, "w", encoding="utf-8") as lf:
        lf.write(f"# Daemon importer log\n")
        lf.write(f"# Started: {datetime.now().isoformat()}\n")
        lf.write(f"# Command: {' '.join(cmd)}\n")
        lf.write(f"# sync_type: {sync_type} biz: {business_id} target: {target}\n\n")
        lf.flush()
        r = subprocess.run(
            cmd,
            env={**os.environ, **env},
            stdout=lf,
            stderr=subprocess.STDOUT,
            timeout=3600,  # 1h timeout per importer (chunked)
        )
    return (r.returncode, str(log_path))


# ----------------------------------------------------------------------------
# Heartbeat
# ----------------------------------------------------------------------------


def write_heartbeat(
    current_sync_type: str | None,
    rows_processed_total: int,
    consecutive_failures: int,
    last_status: str,
) -> None:
    """Escreve heartbeat JSON pra Wagner verificar `Get-Content output/daemon-heartbeat.json`."""
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    data = {
        "last_alive": datetime.now(timezone.utc).isoformat(),
        "pid": os.getpid(),
        "current_sync_type": current_sync_type,
        "rows_processed_total": rows_processed_total,
        "consecutive_failures": consecutive_failures,
        "last_status": last_status,
    }
    try:
        HEARTBEAT_PATH.write_text(
            json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8"
        )
    except Exception as e:
        print(f"[heartbeat] WARN write falhou: {e!r}", file=sys.stderr)


# ----------------------------------------------------------------------------
# Alert (V0 — log local; webhook depois)
# ----------------------------------------------------------------------------


def fire_alert(sync_type: str, consecutive_failures: int, last_log: str) -> None:
    """3+ ciclos consecutivos falharam — log + futuro POST webhook."""
    alert_msg = (
        f"[ALERT] sync_type={sync_type} falhou {consecutive_failures}× consecutivos. "
        f"Último log: {last_log}. "
        f"Wagner: investigar Firebird + tunnel + .env."
    )
    print(alert_msg, file=sys.stderr, flush=True)
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    alert_log = OUTPUT_DIR / "daemon-alerts.log"
    with open(alert_log, "a", encoding="utf-8") as f:
        f.write(f"{datetime.now().isoformat()} | {alert_msg}\n")
    # TODO V1: POST oimpresso.com/api/internal/daemon-alert (criar endpoint)


# ----------------------------------------------------------------------------
# Sleep logic (per-type, off-hours)
# ----------------------------------------------------------------------------


def is_off_hours(now: datetime | None = None) -> bool:
    """22h-6h BRT considerado off-hours (sleep 6×).

    Sem usar pytz/zoneinfo — assume relógio local Wagner já BRT.
    """
    n = now or datetime.now()
    h = n.hour
    return h >= 22 or h < 6


def compute_sleep(sync_type: str, base_intervals: dict[str, int]) -> int:
    """Sleep adaptado per-type + off-hours."""
    base = base_intervals.get(sync_type, 300)
    if is_off_hours():
        return base * OFF_HOURS_MULTIPLIER
    return base


# ----------------------------------------------------------------------------
# Cycle (roda 1 vez por sync_type)
# ----------------------------------------------------------------------------


class CycleState:
    """Estado per-sync_type — tracking consecutive failures, last run."""

    def __init__(self):
        self.last_run_at: dict[str, datetime] = {}
        self.consecutive_failures: dict[str, int] = {}
        self.rows_total: int = 0


def should_run_type(state: CycleState, sync_type: str, intervals: dict[str, int]) -> bool:
    """Decide se já passou tempo suficiente desde último run."""
    last = state.last_run_at.get(sync_type)
    if last is None:
        return True
    interval = compute_sleep(sync_type, intervals)
    return (datetime.now() - last).total_seconds() >= interval


def run_cycle(
    state: CycleState,
    types: list[str],
    business_id: int,
    alias: str,
    target: str,
    confirm: bool,
    env: dict,
    intervals: dict[str, int],
) -> None:
    """Roda 1 ciclo (visita cada sync_type que está due)."""
    for sync_type in types:
        if not should_run_type(state, sync_type, intervals):
            continue

        # heartbeat antes
        write_heartbeat(
            current_sync_type=sync_type,
            rows_processed_total=state.rows_total,
            consecutive_failures=max(state.consecutive_failures.values(), default=0),
            last_status="running",
        )

        try:
            rc, log_path = run_importer(
                sync_type=sync_type,
                business_id=business_id,
                alias=alias,
                target=target,
                confirm=confirm,
                env=env,
                log_dir=OUTPUT_DIR,
            )
            state.last_run_at[sync_type] = datetime.now()
            if rc == 0:
                state.consecutive_failures[sync_type] = 0
                # rows count vem do importer audit JSON — V0 incrementa estimado
                state.rows_total += 1
                print(f"[ok] {sync_type} concluído rc={rc} | {log_path}", flush=True)
            else:
                state.consecutive_failures[sync_type] = state.consecutive_failures.get(sync_type, 0) + 1
                print(
                    f"[err] {sync_type} falhou rc={rc} consecutive={state.consecutive_failures[sync_type]} | {log_path}",
                    file=sys.stderr, flush=True,
                )
                if state.consecutive_failures[sync_type] >= ALERT_THRESHOLD_CONSECUTIVE_FAILURES:
                    fire_alert(sync_type, state.consecutive_failures[sync_type], log_path)
        except subprocess.TimeoutExpired:
            state.consecutive_failures[sync_type] = state.consecutive_failures.get(sync_type, 0) + 1
            print(f"[err] {sync_type} TIMEOUT", file=sys.stderr, flush=True)
        except Exception as e:
            state.consecutive_failures[sync_type] = state.consecutive_failures.get(sync_type, 0) + 1
            print(f"[err] {sync_type} exception: {e!r}", file=sys.stderr, flush=True)

        # heartbeat depois
        write_heartbeat(
            current_sync_type=None,
            rows_processed_total=state.rows_total,
            consecutive_failures=max(state.consecutive_failures.values(), default=0),
            last_status="idle",
        )


# ----------------------------------------------------------------------------
# Main loop
# ----------------------------------------------------------------------------


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--target-business", type=int, default=164,
                        help="default 164 = Martinho Caçambas")
    parser.add_argument("--alias", default="MartinhoServidor",
                        help="default MartinhoServidor (registry HKCU Wagner)")
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="prod",
                        help="default prod — daemon roda Hostinger real")
    parser.add_argument("--confirm", action="store_true", default=True,
                        help="auto-true pra daemon prod (revertido a False explicitamente em dry-run)")
    parser.add_argument("--once", action="store_true",
                        help="Roda 1 ciclo e termina (testes/cron)")
    parser.add_argument("--types", default=None,
                        help=f"Lista CSV de sync_types (default {list(IMPORTER_MAPPING.keys())})")
    parser.add_argument("--loop-sleep", type=int, default=60,
                        help="Sleep entre verificações 'is something due' (default 60s)")
    parser.add_argument("--no-tunnel", action="store_true",
                        help="Não abre SSH tunnel (usa MYSQL_HOST direto — útil em CT 100)")
    args = parser.parse_args()

    # Resolve types
    if args.types:
        types = [t.strip() for t in args.types.split(",") if t.strip()]
        unknown = [t for t in types if t not in IMPORTER_MAPPING]
        if unknown:
            print(f"[err] sync_types desconhecidos: {unknown}", file=sys.stderr)
            return 2
    else:
        types = list(IMPORTER_MAPPING.keys())

    # confirm sanidade: dry-run não precisa
    if args.target != "prod":
        args.confirm = False

    print("="*70, flush=True)
    print(f"== Daemon dual-sync — biz={args.target_business} alias={args.alias} ==", flush=True)
    print(f"   target={args.target} types={types}", flush=True)
    print(f"   once={args.once} no_tunnel={args.no_tunnel}", flush=True)
    print(f"   PID={os.getpid()} heartbeat={HEARTBEAT_PATH}", flush=True)
    print("="*70, flush=True)

    intervals = dict(DEFAULT_INTERVALS)
    # Permite override per-type via env var DAEMON_INTERVAL_VENDAS=120 etc
    for st in intervals.keys():
        ev = os.environ.get(f"DAEMON_INTERVAL_{st.upper()}")
        if ev:
            try:
                intervals[st] = int(ev)
                print(f"[env] DAEMON_INTERVAL_{st.upper()}={ev} (override)", flush=True)
            except ValueError:
                pass

    env: dict = {}
    tunnel: subprocess.Popen | None = None

    try:
        # Setup credentials + tunnel
        if args.target == "prod" and not args.no_tunnel:
            print("[ssh] Lendo DB_PASSWORD via SSH .env Hostinger (sem ecoar)...", flush=True)
            db_pass = fetch_db_password_via_ssh()
            if not db_pass:
                print("[err] DB_PASSWORD vazio — abortando", file=sys.stderr)
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
        elif args.target == "prod" and args.no_tunnel:
            # CT 100 case ou Hostinger direct — env já tem MYSQL_*
            env = {
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
        # dry-run: env vazio, importers viram dry-run + nada toca MySQL

        state = CycleState()
        write_heartbeat(None, 0, 0, "starting")

        first_iter = True
        while True:
            # Tunnel health check (só se prod com tunnel)
            if tunnel is not None and not is_tunnel_alive():
                print("[ssh] Tunnel morreu — reconectando...", file=sys.stderr, flush=True)
                try:
                    tunnel = reconnect_tunnel(tunnel)
                except Exception as e:
                    print(f"[ssh] Reconnect FALHOU: {e!r}. Sleep 60s + retry...", file=sys.stderr, flush=True)
                    time.sleep(60)
                    continue

            run_cycle(
                state=state,
                types=types,
                business_id=args.target_business,
                alias=args.alias,
                target=args.target,
                confirm=args.confirm,
                env=env,
                intervals=intervals,
            )

            if args.once:
                # 1 ciclo executado — sai
                print("[once] 1 ciclo completo · saindo (--once)", flush=True)
                break

            first_iter = False
            time.sleep(args.loop_sleep)

        write_heartbeat(None, state.rows_total, 0, "stopped")
        return 0

    except KeyboardInterrupt:
        print("\n[stop] Ctrl+C — encerrando gracioso", flush=True)
        write_heartbeat(None, 0, 0, "stopped_user")
        return 0

    finally:
        if tunnel:
            print("[ssh] Fechando SSH tunnel...", flush=True)
            try:
                tunnel.terminate()
                tunnel.wait(timeout=5)
            except subprocess.TimeoutExpired:
                tunnel.kill()


if __name__ == "__main__":
    sys.exit(main())
