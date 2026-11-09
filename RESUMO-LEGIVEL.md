## Lote de paridade — protótipo × vivo

**Universo** (`application-report.json`, 239 telas): `anchored` 223 · `to-create` 8 · `compared` 5 · `blocked` 3

> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai
> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar
> os universos produz série temporal mentirosa (§5 2026-07-27).

**Medido nesta rodada:** 225 linhas · 225 pares (tela, fonte) · 219 telas distintas

| veredito | n |
|---|---|
| NÃO MEDI | 185 |
| DIVERGE (bug) | 33 |
| IGUAL | 7 |

_soma confere (225 = 225)_

**6 tela(s) medidas contra mais de uma fonte** — `Essentials/Knowledge/Index`, `Fiscal/Cockpit`, `Fiscal/Config`, `Manufacturing/Index`, `Manufacturing/Index · gaveta`, `Modules/Index`.

**Achados por campo** (total 981) — o que consertar, não quantas telas doem:

```
   72  cor
   72  shell.atalhoTopo.presenca
   72  shell.grupoHeader.presenca
   72  shell.itemGrupo.presenca
   71  shell.containerMenu.presenca
   67  linha da tabela
   58  kpi align
   56  layout
   51  título font-size
   49  texto
   41  tokens.prod
   27  título font-weight
```

> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score
> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,
> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).

Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar
duas rodadas, rode o **mesmo comando** em dois SHAs.
