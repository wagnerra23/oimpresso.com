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
changed="$(git diff --name-only "${base}" HEAD || true)"
echo "Arquivos mudados:"; echo "${changed:-<nenhum>}"
# Filtro IDÊNTICO ao antigo workflow-level `paths:` (+ os scripts de intenção e este arquivo).
if echo "${changed}" | grep -Eq '(^scripts/contrato-de-tela\.mjs$|^scripts/contrato-de-tela\.test\.mjs$|^scripts/auditar-intencao-fluxo\.mjs$|^scripts/adversario-intencao-fluxo\.mjs$|^governance/design/contracts/|^resources/js/Pages/.+\.tsx?$|\.contract\.json$|^Modules/Whatsapp/Http/Controllers/Admin/ChannelsController\.php$|^\.github/workflows/contrato-de-tela\.yml$|^\.github/scripts/contrato-de-tela-detect\.sh$)'; then
  echo "relevant=true" >> "${GITHUB_OUTPUT}"
  echo "→ arquivo de contrato/tela mudou: RODA os verificadores."
else
  echo "relevant=false" >> "${GITHUB_OUTPUT}"
  echo "→ nada relevante mudou: SKIP-AS-PASS (gate verde sem rodar; mesma cobertura de antes)."
fi
# A MESMA base para o step de omissão (não recalcular: duas bases divergem em silêncio).
echo "base=${base}" >> "${GITHUB_OUTPUT}"
