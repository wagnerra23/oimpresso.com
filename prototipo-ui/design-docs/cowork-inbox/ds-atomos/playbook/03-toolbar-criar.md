---
sessao: "03"
titulo: shared/Toolbar.tsx — barra de 3 zonas (CRIAR; não é o PageFilters)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Components/shared/Toolbar.tsx
nao_toca: shared/PageFilters.tsx · resources/js/Pages/** · ui/**
depende: —
---
# 03 · `shared/Toolbar.tsx` — o que produção **não** tem

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-ui.jsx` :: `Barra` — e as 7 barras já migradas em `ponto-telas.jsx`/`ponto-fechamento.jsx`.
- **âncora (código):** **não existe receptor** — `shared/` tem `PageFilters.tsx` (**2.764 B**, sha `309d6e537180`), que é **outra coisa**: chips de filtro ativo + grid de campos + "Limpar tudo". Leia-o **só** para não duplicar comportamento.
- **NÃO ler:** `Pages/**`.

## B · NÃO INVENTAR
- **Não estenda `PageFilters`** e não o substitua: são peças distintas e ele tem consumidores.
- Zero CSS novo; utilitárias/tokens do repo.
- **Copy:** nenhuma (o componente não escreve texto).

## C · ALVO MEDIDO (dark, T1 estável 1014 nós, aba Espelho, **largura de referência 1280px** — persona Larissa)
- **flex · `flex-wrap` · `gap:8px` · `padding:9px 12px` · `align-items:center`** · bg `--surface`.
- **A moldura é do PAI, não da barra:** `border-radius:12px` + `border:1px solid --border`, `overflow:hidden`. A barra em si tem **`border-bottom-width:0`** neste uso (é bloco solto acima dos cards, não cabeçalho de painel).
- **A 1280px:** barra **1215px × 71px**, tudo em **uma faixa** (os `top` diferem entre filhos porque cada um é centrado na própria altura — 53px o campo, 18px o check, 17px a nota; isso **não** é wrap).
- **6 filhos, nesta ordem, com este regime de flex** (medido, não suposto):

| # | nó | w@1280 | h | `flex` |
|---:|---|---:|---:|---|
| 1 | select "Mês de referência" | 150 | 53 | `0 1 auto` |
| 2 | select "Escala" | 189 | 53 | `0 1 auto` |
| 3 | campo "Buscar" | 362 | 53 | **`1 1 260px`** — o único que estica |
| 4 | check "Só com divergência" | 135 | 18 | `0 1 auto` |
| 5 | nota "11 dias em divergência" | 212 | 17 | `0 1 auto` |
| 6 | **spacer da zona `right`** (do próprio Toolbar) | 102 | **0** | **`1 1 0%`** |

- **Três zonas:** `left` · `center` · `right`. O `right` cola na borda pelo **filho 6** (spacer de altura 0), **não** por `margin-left:auto` em cada filho. Aqui a zona `right` está vazia — o spacer existe e é o que dá os 102px.
- **Nenhum filho é `flex:none`** e **nenhum** é `white-space:nowrap`: encolhimento por `0 1 auto`, esticamento só do campo de busca.
- **Reflow abaixo de ~900px:** a 599px a barra vai a **158px / 5 faixas** — comportamento correto do `wrap`, não defeito. O alvo é o de 1280.

**Errata desta thread (2026-09-09, mesmo dia da emissão):** a 1ª versão deste bloco dizia *"7 filhos, todos `flex:none`, `white-space:nowrap`"*. Falso nos três números. Causa: o 7º filho era um `<span className="pt-sp">` que a migração `.pt-toolbar → PtBarra` arrastou por inércia (spacer do CSS antigo convivendo com o do Toolbar), e o regime de flex foi **suposto**, não lido. O `pt-sp` saiu de 7 barras do build (`ponto-page.jsx` 1 · `ponto-telas.jsx` 5 · `ponto-fechamento.jsx` 1) e o bloco acima é a **remedição** depois da limpeza. Sem isso o [CL] reproduziria um gap fantasma de 82px e um flex que não existe.

## D · COMO VALIDAR
1. `shared/Toolbar.tsx` existe e aceita `left`/`center`/`right` + `bordered` (default: borda de baixo **ligada**, para o uso em cabeçalho de painel) + `dense`.
2. Números do bloco C conferem por `getComputedStyle` em dark.
3. Com `bordered={false}` dentro de um pai com radius+border, a barra **não** desenha borda dupla.
4. **Guarda:** `shared/PageFilters.tsx` **intacto** (0 diff) e seus consumidores sem mudança.
5. Reflow: a 1280px os 6 filhos ficam em **uma faixa** (71px de altura); estreitando, quebram mantendo `gap:8px`, sem overflow horizontal.
6. **Sem spacer duplo:** a zona `right` emite **um** spacer. Se o consumidor já passa o próprio, a barra não soma outro.
7. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** **criar** `resources/js/Components/shared/Toolbar.tsx` — 1 arquivo, nada além.
- **REUSAR:** `cn` · `Button` de `@/Components/ui/button` (se precisar de gatilho interno; **não** reimplemente botão).
- **CRIAR:** `Toolbar` + `ToolbarSpacer` — **dois símbolos, e para aí**. `ToolbarButton`/`ToolbarSearch`/`ToolbarDivider` são outra thread (furam o teto de 3 símbolos).
- **NÃO TOCAR:** `PageFilters.tsx`.
- **PASSO A PASSO:** 1) ler `PageFilters.tsx` só para delimitar escopo · 2) escrever o componente com as 3 zonas · 3) `role="toolbar"` + `aria-label` obrigatório na prop · 4) medir contra o bloco C.
- **DADO:** nenhum.
- **PARAR SE:** aparecer vontade de fundir com `PageFilters` (é decisão de produto, não de PR) ou de criar CSS próprio.

## PRÉ / PÓS
- **antes:** `shared/Toolbar.tsx` **AUSENTE** · `shared/PageFilters.tsx` **PRESENTE** (guarda).
- **depois:** `Toolbar.tsx` presente com `Toolbar`+`ToolbarSpacer` · `PageFilters.tsx` intacto.
- **quebra:** se `shared/Toolbar.tsx` já existir, **não execute** — reporte e pare.

## PROVA
`shared/Toolbar.tsx` existe com as 3 zonas · `PageFilters.tsx` sem diff · `_saida-03.md`.
