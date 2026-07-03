---
casos: Gateways de Pagamento · /settings/payment-gateways
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Gateways de Pagamento

> Tela Tier-0 (**segredos/credenciais** + toggle que libera/trava emissão de cobrança). Persona: **Wagner** (superadmin).
> Passo 3 do [template-onda-modulo] (régua por tela) — complementa a [CAPTERRA-FICHA](../../../../../memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md) (nota 67) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste, no manifesto) · 🧪 comportamento tem teste Feature mas **sem UC-id** (débito de rastreabilidade G-2) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito real desta tela = rastreabilidade, não ausência de teste.** O comportamento já é defendido por `PaymentGatewaysControllerTest` + `PaymentGatewaysControllerStoreTest` (Feature/MySQL, verdes no CT100). O que falta é a G-2 ([ADR 0264]): nenhum teste **cita** um `UC-PG-NN`, então nenhum caso está no manifesto. Cada item vira `UC-PG-NN` **no mesmo PR** que adicionar o id ao teste que já existe (edição de 1 linha) — [ADR 0062] CT100.

---

## Backlog de casos (sem id — entram quando um teste citar o UC-id)

> Regra G-2: UC declarado em heading `## UC-*` sem teste que o cite = órfão → quebra `casos-gate`. Mantidos como bullets até o id ser wired no teste correspondente.

- **[BACKLOG · 🧪 tem teste] GET é read-only puro** — Dado a rota `/settings/payment-gateways` · Quando faço GET · Então nenhuma mutação ocorre (nem cria credencial nem dispara cobrança). _Coberto por `PaymentGatewaysControllerTest::não dispara mutação em GET (read-only puro)` — falta citar `UC-PG-` no teste._
- **[BACKLOG · 🧪 tem teste] Toggle inverte `ativo` do credential** — Dado uma credencial ativa · Quando confirmo o toggle (Trust L3) · Então `ativo` inverte e a lista recarrega. _Coberto por `...ControllerTest::toggle endpoint inverte ativo`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Toggle cross-tenant devolve 404** — Dado credencial de biz=99 · Quando biz=1 tenta togglar · Então 404 (não vaza credencial de outro business — [ADR 0093]). _Coberto por `...ControllerTest::cross-tenant toggle: 404`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Credencial respeita `business_id` global scope** — biz=2 não enxerga credencial de biz=1. _Coberto por `...ControllerTest::Tier 0 IRREVOGÁVEL respeita business_id global scope`._
- **[BACKLOG · 🧪 tem teste] 3 KPIs no partial reload** — ativos / health fail / cobranças hoje chegam via `Deferred` no shape esperado. _Coberto por `...ControllerTest::expõe 3 KPIs no partial reload`._
- **[BACKLOG · 🧪 tem teste] Lista gateways do business + warn deprecated** — PesaPal aparece com label `warn`. _Coberto por `...ControllerTest::lista gateways + warn deprecated PesaPal`._
- **[BACKLOG · 🧪 tem teste] Novo gateway (wizard) cria credencial Inter sandbox** — e rejeita duplicata `(business_id, gateway_key, ambiente)`, rejeita conta de outro business (Tier 0), valida enum de `gateway_key`. _Coberto por `PaymentGatewaysControllerStoreTest` (4 casos)._
- **[BACKLOG · ⬜ sem teste] Health check on-demand atualiza `health_status`** — Dado botão "Testar todos" / "Rodar agora" · Quando aciono · Então o endpoint atualiza `health_status`/`latencia`/`last_check` no DB. _Charter prevê Pest GUARD `health-check endpoint atualiza health_status` mas o teste ainda não existe — candidato a UC + Pest._
- **[BACKLOG · ⬜ sem teste · UI] Ações de linha não-wired** — os botões por-linha `RefreshCw` (rodar health check) e `MoreHorizontal` (mais ações) não têm `onClick` (Index.tsx:248,250). Candidato a UC E2E quando forem ligados.

## Como rodar a suíte
1. **Pest (MySQL real):** lane do PaymentGateway no CT100 ([ADR 0062]) — `PaymentGatewaysControllerTest` + `...StoreTest` já verdes.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela), complementando a CAPTERRA-FICHA. Débito exposto = **UC-traceability** (0 UC-id apesar de baseline Feature forte); a nota de UX (80 Advanced) coexiste com comportamento tested-mas-não-traçado.

[template-onda-modulo]: ../../../../../memory/requisitos/_Governanca/programa-ondas/template-onda-modulo.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
