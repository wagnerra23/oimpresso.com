---
date: "2026-09-22"
time: "14:55 BRT"
slug: revisao-fluxos-maquinas-sem-baseline
tldr: "Revisor executável percorre os fluxos canônicos, resolve máquinas, wiring e provas e consulta o enforcement vivo sem usar baseline local como prova."
decided_by: [W]
cycle: null
prs: [7720]
next_steps:
  - "Pesquisar e fechar as 17 pendências declaradas pelo relatório"
  - "Ligar os quatro selftests anteriores que o selftest-registry encontrou órfãos"
related_adrs:
  - 0329-doutrina-documentacao-de-processo-executavel
---

# Revisão executável dos fluxos e máquinas, sem baseline local

Em 2026-09-22 foi criado `scripts/governance/revisar-fluxos.mjs`. A máquina descobre os
`FLUXO-*.md`, valida sete dimensões, resolve os executáveis citados, localiza invocador e prova,
executa os donos existentes e consulta o enforcement no GitHub vivo com `--live`.

O bite-test cobre documento completo, dimensão ausente, path fantasma, wiring, prova ligada e
remoto com ponto no nome. O workflow de governança invoca o teste e o `--check`.

Resultado medido: 5 fluxos · 6 máquinas citadas · 0 path fantasma. `FLUXO-DESIGN` e
`FLUXO-MAQUINAS` atendem ao contrato; Cancelamento, Deploy e Venda somam 13 lacunas documentais.
Quatro máquinas citadas ainda não têm prova localizada. A bateria local passou em inventário,
catracas e jornada; `selftest-registry` expôs quatro dívidas anteriores de wiring.

O campo `baseline_usada` é `false`. Sem autenticação ou runtime, o estado é `NÃO MEDIDO`.

## Estado MCP no momento do fechamento

As tools MCP de ciclos, tarefas, sessões e decisões não estavam disponíveis nesta sessão do
Codex. O estado foi medido na árvore Git, nos scripts e workflows, e na API viva do GitHub;
nenhum snapshot MCP foi simulado.
