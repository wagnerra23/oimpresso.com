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
