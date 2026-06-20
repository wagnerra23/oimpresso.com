---
name: feedback-dashboard
description: ATIVAR quando Wagner pedir "/feedback-dashboard", "mostra feedback", "como está o feedback", "que feedback tem aberto", "feedback do <cliente>", "feedback da <persona>", "feedback do mês", OU quando Wagner abrir sessão pra review de backlog feedback. Gera relatório agregado do estado atual: pendentes por persona/cliente/severity/módulo + RICE ranking top 10 + patterns emergentes (3+ clientes reportaram similar) + métricas SLA + comparação período. Lê memory/clientes/*/feedback/*.md append-only e calcula. Skill Tier B. Refs ADR UI-0016.
tier: B
---

# feedback-dashboard — Estado atual do feedback canon

Quando ativar:
- `/feedback-dashboard` no input
- `/feedback-dashboard --month` (filtro período)
- `/feedback-dashboard --persona <slug>` (filtro persona)
- `/feedback-dashboard --cliente <slug>` (filtro cliente)
- "como está o feedback"
- "mostra feedback aberto"
- "review backlog feedback"

## Workflow

### 1. Carrega todos feedbacks

Lê `memory/clientes/*/feedback/*.md` (multi-cliente) com frontmatter YAML.

### 2. Aplica filtros (se passados)

- `--month` = filtra últimos 30 dias
- `--persona <slug>` = filtra por persona_slug
- `--cliente <slug>` = filtra por cliente_real
- `--status <novo|triaged|backlog|in_progress|resolved>` = filtra status
- `--severity <0-4>` = filtra severity ≥ N

Default sem filtro = todos abertos (status != closed).

### 3. Agrega métricas

**Top-line:**
- Total feedback aberto
- Δ vs período anterior (cresceu/diminuiu)
- SLA médio resolvido (dias entre data → resolucao.data_resolvido)
- Taxa de cliente_confirmou: true (% de feedbacks resolvidos cliente reconfirmou)

**Por persona** (count + severity média):
```
Kamila    14 abertos  · sev média 2.8  · 2 sev=4
Daniela    8 abertos  · sev média 2.5  · 1 sev=4
Larissa    5 abertos  · sev média 1.8  · 0 sev=4
Jair       2 abertos  · sev média 2.0  · 0 sev=4
```

**Por módulo** (heat map de onde está o ruído):
```
financeiro       11 (Kamila 9, Daniela 2)
oficinaauto       7 (Daniela 5, Jair 2)
sells             4 (Larissa 4)
cliente           3 (Daniela 2, Kamila 1)
```

**Por canal** (de onde vem feedback):
```
whatsapp     18 (62%)
call          7 (24%)
presencial    3 (10%)
email         1 (3%)
```

### 4. RICE ranking — top 10

Calcula `RICE = (Reach × Impact × Confidence) ÷ Effort` pra cada feedback:

- **Reach** = clientes afetados / total clientes
- **Impact** = severity_nng (0-4)
- **Confidence** = 1.0 se feedback real | 0.5 se inferido | 0.3 se hipótese
- **Effort** = estimado por skill (1=horas, 2=dia, 3=semana, 5=mês)

Top 10 por RICE:

```
1. RICE 12.5  Kamila NFe-erro-IE-vazia                 sev 4 · cliente: martinho-cacambas
2. RICE 10.2  Daniela osfechar-4tabs-fotos             sev 3 · pattern (Larissa similar)
3. RICE  9.8  ...
```

### 5. Patterns emergentes

Detecta `pattern_emergente: true` + agrupa por job_por_tras similar:

```
🔥 PATTERN: "saldo cliente sem drill-down" — 3 clientes
   - Kamila @ Martinho (financeiro/contas-receber)
   - Larissa @ Rota Livre (cliente/show — fiado balcão)
   - <prospect> (call discovery)
   Sugere: priorizar feature drill-down saldo
```

### 6. Output canônico markdown

```markdown
# Feedback Dashboard · YYYY-MM-DD

## Top-line
- 29 abertos (+5 vs semana passada)
- SLA médio: 4.2 dias
- Confirmação cliente pós-fix: 87%

## Por persona
[tabela acima]

## Por módulo
[tabela acima]

## RICE top 10
[ranking]

## Patterns emergentes (3+ clientes)
[lista]

## Recomendação Wagner (próxima ação)
- Atacar #1 RICE (Kamila NFe-IE-vazia sev 4)
- Agrupar drill-down saldo (3 personas pedindo)
- Revisar feedback Larissa estagnado (>14 dias sem fix)
```

## Princípios

- **Read-only** — dashboard NÃO modifica feedback file (append-only)
- **Cálculo em tempo da chamada** — sempre lê estado atual (não cacheia)
- **RICE estimativa** — Effort calculado por skill (estimativa); ajusta com aprendizado
- **Pattern detection** — agrupar por job_por_tras (semantic match, não literal)
- **LGPD** — não exibir literal sensível em logs públicos

## Relacionadas

- `feedback-capture` — captura inbound que alimenta este dashboard
- MCP tasks (severity ≥ 3 cria task) — linka via `task_mcp_id`
- `design-deep-analysis` — quando Wagner ataca top RICE, abre design-deep com fricoes carregadas
