#!/usr/bin/env bash
# ct100-paridade-lote.test.sh — selftest do wrapper CT 100 (ADR 0408).
# Fecha a mesma reencarnação que o irmão `ct100-sdd-scorecard-snapshot.test.sh` fecha:
# wrapper que existe no repo mas não roda em workflow nenhum = falsa cobertura.
# Hermético: `curl`/`node`/`npx`/`git` são MOCKS via seam PLT_TEST_BIN (o wrapper hardcoda
# PATH por higiene de cron). Zero rede, zero staging, zero browser. Exit 0 = passa.
#
# Casos — cada um trava um modo de falha que JÁ ACONTECEU nas 8 runs do CI:
#   1. bash -n                        → sintaxe ok
#   2. token ausente no .env          → exit != 0 (sem token a rota é 404 e o lote mediria
#                                       a tela de LOGIN contra o protótipo)
#   3. staging fora do ar             → exit != 0, e NÃO mede
#   4. auth-bridge devolve 302+000    → exit != 0 com a mensagem de redirect/APP_URL — é a
#                                       causa raiz real das 8 runs, e o teste a pina
#   5. lote grava RESUMO com ERRO     → exit != 0 e NÃO publica (ERRO é falha de NAVEGAÇÃO;
#                                       publicar isso como fidelidade mascarou 5 rodadas)
#   6. caso feliz (0 ERRO)            → exit 0, mede e chega na publicação
#   7. divergência NÃO falha          → RESUMO com DIVERGE e 0 ERRO segue exit 0 (render
#                                       pareado não bloqueia — ADR 0290 recusada, 0408 mede)
set -euo pipefail

WRAPPER="$(cd "$(dirname "$0")" && pwd)/ct100-paridade-lote.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
FAILS=0
ok()   { echo "  ✓ $1"; }
bad()  { echo "  ✗ $1"; FAILS=$((FAILS+1)); }

# ── fixture: checkout falso + .env falso + mocks ──────────────────────────────
mkbin() { # $1=dir  $2=http do AUTH-BRIDGE  $3=conteudo do RESUMO  $4=http do /login
  mkdir -p "$1"
  # O mock DISCRIMINA por rota. A 1a versao devolvia o mesmo codigo pra tudo, e o caso
  # do 302+000 falhava no health `/login` antes de chegar no auth-bridge — fixture que
  # nao separa os dois alvos nao consegue testar o alvo certo.
  cat > "$1/curl" <<EOF
#!/usr/bin/env bash
case "\$*" in
  *_visreg-login*) echo -n "$2" ;;
  *) echo -n "${4:-200}" ;;
esac
EOF
  cat > "$1/node" <<EOF
#!/usr/bin/env bash
# design-diff-lote: grava o RESUMO da fixture. lote-resumo-ci: so ecoa.
case "\$*" in
  *design-diff-lote*) mkdir -p "\$PWD/governance/design/targets/medidas"
                      printf '%s' '$3' > "\$PWD/governance/design/targets/medidas/RESUMO.md" ;;
  *) echo "(resumo mock)" ;;
esac
EOF
  printf '#!/usr/bin/env bash\nexit 0\n' > "$1/npx"
  printf '#!/usr/bin/env bash\nexit 0\n' > "$1/git"
  chmod +x "$1"/*
}

prep() { # $1=nome  $2=http do AUTH-BRIDGE  $3=resumo  $4=token(sim/nao)  $5=http do /login
  local d="$TMP/$1"
  mkdir -p "$d/code/node_modules" "$d/staging" "$d/bin"
  if [ "$4" = "sim" ]; then echo 'VISREG_LOGIN_TOKEN=abc123' > "$d/staging/.env"
  else echo 'APP_ENV=staging' > "$d/staging/.env"; fi
  mkbin "$d/bin" "$2" "$3" "${5:-200}"
  echo "$d"
}

run() { # roda o wrapper com o fixture $1
  PLT_TEST_BIN="$1/bin" PLT_CODE="$1/code" PLT_STAGING_ENV="$1/staging/.env" \
  PLT_DEPLOY_KEY="$1/sem-key" PLT_BASE_URL="http://alvo.invalido" \
  bash "$WRAPPER" 2>&1
}

LINHA_OK='| Tela | Fonte | R | Veredito | b |
|---|---|---|---|---|
| A/B | a.jsx | x | IGUAL | 0 |'
LINHA_ERRO='| Tela | Fonte | R | Veredito | b |
|---|---|---|---|---|
| A/B | a.jsx | x | ERRO | — |'
LINHA_DIV='| Tela | Fonte | R | Veredito | b |
|---|---|---|---|---|
| A/B | a.jsx | x | DIVERGE (bug) | 4 |'

echo "== ct100-paridade-lote.test.sh =="

# 1 — sintaxe
bash -n "$WRAPPER" && ok "bash -n" || bad "bash -n"

# 2 — sem token: a rota seria 404 e o lote mediria /login
D="$(prep semtoken 200 "$LINHA_OK" nao)"
if out="$(run "$D")"; then bad "sem token deveria falhar"; else
  case "$out" in *VISREG_LOGIN_TOKEN*) ok "sem token: aborta citando o token" ;;
                 *) bad "sem token: mensagem errada -> $out" ;; esac
fi

# 3 — staging fora do ar
D="$(prep fora 000 "$LINHA_OK" sim 000)"
if out="$(run "$D")"; then bad "staging fora deveria falhar"; else
  case "$out" in *"não respondeu"*) ok "staging fora: aborta antes de medir" ;;
                 *) bad "staging fora: mensagem errada -> $out" ;; esac
fi

# 4 — 302+000: A CAUSA RAIZ das 8 runs (redirect pra host que nao conecta)
D="$(prep redirect 302000 "$LINHA_OK" sim)"
if out="$(run "$D")"; then bad "302000 deveria falhar"; else
  case "$out" in *APP_URL*) ok "302+000: aponta APP_URL e nega concorrencia" ;;
                 *) bad "302000: mensagem errada -> $out" ;; esac
fi

# 5 — RESUMO com ERRO: falha de NAVEGACAO, nao publica
D="$(prep comerro 200 "$LINHA_ERRO" sim)"
if out="$(run "$D")"; then bad "RESUMO com ERRO deveria falhar"; else
  case "$out" in *NAVEGAÇÃO*) ok "ERRO no RESUMO: falha de medicao, nao publica" ;;
                 *) bad "ERRO: mensagem errada -> $out" ;; esac
fi

# 6 — caso feliz
D="$(prep feliz 200 "$LINHA_OK" sim)"
if out="$(run "$D")"; then
  case "$out" in *"medição OK"*) ok "caso feliz: mede e segue" ;;
                 *) bad "feliz: sem 'medição OK' -> $out" ;; esac
else bad "caso feliz deveria passar -> $out"; fi

# 7 — CONTROLE NEGATIVO: divergencia NAO falha (render pareado nao bloqueia)
D="$(prep diverge 200 "$LINHA_DIV" sim)"
if out="$(run "$D")"; then ok "DIVERGE nao falha (e dado, nao defeito)"
else bad "DIVERGE nao deveria falhar -> $out"; fi

echo
[ "$FAILS" -eq 0 ] && { echo "OK — 7/7"; exit 0; } || { echo "FALHOU — $FAILS caso(s)"; exit 1; }
