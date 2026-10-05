---
sessao: "08"
titulo: "Leads: escopo da ficha (show) e raiz do SELECT do CrmUtil — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main ce42054202
thread: 08-leads-escopo.md §08
veredito: "Entregue 2 de 2. Achado 1 consertado neste PR (ficha só abre type = lead). Achado 2 já estava consertado na raiz pelo #8373 (2026-10-01), com teste verde no main; aqui só confirmado."
---

# _saida-08 · Leads: escopo

## Estado ao abrir

`git log origin/main --since=2026-10-01 -- Modules/Crm` mostrou o #8373
(`fix(crm): getLeadsListQuery sem colunas de nome removidas de contacts`). Nenhum PR aberto sobre
o tema (`gh pr list --state open`: só #8642 e #8633, de outros assuntos). A ficha desta thread
foi escrita sobre a base `99e6fa3e08f0`, antes desse merge.

## Achado 1 — ficha `/crm/leads/{id}` sem `type = lead` · consertado

- **Confirmado:** `LeadController::show()` montava a consulta só com `business_id` (+ "só os
  meus"). Um cliente ou fornecedor do mesmo negócio abria pelo id na ficha de lead.
- **Conserto:** `->where('type', 'lead')` na consulta. Fora do tipo é 404 (`findOrFail`), como
  fora do negócio.
- **Quem linka para cá** (varredura de `LeadController::class, 'show'` e `LeadController@show`
  fora de testes, 3 de 3): a lista (`:121`), o kanban (`:260`) e a agenda
  (`ScheduleController:217`, que já só usa esse link quando `contact_type == "lead"` e manda o
  resto para o `ContactController@show`). Nenhum link legítimo passa a dar 404.
- **Teste:** `Modules/Crm/Tests/Feature/CrmLeadShowEscopoTest.php`, tenant 98 × 99:
  lead do próprio negócio → 200 + view `crm::lead.show` (âncora positiva: prova que o 404 dos
  outros casos é do controller, não de rota) · cliente e fornecedor do mesmo negócio → 404 ·
  lead de outro negócio → 404.

## Achado 2 — `CrmUtil::getLeadsListQuery` e as colunas de nome · já consertado (#8373)

- **Schema conferido:** `database/schema/mysql-schema.sql`, tabela `contacts`, tem `name` e não
  tem `prefix/first_name/middle_name/last_name` (a migration de 2020 as criava; o baseline atual
  não as tem).
- **Raiz já consertada no main:** o SELECT agora devolve as 4 chaves como aliases
  (`NULL as prefix`, `contacts.name as first_name`, …) — o formato da API não muda. Isso cobre os
  dois chamadores (`LeadController::index` e `Connector/Api/Crm/FollowUpController:968`).
- **Teste existente:** `Modules/Crm/Tests/Feature/CrmLeadsListQueryTest.php` — inclui
  "FollowUpController::getLeads (GET connector/api/crm/leads) não dá 500 e não vaza lead de outro
  negócio [T0]". Verde no push de `main` de 2026-10-05 (run `37302535959`, lane
  `PHP / Pest (Verticais · MySQL)`, os dois casos com `✓`). Nada a fazer aqui.

## O que entrou

| arquivo | o quê |
|---|---|
| `Modules/Crm/Http/Controllers/LeadController.php` | `show()` filtra `type = lead` |
| `Modules/Crm/Tests/Feature/CrmLeadShowEscopoTest.php` | 3 casos (âncora positiva + 2 de 404) |
| `.github/workflows/verticais-pest.yml` | o teste entra na lane MySQL e no gatilho |
| `memory/requisitos/{Crm,Cliente}/SUPERFICIE.md` | regerados pelo `module-surface` |

## Provas

- Prova do índice ("show de um cliente (não lead) pelo id → 404; API de follow-up não dá 500"):
  a 1ª metade é o teste novo, a 2ª é o teste do #8373 já verde no main.
- Pest: **NÃO rodado local** (regra do repo). A prova da metade nova é a lane `verticais-pest`
  deste PR — conferir no log os 3 casos de `CrmLeadShowEscopoTest` com `✓`, não só "0 failed".
- `module-surface --all --check`: sem drift depois de regenerar.

## Pendente (não feito, e por quê)

1. `edit()`, `update()` e `destroy()` do mesmo controller montam a consulta do mesmo jeito (só
   `business_id`). Não consertei: fora da prova desta thread. Mesmo conserto de 1 linha cada,
   fica para uma thread própria.
2. Os casos novos não viraram UC no `Index.casos.md` porque ele mora em `Pages/` (`nao_toca`
   desta thread) e cobre a lista Inertia, não a ficha Blade.
