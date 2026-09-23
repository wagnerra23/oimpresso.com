---
sessao: "11"
titulo: "Limpeza: blades mortas + nav legado + inbox residual — saída da thread"
autor: "[CL]"
criado: 2026-09-23
base: 1061dbf2e
thread: 11-limpeza-blades-inbox.md
veredito: "entregue sem a parte /react — 25 Blades mortas + nav legado + inbox residual removidos; espelho-pdf fica; W8 não respondida, rota /react intocada."
---

# _saída 11 · Limpeza

## O que saiu, o que ficou

| item | ação | por quê |
|---|---|---|
| 25 Blades em `Modules/Ponto/Resources/views/` (incl. `layouts/module.blade.php` e `dashboard/index.blade.php`) | **removidas** | nenhuma é renderizada — ver varredura abaixo |
| `reports/espelho-pdf.blade.php` | **fica** | `Modules/Ponto/Services/ReportService.php:103` — `PDF::loadView('pontowr2::reports.espelho-pdf', $data)` |
| `cowork-inbox/ponto-dashboard/Index.casos.md` (7.817 B) | **removido** | o casos vivo é `resources/js/Pages/Ponto/Dashboard/Index.casos.md` (26.162 B) |
| `Resources/lang/` | intocado | usado pelas Pages e pelo `DataController` (`module_label`, `menu.*`) |
| rota `/ponto/react` + `Welcome.*` + `WelcomeContratoTest` | **intocados** | **W8 segue `respondida: false`** no JSON do `00-INDICE.md` |

⚠️ **Contagem da thread:** o `11-*.md` dizia 27 Blades / 26 mortas; a lista dela por pasta soma **26**. Nesta sha havia **26 arquivos**, logo **25 mortas + 1 viva**.

## Varredura (repo inteiro, nesta sha)

- `git grep -n -E "pontowr2::[a-z_-]+\.[a-z_.-]+" -- . ':!Modules/Ponto/Resources/views/**'`, descontando as chaves de lang `pontowr2::ponto.*`: **1 uso de código** (o `loadView` acima). O resto são **comentários/docs** (IntercorrenciaController docblock, RUNBOOK/SDD/charters/casos citando a Blade como história).
- Nenhum teste nem script lê as Blades do disco: `git grep "Ponto/Resources/views"` em `scripts/`, `tests/`, `Modules/**/Tests/`, `.github/` → só um docblock em `EspelhoContratoTest.php:24` (âncora histórica do UC, não leitura de arquivo).
- `gh pr list --state open` (12 abertos): **0** tocam `Modules/Ponto/Resources/views/`, `Modules/Ponto/Http/routes.php` ou `cowork-inbox/ponto-dashboard/`.

## Fora do prefixo, declarado

- `Modules/Ponto/Http/Controllers/IntercorrenciaController.php` — **só docblock**: dizia que a Blade `intercorrencias.edit` "NÃO foi apagada"; passaria a afirmar algo falso. Agora aponta para `git show 1061dbf2e:…/intercorrencias/edit.blade.php` (o contrato de paridade segue acessível no histórico).
- `memory/requisitos/Ponto/SUPERFICIE.md` — derivado, regerado por `module-surface.mjs Ponto --write` (216 → 191 arquivos; Views 26 → 1). `--all --check` verde.

## Medições

- `node scripts/governance/blade-migration-census.mjs --ratchet` → OK (nenhum escopo subiu). Esta limpeza não muda endpoints — nenhum controller servia essas Blades.
- Testes: não rodei `TelasNavegacaoTest` no CT 100 — o checkout do container não é esta branch, então verde lá não provaria esta mudança. A prova é a lane `ponto-pest.yml` do PR.

## Resíduo que esta thread NÃO fecha

- **Cowork precisa apagar `cowork-inbox/ponto-dashboard/` do lado dele.** O import do espelho é `/PURGE` a partir do projeto Cowork: se o arquivo continuar lá, o próximo `receber-handoff` o traz de volta.
- **W8** (`/ponto/react`): decisão [W]. Com a resposta, a parte `/react` vira um PR próprio.
