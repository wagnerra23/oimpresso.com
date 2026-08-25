---
id: requisitos-documentacao-runbook-busca
title: "RUNBOOK — /documentacao/buscar (busca no acervo)"
module: Documentacao
tela: Documentacao/Busca
owner: W
status: rascunho
last_validated: "2026-08-06"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao/buscar` (busca no acervo)

> ⚠️ **NUNCA EXECUTADO** — a tela ainda não existe. `last_validated` é data de criação.
>
> O compartilhado vive em **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)**.

## Rota e controller

`GET /documentacao/buscar` → `DocumentacaoController@buscar` →
`Inertia::render('Documentacao/Busca')`. O nome é **`buscar`**, não `busca`. Declarada **antes**
de `{slug}` — invertida, a busca vira 404 de "documento buscar não encontrado".

## A consulta — por que são duas condições

`MATCH(title, content_md) AGAINST (…)` **OR** `LIKE '%termo%'` no título. O índice full-text
descarta palavra curta (`ft_min_word_len`) e stopword; sem o `LIKE`, buscar "NFe" ou "MCP" — dois
dos termos mais usados no projeto — devolveria vazio **sem erro nenhum**. Trocar por só uma das
duas condições "pra simplificar" é o anti-hook declarado no charter.

Nenhum índice novo foi criado: usa o `mcp_md_fulltext_idx` que já existe.

## Estados

| Situação | Resposta |
|---|---|
| Corpus inacessível | `indisponivel = true`, **HTTP 200** — não finge resultado vazio e não dá 503 |
| Termo < 2 caracteres | resultados vazios **sem consultar o banco** |
| Termo válido | até `POR_PAGINA` resultados, ordenados por relevância |

`resultados` fica **eager** nesta fase, contra o default de `Inertia::defer`: a migração é regida
por contrato de paridade, e deferir muda o momento em que o resultado chega — logo muda o que os
casos observam. Passar a deferir é decisão de fase seguinte, com os UC já verdes.

## Sintomas

| Sintoma | Causa provável |
|---|---|
| "Nada encontrado" durante janela sem corpus | os dois estados foram colapsados em um |
| Termo curto sempre vazio | o `LIKE` do título saiu da consulta |
| Busca dá 404 | ordem de rota — `{slug}` foi declarado antes |

## Refs

- [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) · [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) (AR-DOC-020 a 025)
