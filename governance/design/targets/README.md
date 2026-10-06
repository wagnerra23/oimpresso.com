# `governance/design/targets/` — o ALVO medido de cada seção

Saída de `scripts/design-sync/alvo.mjs --alvo` (PR-A1 do protocolo de export). Um arquivo
`<tela>.alvo.json` por tela: o que a seção **tem de ter**, medido no DOM do espelho servido —
nós · filhos · ordem das classes · computed style · truncamento · retângulo.

É **fonte de teste**, não retrato: existe para o `secao-check` (PR-A3) comparar contra o render
e reprovar nomeando o ausente. Não é documentação e não se edita à mão — se o número está
errado, re-rode o comando.

## O gate que consome isto: `scripts/qa/secao-check.mjs`

```bash
npm run secao:check -- --todos --servir-espelho     # o modo do CI
npm run secao:check -- --tela jana--index --url <url do render>
npm run secao:selftest
```

Roda **todas** as seções do alvo, sempre — não só a da onda em curso. É o T6 do protocolo:
regressão de seção vizinha reprova no CI em vez de aparecer em review.

**O que bloqueia, e por que só isso.** Medido em 2026-09-17, espelho servido × o
`jana--index.alvo.json` medido em 2026-09-07: **14 campos divergiram** — `base.assinatura` e
`nos_totais` (2) e `rect.w/h` (12) — e **zero** em estrutura de seção. A causa foi o espelho ter
sido reimportado (o shell perdeu a sidebar, o conteúdo passou de 972 para 1176 px). Um predicado
"tudo igual" nasceria **vermelho herdado** numa árvore limpa: 100% de falso-positivo,
PR-independente — a lápide [§5 2026-08-24](../../../memory/proibicoes.md) (predicado absoluto em
vez de delta). Então:

| campo | papel | bloqueia? |
|---|---|---|
| `ausente` · `nos` · `filhos` · `ordemClasses` · `estilo` · `truncado` | é o **slot** — o que a seção tem de ter | **sim** |
| `rect` (geometria) · `base`/`nos_totais` (página) | consequência de viewport/shell | não — sai como informativo, nunca calado |

**Exit:** `0` conforme · `1` regrediu (nomeia o slot) · `2` **não medi**. O 2 é separado de
propósito: falha de browser/rota/render não acusa o PR — colapsar os dois seria falso-positivo
por não-medição.

**Verde aqui não é "está igual ao design"** (T7). T7 exige a fonte provada fresca
(`cowork-mirror-freshness --compare <snap>`, que depende de `DesignSync.get_file` — auth
interativa, [ADR 0315](../../../memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md),
e **não roda em CI**) mais `design-diff --compare` nos dois renders. Este gate responde uma
pergunta só: *o render ainda tem os slots que o alvo declara?*

**Editar o alvo é a forma de burlar o check** — some o slot do alvo, some a cobrança. Por isso
`governance/design/targets/**` está no gatilho da lane: mexer aqui dispara o comparador.

## Por que NÃO fica em `governance/design/contracts/`

Aquela pasta tem outro dono e outro vocabulário:

| | `contrato/` | `alvos/` (aqui) |
|---|---|---|
| Dono | `contract.schema.json` + `scripts/contrato-de-tela.mjs` | `scripts/design-sync/alvo.mjs` |
| Como mede | **estático** — copy literal + âncora `data-contract` no `.tsx`, sem render | **runtime** — DOM medido no browser |
| Chave `alvo` significa | "dirs/arquivos de produção checados" | (não existe — o arquivo inteiro é o alvo) |

Gravar `<tela>.alvo.json` lá colidiria de pasta **e** de vocabulário: `alvo` já quer dizer outra
coisa naquele schema. O gate estático continua sendo o dono da fidelidade de copy/ordem no fonte
(ADR 0290 derrubou o render pareado em CI; o v1 estático é o que sobreviveu) — este diretório é a
camada de runtime que ele deliberadamente não cobre.

## Comandos

```bash
npm run alvo:mapa -- <url> --raiz <seletor>          # explora: stdout only, nunca grava
npm run alvo:medir -- <url> --tela <slug> --secoes <arq.json>
npm run alvo:selftest                                 # parte pura (sem browser)
npm run alvo:selftest:browser                         # bite-test real: 2 runs idênticos + injeção muda
```

O `--mapa` **não grava de propósito** — mapa é comando, não arquivo ([ADR 0256](../../memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md):
derivado sobrevive, escrito apodrece).

## Entrada: `<tela>.secoes.json` (versionado ao lado do alvo)

O `--alvo` recebe as seções por arquivo (`--secoes`). Ele fica **aqui**, com o mesmo slug do alvo,
porque o alvo tem de ser re-executável por quem não viu a sessão: `<id>: { seletor, campos? }`.
Chave que começa com `_` é nota de proveniência (de onde os seletores foram colhidos) e a sonda a ignora.
Os seletores vêm do `--mapa` (DOM vivo), nunca de lembrança — o §2 do PROTOCOLO tem os 4 seletores
inventados que isso evita.

## Páginas com carga em fases — `--aguardar-sumir` e `--quieto-ms`

"Duas leituras iguais" aprova qualquer fase que fique parada 400 ms — e o Painel da Jana tem três
(`jm-sk-nota` → `.jm-sk` → conteúdo, re-armado quando a empresa do shell chega). Medido 2026-09-06:
o mesmo comando devolveu **719** nós num run e **1011** no seguinte. As duas flags fecham isso:
`--aguardar-sumir .jm-sk` (só mede depois que o esqueleto entrou **e** saiu; se nunca sair, exit 2)
e `--quieto-ms 2000` (janela mínima sem mudança no nº de nós). O alvo grava as duas como
proveniência (`aguardou_sumir`, `quieto_ms`) — quem re-rodar sem elas não reproduz o arquivo, e é
isso que o byte-idêntico denuncia.

## Determinismo

Duas execuções seguidas produzem bytes idênticos (chaves ordenadas, geometria arredondada, zero
timestamp) — sem isso o `--check` do A3 acusaria ruído como regressão. A medida só acontece depois
de `window.__oiLazyDone` **e** de duas leituras iguais de `querySelectorAll('*').length`: número que
ainda está subindo não é medida, é retrato de meio-caminho (§5 2026-08-24).

Bite-test verde 6/6 em 2026-09-03 e 9/9 em 2026-09-06 (`--selftest --browser`, chromium local) — os
3 novos provam `--aguardar-sumir` (esqueleto de 700 ms · controle negativo que nunca sai → NÃO MEDI)
e `--quieto-ms` (carga em 2 fases, 300 e 900 ms → mede o estado final).

## `_ausentes`: onde se declara POR QUE uma seção não foi entregue (PR-A6)

O placar (`scripts/qa/placar.mjs`) cobra, de toda seção que não está no lado medido, um **motivo
declarado**. O motivo mora no `<tela>.secoes.json` — o arquivo de **entrada**, escrito à mão — na
chave `_ausentes`, e **não** no `<tela>.alvo.json`:

```jsonc
{
  "_": "…nota de proveniência…",
  "_ausentes": {
    "metas": { "motivo": "sem endpoint",      "nota": "GET /ia/metas não existe ainda" },
    "kpis":  { "motivo": "decisão [W]",        "nota": "2026-09-17 — fora do recorte" }
  },
  "header": { "seletor": ".jc-page > div:first-child > header" }
}
```

Enum fechado (motivo fora dele é reprovação, nunca tolerância): **`sem endpoint`** ·
**`campo inexistente`** · **`decisão [W]`**.

**Por que aqui e não no `alvo.json`** — o plano do PR-A6 dizia `ausentes:` no alvo. Medido em
2026-09-17: [`alvo.mjs:201`](../../../scripts/design-sync/alvo.mjs) reemite `ausentes: []` em
**toda** medida, então declaração escrita ali é apagada no próximo `--alvo` — e este README já
diz, acima, que o alvo "não se edita à mão". A chave `_ausentes` fica no input porque o prefixo
`_` é justamente o que a sonda ignora por construção (`ALVO_PROBE_SOURCE`: `if (id.startsWith('_'))`).
O placar **reprova em voz alta** se achar `ausentes` preenchido no alvo, pro trap virar mensagem.

O placar reprova por **conteúdo**, não por presença de comentário no PR: ausência sem motivo ·
motivo fora do enum · motivo apontando pra seção já entregue (ponteiro podre) · id que não é seção.
Bite-test: `npm run placar:test` (27 casos, com controles negativos).

## Alvos exportados

| tela | slug | seções | como reproduzir |
|---|---|---|---|
| Jana/Index (Painel `/ia/dashboard`) | `jana--index` | 10 (header · header_titulo · tabs · brief · kpis · metas · analises_titulo · analises · acoes_titulo · acoes) | espelho servido (rota default `chat` + tab `painel`, dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela Jana--Index --secoes governance/design/targets/jana--index.secoes.json --aguardar-sumir .jm-sk --quieto-ms 2000` · viewport 1280×900 |
| Shell da sidebar (`cockpit/_sidebar`, sem rota: aparece em toda tela) | `cockpit--sidebar` | 5 (sb-modos · sb-topo · sb-corpo · sb-rodape · sb-alcas: as âncoras do `contracts/cockpit-sidebar.contract.json`) | espelho servido (rota default, dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela cockpit/_sidebar --secoes governance/design/targets/cockpit--sidebar.secoes.json --quieto-ms 2000` · viewport 1280×900, que no protótipo é o modo **rail** (auto-rail `max-width: 1280px` inclusivo); expanded e hidden não são alcançáveis por esta sonda |
| Financeiro/Unificado — seção 07, drawer do lançamento (thread 00 do playbook `cowork-inbox/financeiro`) | `financeiro--unificado` | 11 (dw-painel · dw-header · dw-fechar · dw-nav · dw-hero · dw-abas · dw-corpo · dw-veredito · dw-lente · dw-rodape · dw-rodape-botao) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela financeiro--unificado --secoes governance/design/targets/financeiro--unificado.secoes.json --rota financeiro --clicar "tbody tr.row-hover" --quieto-ms 1500` · viewport 1280×900. O drawer só existe após o clique — `--rota`/`--clicar` (2026-09-25) vão pro JSON e o `secao-check` os repassa. Seções 01–06 e a aba IA não medidas |
| Ponto/Dashboard/Index (Painel) — thread 32 do playbook `cowork-inbox/ponto` | `ponto--dashboard--index` | 6 (header · tabs · nota · kpis · fila · atividade — 4 são `data-contract` do `ponto-painel.contract.json`) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--dashboard--index --rota ponto --secoes governance/design/targets/ponto--dashboard--index.secoes.json --quieto-ms 2000` · viewport 1280×900. `presenca_agora` e `serie_7dias` existem só na produção e não entram |
| Ponto/Espelho/Index (lista) — thread 33 | `ponto--espelho--index` | 4 (header · tabs · barra · lista — seletores estruturais: a tela não tem `data-contract`) | idem, com `--tela ponto--espelho--index --rota pt-espelho --secoes …/ponto--espelho--index.secoes.json --quieto-ms 2000`. Escala · Trabalhado · HE · Saldo BH · Controla ponto = campo inexistente em `EspelhoController@index` (nota no `_saida-33`) |
| Ponto/Aprovacoes/Index — thread 34 | `ponto--aprovacoes--index` | 5 (header · tabs · kpis · barra · fila) | idem, com `--tela ponto--aprovacoes--index --rota pt-aprovacoes --secoes …/ponto--aprovacoes--index.secoes.json --quieto-ms 2000`. A barra de lote (só com seleção) não é medida |
| Ponto/Escalas/Index — 1ª tela da Vaga 3 (ADR 0418) | `ponto--escalas--index` | 5 (header · tabs · barra · lista · nota — a lista é o `data-contract` `escalas-escalas-cadastradas`; barra e nota são estruturais) | idem, com `--tela ponto--escalas--index --rota pt-escalas --secoes …/ponto--escalas--index.secoes.json --quieto-ms 2000`. O horário do 1º turno na sub-linha do Nome não vem no payload de `EscalaController@index` |
| Vendas · Importação de vendas (`venda-importar`, vivo ainda Blade) — thread A2 do playbook `cowork-inbox/venda-menu` | `vendas--importacao--index` | 5 (header · tabs · enviar · instrucoes · importacoes — estruturais: o protótipo não tem `data-contract` aqui) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela vendas--importacao--index --rota venda-importar --secoes …/vendas--importacao--index.secoes.json --quieto-ms 2000` · viewport 1280×900. A prévia pós-envio (`VendaImportPreview`) não é medida |
| Vendas · Pedido de venda (`venda-pedidos`, vivo ainda Blade) — thread A2 | `vendas--pedidos--index` | 4 (header · tabs · filtros · lista) | idem, com `--tela vendas--pedidos--index --rota venda-pedidos --secoes …/vendas--pedidos--index.secoes.json --quieto-ms 2000`. O modal de editar status não é medido |
| Vendas · Caixa (`venda-caixa`, vivo `Sells/Caixa/Index`) — thread A2 | `vendas--caixa--index` | 5 (header · tabs · abas · kpis · dia) | idem, com `--tela vendas--caixa--index --rota venda-caixa --secoes …/vendas--caixa--index.secoes.json --quieto-ms 2000`. Só a visão **Caixa do dia** (default); a aba **Turnos** (`/cash-register`) exige clique e é escopo da thread 07 |
| Financeiro/Dre/Index (DRE / Relatórios, aba Demonstrativo) — thread 09 do playbook `cowork-inbox/financeiro` | `financeiro--dre--index` | 8 (header · nav · bcrumb · abas · tabela · conta_titulo · conta · cards — `conta`/`conta_titulo` servem à thread 10, coluna Conta em mono) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela financeiro--dre--index --rota fin-dre --secoes governance/design/targets/financeiro--dre--index.secoes.json --quieto-ms 2000` · viewport 1280×900. Abas Balanço e Balancete não medidas (exigem clique) |
| Vendas · Lista de POS (legado Blade, `SellPosController@index`) — thread A1 do playbook `cowork-inbox/venda-menu` | `vendas--pos--index` | 6 (header · tabs · filtros · lista · toolbar · rodape — 3 são `data-contract` do `venda-blade.jsx`) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:<porta>/ --tela vendas--pos--index --rota venda-pos --secoes …/vendas--pos--index.secoes.json --quieto-ms 2000` · viewport 1280×900. O `base.assinatura` carrega o relógio "Atualizado HH:MM" do header: muda de minuto a minuto e sai como informativo |
| Vendas · Remessas (legado, `SellController@shipments`) — A1 | `vendas--remessas--index` | 6 (header · tabs · filtros · lista · toolbar · rodape) | idem, com `--tela vendas--remessas--index --rota venda-remessas`. O modal de status só existe após clique e não é medido |
| Vendas · Descontos (legado, `DiscountController`) — A1 | `vendas--descontos--index` | 6 (header · tabs · aviso · lista · toolbar · rodape — sem widget de filtros) | idem, com `--tela vendas--descontos--index --rota venda-descontos`. Papel default `administrador` (tem `discount.access`); o modal Adicionar não é medido |
| Vendas · Lista de devolução (legado, `SellReturnController`) — A1 | `vendas--devolucao--index` | 4 (header · tabs · kpis · tabela) | idem, com `--tela vendas--devolucao--index --rota venda-devolucoes`. Esta rota monta `VendasModule` (`vendas-extras.jsx`, `.vd-dev-page`), não o padrão `.vb-root` das outras três |
| Repair · Ordens de Serviço (`Repair/Index`, venda de reparo) — A1 do playbook `cowork-inbox/repair` | `repair--index` | 7 (header · tabs · kpis · filtros · status · lista · rodape) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:5577/ --tela repair--index --rota rep-reparos --secoes governance/design/targets/repair--index.secoes.json --quieto-ms 2000` · viewport 1280×900 · rota `rep-*` própria da thread 00 |
| Repair · Folhas de OS (`Repair/JobSheet/Index`) — A1 | `repair--jobsheet--index` | 6 (header · tabs · recorte · filtros · lista · rodape) | idem, com `--tela repair--jobsheet--index --rota rep-folhas` |
| Repair · Painel (`Repair/Dashboard/Index`) — A1 | `repair--dashboard--index` | 6 (header · tabs · alerta · kpis · distribuicao · tendencias) | idem, com `--tela repair--dashboard--index --rota rep-painel` |
| Repair · Produção oficina (`Repair/ProducaoOficina/Index`) — A1 | `repair--producao-oficina--index` | 5 (header · tabs · aviso · contagem · kanban) | idem, com `--tela repair--producao-oficina--index --rota rep-producao` |
| NfeBrasil · Tributação (`NfeBrasil/Tributacao`, aba default **Saúde fiscal**) — thread 12 do playbook `cowork-inbox/fiscal`; alvo de forma por D-ANCORA [W] 2026-10-06 | `nfe-brasil--tributacao` | 6 (header · tabs · abas · saude · pendencias · rodape — `pendencias` é o `data-contract` `saude-fiscal`) | espelho servido por `servirEstatico` (dark) · `npm run alvo:medir -- http://127.0.0.1:<porta>/ --tela nfe-brasil--tributacao --rota fiscal-tributacao --secoes governance/design/targets/nfe-brasil--tributacao.secoes.json --quieto-ms 2000` · viewport 1280×900. É o slug que o `pedido.mjs` acha para `NfeBrasil/Tributacao`; as 3 abas abaixo só montam após clique e têm alvo próprio |
| NfeBrasil · Tributação, aba **Operações** — thread 12 | `nfe-brasil--tributacao--operacoes` | 3 (abas · comecar · operacoes — os dois últimos são `data-contract`) | idem, com `--tela nfe-brasil--tributacao--operacoes --clicar ".fx-page > .fx-chips > [role=tab]:nth-child(2)"`. Drawer da operação e onboarding pelo certificado não medidos |
| NfeBrasil · Tributação, aba **Exceções** (id `regras`) — thread 12 | `nfe-brasil--tributacao--excecoes` | 5 (abas · cascata · toolbar · regras · decisao) | idem, com `--tela nfe-brasil--tributacao--excecoes --clicar ".fx-page > .fx-chips > [role=tab]:nth-child(3)"`. Drawer da regra não medido |
| NfeBrasil · Tributação, aba **Simulador** — thread 12 (insumo da thread 08, D-SIM) | `nfe-brasil--tributacao--simulador` | 2 (abas · simulador) | idem, com `--tela nfe-brasil--tributacao--simulador --clicar ".fx-page > .fx-chips > [role=tab]:nth-child(4)"` |
