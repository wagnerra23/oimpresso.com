---
sessao: "_saida-03b"
thread: "03 · Corrigir as superfícies claras em produção (lista da 02)"
dono: "[C]"
data: 2026-10-21
prefixo_tocado: este recibo
base_lida: wagnerra23/oimpresso.com@main ff2dd7fffc
---
# _saida-03b · o pendente do _saida-03 foi fechado

O `_saida-03` (13/10) deixou uma Page na baseline: **StockTransfer/Create** (`span.inline-flex.h-5` ×2),
porque o conserto dos selos tocava o header antigo. Ela foi fechada depois, no
[#9405](https://github.com/wagnerra23/oimpresso.com/pull/9405) (MERGED 2026-10-14, merge `066fc26bd9`):
a tela passou ao PageHeader canônico e os selos DE/PARA ganharam token no tema escuro. O mesmo PR
esvaziou `scripts/design/tema-escuro-producao-baseline.json`.

## Estado em `ff2dd7fffc`
- `scripts/design/tema-escuro-producao-baseline.json` → `"pages": {}` (nenhuma Page com superfície
  clara fora de papel nas telas de `tests/Browser/visreg-screens.json`).
- Os PRs #9289, #9314, #9316–#9320 aparecem CLOSED: eram empilhados e entraram pelo
  [#9321](https://github.com/wagnerra23/oimpresso.com/pull/9321) (MERGED 2026-10-14).

## Por que o placar ainda diz "em curso" (02, 03 e 05)
As provas da 02 e da 03 são `execucao` sem `path`, tipo que o placar não mede desde a ADR 0397. A 05
passa na própria prova e só fica presa porque depende da 02.

## Pedido ao Cowork
Trocar as provas por estruturais, por exemplo:
- 02: `{tipo:"arquivo", path:"tests/Browser/TemaEscuro/TemaEscuroProducaoTest.php"}` e
  `{tipo:"contem", path:"scripts/design/tema-escuro-probe.mjs", padrao:"--producao"}`
- 03: `{tipo:"contem", path:"scripts/design/tema-escuro-producao-baseline.json", padrao:"\"pages\": {}"}`
