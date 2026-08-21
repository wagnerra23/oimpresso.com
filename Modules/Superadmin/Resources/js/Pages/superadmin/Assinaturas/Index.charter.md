---
id: modules-superadmin-pages-superadmin-assinaturas-index-charter
page: /superadmin/superadmin-subscription
component: Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx
related_prototype: prototipo-ui/cowork/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-20"
related_us: [US-SUPER-003]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
---

# Page Charter — /superadmin/superadmin-subscription

> **Status:** criado em 2026-08-20 na onda SA-O4a (DataTables → Inertia). Nasce `draft`: o
> `charter-live-signal` exige **sinal de prod**, e a tela ainda não foi ao ar. Vai a `live` no
> PR pós-deploy, com a evidência do smoke.
>
> Os **Non-Goals** e **Anti-hooks** vêm do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`, projeto `019dcfd3-…`), transportados e não
> inferidos — [W] ratifica.
>
> Backend: `Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController@index`, rota
> `Route::resource('/superadmin-subscription', …)`. Ver
> [RUNBOOK-assinaturas](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-assinaturas.md).

---

## Mission

Responde **uma** pergunta: *"o dinheiro entrou?"*. É a lista de cobrança do backoffice — que
assinatura está viva, qual está pendente de baixa e qual venceu. Não é BI, não emite cobrança e
não administra o negócio do cliente.

Persona única: [W], escritório, 1440px. Admin de negócio toma 403.

---

## Goals — Features (faz)

O que a tela entrega **hoje**:

- 4 KPI de status (ativas · em trial · pendentes · vencidas ou canceladas), com o recorte
  declarado em texto quando há assinatura **bloqueada** fora da conta.
- 3 filtros combináveis: pacote · status · criada em. Trocar um **preserva** os outros, e todos
  vivem na query string (sobrevivem a refresh e a link colado).
- Tabela de 6 colunas com a **vigência fundida** (`início → fim` numa célula só, com o fim do
  trial embaixo) — é assim no F1, e separar em duas colunas foi o que tornou a tela legada
  ilegível.
- Ordenação por cabeçalho em 5 colunas, resolvida no servidor.
- Lista paginada **no servidor**, 20 por página, total dito em texto.
- Vocabulário PT-BR fechado: assinatura, negócio, pacote, vigência. O enum do banco **nunca**
  aparece.
- Vazio que distingue *"nenhuma assinatura cadastrada"* de *"nenhum resultado para estes
  filtros"*, citando o cruzamento aplicado.

## Non-Goals — Features (NÃO faz)

> Do F1 §Non-goals. Cada item vira Pest GUARD quando [W] ratificar.

- **Não é BI** — nenhum gráfico de série longa nesta tela.
- **Não faz cobrança** — o gateway é `Modules/PaymentGateway`; aqui só o registro e o status.
- **Não edita dado operacional do cliente** (produto, OS, venda).
- **Não exclui assinatura** — cancelamento é append-only (R3), e mesmo ele é da SA-O4b.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

> Cada item existe porque a correção óbvia quebra o produto.

- ❌ **Não aplicar escopo de `business_id`.** A tela é cross-tenant **por desenho**
  ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin). "Consertar" isso esvazia a tela.
- ❌ **Não escrever status durante a leitura.** "Vencida" é rótulo **derivado da data**, não
  gravado. Quem "arruma" isso marcando `expired` ao renderizar transforma uma visita à lista em
  escrita em massa sem audit trail.
- ❌ **Não fundir `Bloqueada` (`declined`) com `Cancelada` (`cancelled`).** São eventos
  comerciais diferentes: inadimplência × pedido do cliente. O KPI declara o recorte em vez de
  esconder o número.
- ❌ **Não tratar `trial` como valor de `status`.** No banco não existe; é `trial_end_date`.
  Filtrar `where status = 'trial'` devolve zero linhas para sempre e ninguém percebe.
- ❌ **Não trocar `orderBy` whitelisted por `$request->input('ordem')` direto.** É injeção.
- ❌ **Não escrever literal monetário** (nem em fixture, nem em teste, nem em comentário). A
  coluna de preço formata o número que vem do payload — Tier 0,
  [proibicoes](../../../../../../../memory/proibicoes.md).

---

## Contrato visual

Travado por `prototipo-ui/contrato/superadmin-assinaturas.contract.json` (gate
`contrato-de-tela`), com âncoras `data-contract` no `.tsx`. A copy literal e a ordem das seções
são de lá — esta seção **aponta**, não repete.

---

## Divergências declaradas contra o F1

Ficam aqui porque escondê-las é como o retrato do sistema apodrece:

| F1 pede | Produção entrega | Por quê |
|---|---|---|
| paginação 6/página | 20/página | mesma escolha da SA-O2; decisão [W] em aberto |
| seleção múltipla + BulkBar | ausente | as 2 ações do F1 ("baixar comprovantes", "exportar") não existem no backend |
| kebab com 5 ações | ausente | SA-O4b — escrita passa pelo `SubscriptionLifecycleService` |
| subtítulo com total aprovado em R$ | contagens apenas | MRR tem dono (`SubscriptionRepository`); um 2º oráculo de receita aqui seria régua duplicada |

---

## Refs

- Casos: [Index.casos.md](Index.casos.md)
- RUNBOOK: [RUNBOOK-assinaturas.md](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-assinaturas.md)
- Protótipo: `prototipo-ui/cowork/superadmin-page.jsx` → `ViewAssinaturas()` (L1044)
- Irmãos: [Negócios](../Negocios/Index.charter.md) · [Visão geral](../Dashboard/Index.charter.md)
