---
id: requisitos-documentacao-runbook-doc
title: "RUNBOOK — /documentacao/{slug} (documento do acervo)"
module: Documentacao
tela: Documentacao/Doc
owner: W
status: rascunho
last_validated: "2026-08-06"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao/{slug}` (documento do acervo)

> ⚠️ **NUNCA EXECUTADO** — a tela ainda não existe. `last_validated` é data de criação.
>
> O compartilhado vive em **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)**.

## Rota e controller

`GET /documentacao/{slug}` (regex `[A-Za-z0-9._-]+`) → `DocumentacaoController@documento` →
`Inertia::render('Documentacao/Doc')`. **Declarada por último** no grupo — ela engole rota irmã
registrada depois.

## Props

`doc` é **moldado**, não o model Eloquent: `slug`, `title`, `type`, `module`, `git_path`,
`git_sha`, `indexed_at`, `pii_redactions_count` — exatamente os campos que a view Blade
consumia. No Blade o model ficava no servidor; em Inertia ele viraria JSON no cliente, levando
junto `content_md`, `metadata`, `scope_required` e `admin_only`. Mais `html`, `nav`, `atual`,
`escopo`.

## Falhas específicas desta tela

| Situação | Resposta |
|---|---|
| Corpus inacessível | **503** |
| Slug inexistente, tipo fora da documentação, ou exige permissão que o usuário não tem | **404 honesto** |

O filtro de acesso mora num lugar só (`consultaBase`): `admin_only = false` e `scope_required`
nulo. Conservador de propósito — documento com escopo fica fora até alguém mapear a permissão.

## Sintomas

| Sintoma | Causa provável |
|---|---|
| Todo link relativo de doc em subpasta dá 404 | a base de resolução voltou a ser `memory/` em vez da pasta do documento |
| Documento de `session`/`handoff` abre | alguém ampliou `TIPOS_DOC` sem decisão — é anti-hook do charter |
| Payload com `content_md` | o molde do `doc` foi trocado pelo model inteiro |

## Refs

- [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) · [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) (AR-DOC-030 a 034, 051)
