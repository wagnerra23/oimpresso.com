---
sessao: "A2"
titulo: "ALVO lote 2 — vendas--importacao · vendas--pedidos · vendas--caixa — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: 5606344ca
thread: 01-telas-legadas.md §A1 · A2
veredito: "entregue — 3 alvos medidos (5 + 4 + 5 seções, 0 ausentes), duas medidas byte-idênticas por tela; secao-check conforme; nenhum PARAR SE disparou. Destrava 05 · 06 · 07 no lado ALVO — mas o slug do índice não é achado pelo pedido.mjs (ver Errata)."
---

# _saída A2 · ALVO do lote 2 de Vendas

Read-only no produto: nenhum arquivo em `resources/js/Pages/` foi tocado (`nao_toca` da thread).

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/vendas--importacao--index.secoes.json` | seletores colhidos pelo `alvo:mapa` neste turno |
| `governance/design/targets/vendas--importacao--index.alvo.json` | saída do `alvo:medir`, nunca editado à mão |
| `governance/design/targets/vendas--pedidos--index.secoes.json` | idem |
| `governance/design/targets/vendas--pedidos--index.alvo.json` | idem |
| `governance/design/targets/vendas--caixa--index.secoes.json` | idem |
| `governance/design/targets/vendas--caixa--index.alvo.json` | idem |
| 3 linhas na tabela "Alvos exportados" do `README.md` da pasta | — |

## Placar

entregue **3 de 3** telas · **14 de 14** seções medidas · ausentes **0**.

| slug | rota do espelho | seções | nós totais | sha256 (16) |
|---|---|---|---|---|
| `vendas--importacao--index` | `venda-importar` | header · tabs · enviar · instrucoes · importacoes | 568 | `6ca1f552c120a097` |
| `vendas--pedidos--index` | `venda-pedidos` | header · tabs · filtros · lista | 608 | `48c9b320d0f96a1f` |
| `vendas--caixa--index` | `venda-caixa` | header · tabs · abas · kpis · dia | 554 | `08a8d0cc0c46b3bb` |

## Como foi medido

- Espelho `prototipo-ui/cowork/Wagner/` deste worktree servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`), o servidor do `secao-check`.
- `cowork-mirror-freshness --preview-ds`: exit 0.
- Mapa: `npm run alvo:mapa -- <url> --rota <r> --raiz 'main > .main-body > *'`, depois `> .cli-ph` e `> .pb-body`. As 3 telas montam o mesmo casco `.pb-root.vb-root` = `div.cli-ph` (p + header) · `div.pb-body`, que abre com `nav.ds-tabbar.vb-nav` (9 abas do menu). Depois:
  - Importação (`TelaImportar`, `venda-blade.jsx:547`): 3 `section.pb-widget` — Enviar planilha · Instruções · Importações.
  - Pedido de venda (`VendaPedidos`, `venda-blade-telas.jsx:309`): 2 `section.pb-widget` — Filtros · Pedidos de venda.
  - Caixa (`VendaCaixa`, `venda-blade-caixa.jsx:102`, visão default `dia`): `nav.vc-abas` · `div.vc-kpis` (4) · `div.vc-dia` (2 widgets).
- Nenhuma das 3 telas tem `data-contract` no protótipo; os seletores são estruturais, ancorados em `.pb-root.vb-root`.
- `alvo:medir … --quieto-ms 2000`, rodado **duas vezes por tela**, viewport 1280×900, dark (padrão do `alvo.mjs`). Importação e Caixa: `cmp` sem saída na 1ª dupla. Pedidos: a 1ª dupla divergiu **só** em `base.assinatura`, porque o relógio do header ("Atualizado 18:21" → "18:22") virou o minuto entre as execuções; re-medido, a 2ª dupla saiu byte-idêntica. Esse campo é de página e informativo no `secao-check` — todos os alvos existentes carregam o mesmo relógio.

## Provas de efeito

- `node scripts/qa/secao-check.mjs --todos --servir-espelho --porta 5553`: **conforme**, rc 0, os 12 alvos da pasta; os 3 novos com `~ base.assinatura` informativo (o relógio).
- `placar.mjs --thread A2`: antes deste recibo, `[proximo] (sem recibo)` — a prova `json_com_chaves` já via o `vendas--caixa--index.alvo.json`.

## Caixa: o pendente "Onda 6+1" — antes e depois

O índice pede registrar. A A2 é read-only, então **antes = depois** no vivo (`Sells/Caixa/Index.tsx` em `5606344ca`):

- `:113` comentário: esperado/conferido/diferença "ficam pra Onda 6+1".
- `:122` "Fechar caixa" navega pro modal legado `/cash-register/close-register/{id}`.
- `:301–312` seção **Movimentos do caixa** — placeholder "read-only · Onda 6+1 wire-up", sangria/suprimento pelo `/cash-register`.
- `:315–335` seção **Conferência física** — placeholder "read-only · Onda 6+1 wire-up", denominações no modal legado.

O que o alvo mede do protótipo é a visão **Caixa do dia** (KPIs + por forma de pagamento + por origem), que é o que o vivo já entrega. Movimentos e conferência vivem, no protótipo, na aba **Turnos** (`/cash-register`: abrir turno, contar a gaveta, fechar), que só monta após clique e **não foi medida** — é o escopo da thread 07.

## Fora do alvo, de propósito

- Importação: a prévia pós-envio (`VendaImportPreview`) só existe depois de escolher arquivo + local e clicar; é da thread 05.
- Pedido de venda: o modal "Editar status".
- Caixa: a aba Turnos (acima) e os modais Detalhe do turno / Abrir / Fechar caixa.

## Errata para o [CC] (não editei o índice)

1. **O `pedido.mjs` não encontra estes alvos pelo nome da Page.** Ele resolve `--tela` normalizando pra letras (`Sells/Caixa/Index` → `sellscaixaindex`) e procura esse slug em `governance/design/targets/`. O índice fixou os slugs como `vendas--<tela>--index`, e a prova da A2 aponta pra `vendas--caixa--index.alvo.json`. Medido: `pedido.mjs --tela Sells/Caixa/Index --secoes` sai **2 NÃO MEDI**. As threads 05 (`ImportSales/…`), 06 (`SalesOrder/…`) e 07 (`Sells/Caixa/…`) vão tomar o mesmo 2 se abrirem pelo `/onda` de seção. Duas saídas, e a escolha é do dono do índice: renomear os slugs pra forma da Page (e trocar a prova), ou o `pedido.mjs` aceitar um apelido. Não escolhi nenhuma.
2. **A proveniência `url` destes 3 alvos é `http://127.0.0.1:5552/`, não `5550`.** A 5550 estava ocupada pelo servidor de outra sessão (worktree `confident-austin`, servindo o espelho **dela**); medir ali mediria outra árvore. A porta não entra no que o `secao-check` bloqueia, mas quem re-medir na 5550 pelo comando do README verá o campo `url` mudar.

## PARAR SE

Nenhum disparou: nenhuma rota nova, nenhum PR acima de 300 linhas de código (o diff é JSON medido + este recibo), nenhuma tela de valor tocada.
