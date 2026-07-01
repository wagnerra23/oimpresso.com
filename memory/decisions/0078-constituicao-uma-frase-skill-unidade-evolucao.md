---
slug: 0078-constituicao-uma-frase-skill-unidade-evolucao
number: 0078
title: "Meta-skill ROI ERP autônomo — skill+missão como unidade operacional (parcialmente superseded por ADR 0079)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: ads
quarter: 2026-Q2
tags: [governance, skills, meta-skill, p0]
supersedes: []
supersedes_partially: []
superseded_by: []
superseded_partially_by:
  - 0079-constituicao-oimpresso-7-camadas-governanca
related:
  - 0040-policy-publicacao-claude-supervisiona
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0070-jira-style-task-management-current-md-removed
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
  - 0076-skills-db-primary-git-destino-drift-alert
pii: false
review_triggers:
  - "Taxa de rejeição de scaffolds >50% por 30 dias → 4 testes da meta-skill duros demais OU equipe propondo skills mal calibradas. Wagner ajusta em v0.2.0."
  - "Anthropic publicar pattern oficial pra meta-skills (governance via skill recursiva)"
  - "Time crescer pra >10 pessoas → talvez SCOPE.md + ActionGate vire necessário"
---

# ADR 0078 — Meta-skill ROI ERP autônomo (parcialmente superseded por ADR 0079)

> ⚠️ **NOTA DE REVISÃO 2026-05-05 (mesma sessão):** Esta ADR foi parcialmente superseded por [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md). A aposta de "constituição = 1 frase + skill recursiva como unidade" foi reavaliada por Wagner como insegura pra um sistema que precisa de compliance LGPD/Portaria/Fiscal + escalabilidade pra time + IAs externas. Constituição em 10 artigos sobre 7 camadas substituiu o framework conceitual.
>
> **O que sobrevive desta ADR:**
> - Meta-skill `meta-skill-roi-erp-autonomo` continua válida e operacional como **L2 OPERATOR ferramenta** (scaffolder de skills).
> - Comando `php artisan skill:scaffold` continua funcionando.
> - A fórmula `Planejar→Executar→Analisar→Organizar` continua válida como template do ciclo de vida de qualquer unit.
>
> **O que NÃO sobrevive:**
> - "Constituição é 1 frase" — agora a Constituição tem 10 artigos formais. A frase desta ADR vira **missão da meta-skill**, não constituição do sistema.
> - "Governança emerge da unidade (skill+missão)" — agora governança desce em 7 camadas formalizadas.
> - "Cada skill é mini-módulo carregando sua governança" — parte verdadeiro (skill carrega rules no contexto), parte insuficiente (precisa também ActionGate, Identity Mesh, SCOPE.md por módulo).

---

# ADR 0078 — Meta-skill ROI ERP autônomo (texto original abaixo)

## Contexto

Sessão 2026-05-05 expôs 3 problemas estruturais convergentes:

1. **Drift descontrolado entre módulos.** 9 controllers em `Modules/Copiloto/` e `Modules/ADS/` que conceitualmente pertenciam a outros módulos (KB, TeamMcp, ProjectMgmt). Causa raiz: novos conceitos entravam via fast-path "ah, é IA, vai pra Copiloto", sem fronteira documentada. Auditoria de 30 módulos revelou nenhum SCOPE.md.

2. **Tasks off-the-record.** Sessão maratona anterior (24 commits, 6 fases UI) ficou 100% em handoff narrativo + git, zero entries em `mcp_tasks`. Violou ADR 0070 silenciosamente. Causa: nenhum hook ou disciplina automatizada.

3. **Governance overengineering em risco.** Inicialmente proposto framework de 7 camadas (Constitution + SRS + Trust Tiers + Identity Mesh + Module Charter + Policy Gating + Audit) com 38h de implementação. Wagner recusou: "uma única aposta. Skill e uma missão. só isso."

Wagner cunhou a fórmula: **Planejar → Executar → Analisar → Organizar → estado da arte**. E pediu que essa formula vire a base de tudo, recursivamente, com Skill como unidade.

Pesquisa convergente (ADR 0072, OpenClaw, Mem0, Letta) mostra que o estado-da-arte 2026 separa governança em camadas, mas Wagner está apostando que **governança emerge da unidade** quando a unidade é bem definida — análogo à abordagem unix "do one thing well" elevada a constituição.

## Decisão

### A constituição do oimpresso é uma frase

> **"Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."**

19 palavras. Filtro construído. Toda decisão arquitetural futura passa por esse teste:

- **Substitui?** Não "ajuda" / "facilita" / "documenta" — substitui de fato.
- **Trabalho humano repetitivo?** Dor real recorrente, não decisão única.
- **Com ROI provado?** Mensurável e medido — não promessa. ROI em tempo, erro ou receita.
- **Rumo ao ERP autônomo R$ [redacted Tier 0]M / 24m?** Conexão direta com a tese — não tangencial, não filosófica.

### Skill é a unidade atômica de evolução

Cada skill é um mini-módulo completo, autônomo, evolutivo, em **um arquivo único** (`.claude/skills/<slug>/SKILL.md`):

- Constituição própria (mission de 1 frase)
- Fronteira própria (`triggers_on` / `does_not_trigger_on`)
- Dono próprio (`owner`)
- Trust level próprio (`trust_level`)
- Versões com rationales (`mcp_skill_versions`)
- Testes (`mcp_skill_test_runs`)
- Auditoria (`mcp_skill_approvals` + `mcp_audit_log`)
- Métricas (`triggered_count`, `helped_outcome_rate`, `false_trigger_rate`)
- ROI declarado (`roi_metric.type` + `baseline` + `target`)

Toda skill segue o ciclo recursivo:

```
Missão (1 frase) → Planejar → Executar → Analisar → Organizar → estado da arte
                                                                      │
                                                                      └── feeds back em Planejar
```

### Meta-skill como filtro de criação

A skill canônica `meta-skill-roi-erp-autonomo` é a constituição em código. Aplica os 4 testes em toda missão proposta antes de criar nova skill. Recusa skills que não passem. **A meta-skill se aplica a si mesma** (recursão pura — ela substitui o trabalho humano de criar skills do zero, com ROI medido em tempo do Wagner).

Comando: `php artisan skill:scaffold "<missão de 1 frase>"`

### O que NÃO virou camada separada

Versão simplificada do framework anterior:

| Conceito originalmente proposto como camada | Decisão |
|---|---|
| Constitution.md (10 artigos) | ❌ vira **1 frase** na meta-skill |
| SRS append-only (centenas de regras) | ⏸️ adiar — emerge naturalmente nas regras dentro de SKILL.md de cada skill |
| Trust Tiers (L0-L4 hierarquia separada) | ⏸️ vira `trust_level:` no frontmatter de cada skill |
| Identity Mesh (mcp_actors table) | ⏸️ vira `owner:` + `trust_level:` em cada skill |
| Module Charter (SCOPE.md per módulo) | ⏸️ adiar — drift atual será resolvido via skills, não SCOPE.md |
| Policy Gating (ActionGate middleware) | ⏸️ adiar — skills auto-load injetam regra no contexto da IA antes de qualquer ação. Gate **por contexto**, não middleware |
| Audit Trail dedicada | ✅ usa `mcp_audit_log` existente |
| ADRs (memory/decisions/) | ✅ continua existindo, mas agora subordinada à constituição-de-1-frase |
| Skills carregados pela IA | ✅ é a unidade. Tudo emerge dela |

### Governança por contexto, não por middleware

Insight central: **a constituição entra no contexto da IA antes da ação, não em middleware após a ação**. Skills auto-load por description match — quando IA vai criar Eloquent Model, a skill `multi-tenant-patterns` é carregada e a IA SABE da regra antes de escrever a primeira linha. Não precisa middleware bloquear depois.

Isso muda governança de "deny after attempt" pra "guidance before attempt". Mais barato, mais rápido, mais auditável (skill carregada fica logada via `triggered_count` e `mcp_audit_log`).

## Justificativa

**Por que 1 frase em vez de 10 artigos.** Wagner cunhou a fórmula recursiva e disse explicitamente "1 única aposta. Skill e uma missão. só isso". Constituição em 1 frase é mais memorável, mais fácil de aplicar como filtro, mais difícil de dilatar com exceções. A frase contém todos os elementos necessários (substituir / humano / repetitivo / ROI / R$ [redacted Tier 0]M / 24m) sem expansão prematura.

**Por que Skill como unidade atômica.** Skills já têm 80% da infraestrutura (Fase 1-4 ontem: editor + test runner + approval queue + publish-to-git). Auto-load por description = governance gratuito. Cada skill é independentemente versionável, testável, auditável, deprecável. Falha de uma não derruba outras. Recursão pura — meta-skill cria skills, e a si mesma.

**Por que governança por contexto, não middleware.** ActionGate middleware adiciona latência, complexidade, e ponto de falha. Skill auto-load injeta regra ANTES da IA agir, então a IA segue a regra naturalmente. Auditoria fica em `mcp_audit_log` quando a skill é carregada — log = evidência de que a regra foi vista. Mais barato e mais semântico.

**Por que adiar SRS, Trust Tiers, Identity Mesh, Module Charter, ActionGate.** Premature optimization. Os problemas que essas camadas resolveriam podem ser resolvidos no escopo da skill (`trust_level:`, `owner:`, regras dentro do body). Se um problema NÃO for resolvido, criamos a camada quando a dor aparecer (review_triggers desta ADR formaliza isso).

**Por que NÃO seguir framework convencional Cedar/OPA/Constitutional AI direto.** Esses são apropriados pra Big Tech (>1k devs). Pra oimpresso (5 pessoas + IAs) o overhead de constitution + trust tiers + identity mesh + middleware é desproporcional ao valor. Skill+contexto entrega 80% do valor a 5% do custo.

**Reabrir esta decisão se:** review triggers no frontmatter ou (a) taxa de rejeição de scaffolds >50% por 30 dias indicando 4 testes mal calibrados, (b) violação real de tenant isolation que skill+contexto não preveniu, ou (c) Anthropic publicar pattern oficial pra meta-skills recursivas que evolua a abordagem.

## Consequências

**Positivas:**

- **Redução brutal de complexidade.** 38h → 4h (12% do orçamento). Constituição cabe em 1 frase. Quase toda infra já existe.
- **Recursão pura.** Meta-skill substitui o trabalho humano de criar skills, é avaliada pelos próprios critérios. Auto-validação.
- **Governança por contexto.** Mais semântico que middleware. IA aprende ANTES de agir.
- **Filtro forte automaticamente.** Os 4 testes rejeitam ruído (skills documentais, augmentativas, vagas) sem revisão humana caso a caso.
- **Cada skill é mini-módulo independente.** Testável, versionável, auditável isoladamente. Sem big-bang refactor.
- **Convergência com estado-da-arte.** OpenClaw 8 princípios + Anthropic Memory Tool patterns alinhados.

**Negativas / Trade-offs:**

- **Aposta arquitetural concentrada.** Se skill+contexto NÃO der conta de governança em produção (ex: IA externa hostil ignorar skill carregada), precisamos voltar pra middleware. Risco real, mas reversível.
- **Métricas (Analisar) ainda não automatizadas.** `triggered_count`, `helped_outcome_rate` precisam de telemetria. Identificado como gap (próxima fase).
- **Drift de módulos não resolvido por esta ADR.** 9 controllers ainda em módulo errado. Mas resolução via skills (skill `module-charter-enforcer` que verifica fronteira em pre-commit) é mais simples que SCOPE.md em 30 módulos.
- **Centraliza poder na meta-skill.** Se ela tiver bug de validação, todas as skills filhas saem mal. Mitigação: meta-skill é versionada como qualquer outra; rollback via supersede.

**Riscos mitigados:**

- **Auto-mem privada não cresce mais** (ADR 0061 mantido). Skills são canônicos.
- **Não vira rewrite**. Cada nova skill é incremento, não refactor.
- **Não compromete `business_id` scope.** Skill `multi-tenant-patterns` continua ativa.
- **PolicyEngine ADS não é tocado.** Skills e PolicyEngine resolvem problemas diferentes (skills = guidance pré-ação; PolicyEngine = decision flow autônomo Brain A/B).

## Implementação

**Fase 1 (FEITO nesta sessão):**

- ✅ `.claude/skills/meta-skill-roi-erp-autonomo/SKILL.md` — meta-skill como constituição em código
- ✅ `Modules/ADS/Services/ScaffoldSkillFromMissionService.php` — service que aplica os 4 testes + gera scaffold
- ✅ `Modules/ADS/Console/Commands/SkillScaffoldCommand.php` — comando `php artisan skill:scaffold "<missão>"`
- ✅ Registro no `AdsServiceProvider`
- ✅ Esta ADR

**Fase 2 (próximas sessões):**

- Importar meta-skill pro DB via `php artisan mcp:skills:import-from-git`
- Trigger logging: cada skill carregada incrementa `triggered_count` em `mcp_audit_log` por slug
- Dashboard `/ads/admin/skills` enriquecido com métricas reais (Analisar)
- Primeira skill filha criada via `skill:scaffold` (validação ponta-a-ponta do ciclo)

**Fase 3 (quando dor aparecer):**

- Drift de controllers entre módulos: criar skill `module-charter-enforcer` (em vez de SCOPE.md em 30 módulos)
- Tasks off-the-record: criar skill `task-id-required-in-commit` (pre-commit hook)
- Governança UI consolidada (se acumular >5 pendências/dia)

## Referências

- [ADR 0040 — Policy Publicação](0040-policy-publicacao-claude-supervisiona.md) — pattern de Wagner delega supervisão
- [ADR 0061 — Conhecimento canônico git/MCP zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0070 — Jira-style task management](0070-jira-style-task-management-current-md-removed.md) — tasks em DB, não markdown
- [ADR 0072 — Maturação memória + Team MCP — OpenClaw SOA 2026](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) — 8 princípios convergentes
- [ADR 0076 — Skills V2: DB primary, git destino, drift por-skill](0076-skills-db-primary-git-destino-drift-alert.md) — infraestrutura skills
- Sessão: `memory/sessions/2026-05-05-noite-meta-skill-constituicao-1-frase.md` (a criar)
