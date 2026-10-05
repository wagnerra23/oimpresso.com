---
sessao: "07"
titulo: "Acompanhamentos: escrita em Inertia — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main ce42054202
thread: 07-acompanhamentos-escrita.md
veredito: "PR-a (adicionar, editar, excluir) e PR-b (recorrente: adicionar e editar) entregues em Inertia, pelas mesmas rotas. Registro (log) no PR seguinte; antecipado pendente (PR-c)."
---

# _saida-07 · Acompanhamentos → escrita em Inertia

## Placar

| PR | escopo | estado |
|---|---|---|
| PR-a (#8649) | modal "Adicionar/Editar acompanhamento" + coluna "Ação" (Editar, Excluir com confirmação) | mergeado |
| PR-b (`claude/crm-acompanhamentos-recorrente-prb`) | modal "Acompanhamento recorrente": adicionar pela toolbar e "Editar" na aba recorrente | aberto |
| PR-b2 (empilhado no PR-b) | "Adicionar registro" (log) no kebab do avulso, por `ScheduleLogController@store` | a abrir |
| PR-c | "Acompanhamento antecipado"; rodapé por status/tipo; drawer de detalhe (com a lista de registros) | **não feito** |

Prova do json: *"UCs de escrita verdes; os botões da toolbar deixam de levar a `?classico=1`"* —
**parcial: entregue 2 de 3 botões da toolbar.** "Adicionar" (PR-a) e "Recorrente" (PR-b) abrem
modal na própria tela; **ausente "Acompanhamento antecipado"**, que segue em `?classico=1` porque
monta os grupos de clientes/faturas por `getFollowUpGroups`, que devolve HTML (partials Blade) —
trazê-lo exige um formato JSON daquela rota, e o PR-b passaria de 300 linhas. A thread fica `em curso`.

## O que entrou (PR-a)

| arquivo | o quê |
|---|---|
| `Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/_components/FormAcompanhamento.tsx` | modal no padrão do protótipo (`crm-blade.jsx` → Modal "Adicionar acompanhamento"): título, cliente/lead, status, início/fim, descrição, tipo, categoria, atribuídos, notificação |
| `…/Acompanhamentos/Index.tsx` | "Adicionar" abre o modal; coluna "Ação" (kebab) com Editar e Excluir; confirmação de exclusão |
| `Modules/Crm/Http/Controllers/ScheduleController.php` | a linha da lista traz `editar` (valores crus do modal) e as opções ganham `notificar`; `update` ignora `business_id`/`created_by` do payload |
| `Modules/Crm/Services/ScheduleService.php` | datas por `Util::uf_datetime_input` (aceita ISO do modal e o formato da empresa da Blade) |
| `Modules/Crm/Http/Requests/{Store,Update}ScheduleRequest.php` | `contact_id` e `user_id[]` validados contra o negócio da sessão |
| `Index.charter.md` · `Index.casos.md` | UC-CRMACO-08..13 |
| `Modules/Crm/Tests/Feature/CrmAcompanhamentosContratoTest.php` | 1 teste por UC novo, tenant 98 (99 = outro negócio) |
| `.github/workflows/verticais-pest.yml` | a lane passa a disparar também com o Service e os FormRequests |
| `memory/requisitos/Crm/RUNBOOK-acompanhamentos.md` | RUNBOOK MWART (pendente 4 do `_saida-03`) |

## Decisões técnicas (do Code)

- **Mesmas rotas, sem endpoint novo.** `store`/`update`/`destroy` devolvem `{success, msg}` para
  ajax, então o modal usa `fetch` e recarrega só `acompanhamentos` (`router.reload`).
- **Furos Tier 0 fechados no caminho** (provas: UC-CRMACO-09 e 12): o `Schedule` tem
  `$guarded = ['id']` e o `update` repassava o payload inteiro (um `business_id` no formulário
  moveria o acompanhamento de negócio); e `contact_id` não era validado contra o negócio (a lista
  mostraria o nome do contato alheio; idem `user_id[]`, que o `sync` aceitaria de outro negócio). Valem também para a Blade.
- **Excluir na aba recorrente** usa o mesmo `destroy`. **Editar recorrente** segue na Blade.

## Testes

- Pest **não rodado local** (regra do repo). Prova = lane `verticais-pest` do PR; ver o contador e
  as assertions no log, não "0 failed" (LC-13).
- Antes do PR: `contrato-de-tela` limpo, `layout-primitives-guard` sem regressão,
  `casos-coverage-guard` sem violação nova, `tsc --noEmit` sem erro nos arquivos da tela.

## O que entrou (PR-b)

| arquivo | o quê |
|---|---|
| `…/Acompanhamentos/_components/FormRecorrente.tsx` | modal com os campos da Blade `create_recursive_follow_up`: categoria, acompanhamento por, em dias, atribuídos, título, descrição, status, tipo, notificação |
| `…/Acompanhamentos/Index.tsx` | "Recorrente" abre o modal; "Editar" da aba recorrente abre o mesmo modal (a Blade **não tinha** editar no recorrente, só excluir) |
| `Modules/Crm/Http/Controllers/ScheduleController.php` | opções `recorrencia` (valor + grupo → `follow_up_by`) e a linha traz `follow_up_by`, `follow_up_by_value`, `recursion_days` |
| `Modules/Crm/Services/ScheduleService.php` | `updateFollowUp` só normaliza data preenchida: o recorrente não tem datas e `uf_date('')` estourava |
| `Index.charter.md` · `Index.casos.md` · `CrmAcompanhamentosContratoTest.php` | UC-CRMACO-14 (adicionar recorrente) e 15 (editar recorrente, com `business_id` injetado) |

Achado, **não consertado** (fora do prefixo): a Blade `create_recursive_follow_up` manda `is_recursive=true`, e a regra `boolean` do `StoreScheduleRequest` não aceita `"true"` — a tela clássica deve dar 422 no recorrente. Não medido em produção.

## Pendente

1. **PR-b2**: "Adicionar registro" (log), empilhado neste PR.
2. **PR-c**: acompanhamento antecipado (precisa de JSON em `getFollowUpGroups`); rodapé por
   status/tipo e drawer de detalhe com a lista de registros (pendente 2 do `_saida-03`).
3. Tier 0 a olhar no PR-c: `addAdvanceFollowUp` usa as chaves de `follow_ups` como `contact_id` e
   sincroniza `invoices` sem validar contra o negócio.
4. Smoke em produção após o merge (Regra 0 / R1).
