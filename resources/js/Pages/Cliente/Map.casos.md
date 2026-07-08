---
casos: Mapa dos clientes · /contacts/map
irmaos: Map.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o mapa abrir em React e não vazar cliente de outro tenant não muda no refactor.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Mapa dos clientes

> Fase 2 (lanes do Cliente). Âncora comportamental REAL escrita nesta onda (`ClienteMapInertiaTest`, Pest/CT100) — **não** o `Wave1MapInertiaTest` (source-grep + `@group legacy-quarantine`).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CMAP-01 · Abrir o mapa dos clientes (React, com as duas listas)
- **Persona:** Larissa — quer ver onde estão os clientes no mapa; abre a tela e vê o mapa + a lista lateral.
- **Aceite:** Dado a flag `MWART_CLIENTE_MAP` ligada · Quando faço `GET /contacts/map` · Então renderiza Inertia **`Cliente/Map`** (não o Blade `contact.contact_map`) com os props `contacts` (os que têm posição) e `all_contacts` (todos, pra lista pesquisável).
- **Teste:** `tests/Feature/Cliente/ClienteMapInertiaTest.php` — `GET /contacts/map renderiza Inertia Cliente/Map com contacts/all_contacts`.
- **Status: 🧪** — feature test HTTP passa no CI; ✅ com o manifesto regravado.

---

## UC-CMAP-02 · O mapa não mostra cliente de outro tenant (Tier 0)
- **Persona:** operador — o mapa só pode plotar/listar clientes do próprio negócio (ADR 0093, Cliente é PII-heavy).
- **Aceite:** Dado um cliente cadastrado em OUTRO `business_id` · Quando abro `/contacts/map` no meu negócio · Então esse cliente estrangeiro **não** aparece em `all_contacts` (o global scope por `business_id` o esconde).
- **Teste:** `tests/Feature/Cliente/ClienteMapInertiaTest.php` — `Tier 0 — o mapa não lista cliente de outro business`.
- **Regressão que defende:** vazamento cross-tenant na listagem do mapa (`Contact::where('business_id')`).
- **Status: 🧪** — feature test HTTP passa no CI; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Buscar no aside filtra por nome/cidade** — spec e2e do filtro client-side.
- **[BACKLOG] Clicar num item seleciona e renderiza o iframe Google Maps** — e2e (iframe embed).
- **[BACKLOG] Badge "Sem posição" nos contatos sem `position`** — render test.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter=ClienteMapInertiaTest` no CT100 (nunca local/Hostinger).
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Map.tsx` / `ContactController::contactMap`.

## Trilha do tempo
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente). Teste-âncora `ClienteMapInertiaTest` (render Inertia + Tier 0) escrito nesta onda. Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
