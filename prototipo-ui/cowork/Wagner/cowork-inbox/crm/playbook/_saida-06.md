---
sessao: "06"
titulo: "Leads: formulário (reusa Cliente/Create parametrizado — D2) — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main ce42054202
thread: 06-leads-form.md
veredito: "Entregue: 'Adicionar' abre /crm/leads/create, que é o Cliente/Create parametrizado (destino + fonte/estágio/atribuído). O cadastro de cliente segue igual. Em 2 PRs (o diff passou de 300 linhas)."
---

# _saida-06 · Formulário de lead

## Estado ao abrir

Pendente 1 do `_saida-02`: "Adicionar" levava a `?classico=1` e o `LeadController@create`
devolvia o fragmento de modal `view('contact.create')`. D2 ([W] 2026-10-01) = reusar
`Cliente/Create`. Nenhum PR aberto e nenhum commit desde 2026-10-01 em `Modules/Crm` ou
`resources/js/Pages/Cliente` fazia isto (conferido antes de começar).

## O que entrou

| PR | arquivo | o quê |
|---|---|---|
| 1 | `resources/js/Pages/Cliente/Create.tsx` | props opcionais `destino` (url, voltar, títulos, rótulo do salvar) e `lead_opcoes` (deferida). Com `destino`, aparece a seção "Dados do lead" (Fonte · Estágio de vida · Atribuído a) e o POST vai para `destino.url`; o documento vai também em `tax_number`, que é o que o store do Crm lê. **Sem `destino` o caminho é o de antes, literal** — `post('/contacts'`, "Novo cliente", voltar para `/contacts/customer` |
| 1 | `resources/js/Pages/Cliente/Create.charter.md` | Goal "Modo lead" |
| 2 | `Modules/Crm/Http/Controllers/LeadController.php` | `create()` → `Inertia::render('Cliente/Create', …)` com `destino` e `lead_opcoes` deferida (fontes, estágios e usuários por `business_id`). `store()` responde redirect para `/crm/leads` quando a visita é Inertia; o JSON do modal Blade fica para quem não manda `X-Inertia` |
| 2 | idem, `store()` | **dois consertos achados no caminho:** (a) as partes do nome (`prefix/first_name/middle_name/last_name`) iam para o insert, mas não são mais colunas de `contacts` — o create caía em `Unknown column` e respondia "algo deu errado" (mesma causa do `_saida-02`, agora no store); saem do insert como o `ContactController@store` já faz. (b) **[T0]** atribuído, fonte e estágio eram aceitos com qualquer inteiro: um id de outro negócio entrava no lead. Agora só valem se forem do `business_id` da sessão. O tipo gravado é sempre `lead` |
| 2 | `Modules/Crm/Resources/views/lead/index.blade.php` | o botão "Adicionar" da tela clássica vira link para `/crm/leads/create` (antes abria o modal por ajax, que o create não devolve mais) |
| 2 | `Modules/Crm/Resources/js/Pages/Crm/Leads/Index.tsx` | "Adicionar" → `/crm/leads/create` |
| 2 | `Index.casos.md` · `Index.charter.md` | UC-CRMLD-08..10; Non-Goal do formulário riscado com a data |
| 2 | `Modules/Crm/Tests/Feature/CrmLeadsContratoTest.php` | 3 testes novos, tenant 98 (99 = outro negócio) |
| 2 | `.github/workflows/verticais-pest.yml` | a lane passa a disparar também com `resources/js/Pages/Cliente/Create.tsx` |

## Decisões técnicas (do Code)

- **Seção do lead fora do `ClienteForm`.** O `_form/ClienteForm.tsx` é compartilhado com o
  Edit e não está no prefixo; a seção "Dados do lead" fica no `Create.tsx`, acima do form. O
  `useForm` é um só, então o submit leva os campos de lead junto.
- **Atribuído a = um usuário** (Select único, como o protótipo `crm-blade.jsx` desenha
  "Atribuído"). O backend continua aceitando lista (`user_id[]`); a Blade antiga permitia vários.
- **`ehLead` decide por `destino`, não por `lead_opcoes`:** a segunda é deferida e não existe
  no primeiro render.
- Rótulos "Fonte", "Estágio de vida", "Atribuído a": os da lista de leads e do protótipo.

## Provas do json conferidas

- `LeadController.php` não contém `view('contact.create')` — `grep -c` = **0** (prova da thread).
- `Wave1CreateInertiaTest` (lane Cliente) segue com as strings que ele procura
  (`post('/contacts'`, `Novo cliente`, `useForm`, `AppShellV2`, sem `: any`).
- `contrato-de-tela --contract crm-leads.contract.json` → limpo. `validate.mjs` nos dois
  charters → conforme. `layout-primitives-guard` → sem regressão. `casos-coverage-guard` → sem
  violação nova. `tsc --noEmit` → **nenhum erro** em `Cliente/Create.tsx` nem em
  `Crm/Leads/Index.tsx` (o projeto tem 302 erros pré-existentes em outros arquivos).
  `php -l` nos 2 PHP → sem erro.
- Pest: **não rodado local** (regra do repo). A prova são as lanes `verticais-pest` (UC-CRMLD-08..10)
  e `cliente-pest` do PR — ler `assertions`, não só "0 failed".

## Pendente (não feito, e por quê)

1. **Edição do lead** (`edit()` → `view('contact.edit')`) segue Blade. Reusar `Cliente/Edit`
   é outra thread. O `update()` tem o mesmo defeito das partes do nome que o store tinha —
   não consertado aqui (fora do intent).
2. **Campos personalizados** (`custom_field1..10`) e "é exportação" do form Blade não estão no
   `Cliente/Create`; o store aceita, a tela não pede.
3. **Canary/aviso F5:** como nas threads 02/03, vale para todos os negócios com Crm.

## Placar

Esperado após o merge dos 2 PRs: `06` com prova verde (`nao_contem` no `LeadController`) e `_saida`.

## PR

PR 1 `claude/crm-thread-06` (form parametrizado) · PR 2 `claude/crm-thread-06-lead` (Crm, empilhado no 1).
