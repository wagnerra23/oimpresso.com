---
sessao: "07"
titulo: "Acompanhamentos: escrita em Inertia — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main ce42054202
thread: 07-acompanhamentos-escrita.md
veredito: "PR-a entregue (adicionar + editar + excluir em Inertia, pelas mesmas rotas). PR-b (recorrente, antecipado, log) pendente."
---

# _saida-07 · Acompanhamentos → escrita em Inertia

## Placar

| PR | escopo | estado |
|---|---|---|
| PR-a (`claude/crm-thread-07a`) | modal "Adicionar/Editar acompanhamento" + coluna "Ação" (Editar, Excluir com confirmação) | aberto, este recibo |
| PR-b | recorrente + antecipado + log; rodapé por status/tipo; drawer de detalhe | **não feito** |

Prova do json: *"UCs de escrita verdes; os botões da toolbar deixam de levar a `?classico=1`"* —
**parcial**. "Adicionar" deixou de levar à Blade; "Recorrente" e "Acompanhamento antecipado"
seguem em `?classico=1` até o PR-b. A thread fica `em curso`.

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

## Pendente

1. **PR-b**: recorrente, antecipado, log de acompanhamento em Inertia.
2. Rodapé por status/tipo e drawer de detalhe (pendente 2 do `_saida-03`).
3. Smoke em produção após o merge (Regra 0 / R1).
