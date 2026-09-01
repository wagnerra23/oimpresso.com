---
slug: 0387-github-md-diario-cowork-aceito-e-tratado
number: 387
title: "github.md (diário de sync do Cowork) é artefato aceito e tratado pelo protocolo — e a redação 'nunca o inverso' da 0315 deixa de valer como absoluto"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-01"
module: governance
tags: [design, cowork, protocolo, github-md, design-docs, handoff, designsync]
supersedes: []
superseded_by: []
related:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0379-bundle-design-transacao-manifesto-delta-staging
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0325-import-prototipo-designsync-pull-direto
pii: false
---

> **Ordenada por [W] em 2026-09-01** (verbatim: *"então meu protocolo deve ser ajustado para
> aceitar e tratar esse arquivo e remover adrs antigas conflitantes"* e, sobre o mecanismo,
> *"adr não é mais read only"*). Nasce `proposto`; o merge [W] é o ato, e a ratificação formal
> segue o flip próprio previsto pela [ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md).

# ADR 0387 — `github.md` (diário de sync do Cowork) entra no protocolo

## Contexto (medido em 2026-09-01 contra `origin/main` d7b8eca545)

- `github.md` é o **diário de bordo que o [CC] mantém no projeto Cowork** (claude.ai/design):
  cabeçalho `repo:/branch:/path:`, `## Last sync` (data + hash de **árvore**), blocos
  `### Updated in this project` por ciclo (o que mudou no protótipo, achados 🔴 lidos do código
  vivo, erratas do próprio [CC], decisões pendentes `[W]`) e o `## Screen map`
  (protótipo ↔ arquivos do repo).
- Ele **já desce pro repo**: `prototipo-ui/design-docs/github.md` chegou na leva dos 64 `.md`
  (commit `b703fee998`) e foi refrescado pelo export de 27/ago
  ([#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379)). A liberação dos `.md` do
  Cowork foi decisão [W] de **2026-08-20** (verbatim: *"lá tem informações de como construir as
  telas. precisa ser liberado"*) — registrada até hoje **só no docblock** do
  [`cowork-mirror-freshness.mjs`](../../scripts/governance/cowork-mirror-freshness.mjs), sem ADR
  própria. Esta ADR a formaliza.
- Mas o protocolo **nunca o tratou**: nenhuma fase do
  [`protocolo.config.mjs`](../../prototipo-ui/protocolo.config.mjs) lia o diário, o
  [`PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) não o citava, e as medições de frescor o
  classificavam como não-candidato (*"nem podem pousar em `cowork/` — zero candidatos reais"* —
  recibos: [LICOES_CODE rec 08-27](../LICOES_CODE.md) e a
  [proposal de frescor 2026-08-27](proposals/2026-08-27-frescor-do-espelho-eixo-live-only-no-sla.md)).
  Aceito de fato, tratado por ninguém.
- Conflito de redação remanescente: a [ADR 0315](0315-design-sync-claude-design-vs-cowork-charter.md)
  §"Quando `/design-sync` faria sentido" ainda dizia **"Nunca o inverso (claude.ai/design → git)"**
  em absoluto. A [ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md) já a relia
  ("nunca o inverso **para o Design System**"), mas o corpo da 0315 seguia com a frase original —
  e quem lê só a 0315 conclui que descer o `github.md` é proibido.

## Decisão

**D1 — Aceito.** `prototipo-ui/design-docs/github.md` é artefato de primeira classe do loop
design↔code: o **handoff do lado design** — contraparte do `memory/handoffs/` do lado code.

**D2 — Tratado.** A fase −1 do protocolo ganha o bloco `[DIARIO]` no
[`protocolo.config.mjs`](../../prototipo-ui/protocolo.config.mjs) (fonte única de comandos):
ler o diário — `Last sync` + decisões pendentes `[W]` — **antes** de decidir o ciclo.
O [`PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) ganha a subseção §10.7 correspondente.

**D3 — Cópia tratada e transporte.** A cópia tratada é a da **raiz** de `design-docs/`
(a que o export atualiza). `prototipo-ui/design-docs/_projeto-cowork/**` é retrato interno do
próprio projeto — espelho de história, não se trata nem se apaga daqui (edição no espelho some no
próximo export — lápide §5 2026-08-13). Pouso fiel = bundle/payload ou `--export-from`;
transcrição à mão segue proibida (0374). **Ler** via `DesignSync.get_file` é livre (0315 Eixo B).

**D4 — Emenda na 0315.** A frase "Nunca o inverso (claude.ai/design → git)" **deixa de viger
como absoluto**. Redação vigente: *"nunca o inverso para o **Design System** (tokens/componentes —
ADR 0239/0299)"*; para o projeto **Cowork** — build **e** `.md` de processo — o inverso é a
**rota prevista** (0374 + decisão [W] 2026-08-20 + esta). A emenda foi aplicada no corpo da 0315
no mesmo PR, pela exceção da [ADR 0377](0377-append-only-adr-excecao-por-label-emenda-0094.md)
(label `adr-body-edit-W`, autorização [W] desta sessão).

**D5 — Varredura de conflito (o "remover ADRs antigas conflitantes").** Varrido em 2026-09-01
(`git grep` em `origin/main:memory/decisions/` por design-docs/github.md/process-doc + leitura de
0285/0315/0325/0374/0379/0384): a [ADR 0285](0285-handoff-publisher-cowork-to-repo.md) já estava
`deprecated`/arquivada (nada a fazer); a **0315 é a única ativa com redação conflitante** —
resolvida pelo D4 (a ADR não é removida: o restante dela segue vigente); 0374/0379/0325/0384 já
apontavam na direção desta decisão. Nenhuma outra ADR ativa proíbe aceitar/tratar o diário — o
conflito restante era **ausência de tratamento**, não lei contrária.

## O que NÃO muda

- O DS em git segue a fonte (0239/0299); claude.ai/design segue **não** sendo armazém canônico;
  escrita pro lado design segue gated (0315 Eixo A).
- R1 do `cowork-ssot-guard` segue: `.md` não pousa em `prototipo-ui/cowork/` — pousa em
  `design-docs/` (o roteamento que o exportador já faz).
- `github.md` é **registro** do lado design, não fonte de design nem de decisão: achado 🔴 do
  diário vira trabalho **depois** de verificado contra o `main` (a regra do próprio [CC]: *"fato
  sobre o repo = só com leitura do `main` NESTE turno"*). Decisão pendente `[W]` listada nele
  entra na fila de decisão — não se resolve sozinha.
- [ADR 0384](0384-design-sync-recibos-executaveis-por-tela.md) (recibos executáveis) intacta.

## Consequências

- O Code passa a abrir ciclo de design **sabendo o que o design fez e o que espera dele** — os
  achados 🔴, as erratas e as decisões pendentes deixam de depender de [W] colar o conteúdo no chat.
- A cópia tratada envelhece como qualquer espelho: o frescor dela é o do último export. Quem citar
  o diário para uma decisão **data a citação** (o `Last sync` está no próprio arquivo) e, em
  dúvida, refresca pelo transporte antes.
