---
last_validated: "2026-09-05"
slug: runbook-licencas
title: "RUNBOOK — /hrm/leave (Licenças)"
type: runbook
module: Essentials
page: /hrm/leave
component: resources/js/Pages/Essentials/Licencas/Index.tsx
status: rascunho
updated_at: 2026-09-05
version: 0.1
owner: W
---

# RUNBOOK — `/hrm/leave` · Licenças (HRM)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para
> o **PR-9** da onda HRM-O7 do
> [`PEDIDO-CL-hrm.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md).
> Alvo visual medido: [`EXPORT-HRM-2026-09-04.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md) §3.
>
> **Escopo desta onda:** a tela de **Licenças**. Presença, Folha e Painel do HRM **não** entram —
> a [emenda de 2026-09-05 do pedido](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)
> registra que D1 passou a jornada para o `Modules/Ponto` e D2 abriu a folha com encargos como
> projeto próprio. A mesma emenda diz, com todas as letras, que licença **não** é afetada por
> D1/D2/D3 — por isso esta onda anda sozinha.

## 1. Quando esta tela quebra (sintomas)

| Sintoma | Onde olhar |
|---|---|
| `/hrm/leave` devolve **403** | O gate é duplo: assinatura (`essentials_module` no pacote do negócio) **e** permissão (`essentials.crud_all_leave` **ou** `essentials.crud_own_leave`). Sem nenhuma das duas permissões o controller aborta antes de renderizar. |
| Tela abre mas a lista fica no **skeleton** | `licencas` é `Inertia::defer`. Se o partial reload não chega, ver Network `?only[]=licencas` — e conferir se a resposta veio como **JSON do DataTables** em vez de payload Inertia (ver §5, é a armadilha desta tela). |
| Filtro não muda a lista | Os filtros são **server-side** (querystring). Se a URL muda e a lista não, o `only:` do `router.get` não inclui `licencas`. |
| Contador "N de M" com M errado | `M` é o `total` do paginador **depois** dos filtros. Se divergir do KPI, lembre que os KPIs **ignoram** o filtro de propósito (são do negócio inteiro). |
| "Pedir licença" devolve *"algo deu errado"* sem dizer o quê | Quase sempre **formato de data**: o servidor grava com `ModuleUtil::uf_date()`, que parseia com `session('business.date_format')` (default do schema `m/d/Y`). Ver §4. |
| Aprovar devolve **422** | É o `LeaveBalanceService`: o pedido estoura `max_leave_count` do tipo na janela (`year`/`month`). A `msg` do JSON diz o saldo — ela é mostrada no toast. |
| Colaborador vê licença de outro | **Tier 0.** Ver §6 — o recorte é do controller (`queryLicencasSemFiltro`), não da UI. |

## 2. Estrutura

```
Modules/Essentials/
├── Http/Controllers/EssentialsLeaveController.php   # index (Inertia + ramo DataTables), store, changeStatus, destroy, activity
├── Http/Requests/StoreLeaveRequest.php              # validação do pedido (A2 — PR #6797)
├── Services/LeaveRequestService.php                 # statusMap + scope por permissão
├── Services/LeaveBalanceService.php                 # limite por tipo (A3 — PR #6797)
└── Tests/Feature/HrmLicencaTest.php                 # UC-HRM-* (servidor + tela)

resources/js/Pages/Essentials/Licencas/
├── Index.tsx                                        # a tela (PT-01 Lista + drawer PT-02)
├── Index.charter.md                                 # a lei
└── Index.casos.md                                   # o contrato de UC

prototipo-ui/contrato/essentials-licencas.contract.json   # copy literal + ordem das seções
e2e/essentials-licencas.spec.ts                           # stub Playwright
```

## 3. Props que o controller entrega

| Prop | Eager/defer | Por quê |
|---|---|---|
| `licencas` | **defer** | paginado (25) + `with()` de tipo e colaborador |
| `tipos`, `colaboradores` | **defer** | batem no banco só para preencher `<Select>` |
| `kpis`, `saldos` | **defer** | agregações; `saldos` percorre os tipos |
| `filtros`, `permissoes`, `situacoes`, `hoje`, `date_format` | eager | UI state, booleanos e escalares — `Inertia::defer` aqui só adicionaria round-trip |

Regra do projeto: `Inertia::defer` é **default** para prop cara
([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).

## 4. A pegadinha da data (leia antes de mexer no formulário)

`store()` converte com `ModuleUtil::uf_date($valor)`, que faz
`Carbon::createFromFormat(session('business.date_format'), $valor)`. Um `<input type="date">`
devolve **ISO** (`2026-09-21`); com o formato default `m/d/Y` o `createFromFormat` **lança**, o
`try/catch` do controller engole, e a tela recebe HTTP 200 com `success:false` e a mensagem
genérica — o sintoma não aponta para a causa.

Por isso a tela recebe `date_format` como prop e converte na borda do POST
(`paraFormatoDoNegocio`). **Não** "conserte" isso fazendo o endpoint aceitar ISO: o
`StoreLeaveRequest` valida chamando *o mesmo conversor* de propósito (o docblock dele explica),
e a blade legada posta no formato do negócio. Duas convenções de data é o defeito, não a solução.

Corolário já registrado no teste: `21/09/2026` em `m/d/Y` é **mês 21**.

## 5. O ramo DataTables continua vivo (e por quê)

`index()` tem dois caminhos:

```php
if (request()->ajax() && ! request()->inertia()) { /* JSON do DataTables */ }
return Inertia::render('Essentials/Licencas/Index', [...]);
```

- O ramo JSON é consumido **hoje** por `Resources/views/leave/index.blade.php:93`. Medido em
  2026-09-05 (varredura contada: 12 referências ao controller no repo; só essa chama o `index`
  por XHR — as outras são links de menu, sidebar e notificação). Ele sai no **PR-10/HRM-O8**,
  junto das blades.
- `! request()->inertia()` **não é redundante**: o cliente Inertia usa axios, que em algumas
  versões manda `X-Requested-With: XMLHttpRequest` — exatamente o header que `request()->ajax()`
  lê. Sem a segunda perna, um partial reload cairia no JSON do DataTables e o sintoma seria
  "o filtro não atualiza", sem erro nenhum no console.

`show()` e `edit()` passaram a **redirecionar** para `/hrm/leave`. Antes retornavam
`view('essentials::show')` / `essentials::edit`, views que **não existem** → 500 (R10 do charter).
É o mesmo caminho já tomado pelo `EssentialsHolidayController` irmão.

## 6. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- Toda query da tela nasce em `queryLicencasSemFiltro()`, que já entra com
  `where('essentials_leaves.business_id', $business_id)`.
- **R3**: quem tem só `essentials.crud_own_leave` é recortado por `user_id` **no controller**.
  A UI esconde o seletor de colaborador, mas esconder não é defender — o gate é do servidor.
- Escrita com id cru do corpo (`essentials_leave_type_id`, `employees[]`) é fechada pelo
  `StoreLeaveRequest` (`exists` escopado no business) + `confirmarColaboradoresDoTenant()` no
  momento do INSERT — o global scope filtra SELECT, não impede INSERT.
- Testes no tenant **98**, adversário **99**/**2** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
  Nunca `biz=4` (ROTA LIVRE, cliente real) nem `biz=1`.

## 7. Como rodar os testes

Pest **nunca** roda local nem no Hostinger ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)):

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=HrmLicenca"
```

No CI, a lane é `PHP / Pest (Essentials · MySQL)` — o arquivo está na allowlist do
[`essentials-pest.yml`](../../../.github/workflows/essentials-pest.yml). ⚠️ Essa lane **não** é
required, então o vermelho dela não bloqueia merge: leia o resultado, não o selo.

## 8. O que esta tela NÃO faz

Está no charter como Non-Goals e vale repetir onde dói: **não edita licença** (o `update()` do
servidor é vazio de propósito — R9), **não desconta da folha**, **não calcula férias
proporcionais**, e a aba "Tipos de licença" é um **link** para `/hrm/leave-type` (tela e
controller próprios), não um painel local.

Conflitos no período, no drawer, olham **apenas a página carregada** — e o texto diz isso. Varrer
25 de N e afirmar "nenhum conflito" seria mentir sobre a cobertura.
