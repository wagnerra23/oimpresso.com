---
proposal_id: reguas-loop-maquina-evolucao
status: proposed
created: 2026-07-19
proposed_by: claude-code
decided_by: wagner
parent_adr: 0330 (mapa dos níveis)
related_adrs: [0329, 0333, 0334, 0159, 0275, 0336]
type: mecanismo-de-processo
---

# Máquina de evolução em looping das réguas — incremental, barata, auto-persistente

- Status: proposto (decisão [W])
- Data: 2026-07-19
- Autor: [CC], por ordem direta [W] 2026-07-19: *"isso é caro, precisa de um mecanismo que evolua mais eficiente, e mais organizado e em looping. Estrutura de evolução e treinamento mais rapido nivel 9.75. construa essa maquina"*
- Relacionadas: skill `reguas-do-sistema` (regras 1-15) · ADR 0330/0333/0334 (mapa + eixos) · ADR 0329 (executável não-prosa) · errata 0159 (Goodhart) · lápides §5 2026-07-09/10/17/18

## Contexto — o custo, com recibo

O ciclo de medição de 2026-07-18/19 custou **~22M tokens** (grade completa 11,4M · adversário 4,9M · parcial 5,7M com a queda). Os desperdícios foram **medidos pelo próprio ciclo**, não estimados:

| Sangramento | Recibo | Custo |
|---|---|---|
| **1. Gaps falsos re-descobertos** ("existia-mas-invisível") | 7 na completa + **15/16 na parcial** — mecanismos fora do mapa 0330 re-flagrados como gap pela 3ª vez | pesquisa + verificação repetidas a cada rodada |
| **2. Fase Integração = carimbo** | REFUTADO_TB **0 em 81 vereditos contados** (8 runs, ledger 2026-07-18) — binário que só conhece um valor | ~23 agentes × ~130k/rodada ≈ 3M sem poder discriminativo |
| **3. Composição por LLM re-lendo 270k chars** | 2 strikes contados: placar de 07-10 divergia do próprio journal (9 refutadores vs "0/2/3/3" publicado) + 07-18 (fusão de nota, dupla-Δ, listas contraditórias) → adversário inteiro + rodada parcial só pra corrigir | ~7,5M na cadeia corretiva |
| **4. Claims sem identidade persistente** | ledger 2026-07-18: "claims re-geradas a cada rodada, mapeamento 1:1 impossível" — 18 EMPATADAS re-refutadas do zero toda rodada | ~18 × 127k ≈ 2,3M/rodada re-comprando vereditos estáveis |

## Decisão — os 6 órgãos (todos EXTENSÃO de dono existente)

### Órgão 1 — Ledger persistente `memory/reguas/` (o ESTADO que faltava)
`config.json` (TTLs + paths por dimensão) · `retratos.json` (série temporal de notas com recibo e regra de composição declarada) · `claims.json` (claims com **ID persistente**, veredito, peer, TTL, correção obrigatória) · `fraquezas.json` (fraquezas com nota/evidência/degrau/flag existia-invisível).
**Fecha a pendência aberta da regra 12 da skill** (*"as notas de cada retrato precisam de artefato versionado no repo — formato pendente de decisão Wagner"*). Não é catraca nem gate — é o estado versionado do MEDIR.

### Órgão 2 — Modo `delta` no workflow canônico (o corta-custo)
`args.modo: 'delta'` em `reguas-do-sistema.js` (EXTENSÃO do dono — lápide "nunca motor paralelo"):
1 agente **delta-scan** (effort low) lê o ledger + `git log --since=<último retrato>` por `paths_por_dimensao` → só dimensões com Δ material (`delta_min_commits`) re-verificam, e só as fraquezas DELAS; claims só re-refutam se **TTL vencido** (mercado 90d · ACIMA 30d — as perigosas expiram rápido); Integração **não roda** no delta (claims novas só nascem no full). Lado-mercado reusado do ledger (regra 5 da skill já permite).
**Custo-alvo por delta: ≤2,5M tokens** (vs 11,4M full). Full continua existindo — trimestral ou quando o delta acumular sinal.

### Órgão 3 — Composição determinística (regras 16-17 do adversário viram CÓDIGO, não prosa)
A Fase Grade deixa de re-ler 270k chars pra decidir números: o **JS monta as tabelas do journal** (1 fraqueza = 1 linha com a nota do SEU verificador; nota da dimensão = **média aritmética, 1 decimal**, declarada; fusão proibida por construção; dono-único por dimensão do escopo). O agente compositor escreve SÓ a prosa (diferenciais, degraus, leitura fria) e é proibido de alterar número. O **disclosure do placar** (REFUTADO_TB histórico) sai do ledger automaticamente — regra 17 mecanizada. Doutrina 0329: executável > prosa — por isso as regras 16-17 NÃO entram como texto na skill; entram como código aqui.
*Nota de transição:* notas históricas (≤2026-07-18) foram compostas pelo sintetizador; a regra da média vale DESTE retrato em diante (campo `regra_nota` no retrato — sem reescrever história).

### Órgão 4 — Indexador `onde_indexar` (mata o sangramento nº 1 na fonte)
`scripts/governance/reguas-indexar.mjs` (report-only, advisory): consome os payloads `onde_indexar` de `fraquezas.json` (existia-invisível ainda não indexado) → fila legível por alvo (mapa 0330-sucessor / BRIEFINGs) pro zelador/PR humano; `--marcar` fecha itens indexados; `--selftest` com fixture boa/ruim (a casa exige mordida provada). SEM gate novo (lápide 0336: promoção só com mordida).

### Órgão 5 — O looping (cadência)
Delta acoplado à cadência existente — **proposta: o Zelador diário** (piloto 14d já em voo) dispara o delta 1×/semana OU quando `git log` acumular N commits nos paths mapeados; full na cadência trimestral da skill. **Wiring é decisão [W]** (1 linha no zelador) — a máquina nasce invocável barata; não crio cron por conta própria.

### Órgão 6 — Treinamento (o loop de aprender mais rápido)
Cada rodada emite `licoes_candidatas` (NAO_EXISTE reincidente · achado adversarial SUSTENTADO · gap falso re-descoberto) persistidas no ledger → alimentam o fluxo **two-strikes existente** (LICOES_CODE + corpus `origin:incidente` do grade.mjs). Extensão da ponte já pedida nos chips B3/C13 — não motor novo.

## "Nível 9,75" — a régua DA MÁQUINA (com trava anti-Goodhart)

⛔ **9,75 NUNCA é alvo de nota de dimensão** — perseguir nota é o anti-padrão da errata 0159 e da própria skill ("a grade aponta ONDE trabalhar; o índice sobe como consequência"). O 9,75 é o padrão de operação DA MÁQUINA, medido:

| Medida | Alvo | Recibo |
|---|---|---|
| M1 custo/rodada-delta | ≤ 2,5M tokens (−80% vs full) | usage do task do workflow |
| M2 gaps-falsos por rodada | tendência → 0 (era 7+15) | verificações JA_EXISTE_TOTAL sobre fraquezas NOVAS |
| M3 composição fiel ao journal | 100% por construção | placar = contagem JS; selftest |
| M4 lead-time achado→trava | < 48h | data do achado vs merge do fix (precedente: #4546→#4547 em <24h) |

## Lápides §5 respeitadas (checagem prévia, não posterior)

| Lápide | Como esta máquina respeita |
|---|---|
| Roadmap/motor paralelo (07-09) | tudo é extensão: workflow canônico + zelador + two-strikes + skill existente |
| Catraca redundante (07-09) | ledger não é gate; indexador é report-only |
| `verificado_em` auto-declarado (07-09) | delta usa git-log (oráculo aprovado), nunca campo auto-escrito; TTL é cadência de re-verificação EXTERNA, não frescor auto-declarado |
| Presence-gate (07-09) | zero checagem de presença; selftest é fixture-que-morde |
| Perseguir nota (0159) | trava explícita acima; metas são da máquina |
| Chokepoint fantasma (07-09) | invocação provada: delta roda pelo MESMO comando da skill; wiring de cadência declarado como pendência [W], não prometido |

## Pendências de decisão [W]

1. Ratificar esta proposta (merge = ato).
2. Wiring do looping no Zelador (1 linha) OU cadência manual.
3. Emenda da lápide 07-10: braço discriminativo na pergunta de Integração (o carimbo 81/81) — OU manter e aceitar que Integração só roda no full para claims novas (o que este desenho já implementa).
4. O destino das regras 16-17 "de prosa" na skill: esta ADR propõe que NÃO entrem como texto (viraram código — Órgão 3); registrar só o ponteiro.

## Reversão

Ledger é dado versionado (apagar = 1 PR); modo delta é aditivo (full intacto); indexador é advisory. Nada aqui bloqueia merge de ninguém.
