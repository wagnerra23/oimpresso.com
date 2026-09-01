---
date: "2026-09-01"
time: "12:00 BRT"
slug: descida-design-bloqueada-regra-fora-do-read-order
tldr: "A primeira descida pela rota do pacote parou no PASSO 0 — o bundle do Cowork é o MESMO já aplicado em 25/08. Causa-raiz medida: a regra de regenerar estava num arquivo fora dos 6 documentos do read-order do Cowork, então nunca chegou. PR #6501 move a regra pro passo 4 da ROTINA."
prs: [6501, 6498]
related_adrs:
  - 0387-github-md-diario-cowork-aceito-e-tratado
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
next_steps:
  - "[W] mergeia o #6501 — depois disso a próxima sessão de design lê a regra sozinha, sem [W] colar prompt"
  - "Quando o pacote novo subir: baixar partes + aplicar-payload --dry --require-complete-shell → promover → medições (--compare, --live-only --ledger, --sla-docs)"
  - "NÃO aplicar o pacote atual (5023b274) — reverteria 16 arquivos e traria 0 dos que faltam"
---

# Descida do design pela rota do pacote — parou no PASSO 0, e a causa não era o design

## Estado MCP no momento do fechamento

⚠️ **MCP do oimpresso INDISPONÍVEL nesta sessão.** O `brief-fetch` do `SessionStart` caiu por timeout
(*"servidor MCP não respondeu no tempo"*) e as tools (`cycles-active`, `my-work`, `sessions-recent`,
`decisions-search`) não estavam expostas — a busca por elas devolveu só tools de outro servidor.
Operei pelo **fallback filesystem** previsto em [`how-trabalhar.md`](../how-trabalhar.md) §Fallback,
lendo tudo de `origin/main` fresco (o checkout de origem estava −86 commits; nunca validei canon contra ele).

Snapshot possível sem MCP:
- Handoffs de 2026-09-01 em `origin/main`: **nenhum** (este é o primeiro).
- Session logs de 2026-09-01: 1 irmão — `2026-09-01-testes-persistentes-design-code.md`.
- Base do trabalho: `origin/main` em `b99259eda9`.

## O que aconteceu

A tarefa era executar a **primeira descida completa do design pela rota do pacote**, agora que o
`/design-login` foi feito nesta máquina. O PASSO 0 mandava conferir o recibo `bundle regenerado` no
`github.md` do Cowork e **parar se o pacote não tivesse sido regenerado**. Ele não tinha — e o achado
foi mais forte que "ainda não fizeram".

### Por que não aplicar (três recibos independentes)

1. `github.md` baixado inteiro (55.516 chars): **0** ocorrências de `regener` / `bundle regenerado` /
   `por ciclo` / `CODE_NOTES`.
2. `sync/bundle.manifest.json` → `generatedAt = 2026-08-24T22:49:15.818Z`, `bundleId = 5023b274…`,
   255 arquivos, `mode: snapshot`. `list_files` confirma **um único** manifesto em `sync/` (+ 43 partes).
3. **É o mesmo bundle já consumido:** o commit `cf34d5de10` ([#6260](https://github.com/wagnerra23/oimpresso.com/pull/6260), 25/08) diz literal
   *"sincroniza o espelho pelo bundle 5023b274"* — mesmo `bundleId`.

### O custo e o ganho de aplicar assim mesmo (sha256 do bundle × `origin/main`)

| | |
|---|---:|
| Idênticos bundle == espelho | 229 |
| Divergem — **espelho à frente** (reverteria) | **16** |
| Só no bundle | 10 — todos `_ds/` (bundle JS + 7 fontes + 2 CSS), preview-cache |
| Dos 12 arquivos-alvo do critério de sucesso, presentes no bundle | **0** |

Os 16 vieram dos pulls [#6378](https://github.com/wagnerra23/oimpresso.com/pull/6378)/[#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379) (27/08), [#6416](https://github.com/wagnerra23/oimpresso.com/pull/6416) (28/08) e [#6489](https://github.com/wagnerra23/oimpresso.com/pull/6489) (31/08).

### A causa-raiz (o que essa sessão descobriu de novo)

O lado design **não desobedeceu — a regra nunca chegou nele**.

O pedido formal ([#6498](https://github.com/wagnerra23/oimpresso.com/pull/6498), hoje de manhã) foi escrito em
`prototipo-ui/CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`, assumindo no próprio
corpo que *"o Cowork lê daqui, já que ele lê o `main` no início de todo chat"*. Premissa **nunca medida**.

Medido: o `CLAUDE.md` do projeto Cowork nomeia **6** documentos de read-order. Contagem de
`gerar-payload` / `bundle.manifest` / `sync/payload` nos seis:

| Documento | Linhas | Hits |
|---|---:|---:|
| `COWORK-ESTRUTURA-E-TELAS.md` | 101 | 0 |
| `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` | 25 | 0 |
| `PRE-FLIGHT-TELA.md` | 64 | 0 |
| `PROTOCOL.md` | 389 | 2 — **falso-positivo** ("regenera o *placar de tarefas*") |
| `CLAUDE_DESIGN_BRIEFING.md` | 189 | 0 |
| `memory/LICOES_CC.md` | 231 | 0 |

Família §5 2026-07-09: *chokepoint acoplado a um caminho que o fluxo real não percorre*.

E o lado design **esteve ativo**: 5 entradas `Last sync` no `github.md` datadas 2026-09-01
(18:15Z → 22:30Z). Trabalharam e fecharam ciclo sem o pacote — porque não sabiam da regra.

### Também medido: não há rota alternativa hoje

- O gerador **só roda do lado Cowork** — não é lápide, é o cabeçalho do próprio
  [`gerar-payload-partes.mjs`](../../scripts/design-sync/gerar-payload-partes.mjs):
  *"ONDE RODA: na máquina que TEM os arquivos em disco. NÃO roda do lado do agente consumidor."*
- A rota pontual (`get_file` avulso) **não alcança** os que faltam: baixei
  `scripts/cowork-paridade.mjs` e ele voltou **inline** (~9 KB, abaixo do piso de persistência).
  Escrever dali é transcrição — proibida ([ADR 0374](../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)). Os 147 `live-only` são desse porte.

## Artefatos gerados

| Arquivo | Δ | Onde |
|---|---|---|
| `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` | +12/−2 | regra vira **passo 4** da `## 🔁 ROTINA`, com comando e recibo |
| `prototipo-ui/CODE_NOTES.prompt-…-2026-09-01.md` | +10 | errata datada; corpo preservado |
| `memory/LICOES_CODE.md` | +2/−1 | recibo no LC-08 · Ocorrências 122→123 · comentário de recibos → 129 |

Tudo em **[PR #6501](https://github.com/wagnerra23/oimpresso.com/pull/6501)** (`claude/cowork-regenerar-bundle-na-rotina`). Estendi o dono existente —
nenhum doc, gate ou máquina nova.

## Persistência

- **git:** PR #6501 aberto, `MERGEABLE`, aguardando merge de [W] (R10).
- **MCP:** ⚠️ não atualizado — servidor indisponível nesta sessão (ver topo). Task de acompanhamento
  não foi criada; quem retomar deve registrar via `tasks-create` quando o MCP voltar.
- **BRIEFING:** não aplicável (nenhum `Modules/<X>` tocado).

## Lições catalogadas

- **LC-08 (ocorrência nova, registrada no ledger):** premissa *"ele lê o main"* escrita sem medir
  **o quê** ele lê. Sem gate — *"este doc é lido por quem?"* não é derivável do texto, e o gate óbvio
  da classe já está medido e reprovado.
- **`mergeable` da API do GitHub é retrato atrasado:** após resolver o conflito, **7 leituras seguidas**
  disseram `CONFLICTING` e a 8ª disse `MERGEABLE`. Localmente `git merge-tree` já dava `rc=0`.
  Acreditar na primeira leitura teria me feito "consertar" um conflito inexistente (§5 2026-08-13).
- **Conflito de contador não se resolve escolhendo um lado:** duas sessões incrementaram
  `121 → 122` por ocorrências **distintas**. O número certo era **123**; o git não sabe disso.
- **`/tmp` não é o mesmo diretório para Bash e para `gh` no Windows** — reincidência da §5 2026-08-21,
  pega no ato ao passar `--body-file`.

## Próximos passos pra retomar

```
gh pr view 6501            # merge de [W] é o que destrava tudo
```

Depois do merge: a próxima sessão de design lê a regra no item 1 do read-order, fecha o ciclo
regenerando o pacote e escreve `bundle regenerado (…)` no `github.md`. **[W] não precisa colar prompt.**
Aí a descida roda: baixar partes → `aplicar-payload.mjs --dry --require-complete-shell` → promover →
`--compare`, `--live-only --ledger`, `--sla-docs`.

## Pointers detalhados

- Comandos e fases: `node prototipo-ui/protocolo.config.mjs` (painel é a fonte única — não copiar daqui).
- Medição do vermelho herdado do CI: comentário em [#6501](https://github.com/wagnerra23/oimpresso.com/pull/6501#issuecomment-5495570610).
- Pedido formal original + errata: `prototipo-ui/CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`.

## Aberto, e NÃO é deste escopo

**RAGAS da Jana parado há 62 dias** — `jana-ragas-canary.yml` concluiu `failure` em 2026-09-01T10:49Z e
`governance/jana-ragas-{baseline,real-baseline}.json` têm data interna 2026-07-01. Faz o job
`crons de governança vivos? (watchdog G6)` reprovar em **todo** PR do repo (reproduz em
`claude/nice-turing-e58bd2`, `chore/mv-snapshot`, `claude/prova-viva-ancora-historica`).
**Não é required** — união medida: baseline 0 hits, classic 44 contexts sem cron, ruleset 1 context —
então não bloqueia merge. Não silenciei: `governance/cron-vermelho-esperado.json` exige razão,
validade ≤30d e merge de [W] como ato.
