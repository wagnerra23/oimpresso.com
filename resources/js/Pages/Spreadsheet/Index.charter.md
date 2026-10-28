---
page: /spreadsheet/sheets
component: resources/js/Pages/Spreadsheet/Index.tsx
status: draft
owner: wagner
parent_module: Spreadsheet
last_validated: '2026-10-13'
related_prototype: prototipo-ui/cowork/Wagner/planilhas-page.jsx
related_runbook: memory/requisitos/Spreadsheet/RUNBOOK-spreadsheet-index.md
related_us: [US-SHEET-002]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
charter_version: 1
---

# Charter — Planilhas (lista e pastas)

**Rota:** `/spreadsheet/sheets`.

**Missão.** Mostrar as planilhas que a pessoa criou ou recebeu, agrupadas por pasta, com o caminho
para abrir e criar.

**Padrão de tela:** PT-01 Lista (variante árvore).
**Plano:** [RUNBOOK-spreadsheet-index.md](../../../../memory/requisitos/Spreadsheet/RUNBOOK-spreadsheet-index.md) ·
**Casos:** [Index.casos.md](./Index.casos.md).

## Âncora de design

`planilhas-page.jsx` → `PlanilhasPage` (lista): cabeçalho com "Nova pasta" e "Criar planilha",
busca, expandir/recolher, árvore de pastas com "Sem pasta" por último, linha com "Editada há",
autor, contagem de compartilhamento, "Abrir" e kebab.

## Goals

- Árvore: uma seção por pasta do business (ordem alfabética) + "Sem pasta" (`folder_id` nulo).
- Linha: nome, "Editada há …" (data completa no title), "criada por você" ou o autor, e
  "compartilhada com N usuários · N funções · N tarefas" quando houver.
- Busca por nome abre as pastas que têm resultado; sem resultado mostra estado vazio de busca.
- Quem não tem `create.spreadsheet` vê as ações de criar desabilitadas e o aviso de leitura.

## Non-Goals (NÃO faz)

- ❌ Renomear pasta: não há rota no backend. (Nova pasta, mover, compartilhar e excluir entraram em
  2026-10-27, pelos endpoints da Blade; o editor segue na Blade até a thread 04b.)
- ❌ Busca no conteúdo das células — só o nome.

## Anti-hooks

- ❌ Não envia `sheet_data` na lista: DTO explícito (id, nome, pasta, data, autor, contagens).
- ❌ Não lista planilha de outro business, nem por compartilhamento ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ Não liga a flag `useV2SpreadsheetIndex` por default: ligar é cutover do [W].

## Divergências declaradas

- Compartilhamento como **contagem**, como no protótipo. A Blade lista nomes de usuários e funções
  e nunca mostrou tarefas (o `elseif` dela repete `'role'`).
- "Renomear" da pasta (protótipo) não existe no backend: fica fora até haver rota.

## Dados / props

```
Spreadsheet/Index (Inertia)
  podeCriar: eager → bool
  planilhas: Inertia::defer → Array<{ id, nome, pasta_id|null, atualizada_em|null, criada_por|null,
                                      minha, compartilhada: { usuarios, funcoes, tarefas } }>
  pastas:    Inertia::defer → Array<{ id, nome }>
```

## Testes

`Modules/Spreadsheet/Tests/Feature/SpreadsheetIndexContratoTest.php` (lane `spreadsheet-pest`,
MySQL, tenant 98 × 99) — UC-PLAN-01 a 05.
