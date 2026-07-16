#!/usr/bin/env bash
# ct100-sdd-scorecard-snapshot.test.sh — selftest do wrapper CT 100 (PR #4193).
# Fecha a reencarnação da proibição "Modules/X/Tests sem phpunit.xml = falsa cobertura"
# em .sh: o wrapper existia no repo mas NÃO rodava em workflow nenhum (adversário de
# verificação 2026-07-13, resíduo (b)). Hermético: docker é MOCK via seam SDD_TEST_BIN
# (o wrapper hardcoda PATH por higiene de cron); git de fixture com datas injetadas
# via GIT_COMMITTER_DATE. Zero rede/DB/container real. Exit 0 = passa.
#
# Casos:
#   1. bash -n                       → sintaxe ok
#   2. SDD_SRC ausente               → exit 1 + FATAL (nunca inventa row)
#   3. SDD_SRC sem chave "metrics"   → exit 1 + FATAL (artefato corrompido)
#   4. caso feliz                    → exit 0, docker mock chamado com o artisan certo,
#                                      INPUT_HOST escrito com o conteúdo do artefato
#   5. artefato commitado há 10d     → WARNING de staleness no log (e SEGUE — exit 0)
#   6. artefato fresco               → SEM WARNING
set -euo pipefail

WRAPPER="$(cd "$(dirname "$0")" && pwd)/ct100-sdd-scorecard-snapshot.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
FAILS=0
ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; FAILS=$((FAILS+1)); }

# ── docker mock (registra args, exit 0) ──────────────────────────────────────
mkdir -p "$TMP/bin"
cat > "$TMP/bin/docker" <<EOF
#!/usr/bin/env bash
echo "\$@" >> "$TMP/docker-calls.log"
EOF
chmod +x "$TMP/bin/docker"
# git real via o mesmo seam: no Git Bash (Windows) o git vive em /mingw64/bin,
# fora do PATH hardcoded do wrapper (no CT 100/CI Linux é /usr/bin/git e nem precisa).
cat > "$TMP/bin/git" <<EOF
#!/usr/bin/env bash
exec "$(command -v git)" "\$@"
EOF
chmod +x "$TMP/bin/git"

# ── fixture: checkout git com o artefato commitado ───────────────────────────
make_code_repo() { # $1 = dir, $2 = idade do commit em dias (opcional; vazio = agora)
  local dir="$1" age_days="${2:-}" cdate=""
  [ -n "$age_days" ] && cdate="@$(( $(date +%s) - age_days * 86400 )) +0000"
  mkdir -p "$dir/governance"
  printf '{"metrics":{"composta":41.0},"alertas":["distiller_freshness"]}\n' > "$dir/governance/sdd-scorecard.json"
  git -C "$dir" init -q
  git -C "$dir" add -A
  if [ -n "$cdate" ]; then
    GIT_COMMITTER_DATE="$cdate" git -C "$dir" \
      -c user.name=selftest -c user.email=selftest@test commit -qm 'fixture' --date "$cdate"
  else
    git -C "$dir" -c user.name=selftest -c user.email=selftest@test commit -qm 'fixture'
  fi
}

run_wrapper() { # $1 = SDD_CODE; demais env já exportadas; stdout+stderr → $TMP/out.log
  SDD_TEST_BIN="$TMP/bin" SDD_CODE="$1" SDD_CONTAINER=oimpresso-mcp \
  SDD_INPUT_HOST="$TMP/input-host.json" SDD_INPUT_CT=/var/www/html/storage/app/sdd-scorecard-input.json \
    bash "$WRAPPER" > "$TMP/out.log" 2>&1
}

# ── 1. sintaxe ────────────────────────────────────────────────────────────────
if bash -n "$WRAPPER"; then ok "bash -n: sintaxe ok"; else fail "bash -n falhou"; fi

# ── 2. SDD_SRC ausente → exit 1 ──────────────────────────────────────────────
mkdir -p "$TMP/vazio"
if run_wrapper "$TMP/vazio"; then
  fail "SDD_SRC ausente deveria sair != 0"
else
  grep -q 'FATAL' "$TMP/out.log" && ok "artefato ausente → exit 1 + FATAL (não inventa row)" \
    || fail "artefato ausente: exit != 0 mas sem FATAL no log"
fi

# ── 3. SDD_SRC sem "metrics" → exit 1 ────────────────────────────────────────
mkdir -p "$TMP/corrompido/governance"
echo '{"qualquer":"coisa"}' > "$TMP/corrompido/governance/sdd-scorecard.json"
if run_wrapper "$TMP/corrompido"; then
  fail "artefato sem metrics deveria sair != 0"
else
  grep -q 'FATAL.*metrics' "$TMP/out.log" && ok "artefato sem metrics → exit 1 + FATAL" \
    || fail "artefato corrompido: exit != 0 mas sem FATAL de metrics"
fi

# ── 4. caso feliz (artefato fresco) → exit 0 + docker mock + INPUT_HOST ──────
make_code_repo "$TMP/feliz"
rm -f "$TMP/docker-calls.log"
if run_wrapper "$TMP/feliz"; then
  ok "caso feliz → exit 0"
  grep -q 'exec oimpresso-mcp php artisan governance:sdd-scorecard-snapshot --input=/var/www/html/storage/app/sdd-scorecard-input.json' \
    "$TMP/docker-calls.log" && ok "docker mock chamado com o artisan + --input canônicos" \
    || { fail "docker mock não recebeu a chamada esperada"; cat "$TMP/docker-calls.log" 2>/dev/null; }
  cmp -s "$TMP/feliz/governance/sdd-scorecard.json" "$TMP/input-host.json" \
    && ok "INPUT_HOST = cópia fiel do artefato" || fail "INPUT_HOST diverge do artefato"
  grep -q 'WARNING' "$TMP/out.log" && fail "artefato fresco NÃO deveria ter WARNING" \
    || ok "artefato fresco → sem WARNING de staleness"
else
  fail "caso feliz saiu != 0"; cat "$TMP/out.log"
fi

# ── 5. artefato velho (10d) → WARNING e SEGUE ────────────────────────────────
make_code_repo "$TMP/velho" 10
if run_wrapper "$TMP/velho"; then
  ok "artefato velho → exit 0 (row honesta, não aborta)"
  grep -q 'WARNING.*publish diário parado' "$TMP/out.log" \
    && ok "artefato 10d sem commit → WARNING de staleness no log" \
    || { fail "faltou WARNING de staleness"; cat "$TMP/out.log"; }
else
  fail "artefato velho não deveria abortar (decisão conservadora)"; cat "$TMP/out.log"
fi

echo ''
if [ "$FAILS" -gt 0 ]; then echo "SELFTEST FALHOU ($FAILS)"; exit 1; fi
echo "SELFTEST OK — wrapper morde (ausente/corrompido = sem row) e avisa (staleness) sem esconder o dia."
