---
id: resources-js-pages-ponto-banco-horas-show-charter
page: /ponto/banco-horas/{colaborador}
component: resources/js/Pages/Ponto/BancoHoras/Show.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-003]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/banco-horas/{colaborador} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/BancoHorasController@show` (rota `ponto.banco-horas.show`, permissão `ponto.access`). Extrato de banco de horas de um colaborador + ajuste manual no ledger.

---

## Mission
O gestor inspeciona o saldo atual de um colaborador, o histórico completo de movimentos do ledger, e registra ajustes manuais (crédito ou débito) com observação obrigatória. Reforça visualmente que o ledger é append-only: toda correção é um novo movimento, nunca um update.

---

## Goals — Features (faz)
- Cabeçalho com saldo atual do colaborador (cor por sinal).
- Formulário de ajuste manual: minutos (±) + observação obrigatória (mín. 5 chars) → `POST /ponto/banco-horas/{colaborador}/ajuste`.
- Histórico paginado (50/pág) de movimentos com tipo, minutos, data de referência, observação e registro.
- Aviso explícito de append-only (o saldo é a soma dos movimentos).
- KPIs de acordo: "Teto do acordo" (saldo máximo/mínimo em horas) e "Prazo de compensação" (meses,
  acordo individual). É por eles que a regra de limite/expiração fica visível ao usuário (fecha a
  pendência que estava no `Index.charter.md`). — `D-BH-KPI`, [W] 2026-09-14
  (ATA-DECISOES-2026-09-14, bloco 4, linha 42). ⚠️ **Estado em 2026-09-28:** a construir — nem o
  `Show.tsx` nem o `BancoHorasController` citam teto/prazo. Região no map: `kpis-do-extrato`
  (`memory/requisitos/Ponto/banco-horas-show.map.json`).
  **Construído depois, no mesmo dia** (PR da forma do extrato, empilhado no #8077): o controller
  entrega `acordo` (teto, piso, prazo) lido de `pontowr2.banco_horas` — o mesmo config que o
  `BancoHorasService` aplica — e a tela só exibe. Defendido por `UC-BHSHOW-05`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem apaga movimentos anteriores — ledger append-only (Portaria MTP 671/2021 · CLT). Correção = movimento reverso.
- ❌ Não recalcula/reescreve o saldo — o saldo deriva da soma dos movimentos no backend.
- ❌ Não acessa colaborador de outro business — `firstOrFail` deve validar escopo `business_id` (Tier 0 multi-tenant).
- ❌ Não faz ajuste sem observação — observação é auditada.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- `movimentos` vem via `Inertia::defer` (paginate 50 lazy); `saldo` é eager (valida acesso tenant).
- Ajuste manual grava movimento no ledger via serviço, com `auth()->id()` como autor.
- Toast de sucesso/erro; form reseta em `onFinish`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling — o extrato não se atualiza sozinho.
- ❌ Não muta o ledger em GET — ajuste é POST explícito com validação.
- ❌ Não dispara notificação ao colaborador sem opt-in.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar validação de escopo `business_id` no `firstOrFail` do show
