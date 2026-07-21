---
date: "2026-07-21"
hour: "23:59 BRT"
topic: "Rodada 6 do juiz funcao-scorecard: blind-por-label + de-narração + κ inter-família (4 modelos) + set-fronteira. Validação não-circular 8,5 → 9,0 (proposta, merge [W])"
authors: ["C"]
tags: [funcao-scorecard, nao-circular, kappa, inter-familia, fronteira, blind, de-narracao, grade, rodada-6]
outcomes:
  - "2 leaks de circularidade RESIDUAIS achados+fechados: (a) id do twin no cabeçalho do pack nomeava o veredito (todas as rodadas 2-5); (b) docblock /** */ narrando o defeito. --blind (labels opacos hash-order) + de-narração."
  - "Gap #2 diversidade de modelo RESOLVIDO: 4 famílias (Opus/Sonnet/Fable/Haiku) cegas, κ inter-família = 1,0 nos 6 pares (22/22) no mecânico."
  - "Gap #3 fronteira de erro RESOLVIDO: set frontier/ achou modo de erro real (fr05 golden-lull: Opus+Haiku miss) + 0/20 falso-positivo + incerto-de-intenção não-encodável (fr10)."
  - "2 twins quebrados achados+aposentados: t08 (incerto-de-intenção → braço humano) e t14 (rótulo C3 errado, é C7a). κ honesto pós-leaks: 0,83 sobre 25 / 1,0 sobre 23 válidos."
  - "Re-grade proposta validação-não-circular 8,5 → 9,0. Gap #1 (κ vs gold HUMANO) segue bloqueado em [W] rotular a folha-cega #4626 — e a rodada 6 provou POR QUE ele é necessário (incerto-de-intenção só ele resolve)."
---

# Rodada 6 — juiz `funcao-scorecard`: cross-família + fronteira + os 2 leaks residuais

## TL;DR

O trecho **8,5 → 9-10** da validação-não-circular tinha 3 gaps nomeados (κ vs humano · diversidade de modelo · fronteira de erro). Fechei **2 dos 3** com prova reprodutível do git — e no caminho **achei 2 leaks de circularidade que TODAS as rodadas 2-5 deixaram passar**, o que é a própria dimensão. Proposta: **8,5 → 9,0** (não 10; merge [W] = ratificação). Gap #1 (κ vs gold **HUMANO**) segue bloqueado em [W] rotular a folha-cega do **#4626** — e a rodada 6 mostrou empiricamente que aquele braço não é opcional.

## O que estava 8,5 (integrei, não reinventei)

Fixture de mutação (25 twins, C1..C7d, de-comment da rodada 5) + runner (`--pack`/`--score`/`--kappa`/`--selftest`) + braço-incidente ancorado em teste real + o braço gold-HUMANO **#4626** já montado (folha-cega + gabarito selado por juiz Fable), **pausado esperando [W] rotular**.

## Os 2 leaks residuais (a raiz da não-circularidade)

1. **ID auto-documentado no cabeçalho do pack.** `## t15-atomicidade-bad`, `## t02-unscoped-find`, `## t16-...-ok` — o id NOMEAVA o veredito antes do juiz ler o código. Em **todas** as rodadas 2-5. Fix: `--blind` emite `L01..` em ordem de **hash sha256(id)** (some o tell do id + a adjacência dos pares bom/ruim); o runner recomputa a ordem determinística pra pontuar (`translateBlind`), sem gravar mapa perto dos twins.
2. **Docblock `/** */` narrando o veredito.** O README da rodada 5 admitia como "trabalho futuro"; era material — t13 tinha *"usuário do business A muta o lançamento do business B"* (= o C1 discordo, por escrito). Fix: de-narração — mantido só o contrato genuíno (`@return` de tipo, `@covered-by`, `@transactional`, schema nullable), deletada a prosa que nomeia o defeito.

**Consequência honesta:** o κ das rodadas 2-5 estava **inflado** pelos 2 leaks. Medido de novo, cego de verdade: **0,83** por família sobre os 25 — e a queda foi **inteiramente** 2 twins (t08/t14). Sobre os **23 válidos**: **κ = 1,0** nas 4 famílias.

## Gap #2 — diversidade de modelo (resolvido)

4 famílias julgaram **cegas** (pack inline, sem acesso ao repo → não alcançam o selado): **Opus 4.8 · Sonnet 5 · Fable 5 · Haiku 4.5**.

| Métrica (main, 23 válidos) | Resultado |
|---|---|
| κ vs selado por família | **1,0** (Opus/Sonnet/Fable/Haiku) |
| Calibração (`--score`) | ✅ Opus/Sonnet/Fable · ❌ Haiku (1 falso-positivo no controle t07) |
| **κ INTER-FAMÍLIA (6 pares)** | **1,0 · 22/22 acordo** |

κ inter-família = 1,0 refuta "concordou porque é o mesmo modelo": famílias distintas concordam **perfeitamente** no defeito mecânico. Achado extra honesto: o **menor modelo (Haiku)** carimbou `C3 discordo` no controle limpo (t07) — falso-positivo que Opus/Sonnet/Fable não cometeram.

## Gap #3 — fronteira de erro (resolvido)

Set `frontier/` (10 twins deliberadamente difíceis, labels defensáveis da rubrica). A fronteira **achou o erro**, não confirmou acerto:

| Twin | Esperado | Opus | Sonnet | Fable | Haiku | Achado |
|---|---|---|---|---|---|---|
| **fr05** golden-lull | C2 discordo | ❌ concordo | ✅ | ✅ | ❌ concordo | **modo de erro REAL** — juiz aceita "tem golden" sem checar O VETOR |
| **fr10** incerto-de-intenção | C3 incerto | concordo | discordo | discordo | ✅ incerto | 1/4 — não-encodável (→ humano) |
| **fr08** incerto-estrutural | C5 incerto | ✅ | ✅ | ✅ | ✅ | **4/4** — encodável, fica |
| iscas (fr01/02/04/06/07) | não-discordo | — | — | — | — | **0/20 falso-positivo** |

κ inter-família na fronteira cai pra **~0,60 (75%)** — famílias concordam no fácil e **divergem no difícil**, por construção.

## O achado estrutural (o mais valioso)

`incerto` **se parte em dois**:
- **estrutural** (a incerteza está no código — eager-load desconhecido, fr08): encodável, **4/4 acertam**.
- **de-intenção** (a ambiguidade está FORA do código — "1.0 é default legítimo ou ausência?", t08/fr10): **não-encodável** não-circularmente; o juiz RESOLVE em vez de deferir.

→ o incerto-de-intenção é do braço **gold HUMANO (#4626)**. A rodada 6 provou **empiricamente** por que aquele braço é necessário, não opcional.

## Integridade da fixture — 2 twins quebrados aposentados

- **t14** (incidente empty-value): rótulo **C3 estava ERRADO desde a rodada 4**. C3 mede o RETORNO ser sentinela; aqui o retorno é array (ausência = array vazio é OK, cf. t24) — o defeito são os ELEMENTOS poderem ser null sob `@return string[]` = **C7a**. 4/4 não viram C3; 2 (Haiku, Sonnet) acharam C7a independentemente. A narração mascarava o mismatch.
- **t08** (incerto-de-intenção): 4/4 erraram → migra pro braço humano.

`retired: true` no manifesto — ficam na ORDEM cega (labels estáveis, r6 reprodutível), fora das métricas.

## Re-grade (honesta, proposta)

**Validação-não-circular do juiz: 8,5 → 9,0.** Prova reprodutível: `calibracao-2026-07-21/judge-r6-*-{main,frontier}.json` + `node scripts/governance/funcao-scorecard-calibracao.mjs --score/--kappa-inter [--set frontier]`.

**Falta pra 9,5-10 (não inflar):**
1. **Gap #1 κ vs gold HUMANO** — bloqueado em [W] rotular a folha-cega (#4626). É o ground-truth que casa com SWE-bench **Verified**; a rodada 6 mostrou que o incerto-de-intenção só ele resolve.
2. Endereçar o modo **fr05** (golden-lull) — rubrica ou nota.
3. FP de controle do Haiku sem reincidência + acúmulo de rodadas humanas.

**NÃO é 10.** Merge [W] = ratificação (R10).

## Arquivos

- `scripts/governance/funcao-scorecard-calibracao.mjs` — `--blind` (labels opacos hash-order), `translateBlind`, `--kappa-inter`, `--set frontier`, skip `retired`, `incertoOk` N-twins.
- `tests/governance-fixtures/funcao-scorecard/twins/t12,t13,t14,t16,t20` — de-narração dos docblocks.
- `tests/governance-fixtures/funcao-scorecard/manifesto-SELADO.json` — t08/t14 `retired` + razão.
- `tests/governance-fixtures/funcao-scorecard/frontier/` — 10 twins + manifesto selado (novo braço).
- `calibracao-2026-07-21/judge-r6-{opus,sonnet,fable,haiku}-{main,frontier}.json` — vereditos das 4 famílias.
- `memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md` §5 (rodada 6, append).

## Fronteira honesta (inalterada)

Calibra o **INSTRUMENTO** (o juiz discrimina defeito mecânico não-circularmente, agora cross-família e testado na fronteira). **NÃO** re-valida vereditos de função REAL (`ProductUtil` etc.) — `validation_status: invalidado` do scorecard dele não muda por esta rodada.
