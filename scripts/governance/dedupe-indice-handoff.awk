# Dedupe do índice de handoff (memory/08-handoff.md) — SOB DEMANDA, nunca agendado.
#
# Uso:  awk -f scripts/governance/dedupe-indice-handoff.awk memory/08-handoff.md > /tmp/novo
#       # confira, e só então substitua o original
#
# POR QUE EXISTE: o índice é `merge=union` (.gitattributes) e toda sessão que fecha
# insere uma linha no topo. Quando a union duplica uma ENTRADA, o run-set de ninguém
# quebra e nada avermelha — só o índice canônico passa a carregar a linha 2×.
#
# POR QUE NÃO É AGENDADO (regra "LIGUE A MÁQUINA" item 3, memory/proibicoes.md): isto
# ESCREVE em canon. Ligar sozinho é mudar estado sem humano. Rodar é decisão [W].
#
# ── O PREDICADO, e por que não é "contém o slug" ─────────────────────────────────
# Dedupe pelo PRIMEIRO link `(handoffs/...)` do bullet — o destino do título. NÃO por
# "a linha contém o slug": o resumo de uma entrada CITA outros handoffs (convenção do
# índice desde 2026-07), então "contém" casa MENÇÃO CRUZADA e acusaria entrada legítima.
# Medido em 2026-09-21 no índice de main: a forma "contém" dava 15 duplicatas onde havia
# 3 — 12 eram menção. Ver a errata no bloco `memory/08-handoff.md merge=union` do
# .gitattributes e o recibo em memory/LICOES_CODE.md (LC-11).
#
# ── SEGURANÇA: aborta em vez de decidir ─────────────────────────────────────────
# Se a duplicata NÃO for byte-idêntica à que fica, sai com rc=3 e não escreve nada.
# Remover cópia que difere é ESCOLHER O QUE PERDER, e isso é decisão [W], não codemod.
# Este ramo NÃO foi exercido no índice real (os 3 pares de 2026-09-21 eram idênticos);
# ele é exercido pelos controles do bite-test abaixo.
#
# ── COMO PROVAR QUE ELE MORDE (rode antes de confiar) ───────────────────────────
# As duas cópias precisam ser IDÊNTICAS para o ramo de remoção ser exercido — se você
# variar o título, cai no ramo de aborto e o teste mede outra coisa (foi o que aconteceu
# na 1ª versão deste docblock: a fixture tinha `t1`/`t3` e eu anunciei `remove 1`; rodando
# verbatim, ela deu `rc=3`. O anúncio estava errado, não o código).
#
#   printf -- '- [t1](handoffs/a.md) cita (handoffs/b.md)\n- [t2](handoffs/b.md)\n- [t1](handoffs/a.md) cita (handoffs/b.md)\n' \
#     | awk -f scripts/governance/dedupe-indice-handoff.awk
#   # espera: remove 1 (a.md) · PRESERVA b.md, que é 1 destino + 1 menção · rc=0
#
#   printf -- '- [t1](handoffs/a.md) curta\n- [t1](handoffs/a.md) LONGA e diferente\n' \
#     | awk -f scripts/governance/dedupe-indice-handoff.awk
#   # espera: ABORTADO, rc=3, nada escrito
#
#   awk -f scripts/governance/dedupe-indice-handoff.awk memory/08-handoff.md >/dev/null
#   # controle no índice real (já deduplicado): "total removido: 0" · rc=0

substr($0, 1, 3) == "- [" {
  if (match($0, /\(handoffs\/[^)]+\)/)) {
    k = substr($0, RSTART, RLENGTH)
    if (k in firstLine) {
      if ($0 != firstLine[k]) {
        printf("ABORTADO: duplicata de %s NAO e identica a primeira ocorrencia.\n", k) > "/dev/stderr"
        printf("  remover escolheria o que perder — isso e decisao [W], nao codemod.\n") > "/dev/stderr"
        exit 3
      }
      removed++
      printf("removida (identica): %s\n", k) > "/dev/stderr"
      next
    }
    firstLine[k] = $0
  }
}

{ print }

END {
  printf("total removido: %d\n", removed + 0) > "/dev/stderr"
}
