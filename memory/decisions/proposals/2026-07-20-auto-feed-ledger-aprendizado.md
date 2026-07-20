---
proposal_id: auto-feed-ledger-aprendizado
status: proposed
created: 2026-07-20
proposed_by: claude-code
decided_by: wagner
parent_adr: 0344 (two-strikes cobre processo)
related_adrs: [0344, 0256, 0224, 0094, 0257, 0264, 0275]
type: mecanismo-de-processo
---

# Auto-feed do ledger de aprendizado — reconciliação §5 ↔ `LICOES_CODE.md` (surface forward-only)

- **Status:** proposto (decisão [W]). O merge deste PR = ratificação (R10), mesma mecânica das promoções 0327/0336/0344.
- **Data:** 2026-07-20
- **Autor:** [CC]. **Origem:** o follow-up que a própria [ADR 0344](0344-two-strikes-cobre-processo.md) §"Escopo NÃO incluído" **ADIOU** (não recusou): *"Feed automático dos 'últimos erros' → ledger (o elo manual descrito acima) — ADR própria."*
- **Guarda-corpo herdado (ADR 0344):** *"NUNCA promover este contador a gate/catraca `required` sem antes resolver o auto-feed."* Esta proposta resolve o auto-feed **mantendo tudo advisory** — não pede promoção a required.

## Rótulo honesto (leia ANTES do resto — senão isto vira o próprio LC-08)

> Este mecanismo **NÃO** "lê os últimos erros de fontes reais", **NÃO** "fecha o loop", **NÃO** auto-classifica.
> Ele **reconcilia dois documentos curados à mão** — o §5 de `memory/proibicoes.md` (prosa-evidência) e o
> ledger `memory/LICOES_CODE.md` (contador) — e **surfaça** as recorrências que o **próprio autor já declarou**
> no §5 ("reincidência / mesma família / EMENDA da lápide / …") e que **nenhuma classe LC ainda conta**.
>
> O que muda: o elo manual da ADR 0344 encolhe de **[detectar + julgar + registrar]** para **[julgar + registrar]** —
> a **DETECÇÃO da recorrência-DECLARADA** vira mecânica a cada SessionStart. O **julgamento** (vira LC? qual? é ruído?)
> e o passo **a montante** (alguém ESCREVER a lápide no §5) **seguem HUMANOS**.
>
> Vender isto como "o auto-feed que lê o erro real" seria uma instância de **LC-08** (afirmar-sem-medir-fonte-certa):
> o que se detecta é a **nota do humano sobre o erro**, não o erro. É o `risco_maior` que o cético adversarial cravou,
> e a mitigação é este parágrafo — no banner, nesta ADR e no PR.

## Contexto — o gap que a ADR 0344 deixou aberto

O loop two-strikes de PROCESSO ([ADR 0344](0344-two-strikes-cobre-processo.md), aceita 2026-07-20) tem **um elo manual**: o campo `Ocorrências` do ledger é mantido **à mão**. Ninguém cruza automaticamente os erros com o ledger — então uma classe pode reincidir e ninguém contar (o "esquecimento" que [W] sente). O hook `licoes-code-two-strikes.mjs` **alarma** quando uma classe já contada tem `Ocorrências ≥ 2` sem gate — mas **não descobre** a reincidência que o número perde.

## Processo — workflow adversarial ANTES de codar (como na promoção da 0344)

3 lentes independentes (arquiteto · cético · escopo-mínimo) → síntese gated pelo cético (~657k tokens, 4 agentes). Só codei **o que sobreviveu ao cético**. O cético matou boa parte da hipótese-semente e **corrigiu o placement** (o arquiteto queria um Check novo no `memory-health.mjs`; o cético provou que o dono do tema é o próprio hook).

## Decisão — 2 sinais, no HOOK, advisory

**Onde mora:** estende `.claude/hooks/licoes-code-two-strikes.mjs` (+ `.test.mjs`). **NÃO** um Check novo no `memory-health.mjs` (rachar a lógica do ledger em 2 arquivos = duplica régua consolidada, lápide §5 2026-07-09) nem script standalone. O hook já parseia o ledger, já roda no SessionStart, já é advisory `exit 0`. O §5 fica **INTOCADO** (prosa append-only; nenhum id adicionado às headings → não acorda os gates diff-aware da lápide 2026-07-12).

**S3 — surface forward-only de recorrência DECLARADA não-contada (o núcleo).** Diferença de conjuntos entre (a) as lápides do §5 que carregam **marcador de recorrência escrito pelo autor** e (b) as datas que o ledger cita. `frontier = max(datas do §5 que a linha Ocorrências cita)` — **DERIVADO, não watermark auto-escrito**. Surfaça as lápides marcadas **> frontier** e fora do ledger, mais-recente-primeiro, capado (~5), + linha de contagem do backlog pré-frontier. **Não** constrói cadeia/edge, **não** resolve qual lápide o marcador aponta, **não** auto-classifica.

**S2 — recibo pendurado (fact-anchor fraco, forma Check-T).** Cada data que o ledger cita resolve a ≥1 lápide `### YYYY-MM-DD` real do §5. Data citada sem lápide → warn (typo/recibo fabricado). Verde = *"as datas citadas existem no §5"* — **NUNCA** *"o recibo/contagem está correto"*. É a trava anti-fabricação sobre os recibos que o próprio loop S3 produz.

**Como cobra:** no banner do SessionStart, ao lado do alarme two-strikes. Advisory `exit 0` sempre — não é workflow, não entra em `required-checks-baseline.json`. Dry-run contado (evidência + ferramenta [W]): `node .claude/hooks/licoes-code-two-strikes.mjs --reconcile`.

## O que sobreviveu × o que foi rejeitado (registro honesto — cada rejeição tem lápide)

| Item | Veredito | Por quê |
|---|---|---|
| **S3 surface forward-only** | ✅ constrói | diferença-de-conjuntos (conteúdo derivado, ≠ presence-gate); marcador author-declared (≠ inferência/achar-a-raiz 07-15); frontier derivado (≠ watermark auto-declarado 07-01/07-09); forward-only + cap (≠ big-bang 07-12) |
| **S2 recibo pendurado** | ✅ constrói (minor) | forma Check-T (o §5 nomeia como legítima); pode ficar vermelho (não tautológico); só na forma surface-assistida |
| **S1 igualdade estrita `Ocorrências`==nº-recibos** | ❌ rejeitado | FP no LC-08 no dia 1 (a linha dá 3 contagens por 3 regras; LC-06 usa formato diferente) + cego ao **under-count** (o caso valioso) — catraca sobre campo auto-declarado (lápides 07-01 `last_validated` / 07-09 `verificado_em`) |
| **Cadeia/família entre lápides** | ❌ rejeitado | "achar a raiz" (07-15) + resolução de data ambígua (datas do §5 não-únicas) = match sintático frágil (allowlist-de-pasta 06-30 / guard @scope 07-09) |
| **Check novo no `memory-health.mjs`** | ❌ rejeitado | duplica o dono do tema (o hook já lê o ledger) + põe surface-de-triagem-humana num gate de PR que o guarda-corpo 4 proíbe. Reservado como caminho FUTURO só se S2 quiser CI-time (mover, não duplicar) |
| **Watermark "reconciliado-até `<data>`"** | ❌ rejeitado | campo auto-declarado (07-01/07-09). Substituído pelo **frontier derivado** das datas que os recibos realmente apontam — só avança quando a reconciliação de fato aconteceu |
| **Fonte CI-vermelho → classe** | ❌ rejeitado | mapear CI-red→classe é interpretação = **LC-08 em pessoa** + fonte quase vazia (ADR 0344 mediu CI 39/40 verde) |
| **Fonte git reverts/hotfix** | ❌ rejeitado | fonte vazia (0 em 60d) + sem tag confiável = chokepoint fantasma (07-09 `flag:set`). Reabre só com sinal medível (ADR 0105) |
| **Fonte degradação-de-sessão** | ❌ rejeitado | não-derivável (sinais in-session, não persistidos); auto-reporte cai em 07-01; how-trabalhar prova que "agente em degradação não se auto-detecta" |
| **Backfill em massa das ~35 lápides pré-frontier** | ❌ rejeitado | big-bang de legado (07-12) + a ADR 0344 adiou. Backlog vira 1 linha de contagem, não nag por-item |

## Evidência — dry-run CONTADO (obrigatório; senão a proposta é o próprio LC-08)

`node .claude/hooks/licoes-code-two-strikes.mjs --reconcile` sobre os arquivos reais (2026-07-20):

```
frontier 2026-07-17 · 34 lápides no §5 · 15 com marcador · 1 LC cita §5 (LC-08)
recibos 3/3 resolvem · 0 pendurado
surface (2): 2026-07-20 "Aposentar mcp-first-warning…" (mesma família)
             2026-07-19 "EMENDA da lápide 2026-07-10…" (mesma família)
0 pós-frontier sem marcador · 13 backlog pré-frontier (deferido, forward-only)
```

**O dry-run pegou um bug que a predição do workflow não viu** (a razão de ele ser obrigatório — [ADR 0344 §Recibo de validação](0344-two-strikes-cobre-processo.md)): o `**Ref:**` do LC-08 diz *"raio-X 2026-07-20"* (metadata, não recibo). A 1ª versão extraía data de **qualquer** linha com "§5" → puxou 07-20 e falseou o `frontier` pra 07-20, **suprimindo** o surface real. Fix: extrair só da linha **`Ocorrências:`** (a convenção do recibo). Travado por teste nomeado. **Os 2 itens de hoje (07-19/07-20) são provavelmente ambos RUÍDO** (uma emenda de processo + uma rejeição-de-ideia) — o que valida "volume baixo" e "o humano PRECISA decidir", e ainda **não** prova utilidade viva sustentada (herda a ironia da ADR 0344).

Controle-negativo (fixture boa/ruim) no `.test.mjs`: (a) frontier cobre tudo → 0 surface + banner vazio; (b) lápide marcada pós-frontier não-contada → **surfaça** (morde); (c) recibo sem lápide → **pendura** (morde); (d) §5 vazio/ausente → neutro (sandbox-safe, `sem fonte → não inventa`).

## Gap residual (sem vergonha — deixado de fora de propósito)

1. **O elo A MONTANTE segue humano:** o passo erro→lápide (alguém escrever no §5) não é automatizado. Reconcilia dois docs curados à mão — a nota do humano sobre o erro, não o erro (M1/M2 do cético).
2. **Recorrência SEM marcador = falso-negativo silencioso** (autor esquece de escrever "reincidência"). Mitigado: lista de marcadores explícita/tunável + meta-count de "pós-frontier sem marcador" (a cauda não fica escondida). Advisory → FN não quebra nada.
3. **Frontier é por-LEDGER, não por-classe** — declaração de classe nova datada < frontier é perdida (per-classe exigiria classificar cada lápide = o fuzzy proibido).
4. **Lápide == frontier (mesmo dia)** é perdida até o log avançar.
5. **S2 prova existência-da-data, não identidade-da-lápide** (datas não-únicas) — verde ≠ "recibo correto".
6. **CI-red / revert / degradação** ficam fora por lápide, não por preguiça.
7. **Ironia herdada da ADR 0344:** a LÓGICA está validada por fixture; a UTILIDADE VIVA (surface-útil vs ruído ao longo de sessões) é não-validada. Os 2 surfaces de hoje são provavelmente ruído → prova "volume baixo", não "valor sustentado".

## Não é presence-gate · não é required · reversão

- **Não é presence-gate:** mede DIFERENÇA DE CONJUNTOS (recorrências declaradas ∖ datas citadas) e CONTRADIÇÃO de recibo (Check-T), não "a seção existe / o campo existe". E **não afirma o próprio enforcement em presente** (o banner reporta os itens/o número, nunca "este gate bloqueia" — lápide 07-16).
- **Não é required:** vive no hook SessionStart `exit 0` sempre. Um surface que devolve o julgamento pro humano nunca é candidato a bloquear. Promover exigiria uma sonda que MORDE — e este não é. Se um dia só o S2 quiser CI-time, o caminho é MOVER a lógica pra um Check-T-sibling em `memory-health.mjs`, advisory-primeiro (ADR 0275) — nunca duplicar, nunca required no calado.
- **Reversão:** remover o bloco de reconciliação (uma função + a chamada). Zero efeito no §5 e nos contadores LC (o hook só LÊ). Degradê: se ruidoso mas não inútil, rebaixa pra só a linha-de-contagem. Se S2 der FP (colisão MM-DD cross-ano), desliga só S2 mantendo S3.

## Ratificação (R10)

Merge deste PR por [W] = ato de ratificação. **Ao aceitar**, o passo canônico "não re-propor" é adicionar **um** tombstone consolidado ao §5 do `proibicoes.md` cobrindo as alternativas rejeitadas acima (S1-estrita · watermark · CI-red→classe · Check-em-memory-health · big-bang) — deliberadamente **não** feito nesta PR (o §5 fica intocado enquanto a decisão é proposta; a rejeição vira canon junto com o aceite, forward-only).
