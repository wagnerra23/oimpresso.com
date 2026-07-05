---
title: "Adversário — red-team do PLANO-APROFUNDAMENTO-AVALIACOES + de-pin Opus→tier-da-sessão"
slug: adversario-plano-aprofundamento
kind: session
date: "2026-07-05"
topic: "adversário do plano de aprofundamento das avaliações — T6 duplicação + de-pin Opus→tier-da-sessão"
authors: [C]
alvo: "memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md"
related_adrs: ["0106-recalibracao-velocidade-fator-10x-ia-pair", "0264-governanca-executavel-trio-dominio-e2e", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0316-esquecimento-real-adr-morta-tombstone-git-auditoria"]
tags: [adversario, red-team, T6, model-tier, duplicacao]
---

# Adversário — red-team do plano de aprofundamento das avaliações

> **Por que este arquivo existe (LEIA ANTES de criar doc de avaliação):** numa sessão eu escrevi um
> segundo plano (`PLANO-AUDITORIA-PROJETO-INTEIRO.md`) que **duplicava** o canon `PLANO-APROFUNDAMENTO-AVALIACOES.md`
> — violação da regra T6 (roadmap paralelo, proibicoes.md §5, entrada 2026-06-05). O adversário pegou, o
> duplicado foi **deletado**. Este doc guarda o red-team em arquivo SEPARADO pra a próxima sessão não repetir:
> **antes de propor plano/auditoria de avaliação, ler ISTO + `Glob memory/**/PLANO*` e ESTENDER o canon, nunca abrir paralelo.**

## Contexto

A grade de governança de conhecimento (~74/100, 2026-07-04) cobriu só uma fatia. Ao responder "isso cobre
o projeto inteiro?", eu escrevi um plano de 5 fases — sem rodar `Glob`/`Grep` antes. O canon do Felipe [F]
(`PLANO-APROFUNDAMENTO-AVALIACOES.md`, commit `abe999e3`, mesma data) já cobria os mesmos 5 buracos em 5 Ondas.

## Achados do adversário (severidade ↓)

| # | Sev | Achado | Evidência | Ação |
|---|---|---|---|---|
| 1 | **FATAL** | Plano duplicado = roadmap paralelo proibido (T6) | canon `abe999e3` cobre F0=Onda0 … F5=Onda4/5, 1:1; ainda por cima o canon já **construiu** a máquina (Check X) que o meu só propunha | duplicado **deletado**; usar só o canon |
| 2 | Alto | Claim sem evidência: eu disse "zero lente de segurança" | `memory/audits/2026-05-pre-sales/03-security-review-quick.md` (2026-05-09) já existe e achou **A-1 Critical** (rota `/install/install-alternate` pública → `migrate:fresh --force` = wipe da DB de prod) | canon corrigido: baseline linkado + A-1 vira 1º item da Onda 3 |
| 3 | Alto | Troca de modelo desnecessária Fable→Opus | título/status diziam "execução Opus 4.8"; agentes `capterra-senior`/`audit-senior-expert`/`estado-da-arte` têm `model: opus` hard-pinned (`.claude/agents/*.md`) → cada spawn troca a sessão pra Opus | canon de-pinado: Regra global #9 + override `model: fable` nos spawns (Fable 5 é o tier GA mais alto) |
| 4 | Médio | "Nota 0-100" de segurança é teatro | score inventado não-reproduzível (o tipo "P0 fatal" inflado que o projeto condena, how-trabalhar §degradação) | **recomendação aberta:** Onda 3 usar severidade CVSS-like only, sem nota 0-100 |
| 5 | Médio | Nota baixa ≠ risco alto (conflação) | Compras 58 é puxada por D6/D7/D9 (perf/LGPD/observabilidade), não por risco de dinheiro; webhook 2026-07-05 mostra Compras 58→59, PaymentGateway 60→63 já subindo | manter Onda 2, mas priorizar por RISCO (cálculo/estoque) dentro do módulo, não pela nota agregada |
| 6 | Baixo | `audit-senior-expert` é agente de WebSearch/pesquisa, não caçador de vuln em código | frontmatter: tools de pesquisa; vuln-hunt é leitura de código | Onda 3 ancora na skill `/security-review` (code-level); o agente entra só pra pesquisar padrão de ataque por dimensão |
| 7 | Estrutural | CT100 inacessível de sessão nuvem | `tailscale: No such file or directory` neste ambiente | canon já trata certo (Onda 1 sem pré-req; Onda 4 gated CT100) — sem mudança |

## O que já foi aplicado no canon (mesmo PR)

- Título ×2 + Status vivo: "Opus 4.8" → "tier da sessão".
- Regra global **#9 — NÃO force Opus** (com receita do override `model: fable`).
- Onda 2/3: spawns com override `model: fable`.
- Onda 3: baseline `03-security-review-quick.md` + A-1 Critical linkado.
- Ponteiro pra este adversário no Status vivo do canon.

## Recomendações abertas (decisão Wagner — não aplicadas unilateralmente)

- **#4** trocar "nota 0-100" da Onda 3 por severidade-only.
- **#2/A-1** verificar se `POST /install/install-alternate` ainda é público em `origin/main` (se sim = Critical vivo, vira task P0 imediata, fora da fila das ondas).

## Lição perene

Antes de escrever QUALQUER plano/SPEC/roadmap de avaliação: `Glob memory/**/PLANO*` + `Grep` o tema.
Se existe canon do mesmo tema → **estender** (nova Onda/seção, bump `reviewed_at`), nunca abrir paralelo (T6).
E não pinar modelo no doc: o executor roda no tier da própria sessão; Fable 5 ≥ Opus.
