---
date: "2026-09-10"
time: "0815 BRT"
slug: "assume-7178-frescor-baseline-ds"
tldr: "Assumi o #7178 (aberto pelo Codex às 07:51 BRT). O mecanismo de frescor do cache DS e do proto-baseline está de pé e provado por bite-test; consertei o ponto em que ele confundia ambiente cego com baseline podre, e consolidei os dois handoffs não-conformes neste."
decided_by: [W]
cycle: null
prs: [7178]
us: []
next_steps:
  - "Merge do #7178 é decisão [W] (R10) — o PR saiu de draft."
  - "Regenerar os proto-baselines: 8 dos 9 já estavam STALE por prototipo_sha ANTES deste PR, e nenhum tem render_sha256 — o --extract recusa todos até regenerar (--gerar é LOCAL por lei, ADR 0290)."
  - "FORA do escopo deste PR, medido nesta sessão: os jobs 'Schema handoff' e 'Schema session log' usam pathspec CRU 'memory/handoffs/**/*.md', que não casa arquivo na raiz da pasta — detectam 0 arquivos sempre. Chip aberto."
  - "Os cinco achados da reanálise do Codex seguem ABERTOS — este PR não trata nenhum deles."
related_adrs: ["0130-handoff-append-only-mcp-first", "0290-fidelity-lock-v0-recusado", "0326-trava-ancora-compare-fingerprint"]
---

# Handoff 2026-09-10 08:15 BRT — assumindo o #7178 (frescor do cache DS e do proto-baseline)

## TL;DR

O Codex abriu o [#7178](https://github.com/wagnerra23/oimpresso.com/pull/7178) às 07:51 BRT e parou. [W] mandou assumir. O que ele fez continua de pé e está provado; o que faltava era (a) o único check vermelho, que eram os próprios arquivos do PR colidindo de id, e (b) um defeito latente que eu medi: o `--check` acusaria **baseline STALE** onde a verdade era **ambiente sem o cache `_ds`** — o erro de colapsar "não consegui medir" num estado do objeto medido ([proibicoes.md §5, 2026-07-29](../proibicoes.md)).

## O que o Codex estava fazendo (as duas sessões que este handoff consolida)

1. **Reanálise do processo de aplicação** — [session log](../sessions/2026-09-10-reanalise-processo-prototipo.md). Read-only, cinco achados, nenhum consertado: execução Playwright filtrada aceita como integral (reproduzida com CLI real), consumidor DS-átomos incompatível, três paths antigos do Patrimônio 09-11, dez fichas fora do `threads` do índice, e um E2E exigido que só contém `test.fixme`.
2. **Frescor do bundle e do baseline** — [session log](../sessions/2026-09-10-bundle-baseline-frescor.md) — o código que ESTE PR carrega, motivado por [W] apontando referência antiga em Patrimônio/Governança: o hook do DS passou a comparar **bytes** do plano canônico (não mais só existência), o `render-proto-baseline` trocou o default de Downloads legado por `MIRROR_DIR`, e nasceu o `render_sha256` (identidade do grafo local inteiro — shell, JSX, CSS e fontes) que o `--extract` passou a exigir.

Os dois handoffs originais (`2026-09-10-bundle-baseline-frescor.md` e `2026-09-10-reanalise-processo-prototipo.md`) foram **substituídos por este**: eram duas linhas apontando pros session logs, estavam fora do regex `YYYY-MM-DD-HHMM-<slug>.md` e sem a seção `## Estado MCP` (ADR 0130), e eram a causa das duas colisões de id. Nenhum conteúdo se perdeu — os session logs, que são o registro de fato, seguem intactos.

## O que eu acrescentei ao PR

- **`vereditoSuperficie()`** no `render-proto-baseline.mjs`: separa **NÃO MEDIDO** de **STALE**. O grafo do render depende do cache `_ds/`, que é **gitignored** — em checkout fresco (o do CI) ele não existe, e ali `superficieRender(MIRROR_DIR)` lança `cache DS ausente`. Sem essa separação, todo baseline com `render_sha256` seria acusado de STALE no CI, para sempre.
- **O `--check` materializa o `_ds` sozinho**, a partir do `mirror-snapshot` **versionado** (offline — o produtor não usa DesignSync nem rede). Falhou? o veredito é aviso, nunca drift; e a linha de `✓` passa a dizer `· render NÃO MEDIDO` em vez de deixar o `⚠` passar batido na rolagem.
- **4 bite-tests** herméticos do veredito no `--selftest` (que roda **hard** no CI).

## Provas rodadas (nesta sessão, worktree fresco em `C:/tmp/wt-7178`)

| O que | Resultado |
|---|---|
| `render-proto-baseline.mjs --selftest` | **SELFTEST OK** — 11 casos de frescor/veredito entre eles |
| `ds-preview-materialize.test.mjs` | 10/10, incluindo os 2 BITE novos do Codex (bundle antigo e fonte indireta antiga são repostos) |
| `--check` com baseline sintético e hash **igual**, **sem `_ds`** | `✓ íntegro` (materializou sozinho) |
| `--check` com hash **diferente**, **sem `_ds`** | `✗ STALE` — morde |
| `--check` com materialização **impossível** (snapshot movido) | `⚠ render NÃO MEDIDO … — o grafo do protótipo não foi conferido` + `✓ … · render NÃO MEDIDO`; **não** acusou STALE |
| `--check` real nos 9 baselines commitados | 8 drift **pré-existentes** (`prototipo_sha`), idêntico ao antes do PR — sem regressão |

## Limites — o que este PR NÃO prova

- **Nada de remoto foi recebido.** O `DesignSync` não está exposto nesta sessão (nem estava na do Codex). As correções provam coerência com os arquivos **já importados no checkout**, nunca que o Cowork vivo continua igual. A comparação real de Patrimônio/Governança com o design vivo **continua devendo** recepção atual pelo transporte canônico.
- **Nenhuma tela, cálculo, dado ou baseline de produção foi tocado.** Zero Pest/PHPStan (não há PHP no diff), zero smoke de aplicação.
- **Os 5 achados da reanálise seguem abertos.** O PR não os corrige e não os declara corrigidos.
- O `--extract` passa a **recusar os 9 baselines históricos** até regeneração. Isso é deliberado (fail-closed) e não quebra CI: nenhum job invoca `--extract` — varredura `rg --hidden` nos consumidores deu `design-memory-gate.yml` (`--selftest`, `--check`, `--nudge`) e `.claude/workflows/validador-modulo-prototipo.js` (`--check`).

## Estado MCP no momento do fechamento

Consultado em 2026-09-10 ~08:05 BRT pelo endpoint `mcp.oimpresso.com/api/mcp` (as tools MCP não estão expostas como ferramenta nesta sessão; a chamada foi HTTP JSON-RPC reusando o resolvedor de token do hook `brief-fetch-curl.mjs`).

- **`whats-active`**: *"Nenhuma sessão Claude Code vista nas últimas 2h — MAS o pipeline de ingest está SEM heartbeat fresco (fresh=0 · stale=0 · dead=303). Posso estar CEGO."* Ou seja: **não há prova de ausência de sessão paralela**. O que autoriza assumir o PR é a ordem direta de [W] ("assuma #7178"), mais o worktree do Codex (`.worktrees/reanalisa-processo-prototipo-20260910`) estar **limpo** no commit já pushado.
- **`cycles-active`**: nenhum cycle ATIVO em COPI.
- **`sessions-recent limit:3`**: os três últimos indexados são os `arte-*` de 2026-08-22 (indexed 2026-09-10) — o índice do MCP **não** tinha os session logs de hoje, que estão neste PR e ainda não mergearam.
- **`brief-fetch`** (brief #625, ~55min): HITL pendente [W] = 5; 12 itens em voo; 683 US não atribuídas (524 sem dono); SDD composta 55,4.
