---
date: "2026-07-09"
topic: "US-GOV-052 P31+P32 — fonte única de skills-tier (gerador de índice) + hue-canon.json com verificador"
authors: [C]
related_adrs: [0190-primary-button-roxo-universal-295, 0095-skills-tiers-convencao-interna, 0314-poda-gates-onda-2-lei-fusoes]
---

# Sessão 2026-07-09 — P31+P32 da revisão memória-processo: skills-index gerado + hue-canon

Consertos P31+P32 da [revisão da memória do processo](2026-07-09-revisao-memoria-processo-doutrina-0329.md) (US-GOV-052). Modelo: `adr-index-generate.mjs` (fonte única GERADA, Log4brains), que já resolveu o mesmo problema pros 4 índices de ADR.

## P31 — tier de skill em 4 fontes que driftavam

Frontmatter dos SKILL.md · lista do CLAUDE.md · banner `tier-a-banner.ps1` · skills-audit divergiam (o PR #4015 corrigiu o CLAUDE.md NA MÃO — ia driftar de novo). Entregue:

1. **Reconciliação do frontmatter** (ressalva bloqueadora do adversário — campo explícito, nunca prosa): `auto_trigger:` explícito nas 6 skills rebaixadas pela ADR 0225 (`mcp-first` intent · `mwart-process`/`mwart-comparative`/`charter-first`/`preflight-modulo` path); `constituicao-ui-aware` e `session-start-check` rebaixadas A→B com `recalibracao_nota` (drift vs critério 0225 — disparam por path/momento, não são núcleo segurança/LGPD; banner e CLAUDE.md pós-0225 já não as listavam); campo novo `resumo:` nas 14 destacadas (vira a linha do CLAUDE.md).
2. **`scripts/governance/skills-index-generate.mjs`** — lê `tier:`+`auto_trigger:`+`enabled:`+`resumo:` das 72 skills e gera (a) o bloco do CLAUDE.md entre `<!-- AUTO:SKILLS-BEGIN/END -->` (SÓ a lista — resto do doc segue manual, ressalva b) e (b) `.claude/skills/_SKILLS-INDEX.md` (tabela completa). `--check` falha em drift OU frontmatter sujo (tier inválido, A+auto_trigger contradição, destaque sem resumo, núcleo ausente do banner — 4ª fonte coberta por menção). Derivação: A+enabled→núcleo · A+enabled:false→dormente · B+auto_trigger≠on_demand→destaque.
3. CLAUDE.md re-apontado: tier de skill agora no índice gerado (03-skills-audit vira histórico).

Efeito colateral honesto (visível no diff pro Wagner revisar): o bloco auto-trigger do CLAUDE.md ganha `constituicao-ui-aware` e `session-start-check` — derivação fiel dos campos, não curadoria nova.

## P32 — hue primário em 3 mapas divergentes

`pageheader-canon` tinha check C4 aprovando o 145 morto (corrigido #4003, mas sem fonte). Entregue:

- **`governance/hue-canon.json`** — `primary_hue: 295`, fonte ADR 0190. Escopo SÓ o primary universal; `SIDEBAR_GROUP_HUE` fica FORA (fonte é `resources/js/Components/cockpit/shared.ts` — não duplicar, ressalva c).
- **`scripts/governance/hue-canon-check.mjs`** — varre o repo por CONSTRUÇÕES declarativas (`expected_hue`/`expectedHue`/`hue_correct`/`hueCorrect`/`primary_hue`) e falha se declararem ≠ canon. NUNCA flagra "145" cru (ressalva do adversário — falso-positivo proibido, provado por teste). Estado atual: 2 confirmações (pageheader-canon pós-#4003), 0 divergência.

## Enforcement (tudo advisory — ADR 0314)

4 steps no `governance-script-tests.yml` (advisory de nascença, workflow existente — sem Check M): teste bite/release + `--check` de cada lado. Testes herméticos no padrão gate-selftest: `skills-index-generate.test.mjs` 14/14 (morde: bloco editado à mão, índice driftado, tier vazio, destaque sem resumo, A+auto_trigger, núcleo fora do banner) e `hue-canon-check.test.mjs` 7/7 (morde: expected_hue 145, hue_correct '145', primary_hue paralelo; NÃO morde: 145 em prosa, SIDEBAR_GROUP_HUE; canon inválido falha alto).

## Pendências honestas

- Banner `tier-a-banner.ps1` é checado por MENÇÃO do núcleo (não gerado) — se o texto do banner mentir tier sem omitir nome, passa. Gerar o banner é evolução possível se reincidir.
- Promoção a required só via reabertura da 0314 (não repropor no calado).
