---
sessao: "04"
titulo: "Planilhas em Inertia — Index entregue atrás de flag OFF; editor e ações de escrita seguem na Blade"
autor: "[CL]"
data: 2026-10-13
base: origin/main f5f11b253f9e
thread: 04-planilhas.md
veredito: "entregue (Index, só leitura) — trio + controller dual-render + teste de contrato + lane MySQL nova. Flag useV2SpreadsheetIndex desligada; ligar é cutover do [W]."
---

# _saida-04 · Planilhas (`/spreadsheet/sheets`)

## 1 · Feito

- `resources/js/Pages/Spreadsheet/Index.tsx` — árvore de pastas recolhível ("Sem pasta" por último),
  linha com "Editada há", autor, contagem de compartilhamento, "Abrir" e kebab; cabeçalho com
  "Nova pasta" e "Criar planilha"; busca por nome; expandir/recolher tudo. Forma de
  `planilhas-page.jsx`.
- `Index.charter.md` + `Index.casos.md` (UC-PLAN-01..05, derivados da SPEC US-SHEET-002 e da Blade).
- `SpreadsheetController::index()` — renderiza `Spreadsheet/Index` com a flag
  `useV2SpreadsheetIndex` ligada e sem `?legacy=1`; senão a Blade de sempre. Props `planilhas` e
  `pastas` adiadas, DTO sem `sheet_data`.
- **Tier 0 corrigido no caminho:** a consulta da lista encadeava `orWhere` sem agrupar, então os ramos
  de compartilhamento (usuário/função/tarefa) escapavam do `business_id`. Agora uma consulta só
  (`planilhasVisiveis`), agrupada, serve a Blade e a tela React. UC-PLAN-02 é o caso que prova.
- `roles()->first()->id` estourava para usuário sem função — virou `?->id`.
- `Modules/Spreadsheet/Tests/Feature/SpreadsheetIndexContratoTest.php` + lane nova
  `.github/workflows/spreadsheet-pest.yml` (MySQL, `--check-assertions`) registrada no
  `gates-registry.json`. A lane roda também o `MultiTenantIsolationTest`.
- RUNBOOK F1 (`memory/requisitos/Spreadsheet/RUNBOOK-spreadsheet-index.md`), âncora na SPEC,
  `SCOPE.md` (`migracao_ui: liberado`), `SUPERFICIE.md` e `_STATUS-GENERATED.md` regenerados.

## 2 · Verificado localmente

- `php -l` nos 2 PHP · `tsc` sem erro no arquivo · `eslint` limpo · baselines de typecheck/eslint sem
  regressão · `memory-schemas/validate.mjs` OK (runbook, charter, spec) · `casos-coverage-guard`
  sem violação nova · `screen-coverage-map --check` OK · `pt-conformance --check` OK ·
  `uc-id-lint` 0 fora do formato · `pages-colisao --check` OK · `required-always-run` OK.
- **Não rodado:** Pest (regra: só CT 100/CI; sem acesso ao CT 100 nesta sessão). O veredito vem da
  lane `spreadsheet-pest` no PR — os UCs ficam 🧪 até ela emitir.

## 3 · Pendente

1. **Tier 0 irmão, não consertado aqui (medido em `SpreadsheetController.php`):** `show()` (≈L186-215)
   e `update()` (≈L262-286) montam a consulta com `where(business_id)->where(id)` seguidos de
   `orWhere` sem agrupar. Os ramos de compartilhamento escapam do `business_id` **e do `id`**: o
   `firstOrFail()` pode devolver outra planilha compartilhada com a pessoa. `destroy()` está certo
   (só `business_id` + `created_by`). Precisa de PR próprio com caso cross-tenant — é o próximo.
2. **04b — editor** (`show`/`create`, Luckysheet) segue Blade.
3. **Ações de escrita na tela React** — nova pasta, mover, compartilhar, excluir: hoje levam à Blade
   (`?legacy=1`). "Renomear pasta" do protótipo não tem rota no backend.
4. **Contrato de tela** `governance/design/contracts/planilhas.contract.json` não escrito: não há alvo
   medido (`governance/design/targets/…alvo.json`) para derivar; e as âncoras `data-contract`
   (`cabecalho`, `arvore`) já estão na Page.
5. **7 testes antigos do módulo seguem órfãos** (`ArchitectureTest`, `Wave26..28`, etc.) — nunca
   rodaram em MySQL; entram na lane em onda própria.
6. **Cutover (F5):** ligar `useV2SpreadsheetIndex` é decisão [W], com aviso + canary 7d; antes, smoke
   visual da tela em produção.
7. SPEC do módulo está `status: arquivado` (fora do anchor-lint) — com a D1 reabrindo o módulo, vale
   rever esse status.
