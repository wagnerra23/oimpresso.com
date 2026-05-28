---
adr: 0225
title: Recalibração skills Tier A pós-Claude 4.8 — 8 always-on → 5 núcleo + 7 auto-trigger
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
supersedes_partially: [0095, 0168]
references:
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0093-multi-tenant-isolation-tier-0.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0095-skills-tiers-convencao-interna.md
  - 0168-protocolo-wagner-sempre-tier-A-irrevogavel.md
  - 0224-hooks-block-vs-advisory-claude-4.8-aware.md
lifecycle: active
---

## Contexto

Segunda ADR da reavaliação Claude 4.8 ([sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md)), sequência depois de [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) (hooks).

O banner SessionStart (`tier-a-banner.ps1`) declarava **8 skills Tier A always-on** + 1 dormente. Cada uma carrega ~90-260 linhas no início de TODA sessão de TODO dev (time MCP Felipe/Maiara/Eliana/Luiz incluso) = ~1.300 linhas de overhead fixo.

Premissa que motivou always-on (maio/2026): **modelo esquece regra entre turnos** (instruction-following imperfeito, janela ~200k). Com Claude 4.8 (1M context, instruction-following muito melhor), o always-on de skill vira redundante — o modelo ativa a skill certa quando o trabalho toca o path/intenção relevante (description matching), no momento exato em vez de carregar tudo no boot.

Critério: **Tier A = sempre relevante (segurança/LGPD/disciplina) OU instituída por Wagner em reação a falha concreta.** Tudo que dispara por path/intenção específica → auto-trigger (Tier B).

## Decisão

### MANTER Tier A (5 skills — núcleo)

| Skill | Razão (NÃO é muleta de modelo) |
|---|---|
| `multi-tenant-patterns` | Segurança Tier 0 IRREVOGÁVEL ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — sempre relevante |
| `commit-discipline` | Núcleo PII/LGPD + disciplina de PR — toda sessão commita |
| `incident-done-checklist` | DoD smoke real (R1) — disciplina de qualidade que nenhum modelo dispensa |
| `memory-first-secret-search` | Wagner instituiu 2026-05-28 ("arrume essa memória pra não acontecer mais") — segurança |
| `hostinger-dns-autonomy` | Wagner instituiu 2026-05-28 ("não me pergunte, foi incapaz") — anti-helpdesk |

### REBAIXAR pra auto-trigger / Tier B (7 skills)

| Skill | Premissa antiga | Por que auto-trigger basta no 4.8 |
|---|---|---|
| `brief-first` | "contexto escasso/caro" (200k) | 1M context: 30k onboarding = 3% janela. Vira conveniência início sessão, não bloqueador |
| `mcp-first` | "tokens exploração caros" | Exploração filesystem barata + agentic loop 4.8 eficiente |
| `mwart-process` | "modelo repete 6 gotchas" | Description já dispara em Edit `Pages/*.tsx` — momento exato |
| `mwart-comparative` | processo visual | Description já dispara em Edit Page Inertia |
| `charter-first` | "não cabe spec inteira" (200k) | Já semi-dormente; dispara ao editar tsx com `.charter.md`. Hook `charter-validate` (advisory) fica |
| `preflight-modulo` | "esquece de ler regras módulo" | Description "ANTES de Edit Modules/<X>/" dispara no momento exato (mira melhor que banner). Hook `modulo-preflight-warning` + Regra Primária proibicoes.md ficam |
| `wagner-protocol-enforce` | "esquece protocolo Wagner" | **Binding REAL já está em `proibicoes.md` REGRA ZERO** (Tier 0 IRREVOGÁVEL), que é `@import` no CLAUDE.md ⇒ SEMPRE em contexto. Always-on da skill era duplicação |

### Achado-chave: PROTOCOLO WAGNER já é Tier 0 duro sem a skill

`memory/proibicoes.md` linha 3+ tem **"REGRA ZERO — PROTOCOLO WAGNER SEMPRE (Tier 0 IRREVOGÁVEL)"** com a tabela completa R1-R11. E `proibicoes.md` é `@import` no `CLAUDE.md` ("## Proibições @memory/proibicoes.md") → carregado em **toda sessão automaticamente**, independente de qualquer skill. Portanto:

- **R1 (smoke real)** + **R10 (aprovação humana)** + R2-R11 continuam **Tier 0 duro** — zero risco de afrouxar
- A skill `wagner-protocol-enforce` always-on era redundante com a REGRA ZERO sempre-carregada
- Rebaixá-la pra on-demand NÃO afrouxa nada — só remove duplicação

## Não-goals

- ❌ **Não remove nenhuma skill** — só muda `tier: A → B` (metadata + banner)
- ❌ **Não afrouxa R1/R10 nem nenhuma regra Wagner** — ficam Tier 0 em proibicoes.md (sempre @import)
- ❌ **Não toca as 3 skills Wagner-instituídas que ficam Tier A** (memory-first-secret, hostinger-dns, + incident-done)
- ❌ **Não remove hooks pareados** (charter-validate, modulo-preflight-warning continuam advisory)
- ❌ **Não mexe em multi-tenant nem LGPD**

## Implementação (este PR)

- `tier: A → tier: B` em 6 frontmatters: brief-first, mcp-first, mwart-process, mwart-comparative, charter-first, wagner-protocol-enforce (+ `preflight-modulo` ganha `tier: B` explícito)
- `charter-first`: `always_on: true → false`; `wagner-protocol-enforce`: `auto_trigger: session_start → on_demand` + nota recalibração
- `tier-a-banner.ps1` reescrito: 5 Tier A núcleo + 6 auto-trigger listadas + nota PROTOCOLO WAGNER on-demand (R1/R10 Tier 0 via proibicoes)
- Esta ADR (`supersedes_partially: [0095, 0168]` — append-only, não reescreve as mães)

## Consequências

✅ **Boas:**
- ~1.300 → ~620 linhas de overhead SessionStart (5 skills núcleo vs 8+); resto carrega só quando relevante
- Mira melhor: skill ativa no momento exato do trabalho (Edit Pages/, Edit Modules/) vs sempre no boot
- Time MCP entra com SessionStart mais leve e focado
- Zero afrouxamento Tier 0: proibicoes.md REGRA ZERO (sempre @import) + 9 hooks deny (ADR 0224) + 5 skills núcleo
- Banner honesto: reflete o que realmente sempre-importa vs o que dispara por contexto

⚠️ **Tradeoffs:**
- Skill auto-trigger depende do description matching disparar — se a description for fraca, pode não ativar. Mitigação: as 6 rebaixadas têm descriptions fortes ("ANTES de Edit Pages/*.tsx", "ANTES de Edit Modules/") que disparam por path
- `brief-first` não força mais brief no boot — Wagner pode chamar manualmente OU a description ainda sugere. Se Wagner sentir falta, reverter brief-first pra Tier A é 1 linha
- Mudança de convenção que time MCP precisa entender — banner novo documenta
- `supersedes_partially` em 0095/0168 — append-only respeitado, mas exige ler 0225 junto com as mães

## Validação

- ✅ 7 frontmatters editados (`tier: B`)
- ✅ `tier-a-banner.ps1` reflete 5 núcleo + 6 auto-trigger
- ✅ proibicoes.md REGRA ZERO confirmada (R1-R11 Tier 0, sempre @import via CLAUDE.md)
- ✅ Hooks pareados (charter-validate, modulo-preflight) permanecem advisory
- ⏳ Teste real: próxima sessão SessionStart mostra banner novo; skills auto-trigger ativam ao tocar path relevante

## Notas

- Sequência reavaliação 4.8: 0224 (hooks) → **0225 (skills)** → 0226 (brief v2) → 0227 (MWART single-layer) → 0228 (subagent nativo)
- Wagner aprovou triagem caso-a-caso 2026-05-28 ("sim faça vamos testar") após ver tabela das 12 skills
- Reverter qualquer rebaixamento = 1 linha (`tier: B → A` + banner) — totalmente reversível
- ADR 0226 (brief v2) é independente, pode ir em paralelo na próxima sessão
