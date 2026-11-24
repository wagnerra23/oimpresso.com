#!/usr/bin/env bash
# Hook ACTIONS_RUNNER_HOOK_JOB_STARTED: roda antes de cada job, antes do actions/checkout.
# O runner é persistente, então o workspace sobrevive entre jobs.
#
# Defeito original (medido 2026-10-28: 14 de 25 falhas nos ct100-leve-*): um job com
# sparse-checkout deixava o índice marcado; o checkout seguinte desligava o sparse, mas
# arquivos versionados ficavam fora da árvore (marca skip-worktree) e o job caía com
# "Cannot find module". O 1º conserto apagava o workspace INTEIRO antes de cada job.
#
# Custo desse conserto (medido 2026-11-19 com as 8 vagas ligadas): cada job leve regravava
# ~955 MB do zero (508 MB de .git — os gates usam fetch-depth: 0 — e 447 MB de árvore),
# e a pressão de disco do CT 100 foi a 85% (avg10), com o health do MCP em 0,6–4,9 s.
#
# Agora: preserva o .git e desfaz só o que causava o defeito — sparse-checkout ligado e
# marcas skip-worktree — e depois descarta mudanças e não-versionados. O actions/checkout
# encontra o repo, busca só os commits novos e reescreve só os arquivos que mudaram.
# Qualquer falha nesse caminho cai no comportamento antigo (apagar tudo), que é correto,
# só mais caro.
set -uo pipefail
ws="${GITHUB_WORKSPACE:-}"
case "$ws" in
  /home/runner/_work/*) ;;
  *) echo "limpa-workspace: GITHUB_WORKSPACE fora de /home/runner/_work ('$ws'), nada apagado"; exit 0 ;;
esac
[ -d "$ws" ] || exit 0

apaga_tudo() {
  find "$ws" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  echo "limpa-workspace: $ws esvaziado ($1)"
}

if [ ! -d "$ws/.git" ]; then apaga_tudo "sem .git"; exit 0; fi

# ── Espelho da história + poda da marca de raso ─────────────────────────────────
# Medido 2026-11-19: 17 dos 56 workflows leves pedem fetch-depth: 0 e o resto pede 1.
# Na mesma vaga um job raso truncava o repo e o seguinte, completo, baixava e gravava
# ~260-500 MB de história de novo. Com um espelho bare do main por vaga (alternates)
# e a marca de raso podada, os dois tipos de job gravam ~1-2 MB (testado num ref de PR
# real: fsck OK, merge-base certo, só os commits do PR baixados).
# A poda só tira da marca o commit cujos pais existem (local ou no espelho, que tem a
# história completa do main) — commit de PR com pai ausente continua marcado, e o
# checkout completo o aprofunda pela rede. Falha aqui não impede o job.
ESPELHO=/home/runner/espelho.git
espelho() {
  local url; url=$(git -C "$ws" remote get-url origin 2>/dev/null) || return 1
  case "$url" in https://github.com/*) ;; *) return 1 ;; esac
  if [ -d "$ESPELHO" ]; then
    timeout 120 git -C "$ESPELHO" fetch -q --no-tags origin +refs/heads/main:refs/heads/main || return 1
  else
    rm -rf "$ESPELHO.tmp"
    timeout 600 git clone -q --bare --single-branch --branch main "$url" "$ESPELHO.tmp" || { rm -rf "$ESPELHO.tmp"; return 1; }
    mv "$ESPELHO.tmp" "$ESPELHO"
  fi
  local alt="$ws/.git/objects/info/alternates"
  mkdir -p "$(dirname "$alt")"
  grep -qxF "$ESPELHO/objects" "$alt" 2>/dev/null || echo "$ESPELHO/objects" >> "$alt"
}
poda_raso() {
  local sh="$ws/.git/shallow" c p ok
  [ -f "$sh" ] || return 0
  : > "$sh.novo"
  while read -r c; do
    ok=1
    for p in $(git -C "$ws" cat-file -p "$c" 2>/dev/null | sed -n 's/^parent //p'); do
      git -C "$ws" cat-file -e "$p^{commit}" 2>/dev/null || { ok=0; break; }
    done
    [ "$ok" = 1 ] || echo "$c" >> "$sh.novo"
  done < "$sh"
  if [ -s "$sh.novo" ]; then mv "$sh.novo" "$sh"; else rm -f "$sh" "$sh.novo"; fi
}
if espelho; then
  poda_raso && echo "limpa-workspace: espelho do main em dia, raso=$(git -C "$ws" rev-parse --is-shallow-repository)"
else
  echo "limpa-workspace: espelho indisponível, segue sem ele"
fi

# ── Compacta os packs quando acumulam ───────────────────────────────────────────
# O actions/checkout grava gc.auto=0, então cada fetch deixa um pack novo e nada junta nem
# apaga. Medido 2026-11-24 na vaga 2, ligada havia 5 dias: 657 packs, 6,85 milhões de objetos
# e 2,5 GB no .git, dentro do tmpfs de 4 GB do _work — 89% cheio, e jobs caindo com "No space
# left on device" já no setup-node. Os objetos distintos eram 283 mil (24× repetidos), e 204 mil
# deles já estavam no espelho. `repack -a -d -l` junta tudo num pack, descarta as cópias e deixa
# de fora o que o espelho tem (-l: objetos de alternates não entram). Roda só acima do limite,
# em RAM (tmpfs), com prioridade baixa; falha não impede o job.
LIMITE_PACKS=50
packs=$(find "$ws/.git/objects/pack" -maxdepth 1 -name '*.pack' 2>/dev/null | wc -l)
if [ "$packs" -gt "$LIMITE_PACKS" ]; then
  antes=$(du -sm "$ws/.git" 2>/dev/null | cut -f1)
  if timeout 300 nice -n 10 git -C "$ws" repack -a -d -l -q; then
    git -C "$ws" prune-packed >/dev/null 2>&1 || true
    echo "limpa-workspace: $packs packs compactados, .git ${antes} MB -> $(du -sm "$ws/.git" 2>/dev/null | cut -f1) MB"
  else
    echo "limpa-workspace: repack falhou com $packs packs; segue sem ele"
  fi
fi

limpa_repo() {
  cd "$ws" || return 1
  git sparse-checkout disable >/dev/null 2>&1 || true
  git config --local --unset-all core.sparseCheckout >/dev/null 2>&1 || true
  git config --local --unset-all core.sparseCheckoutCone >/dev/null 2>&1 || true
  rm -f .git/info/sparse-checkout
  # marcas skip-worktree que sobraram no índice: tira as marcas antes do reset
  git ls-files -v | sed -n 's/^S //p' | git update-index --no-skip-worktree --stdin || return 1
  git reset -q --hard HEAD || return 1
  git clean -ffdxq || return 1
  # prova: nada versionado fora da árvore e nenhuma marca restante
  [ -z "$(git ls-files -v | grep '^S ' | head -1)" ] || return 1
  [ -z "$(git ls-files --deleted | head -1)" ] || return 1
}

if limpa_repo; then
  echo "limpa-workspace: $ws limpo, .git preservado"
else
  apaga_tudo "limpeza do repo falhou"
fi
exit 0
