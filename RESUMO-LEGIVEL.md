## Lote de paridade — protótipo × vivo

**Universo** (`application-report.json`, 162 telas): `anchored` 68 · `compared` 62 · `to-create` 28 · `validated` 4

> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai
> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar
> os universos produz série temporal mentirosa (§5 2026-07-27).

**Medido nesta rodada:** 68 linhas · 68 pares (tela, fonte) · 68 telas distintas

| veredito | n |
|---|---|
| NÃO MEDI | 50 |
| DIVERGE (bug) | 10 |
| IGUAL | 8 |

_soma confere (68 = 68)_

**Achados por campo** (total 963) — o que consertar, não quantas telas doem:

```
   71  shell.atalhoTopo.presenca
   71  shell.containerMenu.presenca
   71  shell.grupoHeader.presenca
   71  shell.itemGrupo.presenca
   68  cor
   62  linha da tabela
   56  kpi align
   55  layout
   42  texto
   40  título font-size
   19  tipografia
   16  kpi.count
```

> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score
> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,
> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).

Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar
duas rodadas, rode o **mesmo comando** em dois SHAs.
