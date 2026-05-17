---
page: /comunicacao-visual
component: resources/js/Pages/ComunicacaoVisual/Index.tsx
charter: resources/js/Pages/ComunicacaoVisual/Index.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 37
tier: B
status: stub
related_adrs: [0104, 0121]
---

# Static Review — /comunicacao-visual (Index — stub)

## 1. Conformidade

**STATUS: stub Sprint 2** declarado no cabeçalho (linha 6). Sprint 1 entregou só API JSON (OrcamentoController + ApontamentoController). UI Inertia ainda em construção.

| Item | Estado |
|---|---|
| Head title PT-BR | ✅ "Comunicação Visual — ComVis" linha 25 |
| Charter ao lado | ✅ existe (`Index.charter.md`) |
| Refs documentadas | ✅ JSDoc completo linhas 9-14 (README, charter, SPEC US-COMVIS-005) |
| AppShellV2 layout | ❌ **AUSENTE** — tela não wrap em AppShellV2 (sem `.layout = (page) => <AppShellV2>...`) |
| Aviso "em construção" | ✅ linha 31-33 amber-700 warning visível |

## 2. Tier vertical (ADR 0121)

- ✅ ComunicacaoVisual = Modules/ vertical especializado (CNAE 1813-0/01) — status "em construção" no `memory/why-oimpresso.md`
- ✅ Candidatos clientes: 6 saudáveis OfficeImpresso (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart)

## 3. AppShell missing (P1)

- ❌ **Sem `.layout = AppShellV2`** — tela renderiza sem topnav, sidebar, breadcrumb. Wagner navegando pra `/comunicacao-visual` cai numa tela "solta" sem chrome. Violação do canon `Layouts/AppShellV2` ADR 0110 Cockpit V2.
- **Fix simples**: adicionar `Index.layout = (page) => <AppShellV2>{page}</AppShellV2>;` no final

## 4. Stub vs charter

- ✅ Stub bem comentado declarando explicitamente "Sprint 2 TODO: pages Inertia próprias (orçamento, PCP Kanban, apontamento)"
- ✅ Aviso amber `UI em construção (Sprint 2). Use endpoints API legados em /comunicacao-visual/api/*`
- ⚠️ Charter F1.5 gate visual ainda não disparou (esperado — stub)

## 5. Tipagem TS

- ✅ `Props { bizName?: string }` simples e adequado pro stub
- ✅ Default value `bizName = 'oimpresso'`

## 6. PT-BR

- ✅ Tudo PT-BR (Head, h1, descrição, aviso)

## 7. Top riscos

1. **AppShellV2 missing** — P1, fix trivial 1 linha
2. **Stub estagnado** — Sprint 2 ainda TODO; cliente piloto (Vargas/Extreme etc) ainda sem UI Inertia. Sinal qualificado pendente (ADR 0105)
3. **`bizName` prop** vem do Controller? Verificar Pest se prop chega
4. **Endpoint legado** `/comunicacao-visual/api/*` ainda em uso (declarado linha 33) — quando UI Inertia ativar, planejar deprecation
5. **Charter** prevê gate visual MWART F1.5 — pré-req antes de Sprint 2 Edit

## 8. Próximos passos round 2

- **Quick fix P1**: adicionar `.layout = AppShellV2` (PR ≤ 3 linhas)
- Sinal qualificado: aguardar cliente Vargas/Extreme pagar Sprint 2 (ADR 0105)
- Confirmar `bizName` prop vem do Controller (Pest test)
- Quando Sprint 2 ativar: rodar mwart-comparative V4 (F1.5 visual gate)

---

**Append-only.**
