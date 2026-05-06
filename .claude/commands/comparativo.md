---
description: Compara um módulo do oimpresso com o estado da arte do mercado. Lê CAPTERRA-FICHA.md + SPEC.md + código real, gera CAPTERRA-INVENTARIO.md em 3 buckets (✅🟡❌), propõe batch de tasks priorizadas P0-P3, e (após aprovação humana) cria as tasks no MCP + apenda US ao SPEC. ADR 0089. Uso `/comparativo <Modulo>`.
---

# /comparativo — auditar escopo do módulo vs estado da arte

Wrapper do skill `comparativo-do-modulo`. Ativa o ciclo completo de auditoria competitiva governada.

## Argumentos

- `$1` (obrigatório) — nome do módulo (case-sensitive, igual ao nome da pasta em `Modules/`)
- `$2` (opcional) — modo:
  - `--dry-run` — gera inventário mas NÃO propõe tasks (só leitura)
  - `--batch` — usa Wagner como decisor "tudo P0+P1" (atalho — útil pra auditar 5 módulos rápido)

Sem argumento `$2`: comportamento padrão (gera inventário + pergunta quais tasks aprovar).

## Exemplos

```
/comparativo RecurringBilling
/comparativo Financeiro --dry-run
/comparativo NfeBrasil --batch
```

## Pré-requisitos

- `memory/requisitos/{Modulo}/CAPTERRA-FICHA.md` deve existir
  - SE NÃO: skill avisa e instrui copiar de `memory/requisitos/_TEMPLATE_capterra_ficha.md`
- `Modules/{Modulo}/` deve existir como módulo nWidart instalado
- Tools MCP `tasks-create`, `tasks-list` precisam estar conectadas (config Bearer em `.claude/settings.local.json`)

## O que executa (resumo)

1. Valida pré-condições
2. Lê CAPTERRA-FICHA.md + SPEC.md + código de `Modules/{Modulo}/`
3. Lê seção "Como auditar este módulo" da FICHA (instruções customizadas)
4. Classifica capacidades em ✅ APROVADO / 🟡 PARCIAL / ❌ AUSENTE
5. Sobrescreve `memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md`
6. Apresenta batch de tasks priorizadas (P0→P3)
7. Aguarda aprovação Wagner
8. `tasks-create` no MCP para aprovadas + apenda US ao SPEC.md
9. `git commit + push` (webhook MCP propaga em <60s)

Detalhes completos: [skill comparativo-do-modulo](../skills/comparativo-do-modulo/SKILL.md) + [ADR 0089](../../memory/decisions/0089-capterra-driven-module-evolution.md).

## Saída

```
✅ Inventário: memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md
✅ Tasks criadas: N (P0:N P1:N P2:N P3:N)
   - TASK-XXX-001: ...
✅ SPEC.md atualizado: +N US
✅ Push: <sha> → main (MCP sync ~60s)
```
