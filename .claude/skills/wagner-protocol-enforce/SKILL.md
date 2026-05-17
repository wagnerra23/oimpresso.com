---
name: wagner-protocol-enforce
description: |
  BLOQUEADOR Tier A always-on — carrega memory/reference/PROTOCOLO-WAGNER-SEMPRE.md
  no SessionStart de TODA sessão Claude no oimpresso. Garante que Claude execute
  automaticamente as 10 regras que Wagner sempre solicita (smoke real, cópia
  literal design aprovado, workflow 3 fases mexer-registrar, multi-tenant Tier 0,
  PT-BR + economia crédito, biz=1 não biz=4 em tests, charter+visual-comparison
  antes Edit Page, branch/worktree disciplina, zero auto-mem privada, aprovação
  humana antes commit/push/merge) — sem Wagner precisar repedir. Origem: sessão
  2026-05-17 Wagner "não é justo eu sempre ficar pedindo a mesma coisa".
trust_level: 1
tier: A
parent_mission: mission.constituicao-v2
charter_adr: 0094-constituicao-v2-7-camadas-8-principios
auto_trigger: session_start
applies_to:
  - any session in oimpresso project
  - both human-driven and agent-driven sessions
related_skills: [brief-first, mcp-first, multi-tenant-patterns, commit-discipline, preflight-modulo, smoke-prod-evidence, charter-first, wagner-request-refiner]
related_agents: [wagner-understand]
canon_doc: memory/reference/PROTOCOLO-WAGNER-SEMPRE.md
---

# Skill: wagner-protocol-enforce

## Quando ativa

**Sempre.** Tier A always-on, carrega em todo system prompt do oimpresso. Pareada com `brief-first` (que carrega estado consolidado) — esta skill carrega o **protocolo de comportamento** que Wagner sempre exige.

## O que faz

Carrega [`memory/reference/PROTOCOLO-WAGNER-SEMPRE.md`](../../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) na memória de trabalho de Claude no início da sessão. O protocolo enumera **10 regras** (R1-R10) que Wagner sempre solicita:

| # | Regra | Trigger |
|---|---|---|
| **R1** | **Smoke real obrigatório** (não narração) | Após merge/deploy/declarar "funcionando" |
| **R2** | Cópia literal quando design aprovado | Wagner aprovou screenshot do prototype Cowork |
| **R3** | Workflow 3 fases (PRE-FLIGHT + DURING + POST) | Edit em `Modules/<X>/` |
| **R4** | Multi-tenant Tier 0 IRREVOGÁVEL | Edit em Model/Controller/Service/Job |
| **R5** | PT-BR + Economia de crédito | SEMPRE |
| **R6** | Cliente ROTA LIVRE biz=4 NUNCA em test/smoke | Pest + smoke prod |
| **R7** | Charter `live` + visual-comparison antes Edit Page | Edit `Pages/<Mod>/<Tela>.tsx` |
| **R8** | Branch + worktree disciplina | Trabalho em worktree filha |
| **R9** | ZERO auto-mem privada | Tentativa Write em `~/.claude/projects/*/memory/` |
| **R10** | Aprovação humana antes commit/push/merge | git push/merge/`gh pr create`/`gh pr merge` |
| **R11** | **Continuar autonomamente até desfecho dentro do escopo pré-aprovado** | Wagner aprovou caminho ("sim pode" + descrição de N passos) — Claude executa do começo ao fim sem pausa interna |

## Protocolo de execução (Claude no SessionStart)

1. **Ler o protocolo completo** em `memory/reference/PROTOCOLO-WAGNER-SEMPRE.md` se ainda não na memória.
2. **Auto-check antes de cada turno terminar** (ver §"Como Claude detecta violação" no protocolo) — passa pelos 10 itens R1-R10.
3. **Quando trigger de regra dispara, executar SEM esperar Wagner pedir.** Por exemplo:
   - Mergei PR → execute R1 (abrir Brave, screenshot, verificar visual) automaticamente.
   - Wagner aprovou screenshot → execute R2 (cópia integral 1 PR, não slice).
   - Vou Edit em `Modules/<X>/` → execute R3 PRÉ-FLIGHT (ler SPEC + RUNBOOK + CAPTERRA + charter + ADRs).
4. **Se incidente novo, AGREGAR ao protocolo** automaticamente:
   - Criar/editar `memory/reference/feedback-<slug>.md`
   - Adicionar entrada em PROTOCOLO-WAGNER-SEMPRE.md §"incidentes catalogados"
   - Commit + push (webhook MCP propaga pro time)

## Sinal de violação observável

Wagner pergunta "o que eu sempre solicito?" — significa Claude esqueceu uma das 10 regras. Catalogar incidente + atualizar protocolo + post-mortem inline no turno corrente.

## Diferença de outras skills Tier A

| Skill Tier A | Carrega | Foco |
|---|---|---|
| `brief-first` | Estado consolidado MCP (~3k tokens) | DADOS atuais (cycle, tasks, ADRs recentes) |
| `mcp-first` | Lembrete tools MCP antes filesystem | LATÊNCIA + economia tokens |
| `multi-tenant-patterns` | Tier 0 `business_id` global scope | SEGURANÇA dados (R4) |
| `commit-discipline` | 1 PR = 1 intent ≤300 LOC | DISCIPLINA git |
| `charter-first` | Charter da página antes Edit `.tsx` | CONTRATO visual/UX |
| `mwart-comparative` | Gate visual + Cowork loop | PROCESSO migração |
| `preflight-modulo` | Pré-flight obrigatório Edit `Modules/<X>/` | DISCIPLINA módulo (R3) |
| **`wagner-protocol-enforce`** | **PROTOCOLO de comportamento (10 regras)** | **AUTO-EXECUÇÃO sem Wagner pedir** |

Esta skill é **ortogonal** às outras Tier A — não substitui nenhuma, ENCAPSULA o pacote completo num único contrato cobrável.

## Hooks que reforçam

- `block-automem.ps1` (R9 enforce automático)
- `block-mwart-violation.ps1` (R7 enforce automático)
- `modulo-preflight-warning.ps1` (R3 reminder)
- CI workflow `governance-gate.yml` (R7, R10, ADR append-only)

## Refs

- [PROTOCOLO-WAGNER-SEMPRE.md](../../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) — canon (10 regras detalhadas)
- ADR 0094 [Constituição v2](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- Agent companion: [wagner-understand](../../agents/wagner-understand.md) — decodificador de pedidos crus
