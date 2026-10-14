---
sessao: "_saida-A1"
thread: "A1 · ALVO: woocommerce (painel · API · log de sincronização)"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/alvo-woocommerce)"
base_lida: wagnerra23/oimpresso.com@main 8fe939db25
---
# _saida-A1

## Entregue: 3 alvos, medidos pela máquina
Gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho `prototipo-ui/cowork/Wagner/`,
servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5581/`.
Tema dark (default do espelho, conferido em `base.theme` dos três), viewport 1280×900, browser limpo.
Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam seletores colhidos com `alvo:mapa`,
registrados em `_mapa` dentro de cada um.

A rota é uma só, `woocommerce` (`app.jsx:846` → `WooCommercePage`, `integra-extras.jsx:62`), com 4 abas
(Produtos · Pedidos · Log de sincronização · Configuração). As três telas de produção do índice
(`index` · `apiSettings` · `viewSyncLog`) caem em três abas:

| slug | aba | Blade de produção | thread | seções | nós |
|---|---|---|---|---|---|
| `woocommerce--index` | Produtos (default, sem clique) | `woocommerce.index` | 01 | cabecalho · abas · kpis · tabela | 622 |
| `woocommerce--log--index` | Log de sincronização (`--clicar` na 3ª aba) | `woocommerce.sync_log` | 02 | cabecalho · abas · log | 488 |
| `woocommerce--api--index` | Configuração (`--clicar` na 4ª aba) | `woocommerce.api_settings` | 02 | cabecalho · abas · barra · formulario | 479 |

Só `woocommerce--index` é prova do índice; os outros dois saem do mesmo título ("painel · API · log") e
do prefixo `${ALVOS}/woocommerce--*`, para a thread 02 já nascer com alvo.

Comando: `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5581/ --tela <slug> --rota woocommerce
--secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`, mais
`--clicar '.main-body > .pb-root > .pb-body > nav.ds-tabbar > button:nth-child(3|4)'` no log e na API.
O clique fica gravado no alvo (`clicar`), e o `secao-check` o repete. Saídas: `ausentes no DOM: 0` nos três.

Casca: `.pb-root` = `div.cli-ph` (p + header) · `div.pb-body` · `div` vazio (fora do alvo, sem filhos).

## Lista real, não esqueleto
`base.assinatura`: o painel traz "Produtos na loja 5 produto(s)" com as colunas SKU · Produto · ID na loja ·
Preço · Estoque enviado e as linhas `PRD…`; o log, "Log de sincronização 3 execução(ões)"; a API, o
formulário (Endereço da loja · Local do estoque enviado · Sincronização automática · Testar conexão). A
conferência de que cada clique trocou de aba é estrutural também: o `.pb-body` do log tem
`nav · section`, o da API `nav · div · section`, o do painel `nav · div.cu-kpis · section`.

Tier 0 da thread 02 já está no desenho: a aba Configuração diz que consumer key e secret "não aparecem
nesta tela".

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Estável:** o `alvo.mjs` espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`,
  mais `--quieto-ms 2000`.
- **Determinismo:** cada alvo re-medido com `--saida` num arquivo temporário e comparado por `cmp`. Log e
  API: byte-idênticos na 1ª dupla. O painel divergiu na 1ª dupla **só** em `base.assinatura`, porque o
  relógio "Atualizado HH:MM" do header virou o minuto (16:18→16:19); a 2ª dupla saiu byte-idêntica, e o
  arquivo gravado é o dela.
- **Hashes distintos** (sha256 do `.alvo.json`): `f949c401` painel · `d6b8b890` log · `bbf38287` API.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: `✓ <slug> — N seção(ões) conforme`,
`secao-check: conforme`, rc=0, nos três.

## Fora deste PR
- `governance/design/targets/README.md` (tabela "Alvos exportados") não foi tocado: o prefixo é
  `${ALVOS}/woocommerce--*`.
- A aba **Pedidos** não tem alvo: não há Blade de produção correspondente entre as três do índice.
- Fora do alvo: o detalhe expandido de uma linha do log, os menus por linha do painel, o estado pós
  "Sincronizar"/"Testar conexão".
- A `url` dos alvos carrega a porta 5581; re-medir em outra porta muda só esse campo.
