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
