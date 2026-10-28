---
page: /officeimpresso/businessall
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/Index.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-10-12'
related_prototype: prototipo-ui/cowork/Wagner/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-empresas.md
related_us: [US-OI-014, US-OI-015]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
charter_version: 1
---

# Charter — Empresas licenciadas (`/officeimpresso/businessall`)

**Missão.** Dar ao suporte da operadora a lista de todas as empresas com o desktop Office Impresso
licenciado, com versão, último acesso e situação, e o caminho para a ficha e o log de cada uma.

**Padrão de tela:** [PT-01 Lista](../../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
**Plano:** [RUNBOOK-empresas.md](../../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-empresas.md) ·
**Paridade:** [empresas-parity.md](../../../../../../../memory/requisitos/Officeimpresso/empresas-parity.md) ·
**Casos:** [Index.casos.md](./Index.casos.md).

## Âncora de design

`officeimpresso-page.jsx` → `ViewEmpresas()`. Desta entrega ficam de fora, porque não têm fonte de dado
ou dependem de outra tela: grupo econômico matriz/filial, uso de máquinas contra o limite, banco no
servidor, filtros de situação e versão, drawer de configuração e "Bloquear empresa" (ficha, tela #4).

## Goals

- Lista das empresas com `is_officeimpresso`: nome, #, razão social, CNPJ, versão disponível, versão
  obrigatória, último acesso (máquina mais recente da empresa) e situação.
- KPIs Empresas / Ativas / Bloqueadas calculados no backend.
- Busca client-side, ordem por # decrescente, 25 por página.
- "Ver computadores" (ficha da empresa) e "Ver log" com `aria-label`.

## Non-Goals (NÃO faz)

- ❌ Ação de escrita na lista — a Blade não tinha; bloquear e configurar são da ficha.
- ❌ Colunas de máquinas e de banco — decisão D1 de [W] em 2026-10-12: ficam na ficha.

## Anti-hooks

- ❌ Não serializa o model `Business` — a prop é DTO de 8 campos (empresas-parity item 28).
- ❌ Não lê `business.dt_ultimo_acesso` — não tem escritor; o último acesso vem de `licenca_computador`.
- ❌ Não volta a ter caminho dual: a flag `useV2OfficeimpressoEmpresas` e o Blade `businessall` saíram no
  cutover ([W] 2026-10-27, RUNBOOK-empresas §F5). Antes disso a flag nasceu OFF e ficou ligada por default desde 2026-10-13.

## Divergência declarada

- O status usa `StatusBadge kind="licenca"` com `empresa_bloqueada`/`ativa` e rótulo curto, não o
  `kind="empresa"` novo que o D4 do parity previa: o valor `empresa_bloqueada` já existe e significa
  exatamente isto; criar outro `kind` no componente compartilhado ficou para quando a ficha (#4) precisar.

## Dados / props

```
Officeimpresso/Empresas/Index (Inertia)
  empresas: Inertia::defer → Array<{ id, name, razao_social, cnpj, versao_disponivel,
                                     versao_obrigatoria, ultimo_acesso (ISO|null), bloqueado }>
  kpis:     Inertia::defer → { total, ativas, bloqueadas }
```

## Testes

`Modules/Officeimpresso/Tests/Feature/ComputadoresEmpresasIndexContratoTest.php` (lane
`officeimpresso-pest`, MySQL, tenant 98 × 99) — UC-EMPS-01, 02, 04, 05, 10, 11, 12.
