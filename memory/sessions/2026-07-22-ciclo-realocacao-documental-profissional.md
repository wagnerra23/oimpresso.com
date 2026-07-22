---
date: "2026-07-22"
hour: "14:30 BRT"
duration: "2h"
topic: "Fechamento profissional do ciclo de classificação, movimento e relink documental"
authors: [W, C]
outcomes:
  - "Classificador pelas três camadas integrado pela PR #4676."
  - "Executor transacional com rollback e recibo Git publicado na PR #4677."
  - "Runbook ligado ao README e piloto real executado com histórico preservado."
prs: [4676, 4677]
us: []
related_adrs:
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio"
---

# Sessão — ciclo profissional de realocação documental

**Data:** 2026-07-22
**Objetivo:** transformar o plano de organização da documentação em um fluxo executável, seguro, verificável e ensinável.

## TL;DR

O ciclo documental foi fechado com classificador, adversário, executor transacional, rollback, histórico consultável no Git, runbook e um piloto real. Não foi feita migração cega do corpus: o fluxo foi desenhado para lotes pequenos e revisados.

## O que foi feito

1. Consolidada a classificação pelas camadas Produto ERP, Produto IA/Jana e IA-OS, sempre com porta-mãe.
2. Criado executor dry-run por padrão, com worktree limpa, `git mv`, relink exato, allowlist de geradores, pós-checks e rollback.
3. Tornado o hash do plano estável apesar do timestamp e da ordem das chaves.
4. Gravado rastro de deslocamento em trailers Git consultáveis por comando.
5. Executado piloto real em guia legado, corrigindo as portas vivas e preservando a linhagem via `git log --follow`.
6. Criado runbook operacional e adicionado à porta global `README.md`.

## Verificação

- Classificador 4/4, adversário 17/17, executor 8/8.
- Gate-selftest 70/70 e documentation-loop verde.
- Onboarding paths e system-map em dia.
- Rollback real observado no primeiro apply; segunda execução concluída.
- `docs:relocation:history` retornou o movimento com commit e data.

## Estado final

A PR #4676 foi fundida. A PR #4677 foi publicada e sincronizada com `main`. O mecanismo fecha o ciclo completo para cada documento; a reorganização total continua incremental por segurança e por decisão canônica.

Handoff: [`memory/handoffs/2026-07-22-1430-ciclo-realocacao-documental-profissional.md`](../handoffs/2026-07-22-1430-ciclo-realocacao-documental-profissional.md)
