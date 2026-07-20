---
status: proposal
title: Loop two-strikes cobre erro de PROCESSO (não só de código); advisory ≠ defesa mecânica
proposed_by: Wagner + Claude
proposed_at: 2026-07-20
relates_to:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Loop de aprendizado two-strikes cobre PROCESSO, e advisory-só conta como "sem defesa mecânica"

> **Status:** `proposal` — [W] promove a ADR aceita (próximo número canônico) após validar o alarme na prática.

## Contexto — o raio-X de 2026-07-20

[W] relatou sensação de "sistema instável, erros recorrentes, esquecimentos". Rodei os mecanismos vivos pra medir (evidência, não narração):

| Sinal | Medido |
|---|---|
| `gate-selftest.mjs` (as catracas mordem, GT-G6, `required`) | **68/68 mordem** ✔ |
| CI vermelho (últimos 40 runs) | **1 falha** |
| reverts/hotfix/incidentes em prod (60d) | **0** com marcador no git |
| ledger de código `LICOES_CODE.md` | 7 lições, todas com gate ou Ocorr. 0-1 |
| aprendizado registrado no §5 do `proibicoes.md` | 1 (mai) → 5 (jun) → **28 (jul)** |

**Diagnóstico:** o loop de **código** está saudável e fechado (CI verde, quase zero revert, lições de código mecanizadas). A dor não é bug de código escapando — é o loop de **processo/comportamento** (os "esquecimentos"): o §5 do `proibicoes.md` registra as lições feito uma metralhadora (28 num mês), mas **não tem contador de reincidência**. Então uma classe pode reincidir e ninguém vê.

Prova concreta: a família **"afirmar/derivar/medir a partir da fonte ou medida errada, sem provar"** aparece como **5 tombstones separados em 3 dias** (§5: 07-15 achado-sem-varredura · 07-16 medir-propriedade-errada · 07-17 oráculo-errado · 07-17 deduzir-quem-roda-parseando · 07-17 crontab-l-falso-negativo). Cada um virou prosa; nenhum acionou o `two-strikes` — porque o §5 não tem `Ocorrências` pra o hook contar.

## Assimetria a corrigir

| Lado | Ledger | Contador + alarme? |
|---|---|---|
| Código/infra | `LICOES_CODE.md` + hook `licoes-code-two-strikes.mjs` | **Sim** (Ocorrências ≥ threshold + `Gate: none` → alarma no SessionStart) |
| Processo/comportamento | §5 `proibicoes.md` (só prosa) | **Não** — reincidência invisível |

O tema do `two-strikes` é *reincidência → defesa mecânica*. Um erro de processo é instância disso **igual** a um erro de código. Logo o ledger do two-strikes deve cobrir os dois — não é régua nova (o §5 proíbe régua duplicada), é **estender o dono do tema**.

## Decisão proposta

1. **O ledger `LICOES_CODE.md` (lido pelo hook) passa a cobrir também classes de PROCESSO/comportamento de agente.** O §5 do `proibicoes.md` continua sendo a **prosa-evidência** (append-only, intocado); o `LICOES_CODE.md` é o **contador** que torna a reincidência visível pro hook. São camadas diferentes (prosa vs contador), não duplicação.

2. **Cobertura só-`advisory` conta como "sem defesa mecânica".** A doutrina two-strikes exige defesa **MECÂNICA** (bloqueia/morde). Um `nudge`/`warn` advisory que a classe atravessa 5× não é defesa mecânica — é evidência de que *falta* uma. O `semGate()` do hook passa a tratar `Gate: advisory|parcial|insuficiente <...>` como "sem gate" → segue alarmando até virar sonda que morde. (Nome de gate real que só *menciona* "advisory" entre parênteses NÃO casa — só o prefixo declarado.)

3. **Backfill forward-only + oportunístico.** A lápide 2026-07-12 do §5 proíbe big-bang de backfill de legado. Só a classe que está gritando entra agora — **LC-08 `afirmar-sem-medir-fonte-certa` (Ocorrências: 5, Gate: advisory)** → dispara o alarme no próximo SessionStart. As demais viram `LC-NN` quando reincidirem.

## Efeito imediato

No próximo início de sessão o banner passa a gritar:

```
=== LICOES [CODE] - gatilho two-strikes (audit loop de aprendizado) ===
  [!] 1 classe(s) repetiram (>= 2x) e NAO tem gate. PROMOVER A DEFESA MECANICA:
      LC-08 - Afirmar/derivar/medir a partir da FONTE ou MEDIDA errada ...  (5x, sem gate)
```

Ou seja: a resposta à pergunta do [W] — *"dá pra ler os últimos erros e ver se viraram aprendizado?"* — passa a ser **sim, e o sistema cobra** quando um erro reincidiu e ainda não virou defesa.

## Escopo NÃO incluído (follow-ups honestos — não construir agora)

- **Estado "gateado-mas-vazando" rico:** hoje o contador não distingue "ocorreu antes do gate" de "reincidiu DEPOIS do gate". Modelei a família fonte-errada como `Gate: advisory` (verdade: os hooks existentes não bloqueiam) — mas um dia o ledger pode ganhar `Ocorrências-pós-gate` pra medir vazamento com gate bloqueante já no lugar.
- **Feed automático dos "últimos erros" → ledger.** O elo AINDA é manual: alguém registra a ocorrência. O loop 100% fechado seria um script que lê CI vermelho / incidentes / degradação de sessão e cruza com o ledger, alarmando classe recorrente sem lição. É a peça grande; fica pra ADR própria.
- **A sonda que MORDE a classe fonte-errada.** O alarme diz "promova a defesa mecânica" — a defesa em si (ex: bloquear "a raiz é X" sem varredura contada citada) é trabalho separado, guiado pelo próprio alarme.

## Reversão

Trivial: remover LC-08 do `LICOES_CODE.md` e reverter o `semGate()` do hook. Advisory (exit 0 sempre); nunca bloqueia merge nem sessão.
