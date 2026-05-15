---
name: brief-update
description: Use SEMPRE depois de commit/merge de PR que altere capacidades, diferenciais, score Capterra, UX visível, ou gaps de um módulo do oimpresso. Atualiza `memory/requisitos/<Modulo>/BRIEFING.md` automaticamente — lê SPEC + AUDIT-LOG + CAPTERRA-INVENTARIO + últimos handoffs, regenera o briefing canon (1 página executiva) e commita junto. Wagner enxerga estado real do módulo sem ter que pedir. Ativa quando — (a) git commit/PR mergeado que toque `Modules/<X>/` + `resources/js/Pages/<X>/`; (b) novo PR fecha gap/US relevante; (c) Wagner pede "atualiza briefing", "briefing do módulo", "estado consolidado de <X>"; (d) audit canon roda (`module-completeness-audit`, `comparativo-do-modulo`). Tier B auto-trigger.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0095
---

# Brief-update — manter BRIEFING.md canônico atualizado

## Quando ativa

Toda vez que um PR mergeado **altera o módulo de forma visível ao Wagner**:

- Capacidades novas (US done, feature shippada)
- Score Capterra subiu/desceu
- Gap fechado / aberto
- UX visível modificada
- Diferencial competitivo novo
- Cliente piloto / canary mudou

**Trigger detectáveis:**
- `git log -1 --name-only` mostra arquivos em `Modules/<X>/` + `resources/js/Pages/<X>/`
- PR title começa com `feat(<modulo>)`, `perf(<modulo>)`, `fix(<modulo>)` significativo
- AUDIT-LOG entry nova (skill `module-completeness-audit` rodou)
- CAPTERRA-INVENTARIO atualizado

## Regra de ouro

> Wagner não deve precisar pedir briefing. **Ele deve sempre existir, sempre atualizado, sempre canon em `memory/requisitos/<Modulo>/BRIEFING.md`.**

## Como aplicar (6 passos)

### 1. Detectar módulo afetado

Pelo diff do PR:
```bash
git diff --name-only main...HEAD | grep -oE "Modules/[A-Z][a-z]+" | sort -u
```

Cada módulo único listado precisa BRIEFING atualizado.

### 2. Ler fontes canônicas em ordem

```bash
# Sources canon (ordem importa — última override antiga):
memory/requisitos/<Modulo>/COMPARATIVO-MERCADO-*.md  # score % atual
memory/requisitos/<Modulo>/CAPTERRA-INVENTARIO.md    # gaps detalhados
memory/requisitos/<Modulo>/AUDIT-LOG.md              # eventos recentes
memory/requisitos/<Modulo>/SPEC.md                   # US done/todo
memory/handoffs/*<modulo>*.md                        # últimos 5
memory/sessions/*<modulo>*.md                        # últimos 3
```

### 3. Calcular % por dimensão

Use a tabela canônica do template:

| Dimensão | Como calcular |
|---|---|
| Operacional PME (P0+P1) | (P0 done + P1 done) / (P0 spec + P1 spec) × 100 |
| Capterra top-mercado | última linha do COMPARATIVO-v2 (53.4/59 = 91% etc) |
| Diferencial competitivo | qualitative — bate com "Diferenciais únicos" section |
| Cobertura SPEC formal | US `done` / US total spec'adas |
| Documentação canon | (AUDIT-LOG fresh + CAPTERRA fresh + SPEC sync) / 3 |
| Deploy/ops | qualitative — biz=1 prod live? canary ativo? CT 100 deploy pendente? |

### 4. Aplicar template

Copiar de [memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md](../../memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md) e preencher 12+1 seções.

**Limite: 150 linhas (1 página scroll).** Se passar, comprimir.

### 5. Última seção §13 atualização

```markdown
**Atualizado:** YYYY-MM-DD HH:MM BRT pelo PR #NNN (<título>)
**Próximo update esperado:** quando próximo PR relevante mergear
**Mantenedor:** Claude (auto via skill `brief-update`) + Wagner (review)
```

### 6. Commit junto com PR original OU separado

**Preferido:** commit junto no mesmo PR (sufixo `+ briefing update`)

**Fallback:** PR pequeno separado `docs(<modulo>): atualiza BRIEFING pós-#NNN` quando PR original já foi mergeado.

## Anti-hooks (PROIBIDO)

- ❌ **NÃO** apagar histórico — apenas section §13 "Atualizado" sobrescreve. Seções 1-11 são append-friendly (revisada por novos PRs).
- ❌ **NÃO** inventar % se não há fonte canônica recente (>30d). Marcar `?% (stale, próxima audit YYYY-MM-DD)` é OK.
- ❌ **NÃO** duplicar conteúdo do COMPARATIVO ou SPEC — BRIEFING é **executive summary**, não substitui.
- ❌ **NÃO** passar de 150 linhas (1 página scroll Wagner). Comprimir tabelas se necessário.
- ❌ **NÃO** atualizar BRIEFING se PR for puro chore/test/style sem impacto Wagner-visible. (Heurística: PR title `chore(...)` ou `test(...)` ou `refactor(...)` pequeno → skip.)

## Exceções legítimas (skip)

- PR de docs (memory/, AUDIT-LOG, SPEC) puro — auto-feedback loop não-necessário
- Refactor interno sem mudança user-facing
- Test-only PRs
- Hotfix < 30min sem impacto capacidade

## Como saber se está aplicado

- `git log --name-only -- memory/requisitos/*/BRIEFING.md` mostra updates recentes
- Cada módulo ativo (Whatsapp, Sells, Repair, etc) tem BRIEFING.md atualizado <30d
- Wagner pede "briefing X" e recebe arquivo direto (não need re-gerar)

## Refs

- [BRIEFING-TEMPLATE.md](../../memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md) — template canon
- [proibicoes.md §Sempre fazer](../../memory/proibicoes.md) — regra Tier 0 derivada
- Origem: Wagner 2026-05-15 "manter atualizado o briefing acho isso super necessário" + "ja era para ser assim sempre"
