#!/usr/bin/env bash
# @covers-us US-COPI-140
# ct100-jana-evals.test.sh — selftest do invocador dos evals de staging (US-COPI-140).
#
# Fecha, pro .sh novo, a mesma proibição que o irmão ct100-sdd-scorecard-snapshot.test.sh
# fecha ("Modules/X/Tests sem phpunit.xml = falsa cobertura"): script no repo que não roda
# em workflow nenhum. Este É registrado em .github/workflows/governance-script-tests.yml.
#
# Hermético: `docker` é MOCK via seam JANA_EVALS_TEST_BIN. Zero rede, zero container,
# zero LLM, zero custo. Exit 0 = passa.
#
# Casos:
#   1. bash -n                        → sintaxe ok
#   2. container ausente              → exit 1 + FATAL, e NADA invocado (não inventa run)
#   3. caso feliz                     → exit 0 e invoca os 2 evals com o artisan certo
#   4. NÃO invoca jana:drift-sentinel → decisão consciente travada (é ['live'], já roda
#                                        em prod; invocar aqui rodaria o canary 2×/semana)
#   5. um eval falha                  → exit 1 (NÃO mascara) e o irmão AINDA roda
#   6. JANA_EVALS_SAMPLE=N            → repassa --sample-size=N pro ragas
#   7. sample default (0)             → NÃO passa --sample-size (gold-set completo)
set -uo pipefail

SCRIPT="$(cd "$(dirname "$0")" && pwd)/ct100-jana-evals.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
FAILS=0
ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; FAILS=$((FAILS + 1)); }

# ── docker mock: registra args; `inspect` e `exec` controlados por env ────────
mk_docker() {
  local inspect_rc="$1" exec_rc="$2"
  mkdir -p "$TMP/bin"
  cat > "$TMP/bin/docker" <<EOF
#!/usr/bin/env bash
echo "\$@" >> "$TMP/docker-calls.log"
case "\$1" in
  inspect) exit $inspect_rc ;;
  exec)    exit $exec_rc ;;
  *)       exit 0 ;;
esac
EOF
  chmod +x "$TMP/bin/docker"
  : > "$TMP/docker-calls.log"
}

run_script() {
  env JANA_EVALS_TEST_BIN="$TMP/bin" "$@" bash "$SCRIPT" > "$TMP/out.log" 2>&1
  echo $?
}

# ── 1. sintaxe ───────────────────────────────────────────────────────────────
if bash -n "$SCRIPT" 2>/dev/null; then ok "bash -n: sintaxe ok"; else fail "bash -n falhou"; fi

# ── 2. container ausente → exit 1, nada invocado ──────────────────────────────
mk_docker 1 0
RC="$(run_script)"
if [ "$RC" = "1" ]; then ok "container ausente: exit 1"; else fail "container ausente: esperava exit 1, veio $RC"; fi
if grep -q "FATAL" "$TMP/out.log"; then ok "container ausente: loga FATAL"; else fail "container ausente: sem FATAL no log"; fi
if ! grep -q "exec" "$TMP/docker-calls.log"; then ok "container ausente: NÃO invocou eval (não inventa run)"; else fail "container ausente: invocou eval mesmo assim"; fi

# ── 3. caso feliz → exit 0 + os 2 evals invocados ────────────────────────────
mk_docker 0 0
RC="$(run_script)"
if [ "$RC" = "0" ]; then ok "caso feliz: exit 0"; else fail "caso feliz: esperava exit 0, veio $RC"; fi
if grep -q "artisan jana:recall-eval --mode=real" "$TMP/docker-calls.log"; then ok "caso feliz: invocou jana:recall-eval --mode=real"; else fail "caso feliz: NÃO invocou o recall-eval"; fi
if grep -q "artisan jana:ragas-real-eval --json" "$TMP/docker-calls.log"; then ok "caso feliz: invocou jana:ragas-real-eval --json"; else fail "caso feliz: NÃO invocou o ragas-real-eval"; fi
if grep -q "DB_CONNECTION=mysql" "$TMP/docker-calls.log"; then ok "caso feliz: passa DB_CONNECTION=mysql (MySQL real do staging)"; else fail "caso feliz: sem DB_CONNECTION=mysql"; fi

# ── 4. drift-sentinel NÃO é invocado (decisão travada) ───────────────────────
if ! grep -q "drift-sentinel" "$TMP/docker-calls.log"; then
  ok "NÃO invoca jana:drift-sentinel (é ['live'], já roda em prod — não duplicar canary)"
else
  fail "invocou drift-sentinel: rodaria o canary 2×/semana, uma contra o corpus errado"
fi

# ── 5. eval falha → exit 1, sem mascarar, e o irmão ainda roda ───────────────
mk_docker 0 1
RC="$(run_script)"
if [ "$RC" = "1" ]; then ok "eval falhou: exit 1 (não mascara)"; else fail "eval falhou: esperava exit 1, veio $RC"; fi
if grep -q "NÃO mascarado" "$TMP/out.log"; then ok "eval falhou: log diz explicitamente que não mascarou"; else fail "eval falhou: log não registra a falha"; fi
if [ "$(grep -c 'artisan jana:' "$TMP/docker-calls.log")" = "2" ]; then
  ok "eval falhou: o SEGUNDO eval ainda roda (falha de um não aborta o outro)"
else
  fail "eval falhou: abortou a série — esperava os 2 evals invocados"
fi

# ── 6. sample repassado ──────────────────────────────────────────────────────
mk_docker 0 0
RC="$(run_script JANA_EVALS_SAMPLE=3)"
if grep -q -- "--sample-size=3" "$TMP/docker-calls.log"; then ok "JANA_EVALS_SAMPLE=3: repassa --sample-size=3"; else fail "JANA_EVALS_SAMPLE=3: não repassou --sample-size"; fi

# ── 7. sample default → gold-set completo (sem --sample-size) ────────────────
mk_docker 0 0
RC="$(run_script)"
if ! grep -q -- "--sample-size" "$TMP/docker-calls.log"; then ok "sample default: NÃO passa --sample-size (gold-set completo)"; else fail "sample default: passou --sample-size indevidamente"; fi

echo ""
if [ "$FAILS" -eq 0 ]; then
  echo "ct100-jana-evals.test.sh: TODOS OS CASOS PASSARAM"
  exit 0
fi
echo "ct100-jana-evals.test.sh: $FAILS caso(s) falharam"
exit 1
