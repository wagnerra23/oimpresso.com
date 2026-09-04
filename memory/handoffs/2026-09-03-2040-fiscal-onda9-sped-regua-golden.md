---
date: "2026-09-03"
time: "2040 BRT"
slug: fiscal-onda9-sped-regua-golden
tldr: "Onda 9 do Fiscal mergeada (#6708): régua de 4 checagens no SPED + golden do EFD-ICMS/IPI. O golden revelou que o gerador quebrava com TypeError antes de terminar e nunca tinha rodado ponta-a-ponta. LEIA A ERRATA no fim: a fonte do Cowork, lida depois do merge, mostra 5 Goals no charter (entregamos 1) e responde a pergunta da prévia — o F1 encena, nunca gerou."
decided_by: [W]
prs: [6708]
us: [US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
next_steps:
  - "Onda seguinte: os 4 Goals do charter do Cowork que faltam (bypass superadmin, prévia, cartão de validação externa, blocos) + mover a régua do drawer para barra na página"
  - "[W] decide a prévia em PRODUCAO: o F1 encena (Non-Goal do charter), mas encenar em prod seria fabricar — saídas honestas são a estrutura dos blocos e/ou o golden como referência de layout"
  - "[W] decide o emitente do registro 0000 (CNPJ/IE vazios, UF fixa SP) — muda o CFOP de toda operação"
  - "Smoke visual autenticado da régua no drawer (sessão caiu no deploy; senha é proibida ao agente)"
  - "[W] decide o wiring do ds-guard: 20 dos 39 css de resources/css/ acusariam, e >=5 sao falso-positivo por construcao (arquivos de token)"
---

# Handoff — Fiscal Onda 9 (SPED): régua de geração + golden file

## O que aconteceu

[PR #6708](https://github.com/wagnerra23/oimpresso.com/pull/6708) **mergeado** (`3f935647ed`,
23:22Z) com autorização explícita de [W]. Fecha `UC-FSF1-03` e `UC-FSF1-05`, os dois casos que
desceram do Cowork vermelhos de propósito.

Narrativa completa em
[`memory/sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md`](../sessions/2026-09-03-fiscal-onda9-sped-regua-golden.md).

## Estado MCP no momento do fechamento

⚠️ **As tools MCP do oimpresso não estavam disponíveis nesta sessão.** O brief chegou pelo hook
`brief-fetch-curl` do SessionStart (Brief #602, gerado há ~1h, cycle sem nome, 4 HITL pendentes com
[W]), **não** por tool — e o `whats-active` (LC-19) não pôde ser chamado. Registro isso como
limitação da sessão, não como consulta feita.

Substituto usado: `list_sessions` do host, que mostrou **5 sessões Fiscal paralelas ativas**
(Ondas 1, 2, 3 e duas na 7) — **nenhuma na Onda 9**. A checagem que de fato importava foi feita por
git, antes do merge: a interseção entre os arquivos que o `main` mudou desde a base e os que este
PR muda deu **vazia**.

## Estado do CI e do deploy

**CI:** 45/45 required verdes. Dois advisory vermelhos, **ambos com autoria medida como alheia**:

- **watchdog G6** — `jana-ragas-canary` falhou às 10:25Z, 11h antes do PR existir, mais um baseline
  parado há 64d. Causa raiz já diagnosticada em
  [2026-09-03-watchdog-g6-tres-achados-ragas.md](../sessions/2026-09-03-watchdog-g6-tres-achados-ragas.md)
  (conta OpenAI sem crédito desde 08-31, US-COPI-145).
- **visual-regression** — a tela é **Home**, e o PR não toca nenhum arquivo de Home. A baseline dela
  é o **mesmo blob** na última run verde do main e na base do PR; o render mudou por
  [#6698](https://github.com/wagnerra23/oimpresso.com/pull/6698) e
  [#6690](https://github.com/wagnerra23/oimpresso.com/pull/6690) sem regravar a baseline. O main
  está verde só porque o workflow não voltou a rodar lá depois desses merges.

**Deploy:** `success`. O deploy do próprio commit foi cancelado por concorrência; rodou o do
`f6058d59`, que tem `3f935647ed` como ancestral. O asset compilado servido por produção
(`build-inertia/assets/Sped-D55BGGG7.js`, mtime 23:31:20) contém a copy da régua, e o `curl -sv`
devolve `HTTP/1.1 200 OK`.

## Três coisas que ficam abertas

### 1. Pergunta a [W] — a prévia do TXT (item 5 do placar, não entregue)

> ⚠️ **Esta seção foi SUPERADA pela §ERRATA no fim deste handoff.** A pergunta abaixo está mal
> formulada: a fonte do Cowork mostra que a prévia nunca exigiu rodar o gerador. Fica preservada
> como o que se sabia na hora do merge.

O pedido mandava **parar e perguntar** se a prévia exigisse gerar o arquivo inteiro em request
síncrono. Exige: o único método público do Service monta o arquivo completo em memória, não há modo
parcial, e uma prévia server-side **contornaria a trava fail-secure** `sped_simples_only_lock`.
As saídas são **job com artefato** ou **modo parcial no gerador** — as duas são decisão.

Hoje `previaTxt` é `null` e a tela **declara a ausência** em texto. Está como Non-Goal no
`Sped.charter.md`, com a pergunta escrita.

### 2. Achado de motor fiscal — decisão [W], não conserto silencioso

O registro `0000` sai com **CNPJ vazio, IE vazia e UF fixa `SP`**: a tabela `business` não tem
`state`/`tax_number`/`inscricao_estadual` (moram em `business_locations`). Como o CFOP
interno×interestadual é decidido pela UF do emitente, **toda** operação é comparada contra SP —
no golden, uma nota SC→SC saiu com CFOP `6102` em vez de `5102`.

Não consertado nesta onda (é motor fiscal, a lei da onda proíbe). A trava já cobre o risco em
produção. Documentado em `Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.meta.md`.

### 3. Smoke visual autenticado — pendente

O deploy encerrou as sessões (browser interno e Chrome real caíram em `/login`) e digitar senha é
proibido ao agente. O render da régua **dentro do drawer** não foi visto. O que foi provado: código
no servidor, asset compilado com a copy, `HTTP 200` no asset, e contraste **12.40:1 (AAA)** medido
no drawer real antes do merge.

Para conferir: abrir `/fiscal/sped` autenticado e clicar na lupa de uma competência. ⚠️ Todos os 5
períodos de biz=1 têm **0 notas autorizadas**, então o motivo esperado é "Sem notas autorizadas no
período" — para exercitar as 4 checagens é preciso um período com notas, ou ler
`periodos[].checagens` no `data-page`.

## ⚠️ ERRATA da mesma sessão — a fonte do Cowork foi lida DEPOIS do merge, e muda duas coisas acima

Uma sessão irmã (Onda 2) avisou que o pedido do Cowork para esta tela existe no projeto vivo e
podia estar à frente do repo. Estava. Baixei por ID (`cowork-inbox/fiscal/Sped.{casos,charter}.md`,
`README.md` e o protótipo `fiscal-subpages.jsx` §`FxSpedPage`) — o espelho local não servia
(`--sla`: mediu **1 de 258**, 157 ausentes).

**1. O placar "6 de 7" media o pedido derivado, não a fonte.** O charter do Cowork tem **5 Goals**;
esta onda entregou **1** (mais a guarda de entrada no Service, que é do vivo):

| Goal | Estado |
|---|---|
| 1. Barra de validação com os 4 pré-requisitos | 🟡 parcial — os 4 ids e a ordem batem, mas ficou **dentro do drawer**; o protótipo tem `data-contract="validacao-competencia"` como **barra na página**, e o motivo do mês aberto **cita a data em que fecha** |
| 2. Bypass de superadmin explícito (liberar / reativar) | ❌ |
| 3. Prévia do TXT com linhas `\|REG\|…` declaradas amostra | ❌ |
| 4. Cartão de validação externa | ❌ |
| 5. Blocos com os registros que cada um contém | ❌ |

Das 5 âncoras `data-contract` do protótipo (`validacao-competencia`, `panorama-sped`, `previa-txt`,
`blocos-arquivo`, `validacao-externa`), a tela viva tem **zero** — a única que ela tem
(`fiscal-sped-status`) é do vivo, não do F1.

**2. A pergunta da prévia estava MAL FORMULADA, e a fonte a responde.** O charter do Cowork traz o
Non-Goal *"❌ Gerar o arquivo de verdade no F1 — a ação é encenada com o resultado nomeado"*, e o
protótipo renderiza `U.D().SPED_TXT`, um array de linhas de amostra encurtadas. **A prévia nunca
exigiu rodar o gerador.** O que continua sendo decisão [W] é mais estreito: em **produção**,
encenar seria fabricar, o que as leis da onda proíbem — as saídas honestas são a estrutura dos
blocos (Goal 5, fato do layout) e/ou linhas do golden declaradas como referência de layout, nunca
como a competência do usuário.

**3. Armadilha no Goal 4, para quem implementar:** o cartão do protótipo diz literalmente
*"Golden file do TXT: não existe"*. Depois deste PR, **existe**. Copiar a copy produziria afirmação
falsa na tela; o estado verdadeiro hoje é *golden existe · smoke no PVA-EFD nunca executado*. O
charter é de 2026-08-24, anterior ao golden.

**4. Achado de gate mudo, medido e NÃO consertado** (é intent separado e governança de gate): o
`ds-guard.mjs` §8 **morde** o `fiscal-cockpit.css` (`BLOQUEIA: 1`, paleta `--fx-*(6)`), mas o
`design-memory-gate.yml` o alimenta com `git diff -- 'prototipo-ui/**' 'resources/js/Pages/**'` e
há **0** `.css` sob `Pages/`. ⚠️ O glob **não** é cego (pega **78** `.css` sob `prototipo-ui/`); o
buraco é que **`resources/css/` — 39 arquivos, todos os bundles de módulo — nunca entra**. E ligar
não é a linha de YAML que parece: medido, o guard acusa **20 dos 39**, dos quais pelo menos **5 são
falso-positivo por construção** (`tokens/_generated-*.css` e `cockpit.css`, arquivos cujo trabalho
É definir tokens, casam com a régua "≥4 tokens de cor com prefixo bespoke"). Apontar o glob sem
isentar essa população faria o gate **nascer vermelho permanente** — o gate-de-teatro que o §5
enterra. Conserto exige FP medido antes ("LIGUE A MÁQUINA" item 4).

## O que a próxima sessão precisa saber

- **O gerador EFD estava quebrado e ninguém sabia.** Estourava `TypeError` antes de terminar (o PHP
  coage `'9001'`/`'9900'`/`'9990'`/`'9999'` para `int` como chave de array, e isso chegava em
  `registro9900(string $reg)`). Corrigido com um cast. Passou despercebido porque os testes de bloco
  são source-grep, o caso que geraria o TXT skipava, e a trava impede o download em produção.
  **O golden é o primeiro run ponta-a-ponta que já existiu.**
- **Um recibo canon caducou e foi corrigido:** `Sped.casos.md` afirmava desde 2026-07-27 que
  `nfe_emissoes` não existe no CT 100. **Existe desde 2026-07-28.** O recibo antigo ficou como fato
  datado, com a errata ao lado.
- **O manifesto `scripts/casos-test-results.json` não foi tocado.** Alimentá-lo de uma rodada local
  faz ele perder a proveniência dos outros 18 relatórios; com 5 sessões Fiscal paralelas, isso
  colidiria. Os UCs ficam `Status: 🧪` com o recibo do verde ao lado.
- **`.gitattributes` ganhou `-text` para o golden.** O `eol=lf` global o corromperia — o layout
  CONFAZ exige CRLF e o teste separa por CRLF. O git recusou o `add`, e foi assim que apareceu.
