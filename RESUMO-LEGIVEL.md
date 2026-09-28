## Lote de paridade — protótipo × vivo

**Universo** (`application-report.json`, 167 telas): `anchored` 89 · `compared` 49 · `to-create` 26 · `smoked-ci` 3

> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai
> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar
> os universos produz série temporal mentirosa (§5 2026-07-27).

**Medido nesta rodada:** 89 linhas · 89 pares (tela, fonte) · 88 telas distintas

| veredito | n |
|---|---|
| NÃO MEDI | 51 |
| DIVERGE (bug) | 26 |
| IGUAL | 12 |

_soma confere (89 = 89)_

**1 tela(s) medidas contra mais de uma fonte** — `Manufacturing/Index`.

**Achados por campo** (total 1155) — o que consertar, não quantas telas doem:

```
   78  shell.atalhoTopo.presenca
   78  shell.containerMenu.presenca
   78  shell.grupoHeader.presenca
   78  shell.itemGrupo.presenca
   75  cor
   68  linha da tabela
   59  kpi align
   58  layout
   42  título font-size
   40  texto
   37  tokens.prod
   28  sb-alcas
```

> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score
> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,
> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).

Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar
duas rodadas, rode o **mesmo comando** em dois SHAs.
