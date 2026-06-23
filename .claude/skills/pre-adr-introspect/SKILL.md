---
name: pre-adr-introspect
description: ATIVAR ANTES de qualquer Write em `memory/decisions/NNNN-*.md` (ADR nova) OU antes de propor schema novo (`database/migrations/*.php` que adiciona col em tabela existente) OU antes de propor pattern arquitetural (endpoint, middleware, service genérico). Força introspecção do projeto pra detectar patterns canon já estabelecidos (git grep ADRs/migrations/Controllers + diagnóstico prod se ADR depende de estado DB). Previne 80% dos erros de retrabalho mesmo-dia documentados em sessão 2026-05-27 (3 ADRs erratas + 1 RUNBOOK histórico + 2 CI fails Append-only canon). Lição arquitetural ADR 0200 §Lição operacionalizada.
tier: B
trigger: description-matching
parent_adr: 0095
related_adrs: ["0028", "0093", "0094", "0095", "0105", "0120", "0200", "0304"]
---

# pre-adr-introspect — Tier B auto-trigger

> **Quando ativar:** ANTES de qualquer Write em `memory/decisions/NNNN-*.md` (ADR nova) OU antes de propor schema novo em tabela já-existente OU antes de propor pattern arquitetural (Controller API, middleware, Service genérico) que pode duplicar infra canon.

## Origem (custo capturado — sessão 2026-05-27)

Em **2026-05-27** entre 13:23 e 14:59 BRT, 7 PRs criados/mergeados pela mesma sessão. **3 foram retrabalho de erros próprios**:

| Erro | Custo real | Causa raiz |
|---|---|---|
| Inventei `legacy_source` ENUM + tabela satélite `contact_profile_legacy` em ADR 0197 sem `git grep` dos patterns canon Wagner 2024-11 (11 tabelas já usavam `officeimpresso_codigo` + `officeimpresso_dt_alteracao`) | 2 ADRs erratas mesmo dia ([#1731](https://github.com/wagnerra23/oimpresso.com/pull/1731) + [#1735](https://github.com/wagnerra23/oimpresso.com/pull/1735)) | Não introspectei `git grep officeimpresso_dt_alteracao database/migrations/` antes |
| Escrevi RUNBOOK Martinho Fase 3+4 ([#1717](https://github.com/wagnerra23/oimpresso.com/pull/1717)) assumindo fases pendentes — realidade: já estavam em prod com 43.974 vendas + 83.045 títulos há semanas | RUNBOOK virou `historical` no mesmo dia ([#1727](https://github.com/wagnerra23/oimpresso.com/pull/1727) retrospectiva §7) | Não rodei diagnóstico Hostinger ANTES de escrever plano |
| Tentei resolver colisão `number: 195` ([#1714](https://github.com/wagnerra23/oimpresso.com/pull/1714) vs [#1736](https://github.com/wagnerra23/oimpresso.com/pull/1736)) via rename/append em ADR — bloqueado por gate Append-only canon. Existiam 3 precedentes (0101/0102/0119) tratados via `_INDEX-LIFECYCLE.md` | 2 CI fails + 1 PR extra ([#1741](https://github.com/wagnerra23/oimpresso.com/pull/1741) fail · [#1744](https://github.com/wagnerra23/oimpresso.com/pull/1744)) | Não busquei precedente de colisão numérica em `memory/decisions/_INDEX-LIFECYCLE.md` |

**Padrão único:** propus solução nova ANTES de verificar o que o projeto já tinha resolvido. **Mesma lição** documentada em [ADR 0200 §Lição arquitetural](../../../memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md) — e cometi 2 vezes depois disso.

Esta skill operacionaliza o "antes de criar pattern novo, faça `git grep`".

## Checklist obrigatório (rodar ANTES de Write em `memory/decisions/`)

### 1. Pattern canon similar já existe?

```bash
# Busca ADRs canon que abordam tema próximo
git grep -rn "<palavra-chave-do-tema>" memory/decisions/ memory/reference/ 2>&1 | head -20

# Ex (caso ADR 0197 falhou):
git grep -rn "sync.*delphi\|officeimpresso_dt_alteracao\|BaseApiController" memory/decisions/
# → teria revelado canon Wagner 2024-11 antes de inventar legacy_source
```

### 2. Schema/endpoint canon já estabelecido?

```bash
# Busca migrations recentes que adicionaram cols com tema similar
git grep -rn "<field-novo>" database/migrations/ Modules/*/Database/Migrations/ 2>&1 | head -20

# Busca endpoints canon que servem propósito similar
git grep -rn "<endpoint-novo>\|<method-novo>" Modules/*/Http/Controllers/Api/ app/Http/Controllers/Api/ 2>&1 | head -10

# Ex (caso ADR 0197 falhou):
git grep -rn "officeimpresso_codigo\|officeimpresso_dt_alteracao" database/migrations/
# → teria mostrado 11 migrations 2024-11→2025-01 com mesmo pattern
```

### 3. Precedente de colisão / conflict resolution?

```bash
# Número livre CIENTE de trabalho em voo (ADR 0304 — não só a main canônica: vê PRs abertos
# via gh + branches). É a forma canônica de alocar — substitui o "ls + chute" manual.
node scripts/governance/next-id.mjs adr        # → próximo número de ADR livre
node scripts/governance/next-id.mjs us GOV     # → próximo US-<PREFIXO> livre (p/ histórias)

# Convenções de colisão JÁ registradas (precedentes de resolução, se houver colisão):
grep -n "numbering_collisions\|colis" memory/decisions/_INDEX-LIFECYCLE.md

# Ex (caso PR #1741 falhou):
# → teria mostrado 3 precedentes 0101a/b, 0102a/b, 0119a/b
#   resolvidos via Bloco apended no INDEX, NÃO via rename.
# Por que next-id e não `ls`: o `ls` lê só a main → cego a PRs/branches em voo (a colisão
# crônica de 14 ADRs). O alocador vê os dois. Resíduo de corrida = memory-health Check A/N.
```

### 4. Diagnóstico prod ANTES (se ADR depende de estado DB)

```bash
# Se ADR define plano executável (RUNBOOK / Fase X de migração / cleanup retroativo):
ssh -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@oimpresso.com \
  'cd /home/u906587222/domains/oimpresso.com/public_html && php artisan tinker --execute="
    echo DB::table(\"<tabela-relevante>\")->where(\"business_id\", <biz>)->count();
  "'

# Ex (caso RUNBOOK Martinho falhou):
# php artisan tinker → DB::table('transactions')->where('business_id', 164)->count()
# → teria mostrado 43.974 vendas (não 0) antes de escrever "Fase 3 pendente"
```

### 5. Documentos de contrato / pattern canônico do tema?

```bash
# Lista docs de referência canônicos
ls memory/reference/contrato-*.md memory/reference/migracao-*.md memory/reference/<tema>-*.md 2>&1
ls memory/requisitos/_DesignSystem/ 2>&1 | grep -i "<tema>"

# Ex: existe migracao-officeimpresso-pattern.md? contrato-delphi-inviolavel.md?
# Leitura prévia evita reinventar wire/pattern já contratado
```

## Output esperado

Após rodar os 5 itens, gerar bullet list curta:

```
## pre-adr-introspect — relatório (gerar antes do Write)

- Tema: <descrição curta da ADR proposta>
- Número candidato: NNNN (verificado livre: ✅ / colisão same-day: ⚠️)
- Patterns canon similares encontrados:
  - [ADR 0NNN — slug](path) — relação: <reuso / pareado / supersede?>
  - <schema/endpoint canon> em <path>
- Diagnóstico prod (se aplicável):
  - Tabela X biz=Y tem N rows · esperado pelo plano: M (drift: ±%)
- Decisão pós-introspecção:
  - [ ] REUSAR pattern canon X (preferido)
  - [ ] EXTEND pattern canon X com Y campos novos
  - [ ] CRIAR pattern novo (justificar: por que NÃO reusar?)
```

Esse relatório vai no corpo da ADR (seção "Contexto" ou "Análise prévia") ou pelo menos no commit body.

## Quando NÃO ativar

- ADR de **errata pequena** (typo, ref quebrada) que reusa pattern existente — overhead desnecessário
- ADR de **documentação de incidente** post-mortem retroativo (não propõe schema novo)
- ADR de **deprecação** de módulo (não cria nada — só remove)
- Edit em **session log** ou **handoff** (não é ADR canon)

## Integração com outras skills

| Skill | Relação |
|---|---|
| [memory-schema-preflight](../memory-schema-preflight/SKILL.md) | Roda DEPOIS da introspecção — valida frontmatter da ADR já com decisões alinhadas |
| `como-integrar` (agente, não skill — `Agent(subagent_type: "como-integrar")`) | Pareado — `como-integrar` introspecta antes de feature; `pre-adr-introspect` antes de ADR |
| [commit-discipline](../commit-discipline/SKILL.md) | Pré-req antes de commit de PR que cria ADR canon |
| [mcp-first](../mcp-first/SKILL.md) | Tools MCP `decisions-search` cobrem parte do item 1 (canon search) |

## Workflow recomendado

```
1. User pede ADR nova                      → ATIVE esta skill
2. Rode os 5 itens do checklist            → gere relatório
3. APRESENTE relatório pro Wagner          → "achei pattern X canon · proponho REUSAR / EXTEND / CRIAR"
4. Wagner aprova direção                   → SÓ ENTÃO Write memory/decisions/NNNN-*.md
5. memory-schema-preflight valida          → commit
6. commit-discipline valida (1 PR = 1 intent · ≤300 linhas)  → push
```

## Métricas de sucesso

Esta skill é eficaz se nas próximas sessões multi-PR:

- Zero ADRs erratas same-day (vs 3 hoje 2026-05-27)
- Zero RUNBOOK virando `historical` no mesmo dia que foi escrito
- Zero CI fail por "Append-only canon" tentando mexer em ADR canon
- ≥80% das ADRs novas referenciam pattern canon existente no §Contexto

Revisar em 30 dias (2026-06-27) — se métricas batem, virar Tier A always-on.

## Refs

- [ADR 0200 — Contacts adopta canon sync Wagner](../../../memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md) (§Lição arquitetural que esta skill operacionaliza)
- [ADR 0095 — Skills tiers convenção interna](../../../memory/decisions/0095-skills-tiers-convencao-interna.md)
- [_INDEX-LIFECYCLE.md](../../../memory/decisions/_INDEX-LIFECYCLE.md) (única forma de "mover" ADR sem violar append-only)
- [memory-schema-preflight skill](../memory-schema-preflight/SKILL.md) (pareada)
- `como-integrar` agente (`Agent(subagent_type: "como-integrar")`) — pareado (features vs ADRs)
- [contrato-delphi-inviolavel.md](../../../memory/reference/contrato-delphi-inviolavel.md) (exemplo de doc canônico que ADR deve respeitar)
- [migracao-officeimpresso-pattern.md](../../../memory/reference/migracao-officeimpresso-pattern.md) (exemplo de pattern canônico que ADR deve reusar)
