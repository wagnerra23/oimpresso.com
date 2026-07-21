---
date: 2026-07-21
hour: "22:30 BRT"
topic: "Grade: a nova estrutura (catálogo por módulo + parecer-de-função + loop de aprendizado) pontuada vs os melhores 2026 + plano de refinamento"
authors: ["C"]
tags: [grade, reguas, catalogo-modulo, funcao-scorecard, aprendizado, idp, codescene, letta, circularidade, pr-4617]
outcomes:
  - "Grade honesta em 6 sub-dimensões: forma-do-conhecimento no topo ou levemente acima; validação-não-circular do juiz é o gargalo (4/10)."
  - "Diferencial = instanciação+recursão, NÃO invenção (todo componente tem peer). §5 falácia-de-composição respeitada."
  - "Refinamento nº1 = fixture não-circular; INTEGRA o mecanismo tipo:juiz + folha-cega/gabarito-SELADO já canon (PR #4472), não reinventa."
---

# Grade — catálogo + aprendizado + parecer-de-função vs os melhores (2026)

## TL;DR

A **forma do conhecimento** (schema de tópico + loop de rejeição) está no topo/levemente acima da barra; **todo componente tem peer** (diferencial = instanciação+recursão, não invenção); o **gargalo é a validação NÃO-circular do juiz (4/10)**, e o refinamento nº1 **integra** o mecanismo `tipo:"juiz"` + folha-cega/gabarito-SELADO já canon (#4472).

## Contexto

**Pedido [W] (2026-07-21):** olhar o [PR 4617](https://github.com/wagnerra23/oimpresso.com/pull/4617), refinar, **pontuar vs os melhores e comparar com o que temos**, e entender/explicar o mecanismo de aprendizado.
**Método:** 2 pesquisas web focadas (~11 buscas) + verificação no repo vivo. Refutou toda claim de "acima" com peer publicado.

## Placar (0-10 por sub-dimensão · a barra nomeada)

| Eixo · barra 2026 | Nota | Posição honesta |
|---|---|---|
| **Catálogo/IDP** (SCOPE/SUPERFICIE/BRIEFING/tópicos) · Backstage+Cortex | **7,0** | acima: granularidade de tópico + superfície derivada · at-par: ownership+CI in-repo (=`catalog-info.yaml`) · atrás: grafo tipado, scorecard de sinais-vivos, UI consultável |
| **Memória viva** (superfície + tópicos + freshness) · Letta/Zep/Swimm | **7,5** | acima: separação epistêmica tipada (observado/intenção/veredito/contradição) — Letta/mem0/Zep NÃO fazem · at-par: memória-git (Letta convergiu fev/2026) · atrás: fato bi-temporal (Zep), reflexão autônoma (Letta) |
| **Humano-no-loop + ledger de rejeição** (ADR 0345) · Dosu/Mintlify/Rekor | **8,0** | acima (o mais forte): o loop *integrado* (refutação multi-crítico + sintetizador-nunca-apaga-minoria + incerto + humano + registro de rejeição) · at-par: gate humano + append-only · atrás: pinar juiz/rubrica/hash, hash-chain à prova de adulteração |
| **Code-health ancorado** · CodeScene (validado por outcome) | **6,0** | atrás como métrica *validada*; at-par/à-frente no *que* ancora (risco de negócio que fator estático não pega) |
| **Validação NÃO-circular do juiz** · CodeJudgeBench/SWE-bench Verified | **4,0** | **o gargalo.** Meu bite-test acertou os 3 modos de vazamento (rótulo do próprio artefato, veredito pré-declarado, contexto-resposta carregado). `invalidado` está certo; prova = zero hoje |
| **`incerto`/abstenção** · selective-prediction | **7,5** | at-par/acima no design (abstém por condição objetiva, foge da miscalibração de confiança auto-reportada); falta prova de calibração |

## Veredito honesto (3 frases)

A **forma do conhecimento** está no nível ou levemente acima da barra — o schema de tópico e o loop crítica→humano→canon com registro-de-rejeição são mais ricos do que Dosu/Mintlify/Letta/mem0/Zep publicam, e nenhum peer único shipa o loop integrado. Mas **todo componente tem um par** (git-memory=Letta, drift-gate=Swimm, gate-humano=Dosu, ledger=Rekor/EU-AI-Act, multi-crítico=AutoGen) — o diferencial é **instanciação + recursão, não invenção** (alegar o contrário é a falácia-de-composição que o §5 já barra). Materialmente **atrás em 2 coisas**: a fixture não-circular do juiz (4/10) e a plataforma (grafo tipado, fato bi-temporal, ledger à prova de adulteração).

## Refinamentos do 4617 (ranqueados por impacto)

1. **⭐ Fixture não-circular do juiz** (4→8). Destrava o `validation_status: invalidado`. **INTEGRA o que já existe** (achado: [W] tinha razão): o mecanismo `tipo:"juiz"` no `sdd-verification-ledger.json` + o padrão **`folha-cega`/`gabarito-SELADO`** + a regra `_quem_monta_nao_exibe` + `ledger-check --juiz-report` — **já canon** ([PR #4472](https://github.com/wagnerra23/oimpresso.com/pull/4472), 2026-07-17; a rodada 1 dele queimou pela MESMA circularidade do meu bite-test). Falta: fonte de rótulo OBJETIVA pro parecer-de-função (mutação + incidente, não humano — custo humano por-critério é proibitivo) + uma rodada de calibração do juiz funcao-scorecard nesse molde.
2. **Arestas tipadas no catálogo** — emitir `dependsOn`/`providesApi` do `SCOPE.md` → catálogo consultável (Backstage/Cortex).
3. **Fato bi-temporal no tópico** — `valid_from`/`valid_until` (Zep): comportamento superado auto-expira.
4. **Endurecer o ledger** — pinar `crítico+prompt+hash-da-rubrica` por lote + re-rodar amostra de ideias REJEITADAS vs o estado atual (registro de rejeição também apodrece — ninguém no mercado checa).
5. **C2 + fechar `incerto`** — golden tem que cobrir o *vetor específico*; um `incerto` precisa de gatilho que o resolva (mapear callers).

## O mecanismo de aprendizado (explicado — pedido [W])

"Aprendizado" **não** é treinar pesos (fine-tuning rejeitado — ADR 0345). É **memória organizacional em Git**. O loop que vira ideia em canon durável: **proposta** (IA geradora) → **crítica independente** (sessão fresca, outro modelo, refuta contra código/testes, mede taxa-de-erro; ≥2% = REPROVADO) → **síntese central** (reconcilia, nunca apaga minoria, usa `incerto`) → **correção + re-verificação do lote** até erro<2% → **aprovação humana** (R10) → **materialização** (tópico/ADR/SPEC/teste) → **ledger append-only** (gerador/refutador/sessão-fresca/erro/PII/veredito) → **registro de rejeição** anti-regressão (§5). Hooks gravam só fato determinístico; prosa entra como proposta revisável. O próprio 4617 passou por isso (R1 8/139 → R4 0/1437). Camadas: pesos (não mexe) · contexto de sessão (some) · **memória-git (dura)**.

## Fontes (2026)
Backstage/Cortex/Port/OpsLevel · Letta MemFS · Zep bi-temporal · Swimm/Dosu/Mintlify · CodeScene Code Health + "Code Red" (arXiv 2203.04374) · CodeJudgeBench (arXiv 2507.10535) · SWE-bench Verified · Bias-in-the-Loop (arXiv 2604.16790) · abstenção/selective-prediction (arXiv 2607.04430, SelectLLM) · Sigstore Rekor / EU-AI-Act eval trails.

## Próximo passo (autorizado [W]: "pode fazer")
Construir a fixture (nº1) **integrando** o mecanismo `tipo:"juiz"` + folha-cega/gabarito-SELADO — braço-mutação (rótulo objetivo) primeiro. Investigação do que reusar em curso (mutation infra + corruptors + incidentes + harness de refutação).
