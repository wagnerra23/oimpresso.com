#!/usr/bin/env bash
# Detecção de arquivos relevantes do workflow contrato-de-tela.yml (skip-as-pass · ADR 0261).
# Fonte ÚNICA: os dois jobs do workflow (`contratos-de-tela` e `preflight-omissao`) chamam este
# arquivo, para que a base do diff e o filtro de path não divirjam em silêncio entre eles.
# Entrada (env): EVENT_NAME · PR_BASE_SHA · PUSH_BEFORE. Saída: `relevant` e `base` em $GITHUB_OUTPUT.
set -euo pipefail
if [ "${EVENT_NAME}" = "pull_request" ]; then
  # Base VIVA: num merge ref (o que o checkout de `pull_request` materializa), o que o
  # PR muda é HEAD^1..HEAD — o 1º pai É o tip de main que o merge usou, imune a main
  # andar. `pull_request.base.sha` é o tip de QUANDO O EVENTO NASCEU e arrasta junto o
  # que entrou em main no meio. Idioma do visual-regression (#6009); §5 2026-09-15.
  if [ "$(git rev-list --count --no-walk --merges HEAD)" = "1" ]; then
    base="$(git rev-parse HEAD^1)"
  else
    base="${PR_BASE_SHA}"
  fi
else
  base="${PUSH_BEFORE}"
fi
# Fallback se base vazio/inválido (primeiro push, force-push, etc.)
if [ -z "${base}" ] || [ "${base}" = "0000000000000000000000000000000000000000" ] || ! git cat-file -e "${base}" 2>/dev/null; then
  base="$(git rev-parse HEAD~1 2>/dev/null || git rev-parse HEAD)"
fi
echo "Base do diff: ${base}"
# Sem `|| true`: `git diff` sai 0 mesmo sem diferença, então rc≠0 é falha real e tem de
# aparecer — engolir o erro virava "nada mudou" e o gate pulava (§5 2026-08-11).
changed="$(git diff --name-only "${base}" HEAD)"
echo "Arquivos mudados:"; echo "${changed:-<nenhum>}"
# O que conta como relevante é DERIVADO em scripts/contrato-de-tela-relevante.mjs: telas sob
# qualquer raiz de Pages (núcleo + Modules/<X>/Resources/js/Pages, via scripts/qa/page-path.mjs)
# + os alvo[] dos próprios *.contract.json + as peças do gate. O regex à mão que morava aqui
# só via resources/js/Pages e deixava telas de módulo e o alvo do cockpit-sidebar sem check.
# Se o node falhar, `set -e` derruba o step: falha visível, nunca "não relevante" em silêncio.
relevant="$(printf '%s\n' "${changed}" | node scripts/contrato-de-tela-relevante.mjs)"
if [ "${relevant}" = "true" ]; then
  echo "relevant=true" >> "${GITHUB_OUTPUT}"
  echo "→ arquivo de contrato/tela mudou: RODA os verificadores."
else
  echo "relevant=false" >> "${GITHUB_OUTPUT}"
  echo "→ nada relevante mudou: SKIP-AS-PASS (gate verde sem rodar; mesma cobertura de antes)."
fi
# A MESMA base para o step de omissão (não recalcular: duas bases divergem em silêncio).
echo "base=${base}" >> "${GITHUB_OUTPUT}"
