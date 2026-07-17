---
slug: 0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula
number: 342
title: "Schema de ADR: slug/refs aceitam ponto e maiúscula pra casar 3 filenames legacy irrenomeáveis"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-07-17"
module: governance
kind: meta
supersedes: []
related:
  - 0095-skills-tiers-convencao-interna
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0341-memory-schema-charter-spec-required-emenda-0314
pii: false
---

# ADR 0342 — slug/refs de ADR aceitam ponto e maiúscula (casar filename legacy)

> Emenda cirúrgica ao `scripts/memory-schemas/adr.schema.json` (a FONTE ÚNICA do vocabulário de
> frontmatter de ADR — [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)).
> Aprovada por Wagner nesta sessão (2026-07-17, pergunta "relaxar o pattern do schema" vs "deixar
> como exceção permanente" vs "renomear os 3 arquivos").

## Contexto

O schema exigia `slug` (e os itens de `related`/`supersedes`/`superseded_by`/`supersedes_partially`)
casarem `^[0-9]{4}-[a-z0-9-]+$` — kebab lowercase, sem ponto. Mas **3 ADRs têm o próprio filename
fora desse pattern**, e o filename **não pode ser renomeado**:

- `0168-protocolo-wagner-sempre-tier-A-irrevogavel` — `A` maiúsculo
- `0224-hooks-block-vs-advisory-claude-4.8-aware` — ponto em `4.8`
- `0225-skills-tier-a-recalibracao-claude-4.8` — ponto em `4.8`

O torniquete: o linter **required** `AdrFrontmatterLinterTest` exige `slug == filename` (o filename é
lei), enquanto o `memory-schema-gate` (AJV) exigia o pattern lowercase-sem-ponto. **Os dois não podem
ser satisfeitos ao mesmo tempo** sem renomear o arquivo — e renomear é proibido: quebra links de
entrada em todo o corpus e o `governance-gate.yml` bloqueia rename de ADR (append-only, ADR mantém
número/slug). Pior: o ponto/maiúscula **propaga** a falha pra todo ADR que referencia esses 3 pelo
slug real (ex. `related: [0224-hooks-...-claude-4.8-aware]`).

## Relação com a ADR 0341 (o que ela deixou em aberto)

A [ADR 0341](0341-memory-schema-charter-spec-required-emenda-0314.md) (mesma data) promoveu o
`memory-schema-gate` a **required** só em `charter` e `spec` (as 2 famílias com 0 violações) e
**deferiu o adr** com a justificativa *"append-only: consertar é PROIBIDO, Art. 3 — required tornaria
143 decisões PERMANENTEMENTE intocáveis"*.

Esse framing **não considerou a [ADR 0297](0297-excecao-append-only-migracao-legacy-frontmatter-adr.md)**
(`adr-legacy-schema-migration`): a exceção append-only que **já permite** migrar o frontmatter de ADR
desde que o **corpo seja byte-idêntico**. A decisão (corpo) segue imutável; só a etiqueta migra. A
normalização que acompanha esta ADR provou empiricamente que o adr vai de **139 → 0** por esse caminho,
com os 140 corpos byte-idênticos. Ou seja: o adr **é** corrigível — o único bloqueador *estrutural*
restante era o pattern do slug (os 3 filenames acima). Esta ADR remove esse bloqueador.

## Decisão

Relaxar os 5 patterns de slug/refs de `^[0-9]{4}-[a-z0-9-]+$` para
**`^[0-9]{4}-[A-Za-z0-9.-]+$`** (permite `.` e `A-Z`) em ambos os arquivos que carregam o pattern:
`scripts/memory-schemas/adr.schema.json` (fonte) e `memory/decisions/_schema.json` (espelho hand-sync
— não há gerador). Os patterns de data (`decided_at`/`rejected_at`) ficam **intactos**.

A convenção segue kebab lowercase pra ADRs novas (é o estilo preferido); o pattern só deixa de
**rejeitar** os 3 filenames legacy que já existem e são a realidade em disco. `slug == filename`
segue sendo lei pelo `AdrFrontmatterLinterTest`.

## Consequências

- ✅ Os 3 slugs legacy + todo ADR que os referencia passam a validar no AJV → o adr chega a **0**
  violações de schema (fora curadoria de refs órfãs, que é outra coisa — a lei "fato derivado" da 0341).
- ✅ Destrava o **pré-requisito** pra promover `ADR (memory/decisions/*.md)` a required — a Fase 3 que
  a 0341 deixou explicitamente em aberto pro adr. (Esta ADR **não** promove; só habilita.)
- ⚠️ O pattern deixa de **impor** kebab-lowercase; passa a **permitir** ponto/maiúscula. Mitigação: a
  convenção segue documentada (kebab preferido) e `slug == filename` continua required — um filename
  novo fora do kebab é escolha visível de quem cria o arquivo, não um buraco silencioso.
- ⚠️ **Não re-endurecer** este pattern sem endereçar os 3 filenames irrenomeáveis (senão regride:
  0168/0224/0225 voltam a falhar). Esta ADR existe pra que uma sessão futura não "corrija" o pattern
  de volta achando que o `.`/`A-Z` é bug.

## Refs
- Fonte: `scripts/memory-schemas/adr.schema.json` (+ espelho `memory/decisions/_schema.json`)
- Linter required que fixa `slug == filename`: `tests/Feature/Memory/AdrFrontmatterLinterTest.php`
- Exceção append-only usada na normalização: [ADR 0297](0297-excecao-append-only-migracao-legacy-frontmatter-adr.md) (label `adr-legacy-schema-migration`)
- Origem: Wagner 2026-07-17, sessão de normalização dos 143 ADRs (Fase 1, follow-up da [ADR 0341](0341-memory-schema-charter-spec-required-emenda-0314.md))
