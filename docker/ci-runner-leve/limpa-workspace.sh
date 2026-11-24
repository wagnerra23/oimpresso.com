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

# ── Compactação do .git ─────────────────────────────────────────────────────────
# O _work é tmpfs de 4 GB (cria-vaga.sh) e o .git nunca era compactado: cada fetch
# deixava um pack novo, e os packs da época antes do espelho repetiam a história do
# main. Medido 2026-11-24 ~19:50Z: .git de 1,1 a 2,7 GB por vaga (até 657 packs),
# tmpfs a 79-89%, e os jobs caindo em "Set up job" com "No space left on device" ao
# extrair actions/setup-node — esse passo roda ANTES deste hook, então o espaço tem
# de sobrar do job anterior. `repack -a -d -l` deixa fora o que o espelho já tem;
# nas 8 vagas o .git foi a 133-142 MB em ~16 s, com fsck --connectivity-only limpo.
# Só roda com 20+ packs (barato no caso comum). O repack é atômico: se falhar, o .git
# antigo fica e o job segue.
packs=$(git -C "$ws" count-objects -v 2>/dev/null | sed -n 's/^packs: //p')
if [ "${packs:-0}" -ge 20 ]; then
  git -C "$ws" reflog expire --expire=now --all 2>/dev/null
  if git -C "$ws" repack -a -d -l -q && git -C "$ws" prune-packed; then
    echo "limpa-workspace: .git compactado ($packs packs -> $(git -C "$ws" count-objects -v | sed -n 's/^packs: //p'))"
  else
    echo "limpa-workspace: repack falhou, segue sem compactar"
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
