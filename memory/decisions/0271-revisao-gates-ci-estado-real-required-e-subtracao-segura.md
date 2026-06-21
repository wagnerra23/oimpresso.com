---
slug: 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
number: 271
title: "Revisão dos 64 gates CI — estado real dos required (corrige drift da 0261), fonte única de vocabulário ADR, e 1ª onda de subtração segura"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-11"
accepted_at: "2026-06-11"
accepted_via: "Wagner 2026-06-11 no chat: 'Os portões tem que ser revisto. Estão defasado e conflitantes.' → escolheu opção (b) ler tudo e reavaliar → aprovou execução dos 4 passos seguros + esta ADR: 'pode fazer'. Redação por [CL]."
module: governance
quarter: 2026-Q2
tags: [governance, gates, ci, required, enforcement, subtracao, auditoria, anti-elefante-branco, vocabulario, fonte-unica]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0261-enforcement-faseado-gates-ci", "0263-identidade-cor-gate-bloqueante", "0264-governanca-executavel-trio-dominio-e2e", "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0216-governance-drift-framework-driftchecker-plugavel"]
pii: false
---

# ADR 0271 — Revisão dos 64 gates: estado real, fonte única de vocabulário, subtração segura

## Contexto

Wagner, 2026-06-11: *"Os portões têm que ser revistos. Estão defasados e conflitantes."* Auditoria completa (conteúdo dos 64 workflows + scripts invocados + ADRs 0261/0263/0264) confirmou e ampliou:

- **12 conflitos** entre gates — destaque: 2 gates bloqueantes **discordando no mesmo PR** (enum `module`/`status` de ADR em 3 fontes divergentes; `_schema.json` decorativo com 10 valores vs ~37 da fonte executada), 4 réguas pro mesmo charter, 4 baselines de cor, 3 scanners de secrets (canary da ADR 0216 nunca removeu o legacy).
- **1 gate de teatro**: `jana-ragas-gate` "BLOQUEANTE" rodando em mock por default — exit 1 sobre score fixo (sempre verde, avalia nada).
- **1 mentira no canon**: `proibicoes.md` afirmava que `mwart-gate.yml` "bloqueia no merge" — falso (`continue-on-error: true`, só comenta).
- **1 deadlock latente**: `ui-architecture-gate` required (ADR 0263) **e** path-scoped — mesma classe do incidente "Expected" 2026-06-08 que a 0261 documentou e corrigiu nos outros 3.
- **~14 mortos/one-offs** (debug de incidentes fechados com header "remover", webhook nunca configurado, gate que comenta no PR errado).
- **ADR 0261 defasada em 48h**: declara 4 required; o set real é ~9 (0263 somou 3 + ligou `enforce_admins` no dia seguinte ao "esperar ~1 semana"; flip [W] 2026-06-09 somou casos+dominio — ADR 0264).

É a mesma doença do conhecimento (ADR 0270) na camada de fiscalização: gates somados no tempo, nunca reconciliados, papel divergindo da máquina.

## Decisões

### D-1 — Estado real dos required (corrige o drift documental da 0261)

Set required vigente (~9): `ADR frontmatter` · `PHP / Pest (Unit)` · `Frontend / Vite build` · `module-grades-gate` (ADR 0261) + `Conformance` · `UI Lint` · `UI architecture` (ADR 0263, com `enforce_admins: true`) + `casos-gate` · `dominio-gate` (flip [W] 2026-06-09, ADR 0264). Qualquer mudança futura no set required referencia ESTA ADR (a 0261 fica como histórico da Alavanca 1).

### D-2 — Fonte única do vocabulário de ADR

`scripts/memory-schemas/adr.schema.json` é a **fonte única** executada (memory-schema-gate, AJV, strict em arquivos novos/modificados). Mudanças nesta onda:
- `module` ganhou enum **idêntico** ao `MODULE_VALIDOS` do linter Pest (~37 valores; antes aceitava qualquer string — `module: Jana` custou 2 commits nesta sessão).
- `memory/decisions/_schema.json` virou **espelho gerado** da fonte única (antes: 10 valores divergentes, não aceitava `governance` — as próprias ADRs 0261/0263 o violavam — e não era executado por gate nenhum).
- Camadas: schema strict = lei pra ADR **nova**; linter Pest adicionalmente tolera variantes legacy em ADRs aceitas (append-only impede reescrita).

### D-3 — Subtração segura executada nesta onda (6 workflows deletados, 2 desarmados)

| Ação | Alvo | Por quê |
|---|---|---|
| DELETE | `hostinger-final-total-fix` | header "remover pós-incidente"; incidente num_uf fechado (fix #2279) |
| DELETE | `debug-tz-info` · `debug-fin-counts` · `debug-caixa-logs` | debug one-shot de incidentes resolvidos |
| DELETE | `ui-canon-notify` | webhook `UI_NOTIF_WEBHOOK_URL` nunca configurado — no-op em todo push |
| DELETE | `screen-smoke-after-merge` | comenta no "último PR mergeado" (pode ser outro PR); skill não invocável remoto — ruído |
| DESARMA | `jana-ragas-gate` | exit 1 só em `RAGAS_MODE=real`; mock vira advisory (era teatro: bloqueio sobre score fixo) |
| DESARMA | `ui-architecture-gate` | `pull_request` sem `paths:` (required + path-scoped = deadlock "Expected") |
| CORRIGE | `proibicoes.md` §MWART | doc afirmava bloqueio que não existe; corrigido pra refletir soft mode + régua viva no casos-gate |

**64 → 58 workflows.** Nenhuma proteção real foi removida — só morto, teatro e bomba armada.

### D-4 — Pendente ratificação Wagner (próxima onda, NÃO decidido aqui)

A auditoria propõe **LEI de 11** (bloqueiam merge por evitarem catástrofe Tier 0: multi-tenant · Pest · build · financeiro-pest · pii-scan · append-only · secrets-consolidado · no-mock · dominio · phpstan · identity-fundido) + fusões F1-F7 (4 gates de cor→1, 3 de memória→1, 3 de secrets→1, trio RAGAS→1, etc) + rebaixar `module-grades-gate` (check mais caro do repo, métrica composta, não catástrofe) → alvo final **64→33**. Cada item exige palavra do Wagner; quando cravar, vira ADR própria com a lista LEI definitiva.

## Consequências

- Papel e máquina batem de novo (required real documentado; proibicoes corrigida; vocabulário com 1 fonte).
- A classe de conflito "2 gates discordando do mesmo frontmatter" morre na origem.
- Risco residual: o linter Pest e o schema ainda são 2 implementações (1 vocabulário) — drift futuro entre eles é possível; candidato a sync-check na onda das fusões.

## Métricas

- Workflows: 64 → **58** (esta onda) → alvo 33 (pós-ratificação LEI-11).
- Conflitos de vocabulário ADR: 3 fontes divergentes → **1 fonte + 1 espelho gerado + tolerância legacy documentada**.
- Gates de teatro ativos: 1 → **0**.
- Deadlocks latentes required+path-scoped: 1 → **0**.
