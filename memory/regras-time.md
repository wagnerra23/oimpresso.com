# Time interno (5 pessoas)

| Pessoa | Sigla | Papel | WIP máx |
|---|---|---|---|
| **Wagner** | [W] | líder, dono, aprovação final | 2 |
| **Maiara** | [M] | suporte+dev | 2 |
| **Felipe** | [F] | dev+suporte | 2 |
| **Luiz** | [L] | iniciante+dev IA-pair | 1 |
| **Eliana** (esposa) | [E] | **advogada + financeiro + dev IA** | 1 |

> ⚠️ **2 Elianas no projeto:** `Eliana[E]` (esposa, time interno) ≠ `Eliana(WR2)` (cliente externa, PontoWr2). Sempre desambiguar em commits/notas.

> 🎯 **Eliana é advogada + financeiro** (descoberto 2026-05-09). **Decisão Wagner 2026-05-09**: Eliana **NÃO assume DPO formal por enquanto** — vai estudar LGPD com calma primeiro. Sem pressão. Quando/se decidir, retomar. Counsel LGPD externo segue necessário pra Pilares 1-4 do oimpresso Insights (escopo menor — Pilar 5 DaaS externo descartado).

## Regras duras

- **L não mergeia PR sozinho** (F ou W aprova)
- **E não mexe em Jana sprints LGPD**
- **M não faz deploy produção sozinha**
- **W deve evitar virar bottleneck** — delegar code review pra F quando puder
- **PIIs reais (CPF/CNPJ cliente) NUNCA em PR ou commit.** Logs com `[REDACTED]`. Skill `commit-discipline` (Tier A) + `PiiRedactor` enforce automático

## Matriz "quem pode pegar qual tipo de task"

Detalhada em [`TEAM.md`](../TEAM.md) raiz do projeto. 4 níveis:
- ✅ owner (responsável principal)
- 🟢 pode pegar
- 🟡 com supervisão
- ❌ não-pegar

## Convenção em commits

`[W]`, `[M]`, `[F]`, `[L]`, `[E]`, `[L+C]` (Luiz pareado Claude), etc.

Exemplos:
```
feat(jana): PII redactor BR [F]
fix(repair): listagem dual-mode flag [L+C]
docs(adr): aceitar 0094 [W]
```

## Ciclo de trabalho (cycle 2 semanas)

- Cycle como entidade `mcp_cycles` ([ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md))
- Criado via `cycles-create` com goal outcome-oriented
- Goals trackados via `cycle-goals-track`
- WIP+backlog via `tasks-list cycle:current`
- **Daily async 09h:** cada um atualiza status das próprias US via tool MCP `tasks-update`
- **Sex final cycle:** `cycles-close --rollover` move incompletas pro próximo + retro em `mcp_cycles.retro` JSON

## Cliente externo: ROTA LIVRE

`business_id=4`, **Larissa** dona/operadora. Histórico de quirks documentado em auto-memória do agente:
- Monitor 1280px (designs precisam caber)
- Customizações: `format_date` shift +3h ([ADR 0066](decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
- 99% do volume de vendas

## Contato externo PontoWr2

**WR2 Sistemas** — Eliana(WR2) — eliana@wr2.com.br

## Local repos

- Repositório principal: `D:\oimpresso.com`
- Worktrees Claude Code: `.claude/worktrees/<nome>` (gitignored)
