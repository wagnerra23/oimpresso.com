---
page: /sells/create
component: resources/js/Pages/Sells/Create.tsx
charter: resources/js/Pages/Sells/Create.charter.md
review_round: 1
reviewer: W31 (bulk static review)
review_date: 2026-05-17
charter_exists: true
loc: 1409
tier: A
related_adrs: [0104, 0105, 0093]
---

# Static Review — /sells/create

## 1. Conformidade vs charter / SPEC

US-SELL-004 → US-SELL-007 declarados nos comentários iniciais (linhas 1-13). Triagem 18 campos legacy → 8 visíveis + 10 colapsáveis em `<details>`.

| Goal | Estado | Notas |
|---|---|---|
| AppShellV2 layout | ✅ import linha 15 | confere |
| PageHeader shared | ✅ import linha 21 | OK |
| EmptyState shared | ✅ import linha 22 | OK |
| Auto-save draft localStorage | ⚠️ STORAGE_KEY linha 99 cita biz+user (Tier 0 ADR 0093 corretamente declarado) | inspecionar gerador exato round 2 |
| `<details>` colapsáveis | declarado nos comentários | confirmar uso real round 2 |

## 2. Inertia::defer audit

- ⚠️ `Deferred` **não importado** (linha 16 só `router, useForm`). Tela `Create` tem props pesadíssimas: `taxes`, `priceGroups`, `commissionAgents`, `customerGroups`, `accounts`, `typesOfService`, `featuredProducts`, `users`. **D-14 risco P0** — todas essas devem ser `Inertia::defer()` no Controller pra evitar carregar página inteira em ~800ms.
- Pelo charter (não lido aqui, mas inferido), provavelmente apenas filters UI estão eager — produtos featured + featured drops deveriam ser deferred. **Recomendar audit no SellController@create**.

## 3. Multi-tenant Tier 0 (ADR 0093)

- ✅ Comentário linha 99-100 declara explicitamente: *"STORAGE_KEY DEVE incluir business_id + user_id (Tier 0 multi-tenant ADR 0093) — sem isso ROTA LIVRE biz=4 leria draft de biz=1."* — disciplina excelente
- ⚠️ Verificar implementação real do KEY no round 2 (linha 99 só declara `ADVANCED_OPEN_KEY` sem biz)

## 4. Tipagem TS

- ✅ `SellsCreatePageProps` exportado com interfaces completas
- ⚠️ `permissions.maxDiscount?: number | null` — both undefined and null permitidos: usar só `number | null` ou só `?` por consistência (anti-pattern TS)
- ⚠️ `taxes: Record<number, string>` com comentário longo (linha 71-73) explicando NÃO ser array — documentação inline boa, mas indica historic bug = sinal pra refactor backend

## 5. PT-BR e comentários

- ✅ Cabeçalho PT-BR detalhado com refs ADR
- ✅ Comments inline PT-BR

## 6. Top riscos (round 1)

1. **`Inertia::defer` ausente em props pesadas** — D-14 risco recorrente, confirmar Controller
2. **1409 LOC** — maior tela do batch; refactor candidato (PaymentRow, ProductSearchAutocomplete já separados — bom)
3. localStorage key precisa biz+user (declarado mas verificar)
4. `taxes` Record com comentário-de-bug-historic indica que typing backend → frontend está frágil
5. 18 → 8+10 triagem: SPEC US-SELL-004; round 2 confirma se todos 18 estão de fato presentes

## 7. Próximos passos round 2

- Confirmar `Inertia::defer` em `SellController@create`
- Rodar Pest `SellsCreatePageTest` se existir
- Auto-save localStorage: testar cross-tenant biz=1 → biz=4 não vaza
- Verificar 18 campos legacy presentes (mapping Delphi)

---

**Append-only.** Blocos YAML canon abaixo (parseados pelo `ScreenReviewController::parseReviewFile`).

## Round 1 — pending-wagner (2026-05-17T10:00:00-03:00)

```yaml
round: 1
status: pending-wagner
user: W31-bulk-static
at: 2026-05-17T10:00:00-03:00
desvios:
  - "Inertia::defer audit: precisa confirmar Controller (pendência dynamic)"
  - "STORAGE_KEY draft localStorage: declara biz+user mas não confirmado em runtime"
  - "1409 LOC monolítico — maior tela do batch"
  - "taxes Record com comentário-bug-historic indica typing backend→frontend frágil"
  - "Triagem 18→8+10 campos colapsáveis: confirmar todos 18 presentes"
notes: "Round 1 estático bulk W31 sem rodar testes nem screenshot. Findings pra dynamic round 2."
```

## Round 2 — pending-wagner (2026-05-17T15:30:00-03:00)

```yaml
round: 2
status: pending-wagner
user: Claude (dynamic post-Wave-1)
at: 2026-05-17T15:30:00-03:00
desvios:
  - "P0 CONFIRMADO Inertia::defer AUSENTE em SellController@create (linhas 810-842) — 8 props eager (taxes/priceGroups/commissionAgents/customerGroups/accounts/typesOfService/users/invoiceSchemes). Show/Edit/Drafts/Quotations JÁ usam defer. D-14 risk 300ms→50ms validado em outras telas."
  - "P0 CONFIRMADO STORAGE_KEY draft TIER 0 OK — Create.tsx:528-533 usa `oimpresso.sells.create.draft.${bizId}.${userId}` ADR 0093 ✅ (fecha pendência round 1)"
  - "P0 CONFIRMADO FieldError + role=alert + COLLAPSED_FIELD_KEYS auto-open <details> US-SELL-010 implementados ✅"
  - "P0 RESPONSIVIDADE — tela desenhada xl-only (1280+) quebra em 1024/375 (dossier 2026-05-17 tela-venda-arte-responsivo.md: nota 49/100 vs líderes 88/100 vs concorrentes BR 62/100)"
  - "P1 type union string polui payment_status/status — extrair resources/js/Types/Sale.ts shared cross Index/Show/Edit"
  - "Wave 1 ENTREGUE Wave 1 (sessão 2026-05-17): ADR 0165 proposed + ProductLineCard.tsx novo + PaymentRow.tsx refatorado mobile-first. Preview em prototipo-ui/sells-responsive-preview/index.html"
  - "Wave 2 PENDENTE: integrar ProductLineCard em Create.tsx (md:hidden / hidden md:block linhas 851-955) + P0-2 touch h-8→h-11-md-h-8 + P0-3 footer safe-area + P1-1 pills horizontal-scroll mobile + atualizar Create.charter.md viewport mínimo 375px"
notes: "Status sugerido: ITERATE (loop F1.5) pra Wagner aprovar Wave 2 integration. NÃO rejected — 1 PR pontual SellController defer resolve o P0 conhecido. Pré-req: ADR 0165 accepted."
```

**Append-only.**
