---
sessao: "00"
titulo: "Recibo — PUXAR as 9 Pages vivas de Vendas para o protótipo (uma rota venda-* por Page)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 01-telas-legadas.md §00
veredito: "entregue 9 de 9 — as 9 Pages têm rota venda-* fixa (window.VendaRotas, lida pelo design-diff-lote); 6 receberam o que o vivo tem e o protótipo não tinha; Create, CreateV3 e Quotations ficaram com o diff registrado porque a âncora mora fora do prefixo. As 9 rotas renderizam no espelho servido com 0 erro de página e 0 erro de console, em 2 rodadas."
---

# _saída 00 · PUXAR o vivo de Vendas

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Nada subiu ao
> Cowork: escrever no Claude Design exige opt-in do dono (ADR 0315). Até a subida, o check
> `espelho — mexeu depois de verificar` acusa os 4 `.jsx` tocados. Isso é esperado.

## As 9 Pages, conferidas no working tree

`grep -rn "Inertia::render('Sells/" app/Http/Controllers` em `836619f64d` devolve as 9 do índice
e mais duas que nasceram depois dele: `Sells/Pos/Index` (`SellPosController.php:136`, thread 01) e
`Sells/Shipments/Index` (`SellController.php:3815`, thread 02). As duas são das threads 01 e 02 e
ficam fora desta.

| Page | controller | linha |
|---|---|---|
| `Sells/Index` | `SellController` | 663 |
| `Sells/Caixa/Index` | `SellController` | 834 |
| `Sells/Create` | `SellController` · `SellPosController` | 1107 · 308 |
| `Sells/Show` | `SellController` | 2711 |
| `Sells/Edit` | `SellController` | 3223 |
| `Sells/Drafts` | `SellController` | 3283 |
| `Sells/Quotations` | `SellController` | 3337 |
| `Sells/Subscriptions` | `SellPosController` | 2720 |
| `Sells/CreateV3` | `SellsV3Controller` | 44 |

## Por que faltava rota

Antes desta thread, `Sells/Show`, `Sells/Edit` e `Sells/CreateV3` não tinham rota. Um token
`venda-*` que o `app.jsx` não conhece cai em `route.startsWith("venda-")` com `view="pos"`, e a
página abre a Lista de POS. Medido no `HEAD`: `venda-v3`, `venda-ver` e `venda-editar` saíram
com o mesmo texto de 2.677 caracteres, o da Lista de POS.

Agora `VendaBladePage` lê `window.__route` (que o `app.jsx` já publica) contra a tabela `ROTAS` e
monta a vista certa. Nenhuma linha do `app.jsx` mudou. A tabela fica exportada em
`window.VendaRotas`, no formato que o `design-diff-lote` lê (`rotasDaAncora`).

## Mapa rota `venda-*` ↔ Page

| rota | Page Inertia | o que monta |
|---|---|---|
| `venda-todas` | `Sells/Index` | `VendaTodasPage` (venda-index.jsx), via `app.jsx` |
| `venda-nova` | `Sells/Create` | `VendaNova`, que hoje veste o create V3 (ver divergência abaixo) |
| `venda-v3` | `Sells/CreateV3` | `VendaV3Create` direto, sem "Voltar" (o vivo não tem) |
| `venda-ver` | `Sells/Show` | `VendaShow`, página de detalhe da venda `POS-2026-0482` |
| `venda-editar` | `Sells/Edit` | Lista de POS com o editor aberto na venda `POS-2026-0479` |
| `venda-rascunhos` | `Sells/Drafts` | `TelaDraft` rascunhos |
| `venda-cotacoes` | `Sells/Quotations` | `OrcListPage` (orc-page.jsx), via `app.jsx` (D-ORC-2) |
| `venda-assinaturas` | `Sells/Subscriptions` | `TelaAssinaturas` |
| `venda-caixa` | `Sells/Caixa/Index` | `VendaCaixa`, aba "Caixa do dia" |

`rotasDaAncora(venda-blade.jsx)` devolve as 9 pares acima, um token por Page.

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos `.tsx` em `resources/js/Pages/Sells/` no `main` `836619f64d`. Nenhum `.tsx`
foi editado.

| Page | entrou no protótipo |
|---|---|
| `Sells/Index` | Segmento **Foco** (Caixa · Faturamento · Comissão) no cabeçalho, que troca o 4º KPI: **Pagos hoje** ("N pago(s) hoje"), **Notas fiscais** ("autorizadas · N processando · N rejeitadas") ou **Top vendedor (mês)** ("… no mês" / "sem commission_agent atribuído este mês"). 5º KPI **PIX hoje** ("N% do faturamento — imediato"). Faturado hoje com "↑ +N% vs ontem · N vendas"; Ticket médio com "— vs semana passada"; A receber com "✕ N estourado(s) · ▲ N atrasando · ● N fresco(s)" e faixas "0–30d" e "31–60d". Botões **Imprimir caixa** e **Visões ▾** (Lista de vendas · Caixa do dia · Orçamentos · Rascunhos · Assinaturas · Abrir PDV balcão F2). As 7 visões salvas do vivo (Pendentes pgto. · Pendentes · Aguardando faturamento · Atrasadas · NF-e rejeitadas · Faturadas (mês) · Todas) mais "★ Favoritas (pessoais · atalho B)", no lugar do botão avulso "Aguardando faturamento". Busca com "Buscar venda, cliente, chave SEFAZ…". "Nova venda" com o atalho `N`. |
| `Sells/Show` | Vista nova `VendaShow`: título "Venda #…" com data · local; **Imprimir** com as 5 saídas do vivo (Recibo / fatura (P) · Romaneio / packing slip · Nota de entrega · Recibo térmico (80mm) · Orçamento A4), **Editar** e **Excluir** por permissão; KPIs Total · Pago · Falta · Status pgto; seções Cliente, Itens da venda ("N item(s)": Produto · Qtd · Unit · Desc. · Subtotal), Pagamentos ("N lançamento(s)", vazio "Nenhum pagamento registrado"), Frete, Histórico; coluna lateral "Todas as transições" e "Atalhos" (E Editar · P Imprimir · Esc Voltar). |
| `Sells/Edit` | No editor: faixa Itens · Total venda · Pago · Status pgto ("falta receber"); campo **Status** (Final · Rascunho · Cotação · Proforma); bloco "Desconto e observações" (Tipo de desconto: Percentual (%) / Valor fixo (R$), Valor desconto); busca "Buscar produto por nome, SKU ou código de barras…"; "Observação da venda" virou **Observações**; bloco "Responsável, notas e anexos" (Responsável / comissionado, "— Sem responsável —", Nota interna (equipe)); bloco **Frete** (Frete (R$), Status frete "— Selecione —", Endereço entrega, Endereço de cobrança (se diferente de entrega), Detalhes frete) com os placeholders do vivo. |
| `Sells/Drafts` | KPI **Total rascunhos**; coluna Data com hora (o vivo formata data e hora); estados vazios "Nenhum rascunho" / "Comece uma venda nova — pode salvar como rascunho a qualquer momento." e, com busca, "Nenhum rascunho encontrado" / "Tente outro termo de busca.". |
| `Sells/Subscriptions` | KPIs **Total · Ativas · Pausadas**; linha "Cobranças recorrentes — start/stop e acompanhar próxima fatura."; estados vazios "Nenhuma assinatura ativa" / "Configure venda recorrente ao criar uma venda nova." e, com busca, "Nenhuma assinatura encontrada". |
| `Sells/Caixa/Index` | O pendente "Onda 6+1" que a thread 07 fechou no vivo: seção **Movimentos do caixa** ("turno aberto · somente leitura": "Turno #1 · aberto em … · Matriz · troco inicial R$ 300,00", totais por forma Vendas · Despesas · Devoluções com "Total do turno", lista Hora · Tipo · Forma · Venda · Valor) e seção **Conferência física** ("contagem e fechamento do turno": Esperado em dinheiro, Contado em dinheiro, Diferença "bateu certinho / sobra / falta", Comprovantes de cartão, Cheques, "Observação de fechamento (obrigatória: há diferença)", "Fechar caixa com esta contagem", e os dois erros do vivo). KPIs com a copy do vivo ("N vendas", "cash · imediato", "#1"). Título "Caixa do dia" e a linha "Conferência por forma de pagamento, sangrias e fechamento". Corrigido "balão" → "balcão". |

Os números do caixa fecham entre si: vendas do turno 4.586,90 = dinheiro 986,90 + cartão 2.210,00
+ Pix 1.390,00, os mesmos do `TURNOS[0]` que a aba "Turnos" já usava. O esperado em dinheiro é a
mesma conta da aba Turnos (inicial + dinheiro − despesas − devoluções = 1.081,90). A diferença é
feita em centavos inteiros, como no vivo.

## Ficou fora, e por quê

- **`Sells/Create`** — a âncora declarada é `vendas-create-page.jsx`, fora do prefixo. Diff
  registrado: o vivo navega por Dados · Reparo · Produtos · Resumo · Pagamento · Mais opções; a
  âncora tem Dados · Produtos · Frete · Pagamento · Fiscal (e Veículo · Serviços e peças no modo
  oficina).
- **`Sells/Quotations`** — `venda-cotacoes` monta `OrcListPage` (orc-page.jsx, fora do prefixo).
  Diff registrado: o vivo tem KPI "Total cotações", colunas Data · Nº cotação · Cliente · Local ·
  Itens · Ações, ações **Editar · Enviar · Converter em venda** (com confirmação "Converter a cotação
  … em venda?") e busca "Buscar por nº ou cliente…". O `OrcListPage` tem funil
  (Rascunho · Enviado · Negociação · Aprovado · Perdido), KPIs de valor e conversão, e nenhuma ação
  "Converter em venda". A `TelaDraft` de cotações do `venda-blade.jsx` ganhou a mesma copy, mas é
  inalcançável desde a Q-CC.
- **`Sells/CreateV3`** — o `venda-v3.jsx` do espelho é build do código vivo e está **à frente**
  dele: o arquivo mudou em 2026-10-06 (`73ba361e01`), o `CreateV3.tsx` em 2026-09-21
  (`fb0975a5ea`). Título e subtítulo batem. Só ganhou rota.
- **O que o vivo tem e é defeito não entrou.** A faixa "Preview de design — não é a tela de
  produção" do `CreateV3` é aviso de ambiente, não desenho. O rótulo de status `final` em minúscula
  (`lang/pt/sale.php:34`) entrou como "Final".
- **CSS** (fora do glob). A grade de 5 KPIs do Index usa `gridTemplateColumns` inline, porque
  `.vi-kpis` tem 4 colunas no `venda-index.css`.
- **Sidebar** (`data.jsx`). As rotas novas não viraram atalho.

## Só no protótipo — para [W] decidir

Nada disto foi apagado.

1. **`venda-nova` abre a V3.** O item "Adicionar venda" do protótipo abre o create V3; em produção
   `/sells/create` abre `Sells/Create.tsx`. Hoje `venda-nova` e `venda-v3` renderizam o mesmo
   formulário (1.874 × 1.851 caracteres), o que deixa a medida de `Sells/Create` sem rosto próprio.
2. **Index:** botão "Lista de POS" no cabeçalho e a nota "cancelada não entra" no KPI A receber.
   (As três visões Operacional · Financeira · Produção e a emissão de NF-e em lote existem nos dois
   lados: `SellsTabsVisao.tsx:44-52` e `Index.tsx:1635`.)
3. **Rascunhos e cotações:** coluna "Valor" e o bloco de filtros (local · cliente · período ·
   usuário). O vivo só tem a busca.
4. **Assinaturas:** coluna "Faturas geradas" e o aviso "Cobranças recorrentes".
5. **Caixa:** a aba "Turnos" (abrir turno, contar por cédula e moeda, histórico de turnos). O vivo
   não tem lista de turnos.
6. **Detalhe da venda:** o drawer da lista (`VendaDetalhe`) segue com "Mensagem para o cliente" e
   "Ordem de serviço"; a página `Sells/Show` não tem essas seções com esse nome (a emissão fiscal
   ela tem, pelo painel de próxima ação).
7. **Editar:** o protótipo edita em modal; o vivo é página com navegação de seções
   (Dados · Produtos · Pagamento · Resumo · Mais opções) e "Descartar rascunho auto-salvo".

## Um defeito do protótipo que esta thread achou e corrigiu

`TelaPos`, `TelaDraft`, `TelaAssinaturas` e `VendaTodasPage` usavam `<Widget>` sem conferir se o
`produto-blade.jsx` (que publica `window.PBUI`) já tinha chegado pelo lazy-load. No `HEAD`, as
rotas que montam essas telas saíam com 2 erros de página "Element type is invalid … Check the
render method of `TelaPos`/`TelaDraft`". Agora as quatro devolvem `null` até o `PBUI` existir,
como as outras telas do arquivo já faziam.

## Provas

Render no espelho servido por `servirEstatico` (o do `render-proto-baseline.mjs`, que resolve o
DS em `_ds/`), porta 5585 a 5587, viewport 1280×900, esperando `window.__oiLazyDone` e mais
1,5 s. Para cada rota foi conferida a copy puxada do vivo (sem distinção de caixa, porque o CSS
põe títulos em maiúsculas).

| rota | `HEAD` (erros página / console) | agora, rodada 1 | agora, rodada 2 | copy esperada achada |
|---|---|---|---|---|
| `venda-todas` | 2 / 3 | 0 / 0 | 0 / 0 | 5 de 5 |
| `venda-nova` | 0 / 0 | 0 / 0 | 0 / 0 | 1 de 1 |
| `venda-v3` | 2 / 5 (abria a Lista de POS) | 0 / 0 | 0 / 0 | 2 de 2 |
| `venda-ver` | 2 / 5 (abria a Lista de POS) | 0 / 0 | 0 / 0 | 4 de 4 |
| `venda-editar` | 2 / 5 (abria a Lista de POS) | 0 / 0 | 0 / 0 | 4 de 4 |
| `venda-rascunhos` | 2 / 5 | 0 / 0 | 0 / 0 | 2 de 2 |
| `venda-cotacoes` | 0 / 0 | 0 / 0 | 0 / 0 | 1 de 1 |
| `venda-assinaturas` | 2 / 5 | 0 / 0 | 0 / 0 | 3 de 3 |
| `venda-caixa` | 0 / 0 | 0 / 0 | 0 / 0 | 5 de 5 |

O `HEAD` foi medido com cópia do espelho e os 4 `.jsx` repostos por `git show HEAD:`. A segunda
rodada "agora" inclui a última edição do Index (botão "Lista de POS" e "cancelada não entra"
devolvidos).

- Os 4 `.jsx` passam pelo parser do `esbuild` sem erro; nenhum tem byte de controle nem `CR`.
- `node scripts/design/ds-guard.mjs <4 jsx>` → `limpo`, **mas no vácuo**: o guard ignora `.jsx`
  ("ignorado: nao e css/html") e nenhum `.css` foi tocado.
- `rotasDaAncora(venda-blade.jsx)` (de `design-diff-lote.mjs`) → 9 pares Page → token, sem
  repetição de token.
- Não rodei o `design-diff-lote` contra o app vivo: precisa do app no ar com `/_visreg-login`. NÃO
  MEDI o lado produção.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/venda-index.jsx`
- `prototipo-ui/cowork/Wagner/venda-blade.jsx`
- `prototipo-ui/cowork/Wagner/venda-blade-caixa.jsx`
- `prototipo-ui/cowork/Wagner/venda-blade-telas.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/_saida-00.md`
