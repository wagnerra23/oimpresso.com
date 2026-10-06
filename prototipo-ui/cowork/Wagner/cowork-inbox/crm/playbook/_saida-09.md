---
sessao: "09"
titulo: "Leads: editar no Cliente/Edit parametrizado — saída da thread"
autor: "[CL]"
data: 2026-10-06
base: origin/main 73ba361e01
thread: 09-leads-editar.md
veredito: "Entregue: 'Editar' abre /crm/leads/{id}/edit, que é o Cliente/Edit parametrizado (destino + fonte/estágio/atribuído). A edição de cliente segue igual. Em 2 PRs (o diff passou de 300 linhas)."
---

# _saida-09 · Editar lead

## Estado ao abrir

`placar.mjs --thread 09 --proximo` → `proximo` (dependência 06 feita). `LeadController::edit()`
devolvia `view('contact.edit')`, o fragmento que o modal Blade carregava por ajax. Nenhum PR aberto
nem sessão viva em `LeadController.php` ou `Cliente/Edit.tsx` (`whats-active`, `gh pr list`,
`dup-detector --path`); `git log HEAD..origin/main` vazio nesses caminhos.

## O que entrou

| PR | arquivo | o quê |
|---|---|---|
| 1 | `resources/js/Pages/Cliente/Edit.tsx` | props opcionais `destino` (url do PUT, voltar, título, rótulo do salvar) e `lead_opcoes` (deferida). Com `destino` aparece a seção "Dados do lead" e o PUT vai para `destino.url`; o documento vai também em `tax_number`, que é o que o update do Crm lê. **Sem `destino` o caminho é o de antes, literal** — `put(\`/contacts/${c.id}\`)`, "Editar cliente", voltar para o detalhe |
| 1 | `resources/js/Pages/Cliente/_form/DadosLeadSection.tsx` | a seção "Dados do lead" (fonte · estágio de vida · atribuído a) sai do `Create.tsx` e passa a ser usada pelas duas telas; o `<Deferred data="lead_opcoes">` fica na Page, que é onde o `InertiaDeferredFrontendGuardTest` procura |
| 1 | `resources/js/Pages/Cliente/Create.tsx` | usa a seção compartilhada; comportamento igual |
| 1 | `Cliente/Edit.charter.md` · `memory/requisitos/Cliente/SUPERFICIE.md` | Goal "Modo lead"; superfície regerada |
| 2 | `Modules/Crm/Http/Controllers/LeadController.php` | `edit()` → `Inertia::render('Cliente/Edit', …)` com `destino` e `lead_opcoes` deferida (fontes, estágios e usuários por `business_id`) |
| 2 | idem, `update()` | **três consertos:** (a) as partes do nome não são colunas de `contacts` — montam o `name` e saem do update (o defeito que o `_saida-06` registrou); o tipo gravado é sempre `lead`. (b) **[T0]** fonte, estágio e atribuído só valem se forem do `business_id` da sessão. (c) visita Inertia recebe redirect para a ficha; o JSON fica para quem não manda `X-Inertia` |
| 2 | idem, `edit/update/destroy` | **filtram `type = lead`** (helper `leadQuery`, com "só os meus") — o que a 08 deixou aberto depois de pôr o filtro no `show`. Cliente pelo id ou lead de outro negócio: edit/update → 404 (o update antes caía no catch e dizia "algo deu errado"); destroy → sem sucesso |
| 2 | idem, lista e kanban clássicos | o link "Editar" vira navegação direta (`href`, sem a classe `edit_lead` que abria o modal por ajax) |
| 2 | `Leads/Index.casos.md` · `Index.charter.md` | UC-CRMLD-11..13; Non-Goal da edição atualizado com a data |
| 2 | `Modules/Crm/Tests/Feature/CrmLeadsContratoTest.php` | 3 testes novos, tenant 98 (99 = outro negócio), cada um com âncora positiva |
| 2 | `.github/workflows/verticais-pest.yml` | a lane dispara também com `Cliente/Edit.tsx` e `_form/DadosLeadSection.tsx` |

## Decisões técnicas (do Code)

- **Nome no primeiro campo.** `contacts` só tem `name`; o edit manda o nome inteiro em
  `first_name` e o update junta as partes de novo. Editar e salvar sem mexer preserva o nome.
- **Seção compartilhada em vez de cópia.** O bloco do lead tinha ~70 linhas no Create; a 2ª tela
  que precisava dele ganhou um componente em `_form/`, não uma cópia.
- **`leadQuery` como base de edit/update/destroy.** O `show` (thread 08) mantém a consulta dele.

## Provas

- `LeadController.php` não contém `view('contact.edit')` — `grep -c` = **0** (prova da thread).
- `tsc --noEmit`: 302 erros no projeto, o mesmo total do `_saida-06`; nenhum nos 3 arquivos tocados.
- `validate.mjs` nos 2 charters → conforme. `casos-coverage-guard` → sem violação nova.
  `module-surface` Crm e Cliente → sem drift.
- Pest: não rodado local. Vale a lane — ver §Lanes.

## Lanes

- `verticais-pest` no head do #8756 (dispatch `37462576100`, `9c3f37e447`): UC-CRMLD-11, 12 e
  13 ✓ — **271 passed · 1658 assertions, 0 falhas**. `test-lane-coverage --pr 8756` → exit 0.
- `cliente-pest` no head do #8755 (`37451275901`): `ClienteEditInertiaTest`, `Wave1EditInertiaTest`,
  `Wave1CreateInertiaTest` PASS — 267 passed · 1362 assertions. `test-lane-coverage --pr 8755` → exit 0
  (o PR 1 não toca teste).
- Vermelhos que apareceram no caminho e **não eram deste trabalho:** UC-CRMACO-20 e UC-SANEG-14 na
  `verticais-pest` (já falhavam no push de `main` `211b51697c`, run `37382355413`; consertados no
  `main` antes do run final) e o `governance script tests` (advisory: `tema-escuro-probe.mjs` fora do
  `MAQUINAS-INVENTARIO`, falha também no push de `main` `da691acc74`).
- Vermelhos que **eram** deste trabalho, consertados no próprio PR: G-6 do `casos-coverage` (o
  `last_run` dos casos de Create/Edit do Cliente e dos Leads, atualizado com recibo);
  `InertiaDeferredFrontendGuardTest` (a Page alvo de `Inertia::defer` precisa importar `<Deferred>` —
  o embrulho voltou para a Page, e o `DadosLeadSection` ficou só com os campos); PHPStan no
  `@return` de `edit`/`update`. Nota: o `casos-coverage-guard` local deu verde antes do commit
  porque mede o git, não o working tree (§5 2026-08-20).

## Pendente (não feito, e por quê)

1. **Campos personalizados** (`custom_field1..10`) e "é exportação" seguem fora do form (mesmo
   pendente da 06).
2. O handler `.edit_lead` do `crm.js` ficou sem quem o use; não foi apagado (fora do prefixo).
3. **Canary/aviso F5:** como nas threads 02/03/06, vale para todos os negócios com Crm.

## PR

PR 1 [#8755](https://github.com/wagnerra23/oimpresso.com/pull/8755) `claude/crm-thread-09` (front, merge `62aab11e36`) · PR 2 [#8756](https://github.com/wagnerra23/oimpresso.com/pull/8756) `claude/crm-thread-09-lead` (Crm, empilhado no 1; base trocada para `main` depois do merge do 1; merge `1e175d62f6`). Merges feitos pelo gerente da fila de PRs.
