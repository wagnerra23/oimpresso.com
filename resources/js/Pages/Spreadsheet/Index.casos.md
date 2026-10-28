---
id: pages-spreadsheet-index-casos
casos: Planilhas · /spreadsheet/sheets
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do usuário + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista junta planilhas criadas e recebidas por compartilhamento; o compartilhamento cruza usuário, função e tarefa, então o escopo de tenant é Tier 0.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Planilhas (lista e pastas)

> Derivados da [SPEC](../../../../memory/requisitos/Spreadsheet/SPEC.md) US-SHEET-002, do
> comportamento da Blade `sheet/index.blade.php` (o que cada pessoa vê, "Sem pasta", ações só para
> quem criou), do [RUNBOOK](../../../../memory/requisitos/Spreadsheet/RUNBOOK-spreadsheet-index.md) e
> da decisão D1 de [W] em 2026-10-07 — **não do `Index.tsx`**.
> Teste: `Modules/Spreadsheet/Tests/Feature/SpreadsheetIndexContratoTest.php`
> (lane `spreadsheet-pest`, MySQL, tenant 98 × 99).
> Status: 🧪 escrito e na lane, veredito ainda não emitido.

## UC-PLAN-01 · Flag decide a tela; `?legacy=1` volta à Blade · `must`

**Dado** a flag `useV2SpreadsheetIndex` desligada **Quando** a pessoa abre `/spreadsheet/sheets`
**Então** recebe a Blade `spreadsheet::sheet.index` **E** com a flag ligada recebe `Spreadsheet/Index`
com `planilhas` e `pastas` adiadas **E** com `?legacy=1` volta à Blade.

Status: 🧪

## UC-PLAN-02 · [T0] Planilha de outro business não aparece, nem compartilhada · `must`

**Dado** uma planilha do business 99 com compartilhamento apontando para o usuário do business 98
**Quando** esse usuário abre a lista **Então** ela não aparece.

Status: 🧪

## UC-PLAN-03 · Vejo as minhas e as recebidas, não as alheias · `must`

**Dado** no mesmo business uma planilha minha, uma de colega compartilhada comigo e uma de colega
não compartilhada **Quando** abro a lista **Então** vejo as duas primeiras, com `minha` certo em cada
uma, **E** não vejo a terceira.

Status: 🧪

## UC-PLAN-04 · Linha sem conteúdo da planilha, com contagem de compartilhamento · `must`

**Dado** uma planilha minha compartilhada com um usuário e uma função **Quando** a lista carrega
**Então** a linha traz `compartilhada.usuarios = 1` e `compartilhada.funcoes = 1` **E** não traz
`sheet_data`.

Status: 🧪

## UC-PLAN-05 · Pastas do business e "Sem pasta"; leitura sem `create.spreadsheet` · `must`

**Dado** pastas de planilha do business 98 e do 99 **Quando** abro a lista **Então** recebo só as do
98, em ordem alfabética, **E** planilha sem pasta chega com `pasta_id` nulo **E** sem
`create.spreadsheet` a tela recebe `podeCriar = false`.

Status: 🧪

## UC-PLAN-06 · [T0] Não mexo no compartilhamento de planilha de outro negócio · `must`
- **Persona:** usuário de um negócio com o módulo de planilhas.
- **Aceite:** Dado uma planilha de outro negócio · Quando peço o compartilhamento dela ou gravo um compartilhamento nela ·
  Então recebo 404 · E os compartilhamentos dela não mudam.
- **Teste:** `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` — `UC-PLAN-06 · [T0] não mexo no compartilhamento de planilha de outro negócio`.
- **Regressão que defende:** até 2026-10-27 o `post-share-sheet` aceitava qualquer `sheet_id` e criava ou apagava
  compartilhamento de planilha de outro negócio (ADR 0093).
- **Status: ⬜** — escrito e na lane `spreadsheet-pest`; sem run do CI ainda.

## UC-PLAN-07 · Só quem criou compartilha · `must`
- **Persona:** colega que recebeu a planilha.
- **Aceite:** Dado uma planilha que recebi · Quando tento compartilhá-la com um terceiro · Então recebo 404 · E nada muda.
- **Teste:** `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` — `UC-PLAN-07 · só quem criou compartilha; colega que recebeu não redistribui`.
- **Regressão que defende:** a regra da Blade e da lista (Compartilhar só para quem criou) valer também no endpoint, não só
  no botão (SPEC US-SHEET-003; charter "quem recebeu abre e edita, mas não redistribui").
- **Status: ⬜** — escrito e na lane `spreadsheet-pest`; sem run do CI ainda.

## UC-PLAN-08 · [T0] Compartilhar só com gente do negócio · `must`
- **Persona:** dono da planilha.
- **Aceite:** Dado uma planilha minha · Quando compartilho com um colega e com um usuário de outro negócio · Então o colega
  entra · E o id de fora é ignorado.
- **Teste:** `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` — `UC-PLAN-08 · [T0] o dono compartilha com gente do negócio; id de outro negócio é ignorado`.
- **Regressão que defende:** o `post-share-sheet` gravava qualquer id em `shared_id`, inclusive usuário de outro negócio
  (ADR 0093).
- **Status: ⬜** — escrito e na lane `spreadsheet-pest`; sem run do CI ainda.

## UC-PLAN-09 · Compartilhamento em JSON para a tela React · `must`
- **Persona:** dono da planilha abrindo "Compartilhar…" na lista.
- **Aceite:** Dado uma planilha minha compartilhada com um colega · Quando a tela pede o compartilhamento em JSON · Então
  recebo usuários, funções e tarefas do negócio, sem usuário de outro negócio · E `selecionados.user` traz o colega.
- **Teste:** `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` — `UC-PLAN-09 · a tela React recebe o compartilhamento em JSON, só com gente do negócio`.
- **Regressão que defende:** a lista da Blade (`share_sheet`) usa os dropdowns do negócio; a tela React não pode ampliar isso
  (SPEC US-SHEET-003; ADR 0093).
- **Status: ⬜** — escrito e na lane `spreadsheet-pest`; sem run do CI ainda.

## UC-PLAN-10 · Excluir só a planilha que criei · `must`
- **Persona:** usuário com planilhas próprias e recebidas.
- **Aceite:** Dado uma planilha minha e uma recebida de colega · Quando excluo as duas pela tela · Então só a minha some.
- **Teste:** `Modules/Spreadsheet/Tests/Feature/SpreadsheetAcessoEditorTest.php` — `UC-PLAN-10 · excluir pela tela React só apaga planilha que eu criei`.
- **Regressão que defende:** a regra da Blade (excluir só quem criou) valer no endpoint chamado pela tela nova.
- **Status: ⬜** — escrito e na lane `spreadsheet-pest`; sem run do CI ainda.
