---
id: pages-spreadsheet-editor-casos
casos: Planilhas · editor (/spreadsheet/sheets/create e /spreadsheet/sheets/{id})
irmaos: Editor.charter.md (lei) · Editor.tsx (código)
tecnica: Caso de uso = narrativa do usuário + critério de aceite verificável (Dado/Quando/Então)
por_que: o editor abre e grava conteúdo de planilha compartilhada entre usuário, função e tarefa; quem abre e quem grava é Tier 0.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Planilhas (editor)

> Derivados da [SPEC](../../../../memory/requisitos/Spreadsheet/SPEC.md) US-SHEET-002, do comportamento
> das Blades `sheet/create.blade.php` e `sheet/show.blade.php` (quem importa, quem grava, para onde
> "Voltar" leva) e do [RUNBOOK](../../../../memory/requisitos/Spreadsheet/RUNBOOK-editor.md) — **não do
> `Editor.tsx`**.
> Testes: `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` (UC-PLED-01 a 07), lane
> `spreadsheet-pest`, MySQL, tenant 98 × 99.
> Status: 🧪 escrito e na lane, veredito ainda não emitido.

## UC-PLED-01 · Abro a minha e a recebida; a do colega não · `must`

**Dado** uma planilha minha, uma recebida e uma de colega sem compartilhamento **Quando** abro cada uma
**Então** as duas primeiras abrem **E** a do colega dá 404.

Status: 🧪

## UC-PLED-02 · [T0] Planilha de outro negócio não abre · `must`

**Dado** uma planilha de outro negócio compartilhada com o meu usuário **Quando** abro **Então** 404, sem
o nome dela na resposta.

Status: 🧪

## UC-PLED-03 · [T0] Ter uma planilha recebida não deixa gravar a do colega · `must`

**Dado** uma planilha recebida e outra do colega **Quando** gravo na do colega **Então** o nome dela não
muda **E** gravar na recebida passa.

Status: 🧪

## UC-PLED-04 · Abrir planilha devolve o editor React sem dado cru · `must`

**Dado** uma planilha minha **Quando** abro `/spreadsheet/sheets/{id}` **Então** recebo
`Spreadsheet/Editor` com `planilha = { id, nome, dados, minha: true }` e `pode_gravar` **E** a prop não
traz `business_id` nem `created_by`.

Status: 🧪

## UC-PLED-05 · Planilha nova abre o editor na pasta pedida · `must`

**Quando** abro `/spreadsheet/sheets/create?folder_id=N` **Então** recebo `Spreadsheet/Editor` com
`planilha = null` e `pasta_id = N`.

Status: 🧪

## UC-PLED-06 · Recebida: "Voltar" vai às tarefas, não à lista · `should`

**Dado** uma planilha recebida **Quando** abro **Então** `planilha.minha` é falso **E** `voltar` é a lista de
tarefas do Essentials quando a rota dele existe.

Status: 🧪

## UC-PLED-07 · `?legacy=1` abre a Blade até o F5 · `must`

**Quando** abro `/spreadsheet/sheets/{id}?legacy=1` ou `/spreadsheet/sheets/create?legacy=1` **Então**
recebo as Blades `spreadsheet::sheet.show` e `spreadsheet::sheet.create`.

Status: 🧪

## [BACKLOG] só no navegador

- [BACKLOG] Importar um `.xls` mostra "Só arquivos .xlsx".
- [BACKLOG] Salvar grava `sheet_data` no mesmo formato da Blade (string do `getAllSheets()`).
