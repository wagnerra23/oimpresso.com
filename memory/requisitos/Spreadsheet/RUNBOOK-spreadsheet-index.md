---
owner: W
last_validated: "2026-10-13"
slug: spreadsheet-runbook-index
title: "Spreadsheet — RUNBOOK Spreadsheet/Index (lista de planilhas + pastas)"
type: runbook
module: Spreadsheet
status: ativo
date: 2026-10-13
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Spreadsheet `Index` (thread telas-soltas/04)

> Artefato **F1 PLAN** que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
> exige antes de tocar `resources/js/Pages/Spreadsheet/Index.tsx`. Decisão de entrada: **D1 [W]
> 2026-10-07** — Planilhas entra em produção na forma do protótipo
> (`prototipo-ui/cowork/Wagner/cowork-inbox/telas-soltas/playbook/_DECISOES-W-2026-10-07.md`).

| Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|
| `Modules/Spreadsheet/Resources/views/sheet/index.blade.php` | `GET /spreadsheet/sheets` | `Spreadsheet/Index` | PT-01 Lista (variante árvore) |

## Estado final desta onda

`SpreadsheetController::index()` renderiza `Spreadsheet/Index` quando a flag `useV2SpreadsheetIndex`
está ligada (**default OFF** — não está em `FeatureFlagService::$fallbackDefaults`). Flag OFF, ou
`?legacy=1`, serve a Blade intacta. Ligar é cutover do [W] (F5: aviso + canary 7d).

## Escopo

- **Entra:** árvore de pastas recolhível (Sem pasta = `folder_id null`), linha por planilha (nome,
  "Editada há", autor, contagem de compartilhamento), busca por nome, expandir/recolher tudo,
  "Abrir" e "Criar planilha" / "Criar aqui" apontando para o editor Blade.
- **Ficava na Blade até 2026-10-27:** editor (`show`/`create`, Luckysheet — thread 04b), nova pasta,
  mover, compartilhar e excluir. Desde 2026-10-27 as ações moram em
  `_components/AcoesPlanilha.tsx` (o editor segue na Blade até a thread 04b), pelos endpoints da Blade (UC-PLAN-06 a 10).

## Backend (F2)

1. Uma consulta só, `planilhasVisiveis()`, servindo os dois caminhos. A consulta antiga unia os
   ramos de compartilhamento com `orWhere` **sem agrupar**: `business_id = ? AND created_by = ? OR
   (share role) OR (share user) OR (share todo)` — os três ramos escapavam do filtro de tenant.
   Agora é `business_id = ? AND (criador OR share ...)`.
2. `roles()->first()->id` estourava para usuário sem função; virou `?->id`.
3. Payload em DTO explícito, adiado (`Inertia::defer`): `planilhas` e `pastas`. Sem `sheet_data`.
4. Compartilhamento vai como **contagem** (usuários · funções · tarefas), que é o que a linha do
   protótipo mostra. A Blade nunca mostrou tarefas: o ramo `elseif` dela repete `'role'`.

## Testes

`Modules/Spreadsheet/Tests/Feature/SpreadsheetIndexContratoTest.php` (UC-PLAN-*), lane MySQL
`spreadsheet-pest.yml` — o módulo não tinha lane: os 9 arquivos de teste existentes eram órfãos
(`test-lane-coverage.mjs`, 2026-10-13).

## Pegadinhas

- A flag é avaliada no 1º GET de HTML (sem `X-Inertia`) — só ela decide o caminho.
- Partial reload do browser manda `X-Requested-With` junto com `X-Inertia` (§5 2026-09-08); o teste
  usa os dois.
- `retention.spreadsheet.php`: fora do escopo (roteiro 04).
