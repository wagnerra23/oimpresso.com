---
name: charter-first
description: BLOQUEADOR — ANTES de editar qualquer .tsx que tenha .charter.md ao lado (ex Index.tsx + Index.charter.md), chame tool MCP `charter-fetch <page-id>` pra carregar contrato vivo da página (Mission/Goals/Non-Goals/UX targets/Anti-hooks). Tier A always-on — princípio duro #3 Constituição V2 (Charter > Spec). 26 charters em prod, tool MCP deployed em 2026-05-13.
tier: B
auto_trigger: path
resumo: dispara ao editar `.tsx` com `.charter.md`
always_on: false
tier_enforce: hook-pre-tool-use-edit
parent_adr: 0095
related_adrs: [0094, 0101]
enabled: true
ativacao: 2026-05-08 (S6 F1+F2 partial — charters em prod + artisan charter:audit)
plena_ativacao: 2026-05-13 (C1 P0 Onda 4 — tool MCP `charter-fetch` deployed + skill upgrade Tier A always-on + hook `charter-validate` warning-mode)
ativacao_notas: charter-fetch tool MCP entregue 2026-05-13 (CharterFetchTool.php em Modules/Jana/Mcp/Tools). 26 charters .charter.md em produção (21 live + 3 draft + 1 rascunho + 1 piloto). Hook .claude/hooks/charter-validate.{ps1,sh} warn-only — vira bloqueante quando ROI provado.
---

# charter-first — Tier A ATIVA (plena)

> ✅ **PLENAMENTE ATIVA desde 2026-05-13** — Tool MCP `charter-fetch` deployed (CharterFetchTool.php em Modules/Jana/Mcp/Tools/, registrada em OimpressoMcpServer page 2). 26 charters .charter.md em produção (21 status: live + 5 draft/rascunho/proposto). Hook `.claude/hooks/charter-validate.{ps1,sh}` rodando em PreToolUse Edit/Write modo warning (P1 — vira bloqueante quando ROI provado em ≥5 sessões).

## Quando ativar

ANTES de qualquer Edit/Write em `.tsx` que tenha `.charter.md` no mesmo diretório.

Exemplo:
```
resources/js/Pages/Repair/
├── Index.tsx         ← Claude vai editar
└── Index.charter.md  ← skill força chamada charter-fetch primeiro
```

## Como chamar (canon a partir de 2026-05-13)

Tool MCP (servidor oimpresso) — nome varia por dev:
```
mcp__Oimpresso_MCP___Wagner__charter-fetch page_id:"/sells"
mcp__Oimpresso_MCP___Wagner__charter-fetch page_id:"resources/js/Pages/Sells/Index.tsx"
mcp__Oimpresso_MCP___Wagner__charter-fetch page_id:"resources/js/Pages/Sells/Index.charter.md"
mcp__Oimpresso_MCP___Wagner__charter-fetch page_id:"/admin" format:"json"
```

Output retorna 6 seções canônicas: Mission · Goals · Non-Goals · UX targets · Automation hooks · Anti-hooks + frontmatter (status/owner/tier/related_adrs). Se `status: draft|rascunho|proposto`, anexa WARNING — Wagner ainda não aprovou Non-Goals + Anti-hooks (parte sensível anti-alucinação).

## Por que Tier A

**Princípio duro #3 da Constituição v2** (Charter > Spec).

SPEC.md vira lixo em 30 dias. Charter ao lado do `.tsx` sincroniza naturalmente porque:
- Mesmo PR muda código + charter (commit-discipline força revisar ambos)
- Charter define **invariants** (multi_tenant_scope, p95, a11y) que test pode validar
- IA lê charter ANTES de editar — não inventa solução fora do contrato

Sem hook que force chamada `charter-fetch`, charter vira doc morto. Mesmo problema das auto-mems privadas (ADR 0061).

## Mecanismo (futuro)

`.claude/hooks/charter-guard.ps1` (a criar em S4):
1. PreToolUse Edit/Write detecta path `.tsx`
2. Verifica se existe `.charter.md` ao lado
3. Se sim: verifica se `charter-fetch <id>` foi chamado nesta sessão
4. Se não: bloqueia Edit + retorna "Chame `mcp__oimpresso__charter-fetch` antes"

## Frontmatter charter (template — definido em S4)

8 seções canônicas (alinha com [Prompt Contracts](https://understandingdata.com/posts/prompt-contracts-specification-before-code/) 2026):

```yaml
---
charter_id: page.repair.listagem
kind: page                        # page | feature | mission
parent_id: feature.os
trust_level: 2                    # 0=Tier 0 / 3=cosmético
multi_tenant_scope: required      # required | superadmin_only | na (Tier 0)
owners:
  design: maira
  code: felipe
adrs: [0091, 0093]
charter_version: 1
last_verified: 2026-XX-XX
---

## 1. Objective
## 2. Pre-conditions
## 3. Invariants ⚠️
## 4. File scope (ALLOW / DENY)
## 5. Implementation contract (props/eventos)
## 6. Test specifications
## 7. Acceptance criteria
## 8. Anti-patterns
```

## Estado dormente — não dispara nada

Enquanto `enabled: false`:
- Skill carrega no system prompt (custo: ~80 tokens via name+description)
- Mas description "DORMENTE" indica pra Claude não acionar
- Hook PreToolUse não está configurado
- Edit/Write em `.tsx` segue normal

## Critério ativação (S4 entregue)

- [ ] Tool MCP `charter-fetch` deployed em mcp.oimpresso.com
- [ ] `php artisan charter:sync` rodando em CI
- [ ] ≥3 charters preenchidos (Repair listagem, OS detalhe, Tarefas inbox)
- [ ] Hook `charter-guard.ps1` criado e testado
- [ ] Mudar `enabled: false` → `true` no frontmatter
- [ ] ADR específica documentando ativação

## Referências

- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípio duro #3 Charter > Spec
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — convenção tiers
- [memory/sprints/research/s4-charters-deep-dive.md](../../../memory/sprints/research/s4-charters-deep-dive.md) — pesquisa estado-da-arte 2026
