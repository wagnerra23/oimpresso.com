---
id: resources-js-pages-ponto-banco-horas-index-charter
page: /ponto/banco-horas
component: resources/js/Pages/Ponto/BancoHoras/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-002]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/banco-horas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/BancoHorasController@index` (rota `ponto.banco-horas.index`, permissão `ponto.access`). Saldo consolidado de banco de horas por colaborador (ledger append-only).

---

## Mission
O gestor vê o saldo de banco de horas consolidado por colaborador, com totais de crédito/débito do business, e navega pro extrato de movimentos de cada um. O saldo é sempre a soma de um ledger append-only — nunca um valor editável direto.

---

## Goals — Features (faz)
- Lista paginada (30/pág) de saldos por colaborador, ordenada por saldo desc.
- KPIs agregados: crédito total e débito total (cada um com a contagem de colaboradores na sub-linha),
  total de colaboradores no banco e multiplicadores de crédito/débito vigentes.
  — `D-BH-KPI`, [W] 2026-09-14 (ATA-DECISOES-2026-09-14, bloco 4, linha 42): *"ficam os seus 4 …
  o charter é o que está atrasado."* ⚠️ **Estado em 2026-09-28:** o `.tsx` ainda renderiza os 4
  KPIs antigos (crédito total · débito total · nº com crédito · nº com débito, `Index.tsx:90-117`);
  a contagem vai para a sub-linha e entram "total no banco" + "multiplicadores". Região no map:
  `faixa-de-kpi` (`memory/requisitos/Ponto/banco-horas-index.map.json`).
- Saldo formatado em horas:minutos, com cor por sinal (positivo/negativo).
- Link "Movimentos" pro extrato do colaborador (`/ponto/banco-horas/{colaborador}`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita saldo diretamente — ajustes acontecem no detalhe (Show) e viram movimentos no ledger.
- ❌ Não remove nem sobrescreve movimentos — ledger append-only (Portaria MTP 671/2021 · CLT banco de horas).
- ❌ Não expõe saldo de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não calcula folha/pagamento de HE — só apresenta o saldo acumulado.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- `saldos` e `totais` vêm via `Inertia::defer` (paginate + 4 aggregates lazy).
- Paginação usa partial reload (`only: ['saldos']`) — `totais` não re-executa por página.
- Saldo é populado automaticamente pela apuração diária (via serviço de banco de horas).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling — a lista não se atualiza sozinha.
- ❌ Não credita/debita saldo pela navegação — ajuste é ação explícita no Show.
- ❌ Não expira crédito na tela — expiração é regra de backend (config prazo).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [x] Regra de expiração/limite exibida ao usuário — DECIDIDO: vai no extrato (`BancoHoras/Show`),
      como os KPIs "Teto do acordo" e "Prazo de compensação" (E3-bis da thread 27, `D-BH-KPI`,
      ATA-DECISOES-2026-09-14 linha 42). Ver `Show.charter.md` §Goals — a construção está lá.
