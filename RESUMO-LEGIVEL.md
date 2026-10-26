## Lote de paridade — protótipo × vivo

**Universo** (`application-report.json`, 239 telas): `anchored` 223 · `to-create` 8 · `compared` 5 · `blocked` 3

> O lote mede as `anchored`. Esse conjunto muda por trabalho humano — tela que sai
> dele **não foi resolvida, saiu do denominador**. Comparar dois números sem comparar
> os universos produz série temporal mentirosa (§5 2026-07-27).

**Medido nesta rodada:** 225 linhas · 225 pares (tela, fonte) · 219 telas distintas

| veredito | n |
|---|---|
| NÃO MEDI | 171 |
| DIVERGE (bug) | 34 |
| IGUAL | 20 |

_soma confere (225 = 225)_

**6 tela(s) medidas contra mais de uma fonte** — `Essentials/Knowledge/Index`, `Fiscal/Cockpit`, `Fiscal/Config`, `Manufacturing/Index`, `Manufacturing/Index · gaveta`, `Modules/Index`.

⚠️ **1 com vereditos que se CONTRADIZEM entre as fontes:** `Fiscal/Cockpit`. Não colapse por nome — a escolha entre os dois seria arbitrária, não medida. Decidir qual âncora vale é trabalho humano (charter).

**Achados por campo** (total 1329) — o que consertar, não quantas telas doem:

```
   84  shell.atalhoTopo.presenca
   84  shell.containerMenu.presenca
   84  shell.grupoHeader.presenca
   84  shell.itemGrupo.presenca
   81  cor
   67  layout
   67  kpi align
   66  linha da tabela
   52  tokens.prod
   41  sb-alcas
   41  sb-corpo
   41  sb-modos
```

> Campo repetido em N telas é **uma causa sistemática**, não N trabalhos. Sem nota/score
> de fidelidade aqui de propósito: os vereditos não são comensuráveis — bug de prod,
> protótipo à frente e ruído de dado apontam para lados diferentes (§5 2026-07-17).

Dado bruto no artifact `paridade-lote-*`. Não há baseline commitada: para comparar
duas rodadas, rode o **mesmo comando** em dois SHAs.
