---
sessao: "02"
saida: 2026-09-09
base_lida: 39c9233d424c (mesmo arquivo da 01 — sha remedido antes de escrever)
dono: "[CL]"
veredito: FEITA
---
# `_saida-02` — a query ambígua deixa de sortear

## 1 · O defeito, reproduzido
```
$ node prototipo-ui/ancora.mjs Ponto/Index
  charter:  resources/js/Pages/Ponto/Welcome.charter.md      # rc=0, selo ✓
```
`Welcome` é o **último** da ordem de `walk`, não uma resposta. `Pages/Ponto/**` tem
**21 charters** e todos casam `q="ponto"` — o `replace(/\/index$/i,'')` do `norm()`
derruba o sufixo e `comp.includes(q)` aceita o resto.

## 2 · O PARAR SE do pedido: auditoria de chamadores
Feita antes de escrever. **Nenhum step de CI roda `ancora.mjs <tela>`** — o único uso em
workflow é `--selftest` (`design-memory-gate.yml:316`). Os consumidores de API tratam
`!r.ok` e degradam:
- `prototipo-ui/gerar-map.mjs:386` — imprime `⚠️ ancora.mjs: ${r.motivo}` e segue pelo frontmatter do gap.
- `prototipo-ui/design-diff-lote.mjs:419` — vira item de relatório, não exceção.
- `.claude/hooks/post-merge-ui-smoke-required.mjs` — importa só `caminhoDaAncora`/`ehDeclaracaoNa`, **não** `resolveAncora`.

Nada a reportar como quebra.

## 3 · O que mudou
O laço passou a **coletar** `{charter, fm, forte}` em vez de sobrescrever um `hit`.
`norm()` intacto. Aditivos no retorno: `forca` (`'forte'|'fraca'`) e, na ambiguidade,
`ambiguo:true` + `candidatos[]`. Nenhuma regra de "adivinhar melhor" — a resposta é a lista.

## 4 · FP MEDIDO ANTES DE ARMAR
Query derivada do `component` de **todos** os charters do repo:
```
charters: 226 · match FORTE: 226 · fraca: 0 · AMBIGUO: 0 · sem charter: 0
```
O exit 2 **não alcança o uso canônico** — só a query imprecisa, que é o defeito.

## 5 · Códigos de saída (são 3 coisas diferentes)
| caso | antes | depois |
|---|---|---|
| match forte (`/financeiro/unificado`) | 0 | **0** (idêntico) |
| 1 match fraco | 0, calado | **0**, com `⚠️ match FRACO` |
| ≥2 fracos (`Ponto/Index`) | **0 + sorteio ✓** | **2** + lista de candidatos (teto 10) |
| nenhum match | 1 | **1** (mensagem intacta) |

Recuperação de query mangleada pelo MSYS: intacta (BITE do selftest verde).

## 6 · Bite-test
Mutação `if (!fortes.length && achados.length > 1)` → `if (false)`: os **3** BITEs de
ambiguidade ficam `[FAIL]`. Restaurado, `--selftest` rc=0 (**67 PASS · 0 FAIL**).
