---
date: "2026-09-09"
time: "15:30 BRT"
slug: "shipped-log-cron-truncacao-silenciosa"
tldr: "O cron falhou porque o `gh pr list` devolve resposta INCOMPLETA com rc=0 — vazia (dominante: 21 de 104 dias divergiram em 2 varreduras) e pagina perdida (100 de 106/113). A hipotese do `total_count` aproximado foi REFUTADA: ele bate com os itens paginados (101=101). A guarda `>= 1000` media o teto errado e nunca pode disparar. #7118 mergeado; 4 runs completos batendo."
decided_by: [W]
prs: [7118, 7122]
cycle: "CYCLE-08"
related_adrs: ["0130-handoff-append-only-mcp-first", "0167-errata-0130-indice-handoff-historico-longo"]
next_steps:
  - "[W] decide o merge do #7122 (so comentario: a defesa do alvo NAO e especifica do transporte)"
  - "Sexta 2026-09-11 09:00 UTC — a proxima run AGENDADA do cron e o que apaga o vermelho do watchdog G6. `cron-watchdog.mjs:151` filtra `--event schedule`, entao workflow_dispatch nao conta e nada em branch muda aquele estado"
  - "[W] decide se a lapide §5 sai, e se a guarda-com-limiar-errado e classe NOVA. O gate `governance-script-tests.yml:478` cobra marcador de recorrencia, e a propria mensagem diz que para classe nova marcador nenhum e honesto — levar ao [W] em vez de inventar a palavra"
  - "`governance/cron-vermelho-esperado.json` NAO foi usado: havendo conserto, declarar silencio e atalho. Decisao [W] se quiser silenciar ate sexta"
---

# shipped-log-cron: o `gh pr list` mente com `rc=0`, e o cross-check estava certo o tempo todo

## O que motivou

O cron `shipped-log-cron.yml` falhou em 2026-09-09 13:35Z — **1ª falha em 12 runs agendadas** —
com `coletado(4585) ≠ Search total_count(4745) — diff 160`. Efeito colateral: o advisory
*"crons de governança vivos? (watchdog G6)"* fica 🔴 em todo PR aberto depois disso (o eixo 3 do
`cron-watchdog` lê `conclusion: failure` da última run **agendada**).

Duas hipóteses foram postas para medir: (1) paginação incompleta na coleta; (2) `total_count` da
Search API aproximado/instável, sendo o **lado errado** da comparação.

## O que a medição disse

**Hipótese 2 REFUTADA.** O `total_count` é estável (`4749` × 3 leituras) e bate **exatamente** com
os itens paginados no dia divergente: **101 = 101**. O oráculo estava certo.

**Hipótese 1 CONFIRMADA, mas não pela forma prevista.** Não é teto de 1000 nem `--paginate`
ausente. O `gh pr list` devolve resposta **incompleta com `rc=0`**, em duas formas — as duas
indistinguíveis de resposta legítima:

| forma | medição | efeito |
|---|---|---|
| **resposta vazia** (dominante) | janela varrida 2×: **21 dos 104 dias divergiram, e nos 21 o menor valor era 0**; 208 chamadas, **zero** erros visíveis | perde o **dia inteiro** |
| **página perdida** | `merged:2026-09-05` (certo 106): 4 de 25 deram 100 · `merged:2026-09-08` (certo 113): 10 de 30 deram 100 | perde a 2ª página |
| **o próprio ALVO** | 25 leituras de `total_count` do dia: **2 devolveram `0` com `rc=0`** | faria `coletado >= alvo` bater **por acidente** |

O achado que mais paga: a guarda que já existia (`arr.length >= 1000`) **mede o teto errado** — a
truncação corta em **100** — e por isso **nunca pôde disparar**. Quem acusou foi o cross-check da
janela, tarde e sem dizer o dia. É a forma LC-11 (guarda que não morde), no eixo *limiar*.

**O `crossCheck` estava certo em recusar e NÃO foi afrouxado.** Ele é fail-closed, fez o que o
docblock promete (G4) e foi o que revelou o defeito.

## O que foi entregue

**[#7118](https://github.com/wagnerra23/oimpresso.com/pull/7118) — MERGED** por [W] às 15:02Z:
- `leituraSuspeita(n)` = `n === 0 || n % PAGE_SIZE === 0`, no lugar da guarda `>= 1000`
- re-coleta com **alvo conhecido**; `recoletaSuficiente` exige **2 leituras quando `alvo === 0`**,
  que é onde domingo e falha se confundem (3 dias deram zero nas 2 passadas: 06-28 e 07-19 são
  domingos, mais o dia +1 da margem)
- `consolidaAlvo`: reconsulta **só o valor suspeito**, porque dobrar em todo dia agravaria o
  rate limit que causa o defeito
- **passada de recuperação** quando o cross-check reprova — custo zero no caminho feliz, e cobre
  modo de falha que nenhum detector de assinatura pegaria
- `base:main` no servidor (alinha os dois lados do cross-check) + backoff no `gh` com stderr reemitido

Verificação: **4 runs completos** na janela do cron, todos `bate com total_count`. Testes **35 → 58**,
contador conferido contra o baseline de `origin/main` materializado **no mesmo diretório** (§5 2026-07-26).

**[#7122](https://github.com/wagnerra23/oimpresso.com/pull/7122) — ABERTO**, só comentário: o docblock
atribuía a mentira do alvo ao `search/issues` especificamente, o que induziria a trocar o transporte
pelo GraphQL e **remover o `consolidaAlvo` junto**.

## O que NÃO foi feito, e por quê

- **`governance/cron-vermelho-esperado.json` não foi usado.** Havendo conserto, declarar silêncio é
  atalho; exige razão + validade ≤30d + quem declarou, e o merge de [W] é o ato. Decisão [W].
- **Lápide §5 não escrita.** É outro intent: `licoes-rejeitadas.md` é append-only Tier 0 e a §5 do
  `proibicoes.md` é **derivada** (`sec5-derive.mjs`, `--check` no CI). ⚠️ Insumo para quem escrever:
  `governance-script-tests.yml:478` cobra **marcador de recorrência** no bullet "O limite" (14 formas),
  e a própria mensagem diz — *"Se a lapide for classe NOVA de verdade, marcador nenhum e honesto:
  leve ao [W], nao invente a palavra"*. A resposta-vazia é reincidência de **§5 2026-07-31 / 2026-08-01**
  (incrementa Ocorrências); a **guarda com limiar errado** é candidata a classe nova → decisão [W].

## Erros meus nesta sessão (registrados, não apagados)

1. **Duas tentativas de conserto falharam** (diff **301** e **324**) porque meu detector só olhava
   borda de página — **cego para a forma dominante**. Só varrendo a janela 2× o zero apareceu.
2. **Tratei o alvo como confiável sem medir.** Teria virado **cron verde mentindo**, pior que o
   vermelho. Só entrou porque uma sessão irmã cobrou.
3. **Não medi o FP antes de instalar** (`CLAUDE.md` §"Sempre fazer" item 4) — medido depois: **2/104**
   dias têm valor certo múltiplo de 100. É **custo, não resultado errado** (com alvo conhecido o dia
   fecha na 1ª tentativa), mas a regra pede o número antes.
4. **Rebasei uma branch já pushada** e o push seguinte foi rejeitado. **Não forcei**: reconstruí com
   `checkout -B` sobre o remoto + `cherry-pick`, push fast-forward. Force ali descartaria o commit
   que já estava no remoto.
5. **Comparei ambientes diferentes** (meu run local × run do CI) antes de perceber — §5 2026-07-26.
6. **Inventei slugs de ADR** no frontmatter deste handoff (`0294-shipped-log-...`, `0317-cron-watchdog-...`).
   Os NÚMEROS vim dos docblocks (`shipped-log-generate.mjs` cita "ADR 0294 (loop)"; `cron-watchdog.mjs`
   cita "ADR 0317 §2"), mas os slugs eu supus — e os reais são outros: **0294** é
   `mcp-audit-log-hash-chain-tamper-evident` e **0317** é `maquina-revisao-adr-quando-rever-gatilhos`.
   Pego pelo gate de schema + conferência com `ls memory/decisions/`. Removidos do frontmatter;
   ficam só os dois que verifiquei. ⚠️ Resíduo para quem passar por aqui: **os números citados nos
   dois docblocks não batem com os títulos dos ADRs daqueles números** — não investiguei se é
   renumeração ou citação errada na origem, e não vou afirmar qual.

## Três sessões no mesmo arquivo

`local_2aa16486` (diagnóstico da resposta-vazia, chip original) e `local_8fbdcf63` (**#7121, fechado**)
tocaram o mesmo `shipped-log-generate.mjs`. Coordenado por mensagem; as duas cederam. **Três correções
do PR final vieram de fora** — o alvo mentindo, o FP medido antes de instalar, e a evidência bruta.

## Estado MCP no momento do fechamento

Tools MCP **não conectadas** nesta sessão (nenhuma `mcp__oimpresso__*` disponível) — usado o fallback
filesystem, conforme [how-trabalhar.md §Fallback](../how-trabalhar.md). Brief entregue via hook
`SessionStart` (brief #621, gerado há ~15h): cycle sem nome no retorno, 5 HITL pendentes [W],
682 US não atribuídas. `Glob memory/handoffs/2026-09-0*` conferido antes de criar este arquivo
(último era `2026-09-08-1749`), sem duplicata.

## Próxima ação

1. **[W] decide o merge do [#7122](https://github.com/wagnerra23/oimpresso.com/pull/7122)** (só comentário).
2. **Sexta 2026-09-11, 09:00 UTC** — próxima run **agendada** do cron. É ela que apaga o 🔴 do watchdog
   G6: `cron-watchdog.mjs:151` filtra `--event schedule`, então `workflow_dispatch` **não** conta e
   nada no branch muda aquele estado. Se passar, o `--check` (`FRESH_DAYS=4`, `generated: 2026-09-07`)
   também deixa de correr risco a partir de 09-12.
3. **[W] decide** se a lápide §5 sai, e se a guarda-com-limiar-errado é classe nova.
