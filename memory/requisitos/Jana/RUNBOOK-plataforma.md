---
id: requisitos-jana-runbook-plataforma
slug: jana-runbook-plataforma
title: "Jana — Runbook da tela Plataforma (/ia/superadmin/metas)"
type: runbook
module: Jana
tela: Jana/Plataforma
owner: W
status: ativo
date: "2026-09-02"
last_validated: "2026-09-02"
related_adrs:
  - 0052-memoria-jana-3-angulos-faturamento
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0180-sidebar-v3-hierarquia-canonica
preconditions:
  - "Usuário com `jana.superadmin` atribuída DE VERDADE no Spatie (ou `user_type` superadmin) — o `can()` é bypassado pelo Gate::before e NÃO vale aqui (P0 #6421)"
  - "Ações de instalação (atualizar/desinstalar) exigem `can('superadmin')`, mais estreito"
steps:
  - "Logado SEM a permissão: a aba Plataforma não existe na barra e /ia/superadmin/metas dá 403"
  - "Logado COM a permissão: a aba acende (6ª) e a tela lista metas da plataforma (business_id NULL) e metas de clientes, cruas"
  - "Conferir o bloco Instalação: contagens de migrations/seeders/permissões vêm do disco; versão do System property `jana_version`"
  - "Só com `superadmin` real: 'Rodar atualização' e 'Desinstalar módulo' abrem confirmação antes de navegar pra /ia/install/*"
---

# RUNBOOK — Plataforma da Jana (`/ia/superadmin/metas`)

> **Tipo:** runbook reproduzível
> **Irmãos:** [`Plataforma.charter.md`](../../../resources/js/Pages/Jana/Plataforma.charter.md) (lei) · [`Plataforma.casos.md`](../../../resources/js/Pages/Jana/Plataforma.casos.md) (contrato UC) · [`jana-plataforma.contract.json`](../../../prototipo-ui/contrato/jana-plataforma.contract.json)
> **Âncora de design:** `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmPlataforma` (a aba vive no `JmTabs` de `jana-merge.jsx`, só com `jana.superadmin`). Resolva por `node prototipo-ui/ancora.mjs Jana/Plataforma`.
> **Validado:** **estático** contra `origin/main` em 2026-09-02. ⚠️ Fluxo vivo NÃO exercitado nesta data — smoke real com screenshot é o passo 6 (R1).

A visão de **plataforma** da Jana: metas com `business_id NULL` e as metas de todos os clientes, listadas **cruas** — a agregação cross-business que o docblock antigo prometia **não existe** (medido 2026-08-27; a tela diz isso em letra). Mais o bloco de instalação do módulo (nWidart), que hoje é disparado pelo `/manage-modules`.

## 1. O gate — e por que a aba usa o mesmo

| camada | check |
|---|---|
| rota (`SuperadminController::metas`) | `hasPermissionTo('jana.superadmin')` **ou** `user_type ∈ {superadmin, user_oimpresso}` — P0 #6421 |
| aba/dropdown (`DataController::podeVerPlataforma`) | **os mesmos dois** — menu e rota concordam; `can('jana.superadmin')` não serve (Gate::before) |
| botões de instalação | `can('superadmin')` real (`BaseModuleInstallController`) |

## 2. Smoke (passo 6 — R1)

```bash
curl -sv https://oimpresso.com/ia/superadmin/metas 2>&1 | grep '^< HTTP'   # 302 → login sem sessão; 200 superadmin; 403 dono comum
```

Logado como [W] (biz=1): screenshot 1280 da aba acesa em 6ª posição + as duas tabelas + bloco de instalação. **Não** clicar em Desinstalar em prod.

## 3. Errata 2026-09-03 — a porta (b) do gate é INALCANÇÁVEL no grupo `/ia`

A tabela do §1 lista duas portas na rota: `hasPermissionTo('jana.superadmin')` **ou**
`user_type ∈ {superadmin, user_oimpresso}`. A leitura do controller está certa; a conclusão, incompleta.
O grupo `/ia` carrega o middleware `CheckUserLogin` (`Modules/Jana/Http/routes.php`), que faz
`if ($request->user()->user_type != 'user' ...) abort(403)` — qualquer `user_type` fora de `'user'` leva
**403 antes do controller**. A porta (b) existe no código e não pode ser exercida em rota nenhuma do grupo.

- **Como foi achado:** no 1º run real do `SuperadminMetasCrossTenantTest` (#6421), que **nunca tinha rodado
  em lane nenhuma** — não estava no `jana-pest.yml` nem no `ci-sqlite-pest.list`, e skipa em sqlite por
  desenho. Ele afirmava *"superadmin por user_type segue entrando"* e reprovou. O caso foi reescrito para
  assertar o 403 real, com controle positivo de que o predicado das duas portas (`DataController::podeVerPlataforma`)
  diria sim se a request chegasse nele; o teste entrou na lane MySQL no mesmo PR.
- **Medido em produção (2026-08-31):** 0 usuários com `user_type` em `('superadmin','user_oimpresso')`; os 5
  que alcançam a tela são do `business_id=1` e entram todos pela porta (a), via o papel `Operacional#1`.
- ⛔ **Remover a porta (b)** é decisão [W] — mexe no gate de uma tela Tier 0. Ela fica, com o alcance declarado
  no código (`SuperadminController::metas`) e aqui.

## 4. Bug fechado 2026-09-03 — as filhas da `Meta` também têm escopo por tenant

`MetaPeriodo` e `MetaApuracao` não têm `business_id`: usam `BelongsToBusinessViaParent`, que aplica
`ScopeByBusinessViaParent` **nelas**. Tirar o escopo só da `Meta` conserta a lista, mas o eager load
`->with('periodoAtual', 'ultimaApuracao')` seguia filtrando as filhas pela sessão — para toda meta de outro
tenant, `periodo` e `ultima` voltavam `null`, e a tela dizia "—" / "nunca apurada" para **todos** os
clientes, sem erro nenhum. Achado pelo `UC-PLAT-03` na lane MySQL, não por leitura.

`withoutGlobalScopes()` na closure do `with` **não** resolve: `ultimaApuracao` é `latestOfMany`, e o `ofMany`
monta a subquery com uma instância nova do model, que nasce com o escopo de volta. O conserto são duas
queries explícitas, agregadas e sem escopo (`whereIn('meta_id', $ids)` + `MAX(data_ref)`), partindo das
`Meta` já resolvidas — sem N+1. Borda **declarada e não consertada**: o predicado de `periodoAtual` compara
`data_fim` (DATE) com `now()` (com hora), então período que termina HOJE não casa; é da relação, não daqui.
