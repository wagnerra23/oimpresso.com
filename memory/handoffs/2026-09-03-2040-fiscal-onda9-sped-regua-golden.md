---
date: "2026-09-03"
time: "2040 BRT"
slug: fiscal-onda9-sped-regua-golden
tldr: "Onda 9 do Fiscal mergeada (#6708): régua de 4 checagens no SPED + golden do EFD-ICMS/IPI. O golden revelou que o gerador quebrava com TypeError antes de terminar e nunca tinha rodado ponta-a-ponta. Prévia do TXT ficou PARADA (decisão [W]); emitente sem CNPJ/IE/UF é achado de motor fiscal, não consertado."
decided_by: [W]
prs: [6708]
us: [US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
next_steps:
  - "[W] decide a prévia do TXT: job com artefato, ou modo parcial no gerador"
  - "[W] decide o emitente do registro 0000 (CNPJ/IE vazios, UF fixa SP) — muda o CFOP de toda operação"
  - "Smoke visual autenticado da régua no drawer (sessão caiu no deploy; senha é proibida ao agente)"
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
