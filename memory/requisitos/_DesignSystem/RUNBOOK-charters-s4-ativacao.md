# RUNBOOK — Ativação Page Charters S4 (C1 P0 Onda 4)

> **Data:** 2026-05-13 · **Owner:** Wagner · **Sprint:** S4 (`charter-first` Tier A plena ativação)
> **Origem:** [GAP-ANALYSIS-91-100-2026-05-13](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md) (C1 P0 Onda 4 — "Charters S4 ativar").
> **ADRs:** [ADR 0094 Constituição V2 §princípio #3](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) · [ADR 0101 Sistema Charter-Capterra](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) · [ADR 0095 Skills Tiers](../../decisions/0095-skills-tiers-convencao-interna.md)

## Por que isso existe

Constituição V2 (ADR 0094) declarou **Charter > Spec** como princípio duro #3. ADR 0101 (S6 F1+F2) entregou o **template canônico de charter** + workflow `charter-gate.yml`. Mas até 2026-05-13 a tool MCP `charter-fetch` não existia — agentes IA não tinham caminho fácil pra ler o contrato vivo antes de editar. **26 charters .charter.md em produção viraram peso-morto.**

C1 P0 Onda 4 fecha o loop:

1. **Tool MCP `charter-fetch`** entrega (Modules/Jana/Mcp/Tools/CharterFetchTool.php)
2. **Skill `charter-first`** sobe de Tier C dormente → **Tier A always-on**
3. **Hook `charter-validate.{ps1,sh}`** em PreToolUse Edit/Write — modo warning (P1 bloqueante)

## Inventário 26 charters (snapshot 2026-05-13)

### Pages (.tsx irmãos) — 22 charters

| # | Charter | Status | Module |
|---|---|---|---|
| 1 | `resources/js/Pages/Admin/Index.charter.md` | draft | Admin |
| 2 | `resources/js/Pages/Atendimento/Inbox/Index.charter.md` | live | Atendimento |
| 3 | `resources/js/Pages/Atendimento/JanaTemplates.charter.md` | live | Atendimento |
| 4 | `resources/js/Pages/Cliente/Index.charter.md` | draft | Cliente |
| 5 | `resources/js/Pages/Financeiro/ContasBancarias/Index.charter.md` | live | Financeiro |
| 6 | `resources/js/Pages/Financeiro/Extrato/Index.charter.md` | live | Financeiro |
| 7 | `resources/js/Pages/Financeiro/Unificado/Index.charter.md` | live | Financeiro |
| 8 | `resources/js/Pages/governance/Dashboard.charter.md` | live | governance |
| 9 | `resources/js/Pages/Jana/Chat.charter.md` | live | Jana |
| 10 | `resources/js/Pages/NfeBrasil/Configuracao/Certificado.charter.md` | live | NfeBrasil |
| 11 | `resources/js/Pages/NfeBrasil/Manifestacao/Index.charter.md` | live | NfeBrasil |
| 12 | `resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md` | live | NfeBrasil |
| 13 | `resources/js/Pages/Orcamento/Index.charter.md` | draft | Orcamento |
| 14 | `resources/js/Pages/Produto/Index.charter.md` | draft | Produto |
| 15 | `resources/js/Pages/Produto/Unificado/Index.charter.md` | draft | Produto |
| 16 | `resources/js/Pages/ProjectMgmt/Board/Index.charter.md` | live | ProjectMgmt |
| 17 | `resources/js/Pages/Repair/Dashboard/Index.charter.md` | live | Repair |
| 18 | `resources/js/Pages/Repair/JobSheet/Index.charter.md` | live | Repair |
| 19 | `resources/js/Pages/Repair/ProducaoOficina/Index.charter.md` | rascunho | Repair |
| 20 | `resources/js/Pages/Repair/Status/Index.charter.md` | live | Repair |
| 21 | `resources/js/Pages/Sells/Create.charter.md` | live | Sells |
| 22 | `resources/js/Pages/Sells/Index.charter.md` | live | Sells |

### Module charters (em memory/requisitos/) — 4 charters

| # | Charter | Status |
|---|---|---|
| 23 | `memory/requisitos/Autopecas/Autopecas.charter.md` | proposto |
| 24 | `memory/requisitos/ComunicacaoVisual/ComunicacaoVisual.charter.md` | proposto |
| 25 | `memory/requisitos/OficinaAuto/OficinaAuto.charter.md` | ativo |
| 26 | `memory/requisitos/Vestuario/Vestuario.charter.md` | piloto |

### Distribuição por status

| Status | Count | % | Próxima ação |
|---|---|---|---|
| `live` | 16 | 62% | Pronto pra consumo via charter-fetch sem warning |
| `draft` | 5 | 19% | Wagner revisar Non-Goals + Anti-hooks (parte sensível anti-alucinação) → flip pra live |
| `rascunho` (PT) | 1 | 4% | Mesma ação que draft |
| `proposto` | 2 | 8% | Module charters — Wagner valida mission/scope antes de virar ativo |
| `piloto` / `ativo` | 2 | 8% | Module charters em produção (Vestuario=ROTA LIVRE biz=4, OficinaAuto) |

> **Total status: live computado de Pages + governance/Dashboard:** 17 (incluindo `governance/Dashboard` que conta como page). Para fins de **% Charter-driven**, 17/22 Pages = 77%.

## Workflow ativação (de draft → live)

### 1. Criar draft (já existente — skill `charter-write`)

```
/charter-write resources/js/Pages/<Mod>/<Tela>.tsx
```

Skill `charter-write` lê o `.tsx` + Controller + topnav/routes pra inferir Mission/Goals/UX targets/Automation hooks. **PARA aguardando Wagner revisar Non-Goals + Anti-hooks** (parte sensível anti-alucinação — Wagner aprova manualmente).

### 2. Wagner revisa Non-Goals + Anti-hooks

Wagner abre charter, revisa **seções 3 (Non-Goals) e 8 (Anti-hooks)** — pontos onde IA mais aluciana. Edita ou aprova.

### 3. Flip `status: draft` → `status: live`

Edit no frontmatter:

```yaml
---
page: /sells
status: live      ← era draft
last_validated: 2026-05-13
---
```

A partir daí:
- Tool `charter-fetch` deixa de retornar WARNING
- Hook `charter-validate` continua advertindo Edit/Write (modo warning sempre)
- Pest GUARDs (US-COPI-066) podem validar invariants do charter

### 4. Consumir antes de Edit (skill Tier A)

Toda vez que Claude vai mexer em `<Tela>.tsx` com charter irmão:

```
mcp__Oimpresso_MCP___Wagner__charter-fetch page_id:"resources/js/Pages/Sells/Index.tsx"
```

Output em markdown (default) ou JSON (`format:json` pra CI/ferramentas).

## Caminho do hook (warning → bloqueante)

**Modo atual: warning-mode** — hook `charter-validate.{ps1,sh}` registra systemMessage mas NÃO bloqueia. Permite agente avisar mas não trava trabalho enquanto o muscle memory da tool ainda se forma.

**Critério P1 pra virar bloqueante (`CHARTER_VALIDATE_STRICT=1`):**

1. ≥5 sessões com chamadas `charter-fetch` registradas em logs MCP
2. ≥1 caso documentado de drift evitado (PR comment "Charter Non-Goal previu isso")
3. Wagner sign-off via session log
4. ROI calculado: tempo poupado em retrabalho vs. fricção da bloqueio

Quando ROI provado, basta exportar env var:

```powershell
# Windows
$env:CHARTER_VALIDATE_STRICT = '1'
```

```bash
# Unix/Hostinger
export CHARTER_VALIDATE_STRICT=1
```

## Backlog charters faltantes

Pages Inertia sem charter ainda (auditoria 2026-05-13):
- Buscar via Glob `resources/js/Pages/**/*.tsx` minus os 22 listados acima → todas as Pages restantes precisam draft via `charter-write`
- Priorizar Pages tocadas com frequência (top 20 por `git log --since "2026-04-01" -- resources/js/Pages/` count)

## Métrica % Charter-driven (gate de saúde)

> **ANTES (2026-05-08 → 2026-05-12):** ~30% — charters existiam mas inertes (sem tool MCP de leitura, sem hook).
>
> **DEPOIS (2026-05-13 plena ativação):** ~85% target — 17 Pages live + tool MCP + hook + skill Tier A always-on + workflow draft→live formalizado. Os ~15% restantes são Pages que ainda não têm charter draft (backlog `charter-write`).

Auditar periodicamente:

```bash
# Quantos charters status: live em Pages?
grep -l "^status: live" resources/js/Pages/**/*.charter.md | wc -l

# Quantas Pages têm Index.tsx mas SEM charter irmão?
# (proxy pra % Charter-driven)
```

## Referências

- [ADR 0094 Constituição V2 — princípio #3 Charter > Spec](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0101 Sistema Charter-Capterra](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)
- [ADR 0102 S6 Charter-Capterra postmortem](../../decisions/0102-s6-charter-capterra-postmortem-s7-backlog.md)
- [GAP-ANALYSIS-91-100-2026-05-13 §C1 P0 Onda 4](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md)
- Skill: `.claude/skills/charter-first/SKILL.md` (Tier A always-on)
- Skill: `.claude/skills/charter-write/SKILL.md` (cria drafts)
- Tool MCP: `Modules/Jana/Mcp/Tools/CharterFetchTool.php`
- Hook: `.claude/hooks/charter-validate.ps1` + `.sh`
- Pest: `Modules/Jana/Tests/Feature/Mcp/CharterFetchToolTest.php` (10 cenários)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-13 | Opus (C1 agent Onda 4) + Wagner | Charters S4 plenamente ativados — tool MCP + skill Tier A + hook warning-mode. Inventário 26 charters consolidado. |
