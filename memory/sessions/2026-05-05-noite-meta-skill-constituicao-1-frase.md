---
date: 2026-05-05
slot: noite
title: "Constituição em 1 frase + meta-skill ROI ERP autônomo + scaffolder"
participants: [W, C]
duration_min: 90
tags: [governance, constitution, skills, foundation, meta-skill, adr-0078]
---

# 2026-05-05 noite — A constituição é uma frase

## Trajetória da sessão

Sessão começou como audit de drift entre módulos (Jana/ADS dumping ground) e proposta de framework de 7 camadas (Constitution + SRS + Trust Tiers + Identity Mesh + Module Charter + Policy Gating + Audit). Wagner recusou a complexidade — "1 única aposta. Skill e uma missão. só isso" — e cunhou a fórmula recursiva **Planejar → Executar → Analisar → Organizar → estado da arte**.

Pivot completo da abordagem: governança não desce em camadas, **emerge da unidade**. Cada skill é mini-módulo independente carregando sua própria constituição (mission), fronteira (triggers), regras (body), versões, testes, métricas. Auto-load por description = governance via contexto, não middleware.

## A frase

> **"Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."**

19 palavras. Toda decisão arquitetural futura passa por esse filtro:

- Substitui? (não documenta/ajuda/lembra)
- Trabalho humano repetitivo? (não decisão única)
- ROI mensurável? (tempo/erro/receita declarado)
- Acelera ERP autônomo R$ [redacted Tier 0]M / 24m? (conexão direta com a tese)

## Entregas concretas

| Artefato | Localização |
|---|---|
| Meta-skill (constituição em código) | `.claude/skills/meta-skill-roi-erp-autonomo/SKILL.md` |
| Service de scaffolding | `Modules/ADS/Services/ScaffoldSkillFromMissionService.php` |
| Comando artisan | `Modules/ADS/Console/Commands/SkillScaffoldCommand.php` |
| Registro no provider | `Modules/ADS/Providers/AdsServiceProvider.php` |
| ADR formalizando a aposta | `memory/decisions/0078-constituicao-uma-frase-skill-unidade-evolucao.md` |

## Comando

```bash
php artisan skill:scaffold "Toda IA tocando código com business_id leu SRS antes de escrever"
```

Output esperado:
- Validação dos 4 testes (substitui? humano repetitivo? ROI? R$ [redacted Tier 0]M?)
- Se passa: cria `.claude/skills/<slug>/SKILL.md` + entry em `mcp_skills` status=draft
- Se falha: retorna motivo, sugere reformulação, não cria
- `--force` ignora validação dos 4 testes

## O que NÃO virou camada separada (decisões deliberadas)

| Originalmente proposto | Decisão |
|---|---|
| Constitution.md (10 artigos) | ❌ vira **1 frase** na meta-skill |
| SRS append-only | ⏸️ adia — emerge nas regras dentro de cada SKILL.md |
| Trust Tiers (L0-L4) | ⏸️ vira `trust_level:` no frontmatter de cada skill |
| Identity Mesh (mcp_actors) | ⏸️ vira `owner:` em cada skill |
| Module Charter (SCOPE.md per módulo) | ⏸️ adia — drift atual será resolvido via skill `module-charter-enforcer`, não SCOPE.md em 30 módulos |
| Policy Gating (ActionGate middleware) | ⏸️ adia — skills auto-load injetam regra ANTES da ação. Gate por contexto, não middleware |

Insight central: **a constituição entra no contexto da IA antes da ação, não em middleware após a ação**.

## Decisões anteriores nesta sessão (linha cronológica)

1. Audit das 4 tools MCP (cycles-active/my-work/my-inbox/triage) — `my-work` quebrado por resolver buscar `users.username='WR23'` enquanto tasks usam `owner='wagner'`.
2. Bootstrap retroativo do MCP DB: ADS jira project (id=23) + CYCLE-02 (id=2, planning) + 6 tasks ADS-1..ADS-6 done com source_git_sha.
3. ADR 0077 propondo `users.mcp_handle` pra resolver bug do `my-work`. Status=proposto.
4. Audit dos 30 módulos: drift detectado em Jana (5 controllers errados) e ADS (4 controllers errados).
5. Wagner rejeitou framework de 7 camadas → pivot pra 1 frase + meta-skill (esta sessão).

## Próximas P0 amanhã

1. **SSH Hostinger:** rodar `php artisan mcp:skills:import-from-git` pra registrar a meta-skill em `mcp_skills`.
2. **Validar visualmente:** abrir `/ads/admin/skills` — meta-skill deve aparecer (seria 16ª skill, fechando o gap de 15→16 que estava no handoff anterior).
3. **Primeira skill filha:** Wagner roda `php artisan skill:scaffold "<sua próxima missão>"` pra validar o ciclo end-to-end.
4. **ADR 0077 (do meio-dia):** ainda pendente aprovação. Decidir se executa ou supersedede.
5. **Trigger logging:** instrumentar carregamento de skill pra incrementar `triggered_count` em `mcp_audit_log` (Fase Analisar do ciclo).

## Aprendizado

**Quando o usuário recusa complexidade, ele está certo.** A primeira proposta (38h, 7 camadas) era tecnicamente correta mas operacionalmente errada pra solo founder + time pequeno. A segunda proposta (1 frase + skill recursiva) é tecnicamente menos ortodoxa mas operacionalmente brilhante.

Wagner cunhou a fórmula que vira o coração do sistema. Eu construí a primeira encarnação. Agora ela se aplica a si mesma e cresce orgânicamente.

## ROI desta sessão (autoteste)

- **Substitui?** Sim — a meta-skill substitui o trabalho de Wagner criar SKILL.md do zero.
- **Trabalho humano repetitivo?** Sim — toda nova skill exige mesmo scaffold.
- **ROI mensurável?** Sim — baseline ~30min Wagner-manual; target <2min via `skill:scaffold`. Economia 28min × N skills.
- **Acelera R$ [redacted Tier 0]M / 24m?** Sim — multiplica a velocidade de codificar regras de governança que destravam autonomia.

A meta-skill passa nos 4 testes que ela mesma define. Recursão pura.
