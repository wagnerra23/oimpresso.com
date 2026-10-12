## Lote de paridade — protótipo × vivo

**Universo** (`application-report.json`, 196 telas): `anchored` 174 · `to-create` 13 · `compared` 7 · `blocked` 2

> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai
> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar
> os universos produz série temporal mentirosa (§5 2026-07-27).

**Medido nesta rodada:** 174 linhas · 174 pares (tela, fonte) · 170 telas distintas

| veredito | n |
|---|---|
| NÃO MEDI | 134 |
| DIVERGE (bug) | 29 |
| MEDIDO · frescor não provado | 11 |

_soma confere (174 = 174)_

**4 tela(s) medidas contra mais de uma fonte** — `Essentials/Knowledge/Index`, `Fiscal/Cockpit`, `Manufacturing/Index`, `Modules/Index`.

⚠️ **1 com vereditos que se CONTRADIZEM entre as fontes:** `Fiscal/Cockpit`. Não colapse por nome — a escolha entre os dois seria arbitrária, não medida. Decidir qual âncora vale é trabalho humano (charter).

**Achados por campo** (total 1104) — o que consertar, não quantas telas doem:

```
   76  shell.atalhoTopo.presenca
   76  shell.containerMenu.presenca
   76  shell.grupoHeader.presenca
   76  shell.itemGrupo.presenca
   73  cor
   63  layout
   63  kpi align
   60  linha da tabela
   47  título font-size
   44  texto
   39  tokens.prod
   23  sb-alcas
```

> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score
> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,
> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).

Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar
duas rodadas, rode o **mesmo comando** em dois SHAs.
