---
slug: 0302-fonte-unica-doneness-anchor-aposenta-status-spec
number: 302
title: "Done-ness com fonte única — a âncora 'Implementado em' decide se a US está pronta; o status: do blockquote é aposentado como sinal de done-ness e a contradição vira catraca"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: governance
quarter: 2026-Q2
tags: [sdd, spec, done-ness, dual-source, fonte-unica, status, anchor, implementado-em, gate, ratchet, governanca]
supersedes: []
superseded_by: []
related: ["0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0258-processo-adr-estado-arte-indice-gerado-supersede-atomico", "0070-jira-style-task-management-current-md-removed"]
pii: false
---

# ADR 0302 — Done-ness com fonte única: a âncora decide, o `status:` é aposentado

## Status

**Proposto** — aguarda aprovação Wagner (caminho "ADR canon" do [CLAUDE.md](../../CLAUDE.md): mudança de **convenção de SPEC** → ADR canon → Wagner aprova; **não** auto-merge). A catraca advisory companheira (`doneness-lint.mjs`) pode mergear separada com CI verde, sem depender deste aceite (ADR 0271 — gate novo nasce advisory).

## Contexto

Toda SPEC (`memory/requisitos/<Mod>/SPEC.md`) carrega DOIS campos que respondem a mesma pergunta — *"essa US está pronta?"* — e eles divergem:

1. **`status:`** no blockquote do US (ex: `> owner: wagner · priority: p1 · status: todo · type: story`). Digitado à mão, governado por **nenhum** gate.
2. **`**Implementado em:**`** — a âncora spec↔código do [ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md), verificável contra o disco por `scripts/governance/anchor-lint.mjs` (`existsSync` dos paths + proveniência `verificado@sha7`).

Dois campos de verdade pra mesma coisa = **drift garantido**. É exatamente a doença que o projeto já matou uma vez: os 4 índices de ADR que divergiam viraram **1 fonte gerada** ([ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md)/[0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md)). O `status:` é o "4º índice" do done-ness.

### Medição real (re-derivada de `origin/main@6bc62a7a45`, 2026-06-22 — regra anti-stale)

Determinística sobre `memory/requisitos/*/SPEC.md` via `scripts/governance/doneness-lint.mjs` + `anchor-lint.mjs` (âncora **viva** = `anchored_ok` \| `parcial`, paths existem no disco; `_pendente_` = tela não construída ≠ viva):

| Métrica | Valor |
|---|---|
| SPECs | **58** |
| US totais | **862** |
| `anchor_coverage` (ADR 0273) | **10,4%** (53 `anchored_ok` + 31 `_pendente_` + 6 `_parcial_` / 862); âncoras **vivas** (ok+parcial) = **59** |
| US com `status:` (a superfície do dual-source) | **456** |
| 🔴 `status=done` **sem** âncora viva — *"diz pronto, zero prova"* | **75** · piores: Whatsapp 30 · Jana 15 · Sells 8 · TaskRegistry 7 · NfeBrasil 6 · NFSe 4 |
| 🔴 `status=aberto` **com** âncora viva — *"diz a-fazer, código existe"* | **15** · Jana `US-COPI-107/108/111/112/113` · ProjectMgmt `US-TR-304/305/306/307/310/311` · PaymentGateway `US-PG-001/002/005` · Governance `US-GOV-021` |
| 🟡 `status=aberto` **sem** âncora (zona-cinza ingovernável) | **363** · piores: Infra 43 · Sells 39 · Whatsapp 38 · Financeiro 37 · OficinaAuto 28 |
| ✅ consistentes (`done`+âncora viva **e** `aberto`+`_pendente_`) | **2** |

**O achado que fecha o caso:** das 456 US que carregam os dois sinais, eles **concordam em apenas 2**. O dual-source não está drifando de vez em quando — está **estruturalmente incoerente**. (Os números do prompt original — 824 US / 8% / 65+15+309 — eram de um commit anterior; re-derivados aqui per regra anti-stale dos ADRs 0273/0275. O bloco de 15 `aberto-com-âncora` é estável e inclui o **`US-COPI-107..113`**, prova viva: a âncora foi reconciliada num passo SDD anterior mas o `status:` ficou stale — drift dual-source acontecendo em tempo real.)

### O problema, em uma frase

`status:` afirma done-ness sem nenhuma verificação; a âncora afirma done-ness com verificação determinística. Manter os dois é manter uma mentira barata ao lado de uma verdade cara — e deixar o leitor adivinhar qual vale.

## Decisão

### 1. A âncora é a fonte ÚNICA de done-ness

`**Implementado em:**` ([ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)) é a **única** resposta canônica pra *"essa US está pronta?"*. "Pronta" = `anchored_ok` (todos os paths citados existem no disco, com proveniência). `_parcial_`/`_pendente_` são estados de 1ª classe (em-progresso / não-construída). Nenhum flag digitado à mão pode declarar done-ness por fora disso.

### 2. O `status:` é aposentado como sinal de done-ness

- **`status: done` deixa de existir** no fluxo novo. Done-ness não se digita — lê-se da âncora. A catraca (§3) flagra `status: done` sem âncora viva.
- **Estado de workflow (`todo`/`doing`/`blocked`/`review`/`backlog`) não é assunto de SPEC** — vive no **registro de tasks do MCP** ([ADR 0070](0070-jira-style-task-management-current-md-removed.md): "Tasks → tools MCP `tasks-*`, nunca markdown"). SPEC descreve **o quê** (o requisito), a âncora diz **se-feito** (verificável), o MCP rastreia **o trabalho**. Três concerns, três casas, zero dual-source (princípio duro 5 — SoC brutal, [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)).
- **US nova nasce sem `status:`.** O [`_TEMPLATE_SPEC.md`](../requisitos/_TEMPLATE_SPEC.md) e a regra do fluxo novo do ADR 0273 §3 passam a omitir o token `status:` (owner/priority/estimate/type seguem livres — são metadados, não done-ness).
- **`status:` legado (456 ocorrências) NÃO é reescrito aqui** (1 PR = 1 intent). É permitido enquanto **não contradisser** a âncora; a catraca torna a contradição visível e o backfill (onda separada, §abaixo) zera.

### 3. Catraca de contradição (`doneness-lint.mjs`) — nasce ADVISORY

Script novo `scripts/governance/doneness-lint.mjs` (fs-puro, determinístico, sem deps/DB/PHP — clone de idioma do `anchor-lint.mjs`, reusa a gramática/classify da ADR 0273). Reprova (`--check` → exit 1) **só nas duas contradições**:

| Contradição | Significado | Catraca |
|---|---|---|
| `status=done` + âncora **não-viva** | diz pronto, zero prova | 🔴 morde |
| `status=aberto` + âncora **viva** | diz a-fazer, mas o código existe | 🔴 morde |
| `status=aberto` + **sem** âncora (zona-cinza) | ingovernável, mas **não é contradição** — é lacuna de cobertura do ADR 0273 | 🟡 advisory, **não** morde |

A zona-cinza (363 US) é dívida de `anchor_coverage`, endereçada pelo backfill do ADR 0273 (SA-A4/A5), **não** por esta catraca — senão 363 US reprovariam no dia 1 sem ação possível. Modos `default`/`--json` saem 0 sempre (advisory); `--check` é o primitivo de enforcement, provado por fixture boa/ruim no `gate-selftest.mjs` (GT-G6 — "quem vigia os vigias").

### 4. Calendário advisory → required (gates nascem advisory — ADR 0271/0275)

| Fase | Gate | Critério de promoção |
|---|---|---|
| **F1 ADVISORY** | `doneness-lint.mjs` em CI, diff-only (SPECs tocados no PR), reporta sem bloquear; entra no `gate-selftest` | nasce junto com o script (PR companheiro) |
| **F2 CATRACA diff-only** | `--check` **required** nos SPECs tocados | as 90 contradições atuais reconciliadas a 0 (backfill, onda separada) + 14d advisory com falso-positivo <5% + flip Wagner (máx 1 promoção required/semana, [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5) |
| **F3 REQUIRED full-tree** | `--check` required na árvore inteira | `conflitos_total` = 0 global + entry em `required-checks-baseline.json` + aprovação Wagner |

## Consequências

- ✅ Done-ness vira **fonte única verificável**: a pergunta "está pronto?" tem UMA resposta, e ela é checável por script sem IA no runtime.
- ✅ A classe de bug "diz done, não tá" (75 US hoje) fica **impossível de entrar calada** no fluxo novo — a catraca acusa.
- ✅ Workflow sai do markdown e volta pro MCP (ADR 0070), fechando a 2ª fonte na origem em vez de tentar sincronizar duas.
- ✅ Mesmo remédio que curou os 4 índices de ADR (ADR 0256/0258), aplicado ao done-ness.
- ⚠️ **Backfill é onda separada** (1 PR = 1 intent): as 90 contradições + 363 zona-cinza NÃO são corrigidas neste ADR nem no PR da catraca. Reescrever ~389 US à mão aqui seria o anti-padrão que esta ADR combate (remendo manual de dual-source). A ADR define a regra; a catraca advisory dá visibilidade; o backfill mecânico (estilo SA-A4) reconcilia depois.
- ⚠️ `status:` legado continua no repo durante o grace-period — leitor humano ainda vê o campo até o backfill. Mitigação: a catraca o marca como suspeito; o canon (este ADR) diz qual vale.

## Alternativas consideradas

1. **`status:` derivado da âncora (campo gerado).** Rejeitado: gera tooling de reescrita pra um campo cujo valor de workflow já pertence ao MCP (ADR 0070). Derivar duplicaria o que já tem dono — manter a 2ª fonte "só que gerada" não é fonte única, é fonte única com cópia.
2. **Manter os dois e só alertar no drift (sem aposentar).** Rejeitado: alerta perpétuo sobre duas fontes é gerenciar o sintoma; a doença é existir duas fontes. ADR 0256 já provou que a cura é colapsar pra uma.
3. **Reescrever as 389 US neste PR.** Rejeitado: viola 1 PR = 1 intent e é frágil (mão humana em massa = nova fonte de erro). Backfill mecânico verificável é onda própria.
4. **Catraca já required.** Rejeitado: violaria "gate novo nasce advisory" (ADR 0271/0275) e reprovaria 90 PRs-órfãos no dia 1.

## Referências

- [ADR 0273](0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) — gramática da âncora (fonte da verdade de done-ness) · [`anchor-lint.mjs`](../../scripts/governance/anchor-lint.mjs)
- [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) / [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) — gates nascem advisory + calendário de promoção
- [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) / [ADR 0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) — fonte única gerada (precedente: 4 índices de ADR → 1)
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — tasks/workflow no MCP, nunca markdown (a casa do `status:` aposentado)
- Medidor: `scripts/governance/doneness-lint.mjs` (PR companheiro) · catraca verificada em `scripts/governance/gate-selftest.mjs`
