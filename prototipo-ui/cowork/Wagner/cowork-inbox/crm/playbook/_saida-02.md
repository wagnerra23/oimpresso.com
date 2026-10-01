---
sessao: "02"
titulo: "Leads (LeadController) → Inertia, detalhe em drawer — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 0754c7213
thread: 03-leads.md §02
veredito: "Entregue a LISTA (index) em Inertia + drawer de detalhe, com contrato + charter + casos + teste no mesmo PR (lei IT2). Formulário (D2) fica para depois: reusar Cliente/Create exige mexer em resources/js/Pages/Cliente/, que a thread não toca."
---

# _saida-02 · Leads → Inertia

## Estado ao abrir

O placar mostrou `pendente`: sem `_saida`, `decisão pendente D2,D4`, `depende de 01 (não feita)`.
D2 e D4 estão respondidas em `_DECISOES-W-2026-10-01.md` (D2 = reusa `Cliente/Create` · D4 =
`Modules/Crm/Resources/js/Pages`). A 01 foi entregue no #8345 só com o recibo, porque o check
required "Contratos de tela" reprova contrato sem a Page; pela opção 1 do `_saida-01`, o contrato
`crm-leads` entra aqui, junto da Page. Molde: thread 03 (#8352).

## O que entrou

| arquivo | o quê |
|---|---|
| `Modules/Crm/Http/Controllers/LeadController.php` | `index()` passa a renderizar `Crm/Leads/Index` com a MESMA consulta do DataTables (`business_id`, `type = lead`, filtros, "só os meus"). `?lead=ID` traz o detalhe do drawer, filtrado dessa mesma consulta. `?classico=1` e `?lead_view=kanban` mantêm a Blade |
| `Modules/Crm/Resources/js/Pages/Crm/Leads/Index.tsx` | filtros (fonte, estágio, atribuído), lista paginada (25) com as 12 colunas do protótipo, busca, densidade Confortável/Compacto, rodapé, drawer (Sheet 760) com "Informação do lead" e "Acompanhamento" |
| `governance/design/contracts/crm-leads.contract.json` | copiado do `_saida-01`; só o `alvo` mudou para o caminho real |
| `Index.charter.md` · `Index.casos.md` | 7 UCs (UC-CRMLD-01..07) |
| `Modules/Crm/Tests/Feature/CrmLeadsContratoTest.php` | 1 teste por UC, tenant 98 (99 como "outro negócio") |
| `.github/workflows/verticais-pest.yml` | a lane MySQL passa a rodar o teste e dispara com o controller e o teste |
| `memory/requisitos/{Crm,Cliente}/SUPERFICIE.md` · `Crm/_STATUS-GENERATED.md` | regerados pelos donos (`module-surface`, `requisitos-status`) |

## Decisões técnicas (do Code)

- **Desvio Inertia × DataTables:** igual à thread 03 — decide por `X-Inertia`, não por `ajax()`
  (§5 2026-09-08). UC-CRMLD-02 prova os dois lados.
- **Drawer sem rota nova:** o detalhe é a prop `lead`, pedida por partial reload (`only: ['lead']`)
  com `?lead=ID`. Sai da consulta da lista, logo não abre lead de outro negócio, cliente
  (`type ≠ lead`) nem lead de colega para quem só vê os próprios. UC-CRMLD-06.
  Observação, **não consertada** (fora da thread): o `show()` Blade (`/crm/leads/{id}`) filtra só
  `business_id`, sem `type = lead` — abre um cliente do mesmo negócio pelo id. Não é vazamento
  cross-tenant; é escopo de tipo. Fica para a fatia que migrar a ficha completa.
- **Achado do CI — a lista de leads já quebrava:** `CrmUtil::getLeadsListQuery` seleciona
  `contacts.prefix/first_name/middle_name/last_name`, colunas que não existem mais no schema (o
  handoff 2026-06-06 já registrava `contacts.first_name` removida). A lane MySQL deu
  `Unknown column 'contacts.prefix'` → 500, na Inertia **e** no DataTables da Blade. Conserto
  dentro do prefixo: o `LeadController` tira essas 4 colunas do SELECT quando o banco não as tem
  (`semColunasDeNomeRemovidas`). A raiz fica no `CrmUtil` (fora do prefixo), e o
  `Connector/Api/Crm/FollowUpController:968` chama a mesma consulta — segue exposto. Pendente.
- **Prefixo de UC:** `UC-CRMLD-`, não `UC-CRMLEAD-`. O regex canônico (`scripts/lib/uc-regex.mjs`)
  aceita prefixo de até 6 caracteres; com 7 o `requisitos-status` contou 0 UC na tela.
- **Datas** em `d/m/Y H:i` do valor gravado, sem o shift +3h do `format_date` legado (como a 03).

## Provas do json conferidas

- `Modules/Crm/Http/Controllers/LeadController.php` contém `Inertia::render(` — ✅ neste PR.
- `contrato-de-tela.mjs --contract crm-leads.contract.json` → 4 seções, âncora + copy + ordem,
  **limpo**. `--map --check` e `--anti-tautologia` limpos.
- `casos-coverage-guard`: sem violação nova. `validate.mjs` no charter: conforme.
  `integrity-check`: IT duros passaram. `tsc --noEmit`: nenhum erro no arquivo novo.
- Pest: **NÃO rodado local** (regra do repo). A prova é a lane `verticais-pest` do PR.

## Pendente (não feito, e por quê)

1. **Formulário de lead (D2).** `resources/js/Pages/Cliente/Create.tsx` grava fixo em `/contacts`
   (`post('/contacts')`) e não tem fonte/estágio/atribuído. Reusá-lo exige parametrizar o form,
   e `resources/js/Pages/Cliente/` está no `nao_toca` desta thread. "Adicionar" leva a
   `?classico=1`. Próxima fatia: uma thread com `Pages/Cliente/Create.tsx` no prefixo.
2. **Kanban, conversão para cliente, "Adicionar/Remover do local"** e as ações por linha (editar,
   excluir): seguem na Blade; os botões levam a `?classico=1`. O kanban abre direto em
   `?lead_view=kanban`.
3. **Campos personalizados** (`custom_field1..10`) da grade Blade: não entraram; o protótipo mostra
   só "Pessoa de contato" e "Interesse", que dependem do rótulo configurado por negócio.
4. **Ficha completa** (`show()`, com as abas de módulo): segue Blade; o drawer linka para ela.
5. **Aviso prévio/canary F5 (ADR 0104):** não há canary nem flag; a troca vale para **todos os
   negócios** com o módulo Crm, como na thread 03. Saída de emergência: `?classico=1`. Decisão [W]
   se quiser canary.
6. **RUNBOOK MWART:** não criado (o hook não cobra Pages de módulo), como na 03.

## Placar

Esperado após o merge: `02` com prova verde e `_saida`; segue `pendente` enquanto a 01 não virar
`feito` (as provas da 01 são os 3 contratos — com este PR, 2 de 3 existem).

## PR

O PR da branch `claude/crm-thread-02`.
