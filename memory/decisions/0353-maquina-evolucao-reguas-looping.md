---
slug: 0353-maquina-evolucao-reguas-looping
number: 353
title: "Maquina de evolucao em looping das reguas — ledger persistente, modo delta, composicao deterministica"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-26"
module: governance
tags: [reguas, evolucao, ledger, delta, adversario, anti-goodhart, governanca, looping, treinamento]
supersedes: []
superseded_by: []
related:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0329-doutrina-executavel-nao-prosa
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
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

## Decisões [W] — 2026-07-26 (merge deste PR = ato)

As 4 pendências abertas em 2026-07-19 ficam resolvidas assim. A recomendação técnica
de cada uma foi do [CC]; o merge é o ato do [W].

### D1 · Ratificar — **SIM**

Os Órgãos 1 e 2 já operam em produção sem lei escrita: o ledger tem `claims` 49 ·
`fraquezas` 57 · `retratos` 2 (série real, `2026-07-18` e `2026-07-26`), e o modo delta
está implementado no workflow canônico. Ratificar não cria nada — **formaliza o que já roda**.

### D2 · Wiring do looping — **Zelador ESCALA, nunca dispara**

Nem cron automático, nem cadência puramente manual: o meio-termo que o próprio
`ZELADOR.md` já descreve. O Zelador roda a SONDA (report-only: `reguas-indexar` + idade
do retrato do topo) e, quando acionável, **escala como resíduo com draft de 1 OK**
(*"rodar `Workflow reguas-do-sistema {modo:'delta'}`?"*). A execução continua sendo
do [W] / sessão dedicada.

**Por quê:** um delta custa ~2,5M tokens. Disparo automático gasta sem pedir; cadência
100% manual devolve o problema pro "você lembra" — que é o labirinto que esta máquina
existe pra matar. Escalar separa **lembrar** (automático, barato) de **gastar**
(humano, deliberado).

### D3 · Emenda da lápide 07-10 (braço discriminativo na Integração) — **EMENDAR**

O veredito de Integração deu `DIFERENCIAL_SISTEMA` em **81 de 81** casos. Binário que
só conhece um valor não discrimina — é a mesma família do `foundation-ratchet`
("0 failures em 300+ runs") e do drift-sentinel tautológico, que o §5 já enterrou.

A emenda (habilitar `REFUTADO_TB` quando o agente não nomeia incremento além da
IDENTIDADE, ou quando UM peer único monta o todo equivalente) mantém o **guard
anti-composição inviolável**: peers DIFERENTES cobrindo cada um um eixo continua
NÃO sendo refutação — somar slices-com-peer é o erro que a lápide-mãe matou.

**Ressalva honesta que fica registrada:** emendar HABILITA o negativo, não PROVA que
ele dispara. Proibido afirmar "agora discrimina" antes do placar de um full
pós-emenda. Critério de reabertura dos dois lados: se seguir 0/N, o medidor continua
carimbo; se disparar num caso que o [W] julgue diferencial real, a pergunta
over-corrigiu.

### D4 · Regras 16-17 (prosa × código) — **CÓDIGO**

Não entram como texto na skill. Viraram o Órgão 3 (composição determinística) e a
skill registra só o ponteiro. É a doutrina 0329 aplicada (*executável > prosa*) e a
lápide 2026-07-16 (*artefato não restateia o que outro sistema sabe melhor —
aponta pro dono*).

## Estado dos 6 órgãos no ato da ratificação (medido, não afirmado)

| Órgão | Estado | Recibo |
|---|---|---|
| 1 · Ledger `memory/reguas/` | ✅ vivo | `config` 5 · `retratos` 2 · `claims` 49 · `fraquezas` 57 |
| 2 · Modo delta | ✅ implementado | 12 pontos de código no workflow canônico |
| 3 · Composição determinística | 🟡 parcial | `regra_nota` já nos retratos; 4 pontos no workflow |
| 4 · Indexador `reguas-indexar.mjs` | 🟡 script pronto, 0 invocador executável | **por desenho** — o `ZELADOR.md` declara "sonda, não notificação" |
| 5 · Looping (cadência) | ❌ → **D2 resolve** | `ZELADOR.md` descreve o passo; nenhum cron o executa |
| 6 · Treinamento | ❌ não implementado | `licoes_candidatas` = 0 ocorrências; `fraquezas` sem campo de lição |

O Órgão 4 **não é obra parada** — é órfão declarado, na mesma classe dos CLI-manuais
legítimos (`adr-supersede`, `doc-id-stamp`). A ADR original já dizia: *"não crio cron
por conta própria"*.

O Órgão 6 fica **aberto e assumido**: ratificar não o implementa. Ele é o elo
achado→lição→two-strikes, e sem ele a máquina mede mas não aprende mais rápido.

## Reversão

Ledger é dado versionado (apagar = 1 PR); modo delta é aditivo (full intacto); indexador é advisory. Nada aqui bloqueia merge de ninguém.
