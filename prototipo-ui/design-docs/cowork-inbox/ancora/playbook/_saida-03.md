---
sessao: "03"
saida: 2026-09-09
base_lida: 39c9233d424c (mesmo arquivo da 02 — sha remedido antes de escrever)
dono: "[CL]"
veredito: FEITA — e entrega o número que a D-COMPONENT esperava
---
# `_saida-03` — o `--list` prova o arquivo

## 1 · O que mudou
Dois campos **aditivos** por linha do `--list --json`: `caminho` (o que `caminhoDaAncora`
resolve) e `existe`. `hasSource` **não** mudou de semântica nem saiu do JSON — ele responde
"o charter DECLAROU", e os novos respondem "o valor ABRE". Zero extrator novo: reusa
`caminhoDaAncora`, o dono da classificação dos 4 formatos no mesmo arquivo.

**Três estados, e `null` ≠ `false`:** `null` = não há o que abrir (`n/a`, ou valor que não
nomeia arquivo); `false` = nomeia arquivo e ele não está lá. Colapsar os dois transformaria
os `n/a` legítimos em defeito — falso-positivo em massa.

## 2 · GUARDA: os campos antigos são idênticos
Snapshot do `--list --json` antes e depois, comparando só `page`/`source`/`hasSource`/
`charter`/`isNa`/`via`:
```
linhas antes/depois: 226 / 226
linhas com campo ANTIGO alterado: 0
campos depois: caminho · charter · existe · hasSource · isNa · page · source · via
```

## 3 · AS 3 CONTAGENS PEDIDAS (o item 4 do pedido)
| contagem | valor | leitura |
|---|---:|---|
| `via == 'component'` | **0** de 226 | o 3º fallback é **INERTE** hoje |
| `existe == false` | **0** de 226 | nenhuma âncora podre no corpus atual |
| `caminho == null` | **124** de 226 | 119 `n/a` + 5 sem fonte declarada + **0** que declaram algo sem nomear arquivo |

E `existe == true` = **102**.

### O achado sobre a D-COMPONENT
O fallback `mockupJsx(fm.component)` casa **0 vezes** — e não por acaso: `component` é
sempre `resources/js/Pages/….tsx`, e `mockupJsx` procura `-page.jsx`. Ele é **código morto
na prática**, não um caminho em uso. Isso muda o custo da decisão: podá-lo não tira
cobertura de ninguém hoje. **A poda segue sendo D-COMPONENT ([W]), não faço aqui** — esta
thread entrega o número, como o pedido manda.

## 4 · Consumidores
`--list --json` tem **1** consumidor (`scripts/qa/design-coverage.mjs`). Rodado depois da
mudança: **rc=0**, veredito inalterado (66 vinculados · 20 órfãos · 0 quebrados), e o teste
dele (`design-coverage.test.mjs`) passa. `ancora-guard`, `integrity-check` e
`anchor-content-check --check`: rc=0.

## 5 · Bite-test
Mutação `const existe = alvo ? ehArquivo(…) : null` → `= null`: o BITE e o controle do
caminho real ficam `[FAIL]`; o controle do `n/a` segue `[PASS]` — corretamente, porque
`n/a` é `null` nos dois mundos. `--selftest` restaurado: rc=0, **67 PASS · 0 FAIL**.

## 6 · Saída de texto
A linha só ganha sufixo quando `existe === false` (`⚠️ NÃO ABRE: <path>`) — as colunas de
hoje não mudam de posição. Com o corpus atual, **nenhuma linha** ganha o sufixo.
