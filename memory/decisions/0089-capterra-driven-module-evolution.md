---
slug: 0089-capterra-driven-module-evolution
number: 89
title: "Capterra-driven Module Evolution (skill + 3 artefatos)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_at: "2026-05-06"
decided_by: [W]
module: governance
quarter: 2026-Q2
---

# 0089 — Capterra-driven Module Evolution (skill + 3 artefatos)

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Contexto**: governança de evolução de módulos no oimpresso ERP

## Contexto

Evolução de módulo no oimpresso é hoje **reativa**:
- Wagner pensa "falta X" → cria US-XXX-NNN no SPEC.md → Felipe/Maíra implementam
- Cruzamento com mercado é manual e esporádico (em sessões `/comparativos`)
- Não há "inventário canônico" do que **temos** vs **mercado tem** num módulo
- Resultado: gaps são descobertos por reclamação de cliente, não por benchmark sistemático

Mercado não resolve o problema completo:
- Roadmap tools (Productboard/Aha!) — manual, sem agente
- Competitive intel (Klue/Crayon) — caro, não vincula backlog
- Agent-driven dev (Cursor/Claude Code) — código, não roadmap

**Ponto cego do mercado:** ninguém combina análise SOA automatizada por agente + inventário interno × externo + backlog priorizado com aprovação humana.

## Decisão

Toda evolução de módulo no oimpresso passa a seguir o padrão **Capterra-driven Module Evolution** — 3 artefatos versionados em git + 1 skill + (futuramente) 1 tela admin.

### Os 3 artefatos canônicos por módulo

```
memory/requisitos/{Modulo}/
├── SPEC.md                       ← O QUE QUEREMOS (US-XXX-NNN, parser → mcp_tasks)
├── CAPTERRA-FICHA.md             ← BENCHMARK (concorrentes-alvo + capacidades baseline + score P0–P3)
├── CAPTERRA-INVENTARIO.md        ← DIAGNÓSTICO (gerado pela skill: ✅🟡❌ + evidência)
└── adr/                          ← DECISÕES tomadas (Nygard)
```

**Fonte de cada um:**

| Artefato | Quem mantém | Frequência |
|---|---|---|
| SPEC.md | Wagner + skill (apenda US aprovadas) | a cada sprint |
| CAPTERRA-FICHA.md | Wagner (curadoria), eventualmente atualizada após pesquisa | trimestral ou quando concorrente muda |
| CAPTERRA-INVENTARIO.md | **Skill regenera**, Wagner não edita à mão | a cada execução da skill |
| adr/ | Quem tomar decisão | per-decisão |

### A skill (ciclo de execução)

`.claude/skills/comparativo-do-modulo` — única skill genérica para todos os módulos. Quando invocada:

1. **Lê** `memory/requisitos/{Modulo}/CAPTERRA-FICHA.md` (concorrentes + capacidades baseline + score)
2. **Lê** `memory/requisitos/{Modulo}/SPEC.md` (US-XXX-NNN ativos)
3. **Lê** `Modules/{Modulo}/` (código atual — Models/Services/Controllers/Migrations)
4. **Cruza:** classifica cada capacidade da FICHA em 3 buckets:
   - ✅ APROVADO — código + teste + UI cobrem a capacidade
   - 🟡 PARCIAL — código existe mas falta UI, teste, ou edge-case
   - ❌ AUSENTE — não existe
5. **Escreve** `CAPTERRA-INVENTARIO.md` (sobrescreve, git diff é o histórico)
6. **Propõe batch** de tasks para gaps ❌ + 🟡, priorizadas pelo score da FICHA
7. **Pergunta a Wagner** quais aprovar (CLI hoje, tela amanhã)
8. **Cria tasks no MCP** via `tasks-create` para os aprovados — `module:{X} priority:P{N} title:"{capacidade}"`

### Score canônico (declarado na FICHA, não na skill)

| Score | Significado | Critério típico |
|---|---|---|
| **P0** | Bloqueador de venda OU exigido por lei | LGPD, fiscal, multi-tenant security, auth |
| **P1** | ≥80% concorrentes têm; cliente pede explicitamente | Boleto registrado, DRE básica, integração WhatsApp |
| **P2** | ≥50% mercado tem; oimpresso evolui sem por agora | PIX recorrente Open Finance, dunning automático |
| **P3** | Diferenciação opcional / nicho | Split payment, marketplace, gateway internacional |

### Fluxo end-to-end (Fase 1: CLI; Fase 2: UI)

**Fase 1 — atual (CLI no Claude Code):**
```
Wagner: /comparativo RecurringBilling
  → Skill executa 1-7
  → Mostra batch proposto em tabela
  → Wagner: "aprovar 1, 3, 5"
  → Skill: tasks-create P{score} module:{X} ... ×3
  → Skill: apenda US-RB-XXX no SPEC.md
  → Skill: git commit + push (webhook MCP propaga)
```

**Fase 2 — futuro (tela `/admin/srs/{Modulo}`):**
```
Wagner abre tela do módulo
  → Tab "Inventário" mostra CAPTERRA-INVENTARIO.md renderizado
  → Cada gap ❌/🟡 tem botão [Aprovar → US-XXX-NNN]
  → Click → POST /admin/srs/{X}/gap/aprovar
  → Backend chama mesma rotina (tasks-create + apenda SPEC + commit)
  → Notifica time via mcp_audit_log
```

### Por que essa arquitetura é ótima

**Skill genérica, contexto sob demanda via FICHA:**
- 1 skill no harness (não polui registry)
- Domínio específico de cada módulo (concorrentes, capacidades) vive na FICHA, perto do SPEC
- Adicionar módulo novo = criar FICHA, sem tocar `.claude/skills/`
- Atualizar concorrentes = `git commit FICHA.md`, sem redeploy

**Trio SPEC + FICHA + INVENTARIO é simétrico e compõe:**
- SPEC = norte de produto (interno)
- FICHA = norte de mercado (externo)
- INVENTARIO = delta entre os dois (auditoria)

**Histórico vem grátis do git:**
- `git log -p memory/requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` mostra evolução
- Webhook git→MCP sincroniza pra `mcp_memory_documents` automaticamente
- `decisions-search` consegue achar gaps históricos

**Aprovação humana sempre no fluxo:**
- Skill nunca cria task sem confirmação (publication-policy)
- Wagner mantém controle estratégico do roadmap

## Consequências

### Positivas

- Padrão único pra todo módulo — Felipe/Maíra/Luiz/Eliana podem rodar a skill sem aprender N processos
- Backlog do MCP fica naturalmente classificado por `module` + `priority` (P0–P3) sem esforço manual
- Diferencial competitivo do oimpresso ERP: SOA-driven roadmap engine — combinação que mercado não tem
- Reduz ciclo "cliente reclama → Wagner descobre gap" pra "Wagner audita módulo trimestralmente → gap previsto"

### Negativas / custos

- Toda módulo precisa ter FICHA criada antes da 1ª execução da skill (~10–30min de curadoria por módulo)
- Score na FICHA exige Wagner pensar "isso é P0 ou P2?" — boa fricção, mas fricção
- Manter FICHAs atualizadas exige disciplina trimestral (esquecer = inventário fica obsoleto)

### Mitigações

- Template `memory/requisitos/_TEMPLATE_capterra_ficha.md` reduz custo da criação inicial
- Tela Fase 2 vai colocar FICHA no fluxo natural — atualização ad-hoc no admin, não em editor
- Skill `cycles-close` futura pode lembrar Wagner de re-rodar `/comparativo` em cada módulo do cycle

## Estado da arte considerado e descartado

| Alternativa | Por que não |
|---|---|
| 1 skill por módulo (Opção B) | N skills polui registry; cada filha precisa frontmatter completo; manutenção dobrada |
| 1 skill monolítica com tudo no body (Opção A) | ~50KB de domínio carregado sempre; atualizar concorrente = redeploy de skill |
| Productboard/Aha! como SaaS | Sem agente, sem análise SOA; integração com mcp_tasks teria que ser custom; custo R$$$ |
| Klue/Crayon como competitive intel | Foco vendas, não produto; sem geração de tasks; caro pra empresa pequena |

## Implementação (PR/sessão 2026-05-06)

- ADR este documento (governance)
- `.claude/skills/comparativo-do-modulo/SKILL.md`
- `.claude/commands/comparativo.md` (atalho `/comparativo <modulo>`)
- `memory/requisitos/_TEMPLATE_capterra_ficha.md` (template canônico)
- `memory/requisitos/RecurringBilling/CAPTERRA-FICHA.md` (1ª ficha real, prova de conceito)

Tela `/admin/srs/{Modulo}` (Fase 2) entra como US separada — tracked em backlog.

## Validação

- [ ] Wagner roda `/comparativo RecurringBilling` e o output é fiel à realidade (gaps detectados batem com SPEC + código)
- [ ] Score P0–P3 da FICHA produz priorização que faz sentido
- [ ] Tasks geradas no MCP aparecem em `tasks-list module:RecurringBilling` corretamente
- [ ] Aplicar pattern em 2º módulo (Financeiro ou NfeBrasil) sem edição da skill — só nova FICHA
- [ ] Após 1 cycle usando, retro mostra: o pattern reduziu tempo de roadmap planning?

## Referências

- [skill `meta-skill-roi-erp-autonomo`](../../.claude/skills/meta-skill-roi-erp-autonomo/SKILL.md) — 4 testes que toda skill precisa passar (substitui? humano repetitivo? ROI? ERP autônomo?)
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — tasks vivem em `mcp_tasks`, parser SPEC.md → DB
- [ADR 0028](0028-comparativos-capterra-format.md) (se existir, senão template em `memory/comparativos/_TEMPLATE_capterra_oimpresso.md`)
- Sessão 2026-05-06 — discussão Wagner ↔ Opus sobre arquitetura
