---
id: requisitos-documentacao-runbook-index
title: "RUNBOOK — /documentacao (leitura guiada do Guia do Sistema)"
module: Documentacao
tela: Documentacao/Index
owner: W
status: rascunho
last_validated: "2026-08-06"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao` (leitura guiada)

> ⚠️ **NUNCA EXECUTADO** — a tela ainda não existe. `last_validated` é data de criação, não de
> validação; o schema exige o campo.
>
> **O que é compartilhado com as outras três telas** (ordem das rotas, rail derivado, middleware,
> corpus, estados de falha, fases MWART) vive em
> **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)** — ponteiro, não cópia. Aqui fica só o que
> é desta tela.

## Rota e controller

`GET /documentacao` → `DocumentacaoController@index` → `Inertia::render('Documentacao/Index')`.

## Props

| Prop | Origem |
|---|---|
| `html` | `memory/GUIA-DO-SISTEMA.md` convertido no servidor |
| `sumario` | derivado do HTML a cada acesso |
| `fonte` | o caminho do arquivo dono |
| `atualizadoEm` | frontmatter do próprio documento |
| `buscaDisponivel` | corpus acessível? |
| `nav`, `atual`, `escopo` | compartilhados — ver RUNBOOK da superfície |

## Falha específica desta tela

Fonte ausente no deploy → **503 nomeando o arquivo** (`AR-DOC-002`). É a única das quatro telas
que falha por arquivo em disco, e não por corpus.

## Sintomas

| Sintoma | Causa provável |
|---|---|
| Página em branco em vez de 503 | alguém trocou o `abort` por render vazio — a falha honesta é o contrato |
| Sumário desatualizado | virou manifesto commitado em vez de recalculado |
| Busca oferecida com corpus fora | `buscaDisponivel` deixou de refletir `corpusDisponivel()` |

## Refs

- [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) · [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) (AR-DOC-001 a 007)
