---
sessao: "00"
titulo: "Recibo — PUXAR as 4 Pages vivas de Estoque para o protótipo (uma rota est-* por Page)"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d
thread: 00-puxar-vivo.md
veredito: "entregue — 4 rotas est-* ligadas às 4 Pages, publicadas em window.EstRotas; as 9 rotas do módulo renderizam sem erro no espelho servido; só estoque-page.jsx tocado no protótipo."
---

# _saída 00 · PUXAR o vivo de Estoque

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Este recibo
> sobe ao Cowork pelo canal `cowork-inbox/` depois do merge (a isenção de opt-in da ADR 0412 só
> vale para blob já igual ao `origin/main`). O `estoque-page.jsx` não sobe:
> escrever tela no Claude Design exige opt-in do dono (ADR 0315). Até a subida dele, o check
> `espelho — mexeu depois de verificar` acusa o `estoque-page.jsx`. Isso é esperado.

## Por que a rota não era reprodutível

As rotas `est-*` já existiam no `ABAS` do jsx. Faltavam duas coisas:

- **A aba.** O `MP.useAba` lê o localStorage (`oimpresso.estoque.aba`) no primeiro render. Um
  efeito trocava para a aba da rota depois. O primeiro quadro mostrava a aba guardada.
- **As colunas.** O `usarCols` lia `oimpresso.estoque.colsAj` e `colsTr`. Quem escondeu uma
  coluna em "Colunas" mudava a medida da rota.

Agora a rota é o valor inicial da aba (`usarAbaEst`). Nas rotas, o `usarCols` recebe `fixo` e usa
as colunas padrão sem ler nem gravar o storage. A tabela fica em `window.EstRotas`, com o campo
`page`.

## Mapa rota `est-*` ↔ Page

| rota | Page Inertia | o que monta |
|---|---|---|
| `est-ajustes` | `StockAdjustment/Index` | aba Ajustes |
| `est-ajuste-novo` | `StockAdjustment/Create` | formulário "Novo ajuste de estoque" |
| `est-transferencias` | `StockTransfer/Index` | aba Transferências |
| `est-transferencia-nova` | `StockTransfer/Create` | formulário "Nova transferência de estoque" |
| `estoque` · `est-painel` | — (só protótipo) | aba Painel |
| `est-vencimentos` | — (só protótipo) | aba Vencimentos |
| `est-contagem` · `est-contagem-nova` | — (só protótipo) | aba Contagem · "Nova contagem cíclica" |

Nenhuma rota nova no `app.jsx`. Ele já manda `estoque` e todo `est-*` para `window.EstoquePage`
(`app.jsx:859`).

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos 4 `.tsx` e dos 4 charters no `main` `836619f64d`. Nenhum `.tsx` foi editado.

| Page | entrou no protótipo |
|---|---|
| `StockAdjustment/Index` | Coluna "Recuperado" visível por padrão. O vivo sempre mostra; o protótipo a escondia (`off`). Valor zero aparece como R$ 0,00, como no vivo, e não "—". Coluna "Ações" com Ver e Excluir por linha; Excluir só com a permissão de excluir. Vazio sem nenhum ajuste: "Nenhum ajuste de estoque registrado." e o botão "Registrar primeiro ajuste" (com permissão de criar). O vazio com filtro continua o do protótipo. |
| `StockTransfer/Index` | Coluna "Ações" com Ver, Imprimir e Excluir por linha. Vazio sem nenhuma transferência: "Nenhuma transferência registrada." e "Registrar primeira transferência". |
| `StockAdjustment/Create` | Nada novo. O título já era "Novo ajuste de estoque". Só ganhou rota fixa. |
| `StockTransfer/Create` | Título "Nova transferência" virou "Nova transferência de estoque", o do vivo. O formulário mora em `estoque-forms.jsx`, fora do prefixo. |

Nas duas listas o vivo já batia com o protótipo em: busca, filtro de local, período, coluna
"Motivo do ajuste", "Lançado por", "Mexeu no saldo?" e os textos de legenda do trilho.

## Só no protótipo — [W] decide

Nada disso foi apagado.

- **Filtro de local.** O vivo diz "Todas as filiais"; o protótipo, "Todos os locais".
- **"Mexeu no saldo?"** O vivo responde "Sim"/"Não"; o protótipo, "moveu"/"só reserva".
- **Status "Finalizada".** Só existe no protótipo. O vivo converte `final` em `completed` antes
  de mostrar (`StockTransferController.php:154`), então a lista viva nunca mostra "Finalizada".
- **Colunas "Itens" e id.** O protótipo tem a coluna "Itens" e o id (`AJ-0142`, `TRF-0088`) acima
  da referência. O vivo mostra só "Ref. Nº".
- **Paginação.** O protótipo pagina de 10 em 10. O vivo lista até 200 linhas sem paginar (charters).
- **Painel, Vencimentos e Contagem.** Não têm Page viva.

## Ficou fora, e por quê

- **`estoque-forms.jsx`** (fora do prefixo). O vivo rotula o tipo como "Normal (correção)" e
  "Anormal (perda/quebra)", chama o campo de "Filial *", põe Cancelar e Salvar no cabeçalho,
  usa `pending` como status padrão e oferece 3 status de entrada. Nada disso foi portado.
- **`estoque-data.jsx`** (fora do prefixo). O vivo mostra data com hora; o mock só tem a data.
- **O que o vivo tem e é defeito não entrou:** a descrição "registrados em audit trail" do
  `StockAdjustment/Create` e o "(multi-tenant Tier 0)" do `StockTransfer/Create`.
- **CSS.** Os botões de "Ações" reusam a classe `est-mini`, que já existe no `estoque-page.css`.
  Nenhum CSS novo.

## Provas

- **Render.** `servirEspelho` (de `scripts/design/design-diff-lote.mjs`) sobre o espelho,
  1280×900, playwright no scratchpad. As 9 rotas (`estoque` + 8 `est-*`) rodaram duas vezes: com
  storage limpo e com storage sujo (`aba=vencimentos`, `colsAj` sem Recuperado e sem Motivo).
  Resultado: 18 de 18 com `.est-root`, 0 erro de página, 0 erro de console. Cada rota abriu a aba
  ou o formulário da tabela acima nos dois casos. Em `est-ajustes` o cabeçalho saiu igual nos dois:
  `Ajuste|Data|Local|Tipo|Itens|Valor ajustado|Recuperado|Motivo do ajuste|Lançado por|Ações`.
  Botões por linha: 6 Ver e 6 Excluir em ajustes; 5 Ver, 5 Imprimir e 5 Excluir em transferências.
  `window.EstRotas` com 9 chaves.
- `node scripts/design/ds-guard.mjs prototipo-ui/cowork/Wagner/estoque-page.jsx` → `limpo`. O
  guard ignora `.jsx` ("ignorado: nao e css/html"), então isso não mede o jsx.
- `node scripts/design/protocolo.config.mjs --selftest` → OK.
- **Maps.** Os 4 `memory/requisitos/Estoque/stock-*.map.json` ficaram STALE com o jsx novo (no
  `main` o `consumir-map.mjs` saía 0 nos 4). Regenerados com `gerar-map.mjs <gap.md> --atualizar`:
  todas as partes preservadas (13, 10, 15, 12), muda só `prototipo_sha` e `gerado_em`.
  `consumir-map.mjs` → rc 0 nos 4.
- **Baselines.** `render-proto-baseline.mjs --check` no `main` `836619f64d`, antes desta thread:
  12 drifts em 9 baselines (Compras, Financeiro conciliacao/dre/fluxo/impostos/unificado, KB,
  Sells, TeamMcp/forja-cockpit). Não é do `estoque-page.jsx`: com o jsx novo o número é o mesmo.
  **Não regravadas aqui**, por decisão da fila de merges (2026-10-07): regravar baseline volta ao
  [W] e vira um PR único depois que as threads 00 de PUXAR entrarem. O drift pré-existente e o
  efeito deste jsx ficam para esse PR.
- **`_STATUS-GENERATED`.** `requisitos-status.mjs <Mod> --check`: Estoque, Compras, Financeiro e
  KB em dia. Sells drifado já no `main` (a tela `Quotations` ganhou `casos.md` no #8844 e o status
  não foi regerado); regenerado com `--write`. TeamMcp não tem `_STATUS-GENERATED.md` no `main`;
  não criei, fica fora.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/estoque-page.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/estoque/playbook/_saida-00.md`
