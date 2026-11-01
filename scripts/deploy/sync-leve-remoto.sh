#!/usr/bin/env bash
# Roda NO SERVIDOR (via `ssh ... bash -s -- <sha> < este arquivo`), dentro do checkout de
# produção. É o passo de git do job `sync-light` de .github/workflows/deploy.yml.
#
# Leva o servidor ao SHA deste run, nunca a um commit mais novo nem a um ramo ao lado:
#   - main no SHA deste run → reset no SHA.
#   - main já andou e o servidor está ATRÁS deste SHA → reset no SHA mesmo assim.
#   - main já andou e o servidor JÁ CONTÉM este SHA → nada a fazer.
#   - o servidor está num commit que não é ancestral deste SHA → não mexe (sai 0 com aviso).
#
# Por que não pular sempre que a main andou (era assim até 2026-10-29): o pulo contava com o
# run do commit mais novo para publicar tudo, mas o commit mais novo pode não gerar run de
# deploy nenhum (memória, .md que não é casos.md, protótipo — o filtro `paths` do push).
# Medido 2026-10-29: o run do #10147 (casos 151 e 152) pulou porque a main já estava no
# #10132, que só mexia no protótipo; os casos ficaram fora do site até o merge seguinte.
#
# Por que nunca o tip: a classificação runtime_changed=false vale para o SHA deste run. Um
# commit mais novo pode trazer código de runtime, e publicá-lo sem dump-autoload, cache clear
# e OPcache reset foi a receita dos 500 de boot de 2026-06-18 e 2026-06-23.
#
# Uso: bash sync-leve-remoto.sh <sha>     (remote: origin, ramo: main)
set -euo pipefail
SHA="${1:?sha obrigatório}"

git fetch origin
TIP=$(git rev-parse origin/main)
ATUAL=$(git rev-parse HEAD)

if [ "$TIP" != "$SHA" ]; then
  if git merge-base --is-ancestor "$SHA" "$ATUAL" 2>/dev/null; then
    echo "main avançou (tip=$TIP) e o servidor ($ATUAL) já contém $SHA — nada a fazer."
    exit 0
  fi
  # `if !` com erro do git (objeto desconhecido, rc=128) também cai aqui: na dúvida, não mexe.
  if ! git merge-base --is-ancestor "$ATUAL" "$SHA" 2>/dev/null; then
    echo "::warning::o servidor ($ATUAL) não é ancestral de $SHA — PULANDO o sync leve (não move para trás nem para o lado)."
    exit 0
  fi
  echo "main avançou (tip=$TIP), mas o servidor ($ATUAL) está atrás de $SHA: aplicando $SHA."
  echo "O commit mais novo pode não gerar run de deploy; se gerar, ele publica depois deste."
fi

git checkout main
git reset --hard "$SHA"
git log --oneline -3
