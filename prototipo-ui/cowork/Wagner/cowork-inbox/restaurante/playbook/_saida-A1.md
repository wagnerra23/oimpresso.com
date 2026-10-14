---
sessao: "_saida-A1"
thread: "A1 · ALVO: mesas · atendentes · modificadores · reservas · cozinha · pedidos"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/alvo-restaurante)"
base_lida: wagnerra23/oimpresso.com@main 8fe939db25
---
# _saida-A1

## Entregue: os 6 alvos, medidos pela máquina
Gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho `prototipo-ui/cowork/Wagner/`,
servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5581/`.
Tema dark (default do espelho, conferido em `base.theme` dos seis), viewport 1280×900, browser limpo.
Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam seletores colhidos com `alvo:mapa`,
registrados em `_mapa` dentro de cada um.

As rotas são as da `_saida-P1` ("Para a A1") mais `cfg-atendentes`, que está no título da thread:

| slug | rota | fonte no protótipo | seções | nós |
|---|---|---|---|---|
| `restaurante--mesas--index` | `cfg-mesas` | `RestauranteExtrasPage`, `app.jsx:847` | cabecalho · abas · barra · kpis · tabela | 552 |
| `restaurante--atendentes--index` | `cfg-atendentes` | `RestauranteExtrasPage`, `app.jsx:847` | cabecalho · abas · barra · kpis · tabela | 554 |
| `restaurante--reservas--index` | `cfg-reservas` | `RestauranteOperacaoPage`, `app.jsx:848` | cabecalho · abas · kpis · tabela | 542 |
| `restaurante--cozinha--index` | `cfg-cozinha` | `RestauranteOperacaoPage`, `app.jsx:848` | cabecalho · abas · kpis · barra · pedidos | 497 |
| `restaurante--pedidos--index` | `cfg-pedidos-rest` | `RestauranteOperacaoPage`, `app.jsx:848` | cabecalho · abas · filtros · prontos · todos | 561 |
| `restaurante--modificadores--index` | `cfg-modificadores` | `configuracoes-cadastros.jsx` (rota que a P1 manda usar) | cabecalho · abas · toolbar · tabela · nota | 513 |

Comando, por tela: `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5581/ --tela <slug>
--rota <rota> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`.
Saídas: `ausentes no DOM: 0` nas seis.

Casca medida: as cinco telas do Restaurante montam `.pb-root` = `div.cli-ph` (p + header) · `div.pb-body`
· `div` vazio; a barra de abas (`nav.ds-tabbar.vb-nav`, 5 abas) fica dentro do `.pb-body`.
Modificadores é a tela de Configurações (`.cfg-page`), com casca própria. Fora do alvo, sem filhos no
render: o `div` vazio no fim do `.pb-root`, e em Modificadores `div.hrm-pag` e `div.hrm-toast-host`.

## Lista real, não esqueleto
A `base.assinatura` de cada uma traz o conteúdo da tela: Mesas "Todas as suas mesas 3 mesa(s)" com as
linhas Mesa 01…; Atendentes "Pessoal de atendimento 3 atendente(s)" com as colunas Atendente · Mesa
agora · Pedidos hoje; Reservas "Todas as reservas 2 reserva(s)"; Cozinha "Na fila 1 pedidos com item por
preparar"; Pedidos "Itens prontos para servir 3 item(ns)" com Mesa 02.

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Estável:** o `alvo.mjs` espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`,
  mais `--quieto-ms 2000`.
- **Determinismo:** cada tela re-medida com `--saida` num arquivo temporário e comparada por `cmp`.
  Mesas, Reservas, Pedidos e Modificadores: byte-idênticas na 1ª dupla. Atendentes e Cozinha divergiram
  na 1ª dupla **só** em `base.assinatura`, porque o relógio "Atualizado HH:MM" do header virou o minuto
  (16:10→16:11, 16:11→16:12) — o mesmo caso da `_saida-A2` de Produto. A 2ª dupla das duas saiu
  byte-idêntica, e os arquivos gravados são os dela.
- **A-LOTE:** um alvo por tela, hashes (sha256 do `.alvo.json`) diferentes nos seis — `0b6c9948` mesas ·
  `5b66a46f` atendentes · `33a3483c` reservas · `fe32d4c3` cozinha · `ada4e216` pedidos · `af4d23bd`
  modificadores. Mesas e Atendentes coincidem em **todas** as seções: é a mesma `RestauranteExtrasPage` com
  `view` diferente, e a medida é de estilo e estrutura, sem texto. A `base.assinatura` mostra que o
  conteúdo é outro (título, KPIs, colunas da tabela) — não é a mesma tela medida duas vezes.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: `✓ <slug> — N seção(ões) conforme`,
`secao-check: conforme`, rc=0, nas seis.

## Fora deste PR
- `governance/design/targets/README.md` (tabela "Alvos exportados") não foi tocado: o prefixo da thread é
  `${ALVOS}/restaurante--*`.
- **Atendentes** está medido porque o título da thread o lista, mas o índice não lhe deu prova nem thread
  (`00-INDICE.md:238`: "não achei controller próprio no menu — conferir se é o filtro de service staff de
  Usuários"). O alvo fica pronto para quando o dono decidir onde a tela mora.
- Fora do alvo: drawers (detalhe e "Nova reserva", mesa, atendente), estados pós-clique ("Marcar como
  preparado", "servido"), o filtro de atendente diferente de "Todos", e as abas de Configurações além de
  Modificadores.
- A `url` dos alvos carrega a porta 5581; re-medir em outra porta muda só esse campo.
