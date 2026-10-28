---
page: /spreadsheet/sheets/{id}
component: resources/js/Pages/Spreadsheet/Editor.tsx
status: draft
owner: wagner
parent_module: Spreadsheet
last_validated: '2026-10-27'
related_prototype: prototipo-ui/cowork/Wagner/planilhas-page.jsx
related_runbook: memory/requisitos/Spreadsheet/RUNBOOK-editor.md
related_us: [US-SHEET-002]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
charter_version: 1
---

# Charter — Planilhas (editor)

**Rotas:** `/spreadsheet/sheets/create` (planilha nova) e `/spreadsheet/sheets/{id}` (planilha existente).

**Missão.** Abrir uma planilha que a pessoa criou ou recebeu, editar no navegador e gravar.

**Plano:** [RUNBOOK-editor.md](../../../../memory/requisitos/Spreadsheet/RUNBOOK-editor.md) ·
**Casos:** [Editor.casos.md](./Editor.casos.md).

## Âncora de design

`planilhas-page.jsx` → `Editor`: barra com "← Voltar", nome editável, estado ("alterações não salvas",
"salva", "nunca salva"), "Importar .xlsx", "Baixar" desabilitado e "Salvar" / "Atualizar"; a grade
ocupa o resto da tela.

## Goals

- Abrir só planilha do negócio da sessão que a pessoa criou ou recebeu (usuário, função ou tarefa).
- Gravar pelos endpoints de sempre (`store` / `update`), no mesmo formato da Blade.
- Importar `.xlsx` só para quem criou a planilha (ou ao criar uma nova).
- Quem não tem `create.spreadsheet` abre em leitura: sem "Salvar" e com o aviso.

## Non-Goals (NÃO faz)

- ❌ "Baixar": o módulo não tem rota de download (o protótipo já o mostra desabilitado).
- ❌ Grade, barra de fórmula e abas próprias: são as do Luckysheet, como na Blade.

## Anti-hooks

- ❌ Não envia `business_id` nem `created_by` na prop: DTO `{ id, nome, dados, minha }`.
- ❌ Não abre planilha de outro negócio, nem compartilhada com o meu usuário ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ Não muda o formato gravado em `sheet_data`.

## Divergências declaradas

- O protótipo desenha a grade e as abas à mão; a tela usa o Luckysheet, que já as tem.
- "Voltar" de planilha recebida leva às tarefas do Essentials, como a Blade.

## Dados / props

```
Spreadsheet/Editor (Inertia)
  planilha:    { id, nome, dados: array|null, minha } | null   (null = planilha nova)
  pasta_id:    int|null   (só na nova, vem de ?folder_id)
  pode_gravar: bool
  voltar:      string (URL)
```

## Testes

`Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` (lane `spreadsheet-pest`, MySQL,
tenant 98 × 99) — UC-PLED-01 a 07.
