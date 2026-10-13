---
sessao: "01b"
titulo: "Contratos das 7 telas soltas — entregue 5 de 6 contratáveis (Home já tinha dono; kb bloqueada pelo header antigo)"
autor: "[CL]"
data: 2026-10-13
base: origin/main 8fe939db25
thread: 01-contratos.md
veredito: "parcial — perfil e suporte-visao neste PR; documentacao, suporte-empresas e cobranca no #8934; home já coberta por dashboard-visao-geral.contract.json; kb fica para depois da migração do header."
---
# _saida-01 · Contratos das telas soltas

> Recibo complementar ao `_saida-01.md` (06/10), que fica intocado: ele já foi verificado no espelho, e editá-lo à
> mão armaria o required "espelho — mexeu depois de verificar" (§5 2026-09-24). Este arquivo o substitui como estado
> atual da thread.

## Histórico da thread
- **06/10** (recibo anterior deste arquivo): bloqueada, 0 de 7. As Pages não tinham âncora e o índice põe
  `resources/js/Pages/` em `nao_toca`.
- **07/10 · #8934:** `documentacao`, `suporte-empresas` e `cobranca`, com âncoras nas Pages (precedente
  #8873/#8533). Parou em `kb`, `perfil` e `suporte-visao` porque o hook `block-mwart-violation` exige RUNBOOK da
  tela e elas não tinham. O recibo daquele PR ficou só no corpo do PR; o `_saida-01.md` não foi atualizado.
- **13/10 · este PR:** `perfil` e `suporte-visao`, com RUNBOOK criado para cada tela (precedente #9185/#9225). O `kb` entrou e saiu no mesmo PR (abaixo).

## Entregue neste PR
Cada contrato parte do `.alvo.json` da A1 (`governance/design/targets/soltas--<tela>--index.alvo.json`), não do
`.tsx`. Copy só onde protótipo, `base.assinatura` do alvo e código da Page (sem comentário) dizem literalmente o
mesmo, conferida por máquina na faixa de linhas de cada seção.

| contrato | Page | seções (âncora, em ordem) | copy |
|---|---|---|---|
| `perfil` | `User/Perfil` | `header` · `tabs` · `corpo` | 1 · 4 · 10 |
| `suporte-visao` | `Suporte/Visao` | `navrow` · `faixa` · `cabecalho` · `cards` · `titulo-lista` · `lista` · `rodape` | 1 · 4 · 0 · 5 · 1 · 4 · 0 |

## Fora, e por quê (detalhe no `_nota` de cada contrato)
- **`kb` (não entregue):** o `kb/Index.tsx` importa o `PageHeader` antigo (`Components/shared`) e está na dívida do
  required "PageHeader · ratchet (header antigo só decresce)". Tocar a tela, mesmo só com atributo, obriga a migrá-la
  para `@/Components/PageHeader` no mesmo PR (ADR 0409). A migração muda o visual do cabeçalho e não cabe numa thread
  de só atributo, então o `kb` saiu: contrato, âncoras, RUNBOOK e bump de `last_run` revertidos. Mapeamento pronto
  para quando o header migrar: alvo `header`/`stats`/`tri` (sem `abas_mobile`), sem copy comum, `stats` no
  `KpiGrid`, que repassa `data-contract`.
- **`home`**: `dashboard-visao-geral.contract.json` já é dono do alvo `Pages/Home` e passa no gate. A prova
  `home.contract.json` do índice está errada; um segundo contrato seria segundo dono (dito em 06/10 e no #8934).
- **`tabs` do perfil fora de `ordem`**: o `<nav>` é declarado no código antes do header (entra pelo slot `subnav`)
  e o gate ordena pela posição no arquivo. No DOM ele fica dentro do header.
- **Ids**: `titulo_lista` → `titulo-lista` (o schema recusa `_`); o posicional `bloco_3` da visão virou
  `cabecalho` (nome e número da empresa).

## Pages: só âncoras (`nao_toca` relaxado, precedente #9185/#9225)
10 atributos `data-contract`, sem dado, handler, copy nem classe. A âncora `header` do perfil precisou de um `<div>`
sem classe em volta do `PageHeader` canônico, que não repassa atributos ao DOM (mesma solução do #8934); os
primitivos `Inline`/`Grid` da visão repassam pelo `...props`.

## RUNBOOKs criados (exigência do hook, fora do prefixo)
`memory/requisitos/User/RUNBOOK-perfil.md` e `memory/requisitos/Suporte/RUNBOOK-visao.md`: estrutura, âncoras, contrato, tenant e o que a tela não faz.
Os dois passam no `memory-schemas/validate.mjs` (`runbook.schema.json`).

## `last_run` (G-6)
Nos 2 `casos.md` tocados, subiu para 2026-10-13 com nota no `last_run_ci`: revalidação analítica, suite não executada, a mudança é só
atributo e nenhum UC afirma âncora.

## Provas (rodadas localmente, árvore com o PR)
- `contrato-de-tela --contract` nos 2: rc=0, ordem coerente.
- Modos do job "Contratos de tela": os 92 contratos ativos (sem EXEMPLO) rc=0; `--map --check` rc=0 (as 2 telas
  `🟢 portado`, 3/3 · 7/7); `--anti-tautologia` rc=0, nenhum aviso para os 2; `--preflight origin/main` rc=0;
  `contrato-de-tela.test.mjs` e `contrato-de-tela-relevante.test.mjs` rc=0; intenção de fluxo rc=0.
- **Mordida por mutação em cópia isolada (`--root`):** intacta rc=0; sem `data-contract` de `corpo` e `cards`, rc=1 com `seção "<id>" sem âncora data-contract no alvo`.
- `casos-coverage-guard`: sem violação nova deste PR.

## Placar
thread 01: 5 de 6 contratos contratáveis (3 no #8934, 2 aqui) · home coberta por outro contrato · kb aguardando a
migração do header antigo.
