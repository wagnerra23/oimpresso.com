---
slug: feedback-ondas-cowork-transparencia-de-gaps
title: "Cópia Cowork em Ondas — transparência de gaps > otimismo"
type: feedback
category: design-process
date: 2026-05-17
session: stupefied-noether-89f83d
related_adrs: [0094, 0104, 0107, 0114, 0141, 0168, 0169]
related_docs:
  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md
  - memory/reference/PROTOCOLO-WAGNER-SEMPRE.md
  - memory/reference/feedback-design-literal-copy-quando-aprovado.md
---

# Feedback canon — Ondas Cowork + transparência de gaps

> **Wagner palavras textuais 2026-05-17 (sessão `stupefied-noether-89f83d`):**
>
> *"ficou bom não copiou tudo mas ficou muito melhor do que das outras vezes, ainda tem detalhes que não foram colocados. como deveria ser as próximas ondas para garantir a aplicação do novo Design?"*

## Regra

Cópia de design Cowork → Inertia acontece em **N Ondas sequenciais**, cada uma 1 PR. **NÃO** existe expectativa que 1 Onda só entregue todo o prototype com score 9.75. **Existe** expectativa que cada Onda **catalogue explicitamente** o que ficou de fora pra próxima.

## Why

1. **Realidade do prototype KB-9.75:** scores 9.75/10 são compostos por **4 refinos sequenciais** (R1 Fundação +1.2, R2 IA +1.4, R3 Curadoria +1.0, R4 Distribuição +0.55) sobre baseline A+ (5.6). Tentar entregar 9.75 em 1 PR único violaria `commit-discipline` e seria revisável demais.
2. **Lição PR #1032 (Sells/Index Onda 1):** entreguei Visual Base (5.6) + R1 Fundação (6.8) parcial. Wagner reconheceu *"ficou muito melhor do que das outras vezes"* mas notou *"não copiou tudo"* — confirma que cada Onda entrega valor incremental, não totalidade.
3. **Lição PR #1034 (gap legacy):** SellsDateFilter/GroupBy/Grade toggle existiam em `_components/` mas eu não montei. Só detectei via smoke Brave. Se eu tivesse catalogado explicitamente no commit body do #1032 ("NÃO inclui: DateFilter + GroupBy + viewMode toggle — pendentes em Onda corretiva"), Wagner saberia ANTES do smoke e #1034 seria preventivo, não corretivo.

## How to apply

1. **Cada PR de Onda inclui seção "NÃO INCLUI"** no commit body — enumeração explícita do que ficou de fora vs prototype. Transparência > otimismo.
2. **Catálogo de gaps é mensurável:** se o prototype tem 6 refinos KB-9.75 e a Onda 1 entrega R0 (baseline A+), o commit lista R1, R2, R3, R4, polish, tests como NÃO incluídos.
3. **Gaps catalogados viram backlog automático** — Wagner enxerga as próximas Ondas sem precisar perguntar "o que tá faltando?".
4. **Próxima Onda referencia gap específico** que está fechando — não inventa escopo novo.
5. **Pos-smoke Brave**: comparar prod vs prototype lado-a-lado e CONFIRMAR que gaps catalogados batem com o que falta visualmente. Se descobrir gap NÃO catalogado, é violação — atualizar feedback canon + Onda corretiva imediata.
6. **Quando Wagner pergunta "como ficou?"**, resposta inclui:
   - O que entreguei na Onda (✅)
   - O que NÃO incluí (catalogado, transparente)
   - Próxima Onda recomendada (1 frase)

## Anti-padrão

- ❌ Declarar "Onda completa" sem listar NÃO INCLUI
- ❌ Pular catalogação de gaps porque "ficou bom o suficiente"
- ❌ Tentar entregar 9.75 em 1 PR único violando `commit-discipline` sem override autorizado
- ❌ Ondas sem visual-comparison.md prévia (pula F0 do RUNBOOK)
- ❌ Skip da F9/F10 (smoke real + detectar gaps pós-smoke)

## Quando NÃO aplicar (excecões)

- Bug fix tático isolado (1 arquivo, 1 linha) — não é Onda
- Migração simples sem prototype Cowork (Blade direto → Inertia minimal) — `mwart-process` padrão suficiente
- Refactor interno (sem mudança visual) — fora do escopo "Cópia Cowork"

## Doc canônico

[RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — 12 fases obrigatórias + critérios de Onda completa + estimate fator 10x + pattern reusável + anti-padrões.

## Origem catalogada

- **Sessão `stupefied-noether-89f83d` 2026-05-17** — Sells/Index Onda 1 (PR #1032 + #1034 + #1035 governance)
- **5 incidentes** que originaram o protocolo + 1 incidente que originou esta regra de transparência:
  - Eu não catalog uei "DateFilter + GroupBy + Grade toggle pendentes" no PR #1032 body. Wagner descobriu via smoke Brave pós-merge. Hotfix #1034 corretivo. Se tivesse catalogado, Wagner saberia ANTES e #1034 viria como Onda planejada.

## Relação com outros docs

- [PROTOCOLO-WAGNER-SEMPRE.md](PROTOCOLO-WAGNER-SEMPRE.md) — R2 cópia literal + R11 continuar até desfecho
- [feedback-design-literal-copy-quando-aprovado.md](feedback-design-literal-copy-quando-aprovado.md) — quando Wagner aprovou screenshot, cópia integral em 1 PR
- [RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — playbook 12 fases
- ADR 0168 PROTOCOLO Tier A IRREVOGÁVEL
- ADR 0169 (proposta) errata 0168 — adiciona RUNBOOK como artefato 4º da triade
