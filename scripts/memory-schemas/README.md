# scripts/memory-schemas — JSON Schemas canônicos pra frontmatter

> **ONDA 5 S1 — Schema rígido CI** ([ONDA-5-DOSSIER §5](../../memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md))
>
> Schema validation per-type pros .md de `memory/` + Charters em `resources/js/Pages/`.
> Híbrido **A + C**: AJV via GitHub Actions matrix (gate PR) + `php artisan jana:validate-memory` (gate local + cron daily 06:30 BRT).

## Mapa file glob → schema

| Glob | Schema | Tipo |
|---|---|---|
| `memory/decisions/*.md` (exceto `_*.md`) | `adr.schema.json` | ADR Nygard |
| `memory/requisitos/*/SPEC.md` | `spec.schema.json` | Spec por módulo |
| `memory/requisitos/**/RUNBOOK*.md` | `runbook.schema.json` | Runbook procedural |
| `memory/sessions/*.md` (exceto `_*.md`, `README.md`) | `session.schema.json` | Session log diário |
| `memory/handoffs/*.md` (exceto `_*.md`) | `handoff.schema.json` | Handoff append-only |
| `resources/js/Pages/**/*.charter.md` | `charter.schema.json` | Page Charter (Tier A) |
| `memory/requisitos/*/BRIEFING.md` | `briefing.schema.json` | 1-pager por módulo (🟡 fiado em GRACE — warn-only, diff-aware) |
| `memory/reference/*.md` | `reference.schema.json` | Doc canônico/gerado (🟡 fiado em GRACE — warn-only, diff-aware) |

> 🟡 **`briefing` e `reference` (proposal estrutura-canon-memoria · Fase 0, fiadas 2026-07-12):** já estão na matriz do [memory-schema-gate.yml](../../.github/workflows/memory-schema-gate.yml) com `grace: true` (força `STRICT=false` só pra elas → `continue-on-error` + comentário de PR "grace"). **NÃO bloqueiam merge** — tocar um BRIEFING/reference divergente gera WARNING, não red (a skill `brief-update` toca BRIEFING direto e não pode travar o time). Promoção grace→required só **depois** do backfill zerar o falso-positivo por família (disciplina ADR 0314 — gate novo nunca nasce bloqueante). Servem também pra: (a) validação local dos codemods de normalização, (b) `system-map.mjs` ler status confiável do BRIEFING, (c) `memory-schema-preflight`.
>
> **Escopo do glob `reference`:** só `memory/reference/*.md` (os ~143 divergentes vivem aí). Os docs de topo `memory/*.md` — incluindo os **5 @imports do CLAUDE.md** (`why-`/`what-`/`how-trabalhar`/`proibicoes`/`regras-time`) — ficam **FORA** da matriz (instrução Tier 0 do `reference.schema.json`); ampliar o glob pra `memory/*.md` exige excluir esses 5 primeiro.

## Grace period 14d (ENV `JANA_VALIDATE_MEMORY_STRICT`)

| ENV value | Comportamento CI | Comportamento artisan |
|---|---|---|
| `false` (default 14d) | continue-on-error (warning) | exit 0, warning no stdout |
| `true` (após Wagner sign-off) | bloqueia merge | exit 1 |

## Normalização de legado — forward-only + oportunística (NUNCA mass-fix)

> ⛔ **Mass-fix de arquivos legados é PROIBIDO** — [proibicoes.md §5, lápide 2026-07-12](../../memory/proibicoes.md) (*"tocar um arquivo legado ACORDA os gates diff-aware que o protegiam por grandfather"*) + [ADR 0341](../../memory/decisions/0341-memory-schema-charter-spec-required-emenda-0314.md). NÃO escreva codemod que toca em massa os `session`/`handoff`/`reference`/`briefing`/`runbook` divergentes só pra padronizar frontmatter. Big-bang de backfill = descartado. O gate é **diff-aware**: o legado que ninguém toca fica válido-por-omissão para sempre.

**O conceito único chega ao legado por DOIS trilhos — nunca por varredura:**

1. **FORWARD-ONLY (o mecanismo primário):** todo arquivo **novo** nasce schema-válido porque o **template/skill canônico** já emite o frontmatter certo. Auditado + provado por fixture 2026-07-17 (harness AJV espelhando o CI) pras 5 famílias advisory:

   | Família | Fonte forward-only (copie/gere DAQUI) |
   |---|---|
   | session | [`memory/sessions/_TEMPLATE.md`](../../memory/sessions/_TEMPLATE.md) (via skill `encerrar-sessao`) |
   | handoff | [`memory/handoffs/_TEMPLATE.md`](../../memory/handoffs/_TEMPLATE.md) (ADR 0130 · skill `encerrar-sessao`) |
   | briefing | [`memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md`](../../memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md) (via skill `brief-update`) |
   | runbook | [`.claude/skills/cockpit-runbook/TEMPLATE.md`](../../.claude/skills/cockpit-runbook/TEMPLATE.md) (via skill `cockpit-runbook`) |
   | reference | skill [`memory-schema-preflight`](../../.claude/skills/memory-schema-preflight/SKILL.md) (sem template — doc à mão seguindo o skill) |

   **Se um template/skill gerar inválido, o conserto é no TEMPLATE/SKILL — nunca no legado.** (Foi assim que as 3 armadilhas de 2026-07-17 caíram: `date:` sem aspas em session/handoff → objeto `Date`; BRIEFING sem frontmatter → grandfathered; cockpit-runbook com `status: active` + faltando `owner`/`last_validated`.)

2. **OPORTUNÍSTICO (só pras famílias VIVAS):** a distinção que muda a abordagem por família —

   | Família | Natureza | Regra de normalização de legado |
   |---|---|---|
   | **session · handoff** | histórico **append-only** (como ADR) | Grandfathered **PARA SEMPRE**. Só arquivo novo nasce válido. **ZERO fix retroativo** (editar handoff/session antigo viola append-only — [ADR 0130](../../memory/decisions/0130-handoff-append-only-mcp-first.md) + Constituição Art. 3). |
   | **reference · briefing · runbook** | docs **vivos** (editáveis) | Normalizar **SÓ oportunisticamente**: quando o arquivo **já vai ser tocado** por trabalho real que **paga a dívida dele** (âncoras/freshness/conteúdo). Nunca um PR "só de frontmatter" em lote — isso acorda o gate diff-aware sem pagar a dívida que ele revela (a lápide de 2026-07-12). |

**Promoção grace→required** por família (`briefing`/`reference`) segue só depois do backfill **oportunístico** zerar o falso-positivo — nunca por big-bang ([ADR 0314](../../memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md) · [ADR 0341](../../memory/decisions/0341-memory-schema-charter-spec-required-emenda-0314.md): required = decisão deliberada + emenda, nunca merge no calado).

## Como manter

1. **Adicionar campo novo:** edite o `*.schema.json`, rode `php artisan jana:validate-memory` local; se passar, PR.
2. **Adicionar tipo novo:** crie `<tipo>.schema.json` + adicione glob no [.remarkrc.json](../../.remarkrc.json) + matrix de [.github/workflows/memory-schema-gate.yml](../../.github/workflows/memory-schema-gate.yml) + case no `JanaValidateMemoryCommand::detectSchemaForPath()`.
3. **Mudar required:** lembrar grace period — campo novo deve ser opcional por default até backfill rodar.

## Decisão arquitetural

- **A. AJV (Node)** — gate PR, padrão indústria, plugin remark.
- **C. PHP `justinrainbow/json-schema` 5.3.4** (já em composer.lock como transitive dep) — gate local + cron.

Rejeitados: B (`frontmatter-json-schema-action` magro), D (pre-commit não enforce em devs externos), E (`cassarco/markdown-tools` sem JSON Schema oficial).

## Histórico

- **2026-05-13** — Schemas criados em ONDA 5 S1 (agent schema-validator-expert).
- **2026-07-12** — `briefing` + `reference` (proposal estrutura-canon-memoria Fase 0) fiadas à matriz em GRACE (`grace: true` → warn-only, diff-aware). Forward-only: LEGADO em massa segue bloqueado pelos gates diff-aware (proibicoes.md §5). Promoção a required só após backfill FP=0 (ADR 0314).
- **2026-07-17** — Auditoria forward-only das 5 famílias advisory (session/handoff/briefing/runbook/reference) com harness AJV espelhando o CI. **3 furos de template fechados** (não legado): `date:` sem aspas em `sessions/_TEMPLATE.md` + `handoffs/_TEMPLATE.md` (YAML parseava data crua como objeto `Date` → falha `type:string`); `BRIEFING-TEMPLATE.md` sem frontmatter (nascia grandfathered); skill `cockpit-runbook` (TEMPLATE.md + SKILL.md) emitindo `status: active` (fora do enum) + faltando `owner`/`last_validated`. Skill `memory-schema-preflight` também corrigida (exemplos session/handoff citavam `type`/`tldr`/`estado_mcp` em vez dos required `topic` / `slug`+`tldr`). Nenhum arquivo de conteúdo legado tocado (seção "Normalização de legado" acima). Refs ADR 0341.
