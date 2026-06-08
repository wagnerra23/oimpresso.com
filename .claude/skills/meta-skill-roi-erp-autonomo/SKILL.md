---
name: meta-skill-roi-erp-autonomo
mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ 10M em 24 meses."
description: ATIVAR ao criar skill nova, usar `skill:scaffold`, discutir se uma ideia merece virar skill, ou perguntar "isso vira skill?". Aplica 4 testes de validade — substitui? humano repetitivo? ROI mensurável? acelera ERP autônomo R$ 10M / 24m? Recusa missões que não passem. Carrega o ciclo Planejar→Executar→Analisar→Organizar como template do SKILL.md gerado.
type: meta-skill
status: active
version: 0.1.0
trust_level: L1
owner: wagner
created_at: 2026-05-05
generated_from: wagner_explicit_mission
charter_adr: 0078
parent_mission: null
triggers_on:
  - "skill:scaffold"
  - "criar skill"
  - "nova skill"
  - "scaffold skill"
  - "isso vira skill?"
  - "essa ideia merece virar skill?"
  - "como crio uma skill?"
does_not_trigger_on:
  - editar SKILL.md existente (use editor /ads/admin/skills/<slug>/edit)
  - ler skills no repositório
  - revisar skill já criada (use /ads/admin/skills-review)
roi_metric:
  type: time
  baseline: "Wagner cria skill manual em ~30min (escrever frontmatter + body + registrar DB)"
  target: "skill:scaffold reduz pra <2min — 1 frase + edit body"
metrics:
  scaffolds_created: 0
  scaffolds_rejected: 0
  rejection_reasons: []
tier: C
parent_adr: 0095
---

# meta-skill-roi-erp-autonomo

## Missão (constituição do sistema)

> **Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ 10M em 24 meses.**

19 palavras. Filtro construído. Cada palavra carrega peso:

| Palavra | Função |
|---|---|
| **Toda skill** | universal — sem exceção |
| **substitui** | não "ajuda" / "facilita" / "documenta" — substitui de fato |
| **trabalho humano repetitivo** | dor real recorrente, não decisão única |
| **com ROI provado** | mensurável e medido — não promessa |
| **rumo ao** | direção, não obrigação imediata |
| **ERP autônomo** | tese: sistema que se auto-executa dentro de policies |
| **R$ 10M em 24 meses** | âncora econômica e temporal |

## Os 4 testes que toda nova skill precisa passar

Antes de criar uma skill, valide a missão proposta contra:

1. **Substitui?** A skill substitui (ou previne) trabalho humano. Não apenas ajuda/lembra/documenta.
2. **Humano repetitivo?** O trabalho substituído é recorrente — não decisão única ou caso isolado.
3. **ROI mensurável?** Declara qual ROI: economia de **tempo**, redução de **erro**, ou aumento de **receita**. Com baseline + target.
4. **Acelera ERP autônomo R$ 10M / 24m?** Conexão direta com a tese — não tangencial, não filosófica.

Se a missão proposta não passa nos 4 testes → **NÃO crie a skill**. Reformule a missão ou descarte a ideia. A meta-skill se recusa a propagar skills que diluam a tese.

## O ciclo evolutivo (4 fases) — template de toda skill criada

```
                  [Missão — 1 frase]
                         │
                         ▼
           ┌──────────────────────────────┐
           │  1. Planejar                 │  → SKILL.md preenchido
           │     scope, regras, triggers  │     (status=draft → authored)
           └──────────────┬───────────────┘
                          │
                          ▼
           ┌──────────────────────────────┐
           │  2. Executar                 │  → IA carrega skill, age,
           │     uso real em prod         │     incrementa triggered_count
           └──────────────┬───────────────┘
                          │
                          ▼
           ┌──────────────────────────────┐
           │  3. Analisar                 │  → métricas, test runs,
           │     ROI medido, drift        │     feedback humano
           └──────────────┬───────────────┘
                          │
                          ▼
           ┌──────────────────────────────┐
           │  4. Organizar                │  → nova versão semver,
           │     consolida aprendizado    │     4 rationales obrigatórios
           └──────────────┬───────────────┘
                          │
                          ▼
                  [Estado da arte]
                          │
                          └──── feeds back em (1) Planejar ────┐
                                                                │
                                                                ▼
                                                        (próxima iteração)
```

## Anatomia de uma skill como mini-módulo

Cada skill criada segue este formato — fronteira completa em 1 arquivo:

```yaml
---
name: <slug>
mission: "<1 frase derivada da meta-mission>"
description: <quando aciona, o que faz>
type: skill
status: draft|authored|tested|approved|active|deprecated
version: 0.1.0
trust_level: L1|L2|L3
owner: <usuário>
created_at: <data>
generated_from: skill:scaffold
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: <NNNN se houver>
triggers_on:
  - <padrão 1>
  - <padrão 2>
does_not_trigger_on:
  - <exclusão 1>
roi_metric:
  type: time | error | revenue
  baseline: "<estado atual mensurável>"
  target: "<melhoria esperada mensurável>"
metrics:
  triggered_count: 0
  helped_outcome_rate: 0.0
  false_trigger_rate: 0.0
---
```

## Comando

```bash
php artisan skill:scaffold "<missão de 1 frase>"
```

Exemplo:
```bash
php artisan skill:scaffold "Toda IA tocando código com business_id leu SRS antes de escrever"
```

Output:
- Validação dos 4 testes
- Se passa: cria `.claude/skills/<slug>/SKILL.md` + entry em `mcp_skills` status=draft
- Se falha: retorna motivo, sugere reformulação, não cria

## Anti-patterns (skills que a meta-skill REJEITA)

| Missão proposta | Por que rejeita |
|---|---|
| "Documenta o processo X" | só descreve, não substitui |
| "Lembra de fazer Y antes de Z" | checklist mental, não automação |
| "Padroniza estilo Tailwind" | sem ROI mensurável |
| "Skill útil pra IA entender contexto" | vago, sem trabalho humano alvo |
| "Anota preferências do Wagner" | é auto-mem, não skill |
| "Explica como funciona o módulo X" | é runbook/ADR, não skill |
| "Sugere melhorias no código" | augmentativa, não substitutiva |

## Exemplos válidos (skills que satisfazem os 4 testes)

| Missão proposta | Substitui? | Humano repetitivo? | ROI? | R$ 10M/24m? |
|---|---|---|---|---|
| "Toda IA tocando business_id leu SRS antes" | ✅ valida no lugar do Wagner | ✅ acontece toda task multi-tenant | ✅ erro evitado, redução de incidentes | ✅ multi-tenant é foundation do ERP |
| "Toda criação de tarefa em Modules/* gera entry em mcp_tasks" | ✅ substitui o "depois eu cadastro" | ✅ acontece em toda sessão de dev | ✅ tempo de governança | ✅ governança é foundation autonomia |
| "Toda mudança em produção passa por aprovação Wagner" | ✅ substitui revisão manual ad-hoc | ✅ todo deploy | ✅ erro evitado, audit completo | ✅ ERP autônomo precisa governança forte |

## Quando a meta-skill NÃO ativa

- Edição de SKILL.md já existente (use editor UI `/ads/admin/skills/<slug>/edit`)
- Leitura de skill no repositório (apenas ler é grátis, sem ciclo)
- Revisão de skill já criada (use `/ads/admin/skills-review`)

## Histórico de versões

- **v0.1.0** (2026-05-05) — DRAFT inicial. Constituição em 1 frase. 4 testes definidos. Ciclo de 4 fases. Comando `skill:scaffold` projetado. ADR 0078 documentando a aposta.

## Como esta skill se evolui (recursão)

A meta-skill se aplica a si mesma:

- **Planejar:** este SKILL.md (você está lendo)
- **Executar:** quando alguém roda `skill:scaffold` ou pergunta "isso vira skill?"
- **Analisar:** `metrics.scaffolds_created` / `scaffolds_rejected` — quantas skills criou? quantas rejeitou e por quê?
- **Organizar:** se taxa de rejeição estiver muito alta (>50%), sinal de que os 4 testes estão duros demais OU a equipe está propondo skills mal calibradas. Wagner ajusta os 4 testes em nova versão (0.2.0) com 4 rationales obrigatórios.

Recursão pura. A meta-skill se governa pelas próprias regras.
