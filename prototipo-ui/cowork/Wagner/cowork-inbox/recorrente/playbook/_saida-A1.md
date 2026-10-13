---
sessao: "_saida-A1"
thread: "A1 · ALVO 4 Index: assinaturas · planos · faturas · configuracoes"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/recorrente-a1-alvos)"
base_lida: wagnerra23/oimpresso.com@main f363d90b35
---
# _saida-A1

## Entregue: os 4 alvos, medidos pela máquina
Os quatro foram gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho
`prototipo-ui/cowork/Wagner/`, servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`)
em `http://127.0.0.1:5562/`. Tema dark (default do espelho, conferido no `base.theme` dos quatro),
viewport 1280×900, browser limpo. Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam
seletores colhidos com `alvo:mapa`, e o resultado está registrado em `_mapa` dentro de cada um.

As rotas são as que a 00 fixou (`_saida-00`, mapa rota↔Page). O `app.jsx:917-921` manda as quatro para
`window.CobrancaRecorrentePage` com a vista fixa. O `HOJE` fixo do jsx (2026-06-01 12:00) mantém as
datas relativas estáveis.

| slug | rota | Page | seções |
|---|---|---|---|
| `recorrente--assinaturas--index` | `rb-assinaturas` | `RecurringBilling/Index` | 5: cabecalho · kpis · periodo · filtros · lista |
| `recorrente--planos--index` | `rb-planos` | `RecurringBilling/Planos/Index` | 5: cabecalho · subnav · kpis · ciclos · lista |
| `recorrente--faturas--index` | `rb-faturas` | `RecurringBilling/Faturas/Index` | 5: cabecalho · subnav · kpis · filtros · lista |
| `recorrente--configuracoes--index` | `rb-config` | `RecurringBilling/Configuracoes/Index` | 6: cabecalho · subnav · gateways · dunning · nfe · webhooks |

Comando (por tela): `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5562/ --tela <slug> --rota <rota> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`.
Saídas: `ausentes no DOM: 0` nos quatro, rc=0.

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Estável:** a medida espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`
  (dentro do `alvo.mjs`), mais `--quieto-ms 2000`.
- **Determinismo:** cada tela foi re-medida com `--saida` num arquivo temporário; `cmp` deu
  byte-idêntico nas quatro.
- **A-LOTE:** um alvo por tela, quatro hashes distintos (`1945dbda…` · `7bcf53ee…` · `b7e8ac1a…` ·
  `e1afb4c5…`). A `base.assinatura` de cada um abre com o título da própria vista ("Cobrança recorrente",
  "Planos · …", "Faturas · …", "Configurações · …").
- **Configurações:** os 4 cards são `section:nth-of-type(1..4)`. A ordem no texto medido (Gateways →
  Régua de dunning → NFe-de-boleto-pago → Webhooks) confirma que o nome de cada seção bate com o card.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: `✓ conforme` nos quatro (5 · 5 · 5 · 6
seções), rc=0.

## Achado para o índice (não editei o protótipo)
A vista Assinaturas **não tem** a `nav.ph-nav` de sub-telas que Planos, Faturas e Configurações têm logo
abaixo do cabeçalho. O alvo mede o protótipo como ele está; se a nav devia aparecer em Assinaturas
também, é ajuste no `cobranca-recorrente-page.jsx` (Cowork) e re-medida desta tela.

## Fora deste PR
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o prefixo da
  thread é `governance/design/targets/recorrente--*`.
- Planos Create/Edit (drawers, sem rota própria) seguem sem alvo; a rota própria é decisão [W] (`_saida-00`).
- O que a `_saida-00` lista como "só no protótipo" entra no alvo como o protótipo desenha; cada item é [W].
