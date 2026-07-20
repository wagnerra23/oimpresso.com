---
date: "2026-07-20"
topic: "Auto-feed do ledger de aprendizado — reconciliação §5↔LICOES_CODE (surface forward-only) no hook two-strikes"
authors: [C]
prs: [4599]
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0094-constituicao-v2-7-camadas-8-principios
---

# Sessão 2026-07-20 — auto-feed do ledger (o elo manual que a ADR 0344 adiou)

> **TL;DR:** construí o **auto-feed** que a [ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) §Escopo adiou (follow-up #1, `task_d2c3d9be`). O hook `licoes-code-two-strikes.mjs` passa a **reconciliar** o ledger `LICOES_CODE.md` com o §5 do `proibicoes.md` no SessionStart e **surfaça** as recorrências que o **autor já declarou** ("reincidência/mesma família/…") e que nenhuma classe LC ainda conta — **advisory, forward-only, sem auto-classificar**. Desenho passou por **workflow adversarial de 3 lentes** (arquiteto·cético·escopo → síntese gated pelo cético, ~657k tok): sobreviveram **S3 (surface)** + **S2 (recibo pendurado)**; o cético matou S1-estrita, cadeia/família, watermark, Check-em-memory-health e as fontes CI-red/reverts/degradação — **cada uma com lápide §5**. O **dry-run contado** (obrigatório) pegou um bug real que a predição do workflow não viu. PR [#4599](https://github.com/wagnerra23/oimpresso.com/pull/4599) aberta — **aguarda ratificação [W]** (não mergeei; R10).

## O problema (o gap da 0344)

O loop two-strikes de PROCESSO tem **um elo manual**: o `Ocorrências` do ledger é mantido à mão. Ninguém cruza os erros com o ledger → uma classe reincide e ninguém conta (o "esquecimento" que [W] sente). A 0344 §"Escopo NÃO incluído" nomeou isso como ADR própria — esta sessão.

## O que sobreviveu ao cético (e onde mora)

- **Placement corrigido pelo cético:** o arquiteto queria um Check novo no `memory-health.mjs`; o cético provou que o **dono do tema do ledger é o próprio hook** (já lê o ledger, roda no SessionStart, é advisory) → Check novo = duplica régua consolidada (lápide 07-09) + põe triagem-humana num gate de PR. **Estendi o hook.** O §5 fica INTOCADO.
- **S3 — surface forward-only:** diferença de conjuntos entre {lápides do §5 com marcador de recorrência do autor} e {datas que o ledger cita}. `frontier = max(datas do §5 que a linha Ocorrências cita)` — **derivado, não watermark auto-escrito**. Surfaça as marcadas > frontier e fora do ledger, capado, + linha de backlog. **Não** constrói cadeia, **não** resolve qual lápide, **não** auto-classifica.
- **S2 — recibo pendurado:** cada data citada resolve a uma lápide `### YYYY-MM-DD` real (forma Check-T). Verde = "a data existe", nunca "o recibo está correto".

## Rótulo honesto (o `risco_maior` que o cético cravou)

Isto **não** "lê o erro real de fontes reais", **não** "fecha o loop", **não** auto-classifica. Reconcilia **dois docs curados à mão** e detecta a **nota do humano sobre o erro**, não o erro. O elo erro→lápide e o julgamento seguem humanos. Vender como "auto-feed que lê o erro" seria o **próprio LC-08** (afirmar-sem-medir). O que muda: **[detectar+julgar+registrar] → [julgar+registrar]** — a detecção da recorrência-DECLARADA vira mecânica.

## O dry-run contado pegou um bug (a razão de ele ser obrigatório)

`node .claude/hooks/licoes-code-two-strikes.mjs --reconcile` sobre os arquivos reais: o `**Ref:**` do LC-08 diz *"raio-X 2026-07-20"* (metadata, não recibo) → a 1ª versão extraía data de **qualquer** linha com "§5" e falseou o `frontier` pra 07-20, **suprimindo** o surface real. Fix: extrair só da linha `Ocorrências:` (a convenção do recibo). Resultado correto: `frontier 07-17 · 34 lápides · 15 marcadas · 3/3 recibos · surface 07-19+07-20 (ambos "mesma família", provavelmente ruído) · 0 cauda · 13 backlog`. **Os 2 surfaces de hoje serem provavelmente ruído valida "volume baixo" + "humano decide" — não prova utilidade viva sustentada** (ironia herdada da 0344, declarada).

## Evidência

- `--selftest` **28/28** (bite-test: surface morde, S2 morde, good-fixture não avermelha, §5 vazio → neutro sandbox-safe — controle-negativo obrigatório).
- `memory-health.mjs` **0 fail** (12 warns pré-existentes, 0 mencionam meus arquivos).
- Drive-by: `--selftest` usava `url.pathname` (`/D:/…` → `MODULE_NOT_FOUND` no Windows) → `fileURLToPath`. Só mordia no Windows do [W]; CI (Linux) chama o `.test.mjs` direto.

## Rejeitados (cada um com lápide §5 — registro anti-regressão)

S1-igualdade-estrita (FP LC-08 dia 1 + cego ao under-count · 07-01/07-09) · cadeia/família (achar-a-raiz 07-15 + datas ambíguas 06-30/07-09) · watermark auto-declarado (07-01/07-09 → frontier derivado) · Check-em-memory-health (07-09 duplica régua) · CI-red→classe (LC-08 + fonte 39/40 verde) · git-reverts (chokepoint-fantasma 07-09 + 0 em 60d) · degradação-de-sessão (não-derivável) · big-bang backfill (07-12).

## Arquivos

`.claude/hooks/licoes-code-two-strikes.mjs` (+ funções puras + `--reconcile` + drive-by) · `.claude/hooks/licoes-code-two-strikes.test.mjs` (bite-test) · `memory/LICOES_CODE.md` (nota da convenção no header) · `memory/decisions/proposals/2026-07-20-auto-feed-ledger-aprendizado.md` (ADR).

## Pendente

- **Ratificação [W]** do PR #4599 (= aceite da proposal). **Não mergear sozinho** (R10).
- **Ao ratificar:** adicionar 1 tombstone consolidado ao §5 cobrindo os rejeitados (forward-only; §5 intocado enquanto proposta).
- **Não promover a required** sem uma sonda que MORDE — surface de triagem nunca bloqueia (guarda-corpo 0344).
