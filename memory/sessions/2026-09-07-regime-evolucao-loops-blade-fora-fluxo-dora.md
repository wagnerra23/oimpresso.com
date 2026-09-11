---
date: "2026-09-07"
topic: "Regime de evolução por loops vira programa MEDIDO (10 etapas com teste) + ADR 0391 (Blade fora) + medidor de fluxo DORA/Flow do sistema — 3 PRs"
authors: ["C", "W"]
outcomes:
  - "#6948 hook loop-fechar-check estendido (--manifest, detect comando, tri-estado, cache 24h) + manifesto .claude/regime-evolucao.json E1..E10 — selftest roda o detect real de cada etapa"
  - "#6949 ADR 0391 proposta: Blade fora do regime e morre na migração; regime completo para tudo que não é Blade; programa medido, não afirmado"
  - "#6956 scripts/governance/fluxo-sistema.mjs: DORA 4 chaves + retrabalho + fila/WIP (proxy) + adoção — cliente-level e flow_time_us declarados not_yet_measured"
  - "Achado: agent-pr-outcomes (DORA do agente) filtra [CC] e conta 9 de 800 PRs — chip task_3735b9ec aberto e em execução"
prs: [6948, 6949, 6956]
related_adrs: ["0391-regime-de-evolucao-por-loops-blade-fora"]
---

# Sessão 2026-09-07 — como o sistema evolui com o tempo, em quais áreas, e como medir

## TL;DR

[W] perguntou como o sistema evolui e onde isso vale. A resposta medida mostrou 6 loops universais e 6 por área com cobertura desigual, e Blade fora de todo loop de tela. [W] decidiu Blade fora (morre na migração) e pediu plano com toda etapa válida por teste. Entrou o programa medido (10 etapas, detect por comportamento), a ADR 0391 e um medidor de fluxo DORA + Flow do sistema inteiro. Três PRs mergeados.

## Sequência

1. **Pergunta 1** — *"como fazer o sistema evoluir com o tempo? descreva cada"*. Resposta a partir do canon: 12 loops (sinal de entrada · ADR por sucessão · lápide+ledger · catracas · derivado>escrito · trio por tela · design Cowork · métrica fechando o loop · memória em git+MCP · verticais por sinal · grade vs mercado · governança da governança) e onde o sistema está fraco (LC-08 126×, 680 US sem dono, SDD 42,5).
2. **Pergunta 2** — *"vai servir para todas as máquinas ou só algumas partes?"*. Medido contra `origin/main`: telas Inertia 218 (charter 218, casos 152, E2E 54, A11Y 20, scorecard 186 pela porta viva); Blade 1.085 views; 7 lanes Pest required; SDD em 11 de 32 módulos; 11 módulos sem lane Pest. Contagem manual inicial (38/84) estava errada; corrigida pela porta viva antes de virar canon.
3. **Decisão [W]** — *"blade fica fora. ele vai morrer depois de migrar. pode fazer isso tudo"* + *"crie o plano e torne todas as etapas válidas com teste"*.
4. **Construção** — extensão do dono (`loop-fechar-check.mjs`) em vez de máquina nova (LC-19); detect `comando` responde à LC-11 dentro do mesmo hook que a cometeu no item #6 do IA-OS; tri-estado por §5 2026-07-29; alvo derivado da saída por §5 2026-07-17; cache 24h porque 65s por sessão faria o banner ser desligado.
5. **Pergunta 3** — *"isso é o que eu preciso agora? é o que as grandes fazem?"*. Resposta: instrumento, não evolução; comparação por prática (fitness functions, strangler fig, DORA, sinal de uso, SLO, feature flag, postmortem) com fonte; o que falta é execução + sinal de cliente + 2-3 métricas de fluxo + 1 SLO.
6. **Pergunta 4** — *"quais fluxos eu deveria ter? compare com as grandes"* + *"pode fazer tudo"*. Medido DORA em 30d e construído o medidor. Achado do medidor cego (`[CC]` vs `[C]`).
7. **Merge** — autorização textual; #6948/#6949 já mergeados por [W]; #6956 mergeado pelo agente após CI verde (1 vermelho intermediário por drift do índice gerado `MAQUINAS-INVENTARIO.md`, regenerado).

## Números (recibos no handoff e nos PR bodies)

Fluxo 30d: 925 deploys · lead time PR p50 0,7h · CFR pipeline 6,8% · recuperação p50 22 min · retrabalho 27,8% · PRs>300 32,1% · telas servidas 18,8% · WIP wagner 8/2 · fila 680/525/129d.

## Lições (também no handoff)

Contagem manual vs porta viva (2× na sessão, ambas pegas antes do canon) · medidor da casa medindo a população errada · `/tmp` e BOM no Windows · `block-memory-drift` em ADR untracked · nenhum gate novo, o selftest do manifesto é a defesa.

## Pointers

Handoff `memory/handoffs/2026-09-07-2130-regime-evolucao-loops-blade-fora-fluxo-dora.md` · ADR 0391 · PRs #6948 #6949 #6956.
