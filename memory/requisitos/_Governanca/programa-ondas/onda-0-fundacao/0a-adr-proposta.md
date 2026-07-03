---
titulo: Onda 0a — ADR-proposta (travar o mecanismo antes de código)
status: proposto
owner: W
criado: '2026-07-02'
etapa: onda-0-fundacao
related: ../PLANO-MESTRE.md
---

# Onda 0a — ADR-proposta

> Status vivo do programa: [PLANO-MESTRE.md](../PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro) — execução via tasks MCP `parent_plan=programa-ondas`.

## Objetivo

Travar o mecanismo do programa **antes de qualquer código** (governança append-only, R10).
Sem a decisão registrada, cada onda vira improviso e o furo da `/perfil` se repete.

## Artefato a criar

`memory/decisions/proposals/NNNN-programa-ondas-regua-correcao.md` — ADR Nygard, status `proposed`,
**emenda** a:
- [ADR 0256](../../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) (catraca/sentinela/gate/cadência) — reaponta o framework de durabilidade para as camadas de valor.
- [ADR 0264](../../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) (trio `.tsx`+`.charter`+`.casos`) — adiciona a dimensão de cálculo ao trio.

## O que a ADR trava

1. **O ciclo-padrão de 4 passos** (adversário → gaps/backlog/changelog → régua-por-tela → catraca) como o único caminho de onda de módulo.
2. **A extensão da régua — plugar, não fundir:** `casos_coverage` no scorecard de tela + a dimensão D1 (cálculo de valor). Justificativa registrada: fundir screen-grade (UX) com assurance destruiria a clareza "tela bonita ≠ tela testada" (recomendação do inventário de réguas 2026-07-02).
3. **A fila de ondas encaixada** no `_Roadmap_Faturamento.md` e nos roadmaps ativos (T6 — proibido paralelo).
4. **O piso Tier-0:** o conjunto quente (dinheiro/estoque/fiscal) exige **três provas** — (a) teste de cálculo, (b) UC de comportamento defendido, (c) artefato de paridade se for migração. Coerente com [ADR 0271](../../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) (required = só Tier-0).

## Anti-padrões que a ADR proíbe explicitamente

- Gate de **presença** ("charter mudou no diff") — já rejeitado na proibicoes §descartados; enforcement é de **comportamento** (teste que quebra quando a função some).
- Régua nova paralela às 3 existentes (screen-grade/module-grade/casos) — é **extensão**, não substituição.
- Abrir onda de módulo do Faturamento fora do `_Roadmap_Faturamento.md`.

## Critério de pronto

ADR revisável em `proposals/`, frontmatter válido, **Wagner aprova**. Não mexe em código.

## Verificação

- `memory-schema-preflight` valida o frontmatter (status enum, related_adrs como slugs).
- PR docs-only; `governance-gate` não bloqueia (proposta, não edita ADR accepted).
