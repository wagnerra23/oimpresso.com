#!/usr/bin/env python3
"""
install-biz.py — Insere credenciais Inter PJ em rb_boleto_credentials no Hostinger
sem que Claude (ou qualquer log) veja os valores.

Fluxo:
  1. Você preenche credentials.local.json LOCAL (gitignored, no seu PC).
  2. Script SHA-256-fingerprinta os valores e mostra pra você conferir.
  3. SCP do JSON + _remote_install.php pra /tmp/<uuid> no Hostinger.
  4. SSH executa o PHP que boota Laravel, encripta secrets via Crypt::encryptString,
     insere em rb_boleto_credentials e deleta o JSON do /tmp.
  5. Cleanup local opcional via --shred.

Respeita:
  - <user_privacy> Claude SDK: credenciais financeiras NUNCA passam pelo chat.
  - ADR 0030 (credenciais jamais em git) — .json gitignored.
  - ADR 0093 (multi-tenant Tier 0) — --business-id obrigatório, 1 ou 4 só.
  - feedback nunca_publicar_credenciais_no_chat.

Uso:
  # 1. Edita credentials.local.json (template em credentials.example.json)
  # 2. Dry-run primeiro (default):
  python install-biz.py --business-id 1 --credentials credentials.local.json

  # 3. Apply de verdade:
  python install-biz.py --business-id 1 --credentials credentials.local.json --apply

  # 4. Apaga o JSON local após insert (paranoia):
  python install-biz.py --business-id 1 --credentials credentials.local.json --apply --shred
"""

from __future__ import annotations

import argparse
import base64
import hashlib
import json
import os
import shlex
import subprocess
import sys
import tempfile
import uuid
from pathlib import Path

# Conexão SSH Hostinger canônica
SSH_HOST = "u906587222@148.135.133.115"
SSH_PORT = "65002"
SSH_KEY = Path.home() / ".ssh" / "id_ed25519_oimpresso"
REMOTE_APP = "domains/oimpresso.com/public_html"

REQUIRED_FIELDS = [
    "client_id",
    "client_secret",
    "conta_corrente",
    "webhook_secret",
]
# Cert: aceita ou path local ou inline b64
CERT_FIELDS = [
    ("certificado_crt_path", "certificado_crt_b64"),
    ("certificado_key_path", "certificado_key_b64"),
]

VALID_BIZ = {1, 4}
VALID_AMBIENTES = {"sandbox", "production"}

REMOTE_PHP = Path(__file__).parent / "_remote_install.php"


def fingerprint(value: str) -> str:
    """SHA-256 primeiros 12 chars — pra Wagner conferir match com Vaultwarden sem expor valor."""
    return hashlib.sha256(value.encode("utf-8")).hexdigest()[:12]


def load_creds(path: Path) -> dict:
    if not path.exists():
        sys.exit(f"❌ Arquivo {path} não existe. Copia credentials.example.json e preenche.")
    if path.stat().st_size > 64 * 1024:
        sys.exit(f"❌ Arquivo {path} > 64KB — formato inesperado. Aborta por segurança.")
    return json.loads(path.read_text(encoding="utf-8"))


def normalize_certs(creds: dict) -> dict:
    """Resolve cert path → base64. Aceita inline b64 também."""
    for path_key, b64_key in CERT_FIELDS:
        if creds.get(b64_key):
            continue
        p = creds.get(path_key)
        if not p:
            sys.exit(f"❌ Falta {path_key} OU {b64_key} no JSON.")
        cert_path = Path(p).expanduser()
        if not cert_path.exists():
            sys.exit(f"❌ Cert path {cert_path} não existe.")
        if cert_path.stat().st_size > 32 * 1024:
            sys.exit(f"❌ {cert_path} > 32KB — não parece cert PEM válido.")
        creds[b64_key] = base64.b64encode(cert_path.read_bytes()).decode("ascii")
        creds.pop(path_key, None)
    return creds


def validate(creds: dict, business_id: int, ambiente: str) -> None:
    if business_id not in VALID_BIZ:
        sys.exit(f"❌ --business-id deve ser 1 (Wagner WR2) ou 4 (ROTA LIVRE). Recebido: {business_id}.")
    if ambiente not in VALID_AMBIENTES:
        sys.exit(f"❌ --ambiente deve ser 'sandbox' ou 'production'. Recebido: {ambiente!r}.")
    for f in REQUIRED_FIELDS:
        if not creds.get(f):
            sys.exit(f"❌ Campo obrigatório vazio: {f}")
    for _, b64_key in CERT_FIELDS:
        if not creds.get(b64_key):
            sys.exit(f"❌ Cert ainda vazio: {b64_key}")
        try:
            base64.b64decode(creds[b64_key], validate=True)
        except Exception:
            sys.exit(f"❌ {b64_key} não é base64 válido.")


def show_fingerprints(creds: dict, business_id: int, ambiente: str) -> None:
    print("=" * 70)
    print(f"Credenciais Inter PJ — biz={business_id} ambiente={ambiente}")
    print("=" * 70)
    print("Fingerprints SHA-256[:12] (confere com Vaultwarden sem expor valor):")
    fields = REQUIRED_FIELDS + [b64 for _, b64 in CERT_FIELDS]
    for f in fields:
        v = creds.get(f, "")
        size = len(v)
        print(f"  {f:32s}  {fingerprint(v)}  ({size} bytes)")
    print("=" * 70)


def ssh_check() -> None:
    cmd = [
        "ssh", "-4", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10",
        "-i", str(SSH_KEY), "-p", SSH_PORT, SSH_HOST,
        f"test -d {shlex.quote(REMOTE_APP)} && echo ok",
    ]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=20)
    if r.returncode != 0 or "ok" not in r.stdout:
        sys.exit(f"❌ SSH falhou: {r.stderr.strip() or r.stdout.strip()}")


def scp_to_remote(local_path: Path, remote_path: str) -> None:
    cmd = [
        "scp", "-4", "-o", "BatchMode=yes",
        "-i", str(SSH_KEY), "-P", SSH_PORT,
        str(local_path), f"{SSH_HOST}:{remote_path}",
    ]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    if r.returncode != 0:
        sys.exit(f"❌ SCP falhou: {r.stderr.strip()}")


def ssh_exec(remote_cmd: str, timeout: int = 60) -> tuple[int, str, str]:
    cmd = [
        "ssh", "-4", "-o", "BatchMode=yes", "-o", "ConnectTimeout=15",
        "-i", str(SSH_KEY), "-p", SSH_PORT, SSH_HOST, remote_cmd,
    ]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
    return r.returncode, r.stdout, r.stderr


def run_remote_install(remote_json_path: str, remote_php_path: str) -> dict:
    """Executa o _remote_install.php via PHP CLI no Hostinger."""
    cmd = (
        f"INTER_JSON={shlex.quote(remote_json_path)} "
        f"php {shlex.quote(remote_php_path)}"
    )
    rc, out, err = ssh_exec(cmd, timeout=90)
    return {"rc": rc, "stdout": out.strip(), "stderr": err.strip()}


def cleanup_remote(*remote_paths: str) -> None:
    """Remove arquivos /tmp no Hostinger garantidamente."""
    paths_q = " ".join(shlex.quote(p) for p in remote_paths)
    ssh_exec(f"rm -f {paths_q}", timeout=15)


def shred_local(path: Path) -> None:
    """Overwrite com random + delete. Reduz superfície em FS comuns (SSD não 100%)."""
    try:
        size = path.stat().st_size
        with open(path, "wb") as f:
            f.write(os.urandom(size))
            f.flush()
            os.fsync(f.fileno())
        path.unlink()
        print(f"🔒 {path} shredded (overwrite + delete).")
    except Exception as e:
        print(f"⚠️  shred falhou ({e}). Apaga manualmente: rm {path}")


def main() -> int:
    p = argparse.ArgumentParser(description="Cadastra Inter PJ em rb_boleto_credentials sem expor valores.")
    p.add_argument("--business-id", type=int, required=True, choices=[1, 4],
                   help="1=Wagner WR2 (smoke seguro) · 4=ROTA LIVRE (produção real, ADR 0101)")
    p.add_argument("--credentials", type=Path, default=Path("credentials.local.json"),
                   help="Path do JSON local com valores (default: ./credentials.local.json)")
    p.add_argument("--ambiente", choices=sorted(VALID_AMBIENTES), default="production",
                   help="production ou sandbox (default: production)")
    p.add_argument("--nome-display", default=None,
                   help="Nome legível (default: 'Inter PJ — biz=N')")
    p.add_argument("--apply", action="store_true",
                   help="Executa de verdade. Sem essa flag é dry-run.")
    p.add_argument("--shred", action="store_true",
                   help="Após --apply OK, sobrescreve+deleta o JSON local.")
    args = p.parse_args()

    if not REMOTE_PHP.exists():
        sys.exit(f"❌ _remote_install.php ausente em {REMOTE_PHP}")

    nome = args.nome_display or f"Inter PJ — biz={args.business_id}"

    print(f"📂 Lendo {args.credentials}…")
    creds = load_creds(args.credentials)
    creds = normalize_certs(creds)
    validate(creds, args.business_id, args.ambiente)
    show_fingerprints(creds, args.business_id, args.ambiente)

    if not args.apply:
        print("\n🟡 DRY-RUN — nada foi alterado. Confira fingerprints e rode com --apply.")
        return 0

    print("\n🔌 Validando SSH Hostinger…")
    ssh_check()

    creds["_business_id"] = args.business_id
    creds["_ambiente"] = args.ambiente
    creds["_nome_display"] = nome

    uid = uuid.uuid4().hex
    remote_json = f"/tmp/inter-creds-{uid}.json"
    remote_php = f"/tmp/inter-install-{uid}.php"

    local_tmp_fd, local_tmp_name = tempfile.mkstemp(suffix=".json", prefix="inter-")
    local_tmp = Path(local_tmp_name)
    os.close(local_tmp_fd)

    success = False
    try:
        local_tmp.write_text(json.dumps(creds, separators=(",", ":")), encoding="utf-8")
        os.chmod(local_tmp, 0o600)

        print(f"📤 SCP JSON → {remote_json}")
        scp_to_remote(local_tmp, remote_json)

        print(f"📤 SCP PHP  → {remote_php}")
        scp_to_remote(REMOTE_PHP, remote_php)

        print("🔐 PHP remoto: bootstrap Laravel + Crypt::encryptString + INSERT…")
        result = run_remote_install(remote_json, remote_php)

        if result["rc"] == 0 and result["stdout"].startswith("OK:"):
            print(f"✅ {result['stdout']}")
            print("   Próximo: rodar smoke saldo (RUNBOOK §3) ou avisar Claude.")
            success = True
        elif result["stdout"].startswith("ERR_ALREADY_EXISTS"):
            print(f"⚠️  {result['stdout']}")
            print("   Credencial já existe pra esse biz+banco. Pra atualizar, UPDATE manual via tinker.")
        else:
            print(f"❌ rc={result['rc']}")
            print(f"   stdout: {result['stdout']}")
            print(f"   stderr: {result['stderr']}")
    finally:
        try:
            local_tmp.unlink(missing_ok=True)
        except Exception:
            pass
        cleanup_remote(remote_json, remote_php)
        print(f"🧹 Cleanup /tmp Hostinger: ok")

    if success and args.shred:
        shred_local(args.credentials)

    return 0 if success else 1


if __name__ == "__main__":
    sys.exit(main())
