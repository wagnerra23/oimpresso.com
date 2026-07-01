---
page: /advisor/dashboard
route: /advisor
controller: Modules\Financeiro\Http\Controllers\Advisor\AdvisorPortalController@index
persona: eliana
status: draft
created: 2026-05-31
related_us: [US-FIN-037]
onda: 31
component: resources/js/Pages/Financeiro/Advisor/Dashboard.tsx
owner: eliana
last_validated: "2026-05-31"
parent_module: Financeiro
related_prototype: n/a (sem protótipo Cowork — portal contador nasceu na migração DS v4, US-FIN-037; segue DS)
tier: B
charter_version: 1
---

# Charter — Dashboard do Portal do Contador

> Draft gerado durante migração DS v4 (estética crua → DS). Wagner revisa
> Non-Goals + Anti-hooks antes de promover a `status: live`.

## Mission

Dar ao contador parceiro (eliana) uma visão consolidada e **somente leitura**
dos clientes que lhe concederam acesso, com entrada rápida pras telas financeiras
de cada cliente (Visão Unificada e Relatórios), respeitando LGPD.

## Goals

- Listar todos os clientes com grant ATIVO (`revoked_at` null) num grid escaneável.
- Sinalizar de forma inequívoca quando falta consentimento LGPD por cliente.
- Levar em 1 clique pra `/financeiro/{unificado,relatorios}?advisor_view=1&business_id=X`
  (read-only forçado pelo middleware `AdvisorViewScope`).
- KPIs de topo: total de clientes ativos + quantos pendentes de consentimento.

## Non-Goals (revisar — Wagner)

- NÃO permite editar/lançar nada (portal é read-only por design; mutação é do cliente).
- NÃO expõe AppShellV2/sidebar POS (advisor é entidade global, sem `business_id` próprio).
- NÃO agrega números financeiros cross-client (sem somar receita/saldo de todos juntos).
- NÃO gerencia grants/convites aqui (fluxo é do lado do cliente em Config → Contador).

## UX targets

- Portal isolado, alinhado ao sibling `Login.tsx` (Card/Button/Alert + tokens DS v4).
- Cores SÓ via tokens (sem hex/oklch inline, sem `bg-*-NNN` cru).
- Cards de cliente com heading semântico + badge de status LGPD.
- a11y: `section` com `aria-labelledby`/`aria-label`, headings hierárquicos, ícones decorativos.

## Anti-hooks (revisar — Wagner)

- NÃO inventar props fora do contrato do Controller (`advisor`, `clientes[]`, `total_clientes`).
- NÃO assumir métricas financeiras não fornecidas pelo backend.
- NÃO trocar o scope read-only nem o guard `auth:web-advisor`.
- NÃO migrar pra AppShellV2 "pra padronizar" — isolamento é intencional.

## Data contract (Controller)

`clientes[]`: `access_id`, `business_id`, `business_name`, `granted_at_label`,
`can_view_unificado`, `can_view_reports`, `has_consent`, `url_unificado`, `url_relatorios`.
`advisor`: `id`, `nome`, `email`, `referral_code`. `total_clientes: number`.
