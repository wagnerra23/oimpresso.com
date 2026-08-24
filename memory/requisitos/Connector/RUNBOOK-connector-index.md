---
slug: connector-runbook-index
title: "Connector — Runbook da tela Conector (API)"
type: runbook
module: Connector
tela: Connector/Index
owner: W
status: rascunho
last_validated: "2026-08-24"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0180-sidebar-v3-5-grupos-ghosts-header'
  - '0093-multi-tenant-isolation-tier-0'
---

# RUNBOOK — Conector (API)

> **Tipo:** runbook reproduzível · **F1 PLAN** do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> **Fonte de design:** `prototipo-ui/cowork/connector-page.jsx` (22 KB, 4 vistas)
> **Validado:** _pendente_ — este RUNBOOK é a F1; F2..F5 ainda não rodaram.

## Estado final esperado

`/connector/client` renderiza `resources/js/Pages/Connector/Index.tsx` (Inertia/React) com a lista
de clients OAuth do business ativo, mantendo o comportamento hoje servido por
`Modules/Connector/Resources/views/clients/index.blade.php`. Blade permanece como fallback
atrás de flag até a F5.

## 1. Objetivo

Migrar a **única tela viva** do módulo Connector de Blade para Inertia/React.

**Escopo desta migração — 1 tela.** Medido em 2026-08-24: das 5 views que os controllers
referenciam (`clients.index`, `create`, `edit`, `index`, `show`), **só `clients.index` existe**
em disco. As outras 4 são referências a arquivos ausentes — ver §10.

**Fora de escopo (decisão [W], não migração):** o protótipo desenha 4 vistas —
`clients` · `docs` · `saude` · `modulo`. Só `clients` tem contrapartida viva. As outras 3 são
**capacidade nova**, não migração, e não entram aqui.

## 2. Pré-condições

| # | Condição | Como verificar |
|---|---|---|
| 1 | Módulo Connector instalado no business | `/modulos` → Conector ativo |
| 2 | Usuário é `superadmin` | `ClientController@index` aborta 403 sem ela |
| 3 | `APP_ENV != demo` | em demo a tela esconde a tabela (§5) |
| 4 | Laravel Passport instalado | a query usa `Passport::client()` |

## 3. Passo-a-passo

1. **F2 — baseline.** Pest cobrindo `index` (403 sem superadmin · isolamento por
   `business_id` · `secret` visível) e `store` (cria client, `password_client=1`).
2. **F2 — dual render.** `ClientController@index` devolve `Inertia::render('Connector/Index')`
   quando header `X-Inertia` E flag ligada; senão `view('connector::clients.index')`.
3. **F2 — flag** default OFF + comando `connector:enable-v2 <biz>`.
4. **F2 — paridade.** `memory/requisitos/Connector/index-parity.md` campo-a-campo.
5. **F3 — tela.** `Pages/Connector/Index.tsx` seguindo [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md).
6. **F4 — QA.** smoke + audit ≥80.
7. **F5 — cutover.** sem janela de cliente (§F0: admin de plataforma, uso esporádico).

## 4. Tokens CSS

Sem bundle Cowork próprio. Usa tokens semânticos shadcn do `cockpit.css`; **cor crua é
proibida** (`conformance-gate`). A tela nasce do DS — não há `related_prototype` aprovado.

## 5. Estados visuais

| Estado | Origem | Comportamento |
|---|---|---|
| `default` | há clients | tabela ID · Nome · Secret · Ações |
| `empty` | business sem client | empty-state com CTA "Criar cliente" |
| `demo` | `APP_ENV=demo` | esconde tabela; mostra `lang_v1.disabled_in_demo` |
| `forbidden` | sem `superadmin` | 403 — não renderiza |
| `loading` | partial reload | skeleton da tabela |

## 6. Responsividade

Alvo 1280px (monitor do cliente piloto). Tabela em container com `overflow-x: auto`;
a coluna `secret` é longa e **não** pode empurrar o layout.

## 7. Atalhos

Nenhum próprio. O sidebar novo não atribui atalho `G X` a este destino — ele não é hub de
uso diário. Herda ⌘K global.

## 8. Component contract

```
Pages/Connector/Index.tsx
  props: { clients: Array<{id,name,secret}>, is_demo: boolean }
  layout: AppShellV2 (persistent layout)
  PageHeader: title "Conector (API)" · action primary "Criar cliente"
  PT-01 Lista — 6 slots canônicos
```

Destino no sidebar novo (medido em `prototipo-ui/cowork/data.jsx`): **`SUPERADMIN_MENU`**,
item `connector`, label **"Conector (API)"**, com 3 ghosts (`conn-docs`, `conn-saude`,
`conn-modulo`) — que só existirão quando a capacidade nova de §1 for construída.

## 9. DoD checklist

- [ ] Pest baseline ≥5 fixtures (F2) — inclui cross-tenant biz≠biz
- [ ] `index-parity.md` campo-a-campo (F2)
- [ ] Flag + comando artisan (F2)
- [ ] `Pages/Connector/Index.tsx` ≤300 LOC, audit ≥70 (F3)
- [ ] Audit ≥80, CRITICAL=0 (F4)
- [ ] Smoke real com evidência HTTP (R1)
- [ ] Blade removido só após 30d sem incidente (F5)

## 10. Pegadinhas

1. **⚠️ Multi-tenant Tier 0.** A query filtra `u.business_id` via LEFT JOIN em `users` —
   `oauth_clients` **não tem** `business_id` próprio. Perder esse JOIN vaza clients entre
   tenants ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
2. **⚠️ `secret` em texto puro.** A tabela imprime `client->secret` (`makeVisible('secret')`).
   É o comportamento atual e a migração deve preservá-lo por paridade — **mas mascarar é
   decisão [W]**, não conserto silencioso. Registrar no `index-parity.md` como divergência
   proposta, não aplicar por conta própria.
3. **4 views fantasma.** `ConnectorController@index` → `connector::index`,
   `ClientController@create|show|edit` → `connector::create|show|edit`. **Nenhuma existe em
   disco** (medido 2026-08-24: só `clients/index.blade.php` e `layouts/master.blade.php`).
   As rotas `/connector/api`, `/connector/client/create`, `/connector/client/{id}` e
   `.../edit` apontam para views ausentes. Não migrar essas — **decidir com [W]** se some
   a rota ou se nasce a tela.
4. **`create` não é usado.** O cadastro acontece por **modal** na própria index (POST em
   `store`), não pela rota `create`. A tela nova deve manter o modal/drawer, não uma página.
5. **Delete sem confirmação.** O Blade envia DELETE direto pelo botão. A tela nova deve
   confirmar — é melhoria de UX, registrar na paridade.
6. **SPEC desatualizado.** `SPEC.md` D4.c afirma *"zero UI Inertia/Blade próprias por
   design... módulo backend-only"*. É **falso para Blade**: `clients/index.blade.php` é viva
   e roteada. O [CHARTER](CHARTER-rest-api-external.md) (fonte mais forte) prevê
   *"futuro UI admin"*. Pela regra de precedência, corrigir a linha do SPEC no mesmo PR
   que fizer a F2.

## 11. ADR de origem

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART canônico
- [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — sidebar v3 (item + ghosts)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [CHARTER-rest-api-external.md](CHARTER-rest-api-external.md) — contrato do módulo
