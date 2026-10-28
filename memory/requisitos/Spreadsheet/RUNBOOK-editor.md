---
owner: W
last_validated: "2026-10-27"
slug: spreadsheet-runbook-editor
title: "Spreadsheet — RUNBOOK Spreadsheet/Editor (criar e abrir planilha)"
type: runbook
module: Spreadsheet
status: ativo
date: 2026-10-27
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Spreadsheet `Editor` (thread telas-soltas/04b)

> Artefato **F1 PLAN** da [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
> para `resources/js/Pages/Spreadsheet/Editor.tsx`. Entrada: decisão [W] de 2026-10-27 ("pode
> destravar todos os outros vou fazer todos"), sobre a D1 de 2026-10-07 que pôs Planilhas na forma
> do protótipo.

| Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|
| `sheet/create.blade.php` | `GET /spreadsheet/sheets/create` | `Spreadsheet/Editor` (sem planilha) | editor de página inteira |
| `sheet/show.blade.php` | `GET /spreadsheet/sheets/{id}` | `Spreadsheet/Editor` (com planilha) | editor de página inteira |

Âncora de design: `prototipo-ui/cowork/Wagner/planilhas-page.jsx` → `Editor`.

## Estado final desta onda

`create()` e `show()` renderizam `Spreadsheet/Editor`. `?legacy=1` serve a Blade, até o F5 (que apaga
as duas Blades junto com a da lista). Gravar continua nos endpoints de sempre: `POST /spreadsheet/sheets`
(`store`) e `PUT /spreadsheet/sheets/{id}` (`update`).

## Escopo

- **Entra:** barra do editor (Voltar, nome, estado salvo/não salvo, Importar .xlsx, Salvar/Atualizar),
  Luckysheet ocupando o resto da tela, aviso de leitura para quem não pode gravar.
- **Fora:** "Baixar" (o protótipo já o mostra desabilitado: o módulo não tem rota de download), abas
  feitas à mão (o Luckysheet já tem as dele), formula bar própria (idem).

## Backend (F2)

1. Feito no PR anterior: `planilhaAcessivel()` decide quem abre e quem grava (`SpreadsheetAcessoEditorTest`).
2. Props da Page em DTO: `planilha: { id, nome, dados, minha } | null`, `pasta_id`, `pode_gravar`, `voltar`.
   Nada de `business_id` nem `created_by` cru na prop.
3. `dados`: o `sheet_data` é gravado como **string JSON dentro de JSON** (o cast `array` do Model
   codifica a string que a tela manda). Ler devolve a string; a Page faz `JSON.parse`. Linha antiga com
   array de verdade é aceita também. A Page grava exatamente como a Blade: `sheet_data =
   JSON.stringify(luckysheet.getAllSheets())`. Sem mudança no formato salvo.

## Frontend (F3)

- Luckysheet, plugin e LuckyExcel carregados do mesmo CDN que a Blade usa (`layouts/app.blade.php`),
  uma vez por página, por `<script>` injetado — mesmo padrão do `CatalogueQr.tsx`. Nenhuma dependência
  npm nova.
- "Voltar": para a lista quando a planilha é minha ou nova; para as tarefas (Essentials) quando recebi
  por compartilhamento — o mesmo destino da Blade.
- Importar `.xlsx` só para quem criou (a Blade esconde o campo para os outros).

## Testes

`Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` (UC-PLED-01 a 07), lane
`spreadsheet-pest` (MySQL, tenant 98 × 99).

## Pegadinhas

- O Luckysheet escreve no `window` e não tem `destroy` confiável entre visitas Inertia: o container
  tem id fixo e o efeito chama `luckysheet.destroy()` ao desmontar.
- O CSS do Luckysheet é global. Ele só é injetado quando o editor abre.
