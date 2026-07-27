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

# ── 8-10. ELO DO ALERTA: gate_status fail escala pro HITL ────────────────────
# O buraco medido em 2026-07-27: o eval dava `fail` e ninguém era avisado (o
# onFailure do Kernel não dispara — quem invoca é o cron, não o scheduler).
# Mock que EMITE o JSON do eval, pra exercitar o parse + a escalação.
mk_docker_json() {
  local gate="$1" exec_rc="$2"
  mkdir -p "$TMP/bin"
  # O .sh REESCREVE o PATH pro POSIX fixo (/usr/bin:/bin:…) — no CT 100 o python3 está
  # lá, no Git Bash do Windows não. Sem este shim o teste mediria "python3 ausente" em
  # vez de medir a escalação: verde/vermelho por ambiente, não por comportamento.
  if [ ! -x "$TMP/bin/python3" ] && command -v python >/dev/null 2>&1; then
    printf '#!/usr/bin/env bash\nexec "%s" "$@"\n' "$(command -v python)" > "$TMP/bin/python3"
    chmod +x "$TMP/bin/python3"
  fi
  cat > "$TMP/bin/docker" <<EOF
#!/usr/bin/env bash
echo "\$@" >> "$TMP/docker-calls.log"
case "\$1" in
  inspect) exit 0 ;;
  exec)
    if [[ "\$*" == *"jana:ragas-real-eval"* ]]; then
      echo '{"ran_at":"2026-07-26T06:06:46-03:00","week":"2026-07-26","gate_status":"$gate","n_evaluated":51,"faithfulness_avg":0.6865,"relevancy_avg":0.8294,"context_recall_avg":0.3461}'
      exit $exec_rc
    fi
    exit 0 ;;
  *) exit 0 ;;
esac
EOF
  chmod +x "$TMP/bin/docker"
  : > "$TMP/docker-calls.log"
}

# 8. MORDE: gate_status=fail → invoca o alerta, no container CERTO, com as flags
mk_docker_json fail 1
RC="$(run_script)"
if grep -q "governance:ragas-eval-alert" "$TMP/docker-calls.log"; then
  ok "MORDE: gate fail → invoca governance:ragas-eval-alert (o vermelho não morre no log)"
else
  fail "gate fail → NÃO escalou (o alerta morreria no log, que é o bug que isto fecha)"
fi
if grep -q "exec oimpresso-mcp php artisan governance:ragas-eval-alert" "$TMP/docker-calls.log"; then
  ok "alerta roda no container oimpresso-mcp (staging não tem mcp_alertas_eventos)"
else
  fail "alerta foi pro container errado — staging não tem a tabela de alertas"
fi
if grep -q -- "--context-recall=0.3461" "$TMP/docker-calls.log"; then
  ok "alerta carrega a métrica medida (--context-recall=0.3461)"
else
  fail "alerta sem as métricas — obrigaria o humano a cavar o log no CT 100"
fi

# 9. CONTROLE NEGATIVO: gate_status=pass → NÃO escala (verde não vira alerta)
mk_docker_json pass 0
RC="$(run_script)"
if ! grep -q "governance:ragas-eval-alert" "$TMP/docker-calls.log"; then
  ok "controle: gate pass → NÃO escala (não polui mcp_alertas com verde)"
else
  fail "gate pass escalou — viraria spam diário de alerta verde"
fi

# 10. FAIL-OPEN: JSON ilegível não escala e NÃO muda o veredito do eval
mk_docker 0 1   # mock sem JSON — saída vazia
RC="$(run_script)"
if [ "$RC" = "1" ] && ! grep -q "governance:ragas-eval-alert" "$TMP/docker-calls.log"; then
  ok "fail-open: JSON ilegível → não escala e o exit do eval (1) é preservado"
else
  fail "fail-open quebrado: rc=$RC (esperado 1) ou escalou sem JSON"
fi

echo ""
if [ "$FAILS" -eq 0 ]; then
  echo "ct100-jana-evals.test.sh: TODOS OS CASOS PASSARAM"
  exit 0
fi
echo "ct100-jana-evals.test.sh: $FAILS caso(s) falharam"
exit 1
