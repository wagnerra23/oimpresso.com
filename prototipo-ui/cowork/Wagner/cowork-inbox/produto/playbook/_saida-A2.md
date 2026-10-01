---
sessao: "A2"
titulo: "ALVO lote: produto--cadastros · --etiquetas · --importacao · --atualizar-preco — saída da thread"
autor: "[CL]"
criado: 2026-09-30
base: 39484ca0c
thread: 02-alvos.md §A2
veredito: "entregue — 4 alvos medidos (7 + 4 + 3 + 4 seções, 0 ausentes), duas medidas byte-idênticas por tela. Destrava a thread 07 (contratos) no lado ALVO."
---

# _saída A2 · ALVO das 4 telas novas de Produto

Read-only no produto: nenhum arquivo em `resources/js/Pages/` foi tocado (`nao_toca` da thread).

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/produto--cadastros--index.secoes.json` | seletores colhidos pelo `alvo:mapa` neste turno |
| `governance/design/targets/produto--cadastros--index.alvo.json` | saída do `alvo:medir`, nunca editado à mão |
| `governance/design/targets/produto--etiquetas--index.secoes.json` / `.alvo.json` | idem |
| `governance/design/targets/produto--importacao--index.secoes.json` / `.alvo.json` | idem |
| `governance/design/targets/produto--atualizar-preco--index.secoes.json` / `.alvo.json` | idem |

## Placar

entregue **4 de 4** telas · **18 de 18** seções medidas · ausentes **0**.

| slug | rota do espelho | fonte no protótipo | seções | nós totais |
|---|---|---|---|---|
| `produto--cadastros--index` | `prod-cadastros` | `ProdutoCadastros`, `produto-cadastros.jsx:493` | header · widget · abas · barra · aviso · ajuda · tabela | 535 |
| `produto--etiquetas--index` | `prod-etiquetas` | `Etiquetas`, `produto-acoes.jsx:259` | header · produtos · campos · previa | 942 |
| `produto--importacao--index` | `prod-importar` | `ImportarProdutos` → `TelaImportar`, `produto-acoes.jsx:181/71` | header · enviar · instrucoes | 755 |
| `produto--atualizar-preco--index` | `prod-atualizar-preco` | `AtualizarPreco`, `produto-acoes.jsx:193` | header · planilha · passos · instrucoes | 491 |

As rotas saem do mapa `PROD_VIEW` do shell (`app.jsx:805`).

## Como foi medido

- Espelho `prototipo-ui/cowork/Wagner/` deste worktree servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`), o servidor do `secao-check`, na porta 5567. `cowork-mirror-freshness --preview-ds`: rc 0.
- Sanidade antes de qualquer número: `npm run alvo:selftest` 17/17 ok (inclui a conversão de cor contra valores conhecidos que o `garantirSanidade()` exige).
- Mapa: `alvo:mapa --rota <r> --raiz 'main > .main-body'`, depois `> .pb-root`, `> .pb-body` e `> section > .pb-widget-b`. As 4 telas montam o casco `.pb-root` = `div.cli-ph` (p + header) · `div.pb-body` · `div`. Diferente de Vendas, **não há** barra de abas do menu no topo; a única `nav.ds-tabbar` é a das abas de Cadastros, dentro do widget.
- Seletores estruturais ancorados em `main .pb-root`. O único `data-contract` destas telas é `produto-cadastros-abas` (Cadastros), e a seção `abas` mede o mesmo elemento pelo seletor estrutural.
- `alvo:medir … --quieto-ms 2000`, viewport 1280×900, tema dark (default do shell, `app.jsx:659`), rodado **duas vezes por tela** (2ª com `--saida` no scratchpad) e comparado por bytes. Cadastros, Importação e Atualizar preço: idênticas na 1ª dupla. Etiquetas: as duas primeiras duplas divergiram **só** em `base.assinatura`, porque o relógio "Atualizado HH:MM" do header virou o minuto entre as execuções (23:43→23:44, 23:44→23:45); a 3ª dupla saiu byte-idêntica. Campo de página, informativo no `secao-check`.
- `node scripts/qa/secao-check.mjs --todos --servir-espelho --porta 5568`: **`secao-check: conforme`**; os novos `produto--*` com 7 · 4 · 3 seções conforme e só `~ base.assinatura` informativo (o relógio). (A saída foi cortada por `tail -30`, então a linha do `produto--atualizar-preco--index` não aparece no recorte; o veredito final cobre todos.)
- `pedido.mjs --tela produto--cadastros--index --secoes`: rc 0, as 7 seções `ok` — o pedido acha o alvo pelo slug.

## Provas do índice

As 4 provas `json_com_chaves` (`secoes`) da A2 apontam para os 4 `.alvo.json` acima, e as 4 têm a chave. Conferir com `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/00-INDICE.md --thread A2`.

## Fora do alvo, de propósito

- Cadastros: as abas Grupos de preço · Unidades · Categorias · Marcas · Garantias (só a default, Variações, monta sem clique) e os modais de criar/editar/excluir.
- Importação: a variante **Importar estoque inicial** (`prod-importar-estoque`, mesmo `TelaImportar`) e o widget **Conferência do arquivo**, que só existe depois de escolher arquivo.
- Atualizar preço: o estado pós-planilha (nome do arquivo e contagem de erros no passo 2).
- Etiquetas: o modelo de etiqueta diferente do default e as folhas além da 1ª.

## Pendente / errata para o [CC] (não editei o índice)

1. **O `README.md` de `governance/design/targets/` não ganhou as 4 linhas da tabela "Alvos exportados".** O arquivo está fora do `prefixo` desta thread (`${ALVOS}/produto--*.*`), e as threads de Vendas o editaram por conta própria. O comando de reprodução de cada alvo está neste recibo (acima) e no `_`/`_mapa` de cada `.secoes.json`. Se o README deve acompanhar, ou ele entra no `prefixo` das threads de ALVO, ou outra thread o atualiza.
2. **"Importação" é ambíguo no protótipo.** O menu tem duas rotas de importação (`prod-importar` e `prod-importar-estoque`). A ficha cita só "Importação"; medi `prod-importar` (Importar produtos). Se o alvo deve cobrir as duas, falta um slug para a de estoque — decisão do dono do índice.
3. **A `url` destes 4 alvos é `http://127.0.0.1:5567/`.** A porta não entra no que o `secao-check` bloqueia, mas quem re-medir em outra porta verá o campo `url` mudar.

## PARAR SE

Nenhum disparou: nenhuma rota nova, nenhuma tela de valor tocada, nada em `resources/js/Pages/`. O diff é JSON medido + entrada de seletores + este recibo.
