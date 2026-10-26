---
sessao: "_saida-01b"
thread: "01 · Recepção do ZIP: conferir projeto × manifesto e falhar quando divergir"
dono: "[C]"
data: 2026-10-21
prefixo_tocado: este recibo
base_lida: wagnerra23/oimpresso.com@main ff2dd7fffc
---
# _saida-01b · a entrega que o _saida-01 não registra

O `_saida-01` (16/09) é um recibo de **não-construção**, anterior à entrega. A entrega entrou depois,
no [#9404](https://github.com/wagnerra23/oimpresso.com/pull/9404) (MERGED 2026-10-14, merge `8404644b57`),
e não deixou `_saida` no espelho. O corpo do #9404 diz isso: *"O recibo `_saida-01.md` mora no espelho;
atualizá-lo é pedido ao Cowork."* Este recibo registra a entrega; não refaz nada.

## O que entrou (no #9404)
- `scripts/design-sync/receber-handoff.mjs --verificar-pacote <dir>`: a auditoria do `sync/` contra a
  árvore, isolada do `--zip`. Exit 0 conforme · 1 divergente · 2 sem manifesto ou conta que não fecha.
  Só detecta, não regenera (`D-QUEM-REGENERA`).
- Bite-test pelo CLI de fora em `scripts/design-sync/receber-handoff.test.mjs` (paridade → 0;
  1 byte tocado → divergente, exit 1; arquivo sumido → ausente; sem manifesto → exit 2).
- A falha no CI (`D-RECEPCAO-FALHA`) é do job required do espelho (`--unverified --check`), que já
  existia; o #9404 corrigiu o comentário do `cowork-bundle.yml` que apontava para outro lugar.

## Por que o placar ainda diz "em curso"
As 3 provas do índice são do tipo `execucao` sem `path`. O placar não mede esse tipo desde a ADR 0397
(o avaliador de recibo saiu do repo), então a thread fica "indecidível" mesmo entregue.

## Pedido ao Cowork
Trocar as 3 provas por provas estruturais, por exemplo:
- `{tipo:"contem", path:"scripts/design-sync/receber-handoff.mjs", padrao:"--verificar-pacote"}`
- `{tipo:"contem", path:"scripts/design-sync/receber-handoff.test.mjs", padrao:"--verificar-pacote"}`

Conferido em `ff2dd7fffc`: o padrão aparece nos dois arquivos.
