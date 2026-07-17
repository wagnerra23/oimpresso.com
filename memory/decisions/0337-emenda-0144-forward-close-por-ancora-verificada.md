---
slug: 0337-emenda-0144-forward-close-por-ancora-verificada
number: 337
title: "Emenda à 0144 — o sync FECHA-pra-frente um card quando a âncora está verificada (anchored_ok) + SPEC declara done; nunca reabre"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-14"
accepted_at: "2026-07-14"
accepted_via: "Wagner 'sim' 2026-07-14 (sessão stoic-bell) autorizando automatizar o fechamento do split-brain SPEC/âncora↔card, depois do diagnóstico da US-FIN-031 (card todo por 8d com código em prod). Aceite cobre a POLÍTICA (exceção estreita à 0144: forward-close por âncora verificada) + a implementação no TaskParserService; a promoção de qualquer gate fica em PR próprio."
module: governance
quarter: 2026-Q3
tags: [governance, tasks, mcp, taskregistry, sync, ancora, done-ness, forward-close, reconciliacao]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0144-tasks-db-canonico-spec-template
  - 0237-jana-reconcile-loop-unico
  - 0070-jira-style-task-management-current-md-removed
---

# ADR 0337 — Emenda à 0144: forward-close por âncora verificada

## Status

Aceito (Wagner 2026-07-14). Emenda **estreita** à [ADR 0144](0144-tasks-db-canonico-spec-template.md) — não a supersede; abre UMA exceção nomeada, ancorada na [ADR 0302](0302-ancora-fonte-unica-doneness.md).

## Contexto

A [ADR 0144](0144-tasks-db-canonico-spec-template.md) estabeleceu: o **DB (`mcp_tasks`) é canon de estado vivo**; o webhook de sync SPEC→DB **nunca sobrescreve** `status/owner/sprint/priority` de tasks já existentes (mudança só via `tasks-update`, auditada). Isso protege transições humanas de serem pisadas por um `status:` digitado à mão no SPEC — que ninguém governa.

Efeito colateral medido (incidente **US-FIN-031**, 2026-07-14): a US foi entregue e mergeada em `origin/main` em 2026-07-06 (PR #3905), com o SPEC atualizado pra `status: done` **e** âncora `**Implementado em:** ... verificado@ec17185 (2026-07-06)`. Mas o card no MCP ficou **`todo` por 8 dias**. Porque:

1. **`doneness-lint` / `anchor-lint`** ([ADR 0273](0273-anchor-spec-codigo-gramatica.md)/[0302](0302-ancora-fonte-unica-doneness.md)) comparam os dois campos DENTRO do SPEC — param na fronteira do git, não olham o DB.
2. **`TasksReconciler`** ([ADR 0237](0237-jana-reconcile-loop-unico.md)) é detect-only e não tem a faceta "SPEC done + card aberto".
3. **O Zelador diário** só varre `my-work` (doing/review) — a US-FIN-031 era `todo` + unowned → fora do radar.
4. **O próprio sync** já DETECTA o caso (`logarSkipsDeEstadoVivo`: "SPEC done, DB todo — preservado ADR 0144") mas só **loga**, não age.

Causa-raiz única: **a âncora verificada (fonte de done-ness, ADR 0302) nunca era carregada do git pro status do card.** Os lints paravam no git; o DB ficava órfão.

## Decisão

O sync (`TaskParserService::syncAll`) passa a **fechar-pra-frente** um card quando **TODAS** valem (fail-closed):

1. o card no DB está **ativo** (não `done`/`cancelled`);
2. o SPEC declara `status: done` (decisão humana explícita — 1 dos 2 sinais);
3. a âncora `**Implementado em:**` é **`anchored_ok`** pela gramática canônica do ADR 0273: forma `` `path`( · `path`)* · verificado@<7hex> (YYYY-MM-DD) `` **E** todos os paths existem no disco.

Ao fechar: `status = done`, `completed_at = now()` (se nulo), `acceptance_ref` derivado do SHA da âncora (preserva um `acceptance_ref` humano se já houver), + evento `status_changed` auditável (`author: webhook-sync`, nota citando a ADR).

**Por que isto NÃO viola a 0144.** A 0144 blindou o **`status:` digitado** de sobrescrever o DB — e continua blindado (o `status:` sozinho não fecha nada aqui). O que autoriza o forward-close é a **âncora verificável** (ADR 0302 elege a âncora, não o `status:`, como fonte de done-ness). É uma base ratificada NOVA, não a que a 0144 proibiu.

### Invariantes (o que o forward-close NUNCA faz)

- **Nunca reabre.** `done`/`cancelled` no DB são terminais e intocados (preserva a 0144 §"não regride done→cancelled").
- **Nunca toca** `owner`/`sprint`/`priority`.
- **Nunca fecha** com âncora `_pendente_`/`_parcial_`/placeholder/path-morto, nem sem o token `verificado@<sha> (data)`.
- **Não reavalia zumbi** (Page desligada, ADR 0273 SA-A2-bis): o gatilho exige TAMBÉM o `status: done` humano no SPEC, que é a decisão que cobre esse resíduo.
- **Fecho direto** (contorna a FSM `mcp_tasks`, igual ao cancel-de-órfãs que já existe no sync): o PR já passou pelo review real no git; caminhar `doing→review→done` fabricaria eventos falsos. O evento de auditoria explicita que foi reconciliação por âncora.

## Consequências

**Positivas:** o split-brain SPEC/âncora↔card fecha no instante em que o PR ancorado mergeia (o sync já roda por webhook a cada push) — zero lag, zero dependência de dono ou de sessão. Reusa a classificação de âncora do ADR 0273 (nenhum reconciliador novo). `acceptance_ref` sempre preenchido no auto-close ⇒ não dispara o R-B do `TasksReconciler`.

**Custos / riscos:** o sync agora escreve `status` num caso estreito — mitigado por (a) exigir DOIS sinais independentes (SPEC done + âncora verificada no disco), (b) forward-only, (c) cobertura Pest do núcleo puro (`SpecAnchorClassifier` + `deveFecharPorAncora`) travando o contrato. Se a âncora for verificada mas o `status:` humano estiver errado (raro — exige alguém escrever `done` + âncora canônica falsamente), o card fecha; o dono reabre via `tasks-update` (done→review, transição legal).

## Alternativas descartadas

- **Faceta no `TasksReconciler`** — é detect-only por design (0237); auto-fechar exigiria quebrar sua postura. E ele lê só o DB, não parseia o corpus SPEC.
- **Estender o Zelador** — resolve com lag diário e continua dependente da superfície de varredura (my-work). O sync fecha na hora.
- **Hook preventivo local** (forçar `tasks-update` ao editar SPEC pra done) — só cobre sessões Claude locais; não cobre outros contribuidores.

## Implementação

- `Modules/Jana/Services/TaskRegistry/SpecAnchorClassifier.php` — núcleo puro (gramática ADR 0273, path-existence injetada).
- `Modules/Jana/Services/TaskRegistry/TaskParserService.php` — `fecharPorAncoraSeElegivel()` + núcleo puro `deveFecharPorAncora()` + contador `fechadas_por_ancora`.
- `Modules/Jana/Console/Commands/McpTasksSyncCommand.php` — linha "Fechadas p/ âncora".
- `Modules/Jana/Tests/Feature/TaskRegistry/SyncAnchorForwardCloseTest.php` — unit (núcleo) + integração (skip local, CT 100).
