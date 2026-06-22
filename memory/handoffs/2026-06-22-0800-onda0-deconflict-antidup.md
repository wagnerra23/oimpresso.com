---
date: 2026-06-22
time: "0800 BRT"
slug: "onda0-deconflict-antidup"
tldr: "Onda 0 (rede anti-quebra SDD, blueprint #3129): 4 bricks — A sentinela format_date #3178; B pcov #3182; C migration scorecard aplicada em prod via SSH; D ratchet→required agendado. Reconferência achou DUPLICAÇÃO com programa SDD paralelo mais adiantado (#3150=B, #3181/#3143=D) → revertido B #3184, cancelado agendamento, proposta trava anti-dup #3186 (gate dup-detector). Lição-mãe: disciplina não impede duplicação (3ª reincidência) — precisa de gate mecânico."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3129, 3178, 3177, 3182, 3184, 3186]
next_steps:
  - "[W] decide dono + escopo da proposta anti-dup (anti-duplicacao-work-claim-gate.md) — recomendação L3-MVP (gate dup-detector no CI)"
  - "Diagnóstico do gap sistêmico: migrations de Modules/* (nwidart) que não entram no migrate:status → podem estar faltando em prod"
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0279-sdd-medir-governar-floor-nightly", "0066-format-date-shift-3h-preservado-legacy-clientes", "0270-ciclo-vida-informacao-zelador"]
---

# Handoff 2026-06-22 08:00 BRT — Onda 0 anti-quebra: executada → de-conflitada → anti-dup proposto

## Estado MCP no momento
- **CYCLE-08** "Receita — Onda A" · 79% decorrido · 6 dias restantes. Goals: pricing público, migração carteira legacy, MRR R$2000, ComVis V1, Agrosys de-riscado. **Nada desta sessão toca o cycle** — foi trilha de governança SDD (meta), ortogonal à receita.
- `my-work`: 30 tasks (7 review · 8 blocked · 15 todo) — inalteradas.

## O que aconteceu
Wagner: *"refazer o sistema com SDD otimizado"* → blueprint #3129 → **Onda 0** (rede de enforcement anti-quebra). Executei 4 bricks:
- **A** oráculo do dinheiro — `num_uf` já tinha teste; faltava o sentinela do `format_date` +3h (ADR 0066 pré-cond #5, era TODO). Escrito tz-independente (#3178 ✅).
- **B** pcov/coverage — workflow advisory lane-sqlite (#3182).
- **C** migration scorecard em prod — **apliquei via SSH** (key-based). `mcp_sdd_scorecard_history` não existia **nem no `migrate:status`** (migration de módulo nwidart fora do migrator principal); apliquei cirúrgico via `--path` + snapshot → **1 row (composta 50)**.
- **D** ratchet→required — agendado 29/jun (janela de 14d, ADR 0275 §5).

**Reconferência (Wagner: "tem outro fazendo isso?") destapou duplicação massiva:** programa "armamento SDD" paralelo (várias sessões Claude, ~15 PRs hoje #3138-#3182) já fazia o mesmo, **mais adiantado** — **#3150** = coverage full-suite CT100 (= meu Brick B); **#3181/#3143** = ratchet/foundation→required (= meu Brick D).

**De-confliction:** revertido Brick B (**#3184**); **cancelado** o agendamento de 29/jun; nota de reconciliação no #3177; **proposta a trava anti-duplicação** (#3186).

## Artefatos gerados (canon)
- Genuínos mantidos: **#3178** (sentinela format_date) + **Brick C** (migration prod aplicada).
- Revertido: **#3184** (desfez #3182 — #3150 é fonte única de coverage).
- Docs: blueprint+proposta Onda 0 (#3129/#3177), verificação (`memory/sessions/2026-06-21-verificacao-rede-onda0-estado-real.md`), **proposta anti-dup** `memory/decisions/proposals/anti-duplicacao-work-claim-gate.md` (#3186).

## Persistência
git → webhook GitHub→MCP (~2min). Brick C: aplicado direto em prod Hostinger (SSH key-based, `--path` cirúrgico).

## Próximos passos pra retomar
- **[W] decide:** dono + escopo da proposta anti-dup — recomendação **L3-MVP** (gate `dup-detector` no CI). É a peça que impede re-duplicação por máquina (teria barrado meu #3182).
- Promoções SDD a required seguem com o programa paralelo (#3181/#3143), **não comigo** (anti-duplicação aplicada a mim mesmo).

## Lições catalogadas
1. **Duplicação de trabalho reincidente (3ª vez).** Rodei a Onda 0 inteira duplicando #3150/#3181/#3143 — apesar do handoff #3092 já avisar. **Disciplina não resolve**; a correção é mecânica: o gate `dup-detector` (proposto #3186). Rodar "tem outro fazendo isso?" tem que ser **antes** de construir.
2. **Git-bash Windows mastiga `git show/cat-file origin/main:path`** (vira `origin\main;...`) → falso "AUSENTE"/"0 continue-on-error". Usar `MSYS_NO_PATHCONV=1` ou `git ls-tree origin/main -- path` (sem `:`). O **Grep tool** falhou silencioso no glob `app/**/*.php` — `git grep` pegou.
3. **Migration de módulo nwidart não entra no `migrate:status`** → o deploy (`migrate --force`) nunca aplicou `mcp_sdd_scorecard_history`; snapshot diário falhava calado. Aplicar via `--path`. **Gap sistêmico**: outras migrations de `Modules/*` podem estar faltando em prod.

## Pointers detalhados (on-demand)
- Proposta anti-dup (a peça que importa): `memory/decisions/proposals/anti-duplicacao-work-claim-gate.md`
- Handoff da duplicação anterior: `handoffs/2026-06-20-2115-sessao-duplicada-armamento-sdd.md`
- ZELADOR (reconciliador reativo, complementar): `scripts/governance/ZELADOR.md`
