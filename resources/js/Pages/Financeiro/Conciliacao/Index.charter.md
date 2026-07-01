---
page: /financeiro/conciliacao
component: resources/js/Pages/Financeiro/Conciliacao/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-31"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [93, 101, 104, 236]
related_prototype: prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaConciliacao) — tela viva evoluiu além do protótipo (extrato via API, ADR 0236)
tier: A
charter_version: 1
---

# Page Charter — /financeiro/conciliacao

> **Status:** live (Onda 19, 2026-05-19 #49 — Conciliação OFX MVP).
> Charter criado retroativamente na Fase 1 da [ADR 0236](../../../../../memory/decisions/0236-extrato-conciliacao-modelo-unificado.md)
> (2026-05-31), quando a tela passou a enxergar TAMBÉM o extrato sincronizado
> via API do banco (`fin_extrato_lancamentos`), além do upload OFX.

---

## Mission (1 frase)

Reunir num só lugar todas as linhas de extrato bancário pendentes — de **upload OFX**
e de **sync API do banco** — e deixar o usuário conciliá-las com títulos abertos.

---

## Goals — Features (faz)

- Upload de arquivo OFX (parser `<STMTTRN>`) → linhas pendentes
- Lista unificada das DUAS origens com coluna **Origem** (chip Banco / OFX) — ADR 0236 Fase 1
- 4 KPIs: pendentes · sugeridos · conciliados · ignorados (somados das duas origens)
- Sugestão automática de match (fuzzy: valor + data ±3 dias) com `fin_titulos` abertos
- Confirmar match (conciliar) / Ignorar linha — grava na tabela de origem correta
- Busca client-side por descrição

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD (Non-Goal violado = CI quebra).

- ❌ Editar valor/data/descrição de uma linha de extrato (vem imutável da fonte)
- ❌ Migrar/mover linha entre as tabelas de origem (Fase 2 ADR 0236, atrás de flag)
- ❌ Parser CNAB / Open Banking direto na tela (próxima Onda)
- ❌ Conciliação N:N (1 linha ↔ N títulos) — hoje é 1:1
- ❌ Desfazer conciliação já confirmada (append-only; reverter é outra US)
- ❌ Export PDF/Excel da conciliação
- ❌ Editar regra de match (score fixo 0.85 no MVP)

---

## UX Targets

- p95 first-paint < 800ms (props vêm prontas do Controller)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- AppShellV2 + PageHeader canon
- Empty state PT-BR ("Nenhuma linha importada…")
- Chip de origem com `title` (tooltip) explicando Banco vs OFX
- Format BRL em todo valor; valor negativo em vermelho

---

## UX Anti-patterns

- ❌ Modal pra conciliar (ação inline na linha)
- ❌ Confirmação dupla pra Ignorar
- ❌ Cor crua Tailwind (`bg-COR-NNN`) — usar token semântico (R1 ui:lint)
- ❌ Paginação (limit 200 fixo por origem)
- ❌ `window.location.reload()` após ação (usa `router.post` + `preserveScroll`)

---

## Automation Hooks

- `ConciliacaoController::index` lê `fin_bank_statement_lines` (OFX) + `fin_extrato_lancamentos` (API), normaliza pro mesmo shape (`uid`/`origem`)
- `upload()` parseia OFX e faz `insertOrIgnore` idempotente (anti-race)
- `sugerirMatches()` roda fuzzy match nas duas origens contra `fin_titulos`
- `match()`/`ignorar()` resolvem a tabela pela `origem` do POST
- Multi-tenant: TODA query filtra `business_id` (global scope + filtro explícito)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails/SMS/WhatsApp
- ❌ Não chama gateway/banco externo na request (lê do cache local)
- ❌ Não muda o saldo da conta
- ❌ Não cria/edita Titulo (só vincula via titulo_id na conciliação)
- ❌ Não acessa dados de outro `business_id` (multi-tenant Tier 0 — ADR 0093)
- ❌ Não roda Brain B/Sonnet

---

## Métricas vivas (Pest GUARD)

```php
// Modules/Financeiro/Tests/Feature/ConciliacaoLeExtratoApiTest.php (Fase 1 ADR 0236)
it('index lista linha api alem de ofx');                       // ✅ existe
it('sugerir matches casa linha api e marca na tabela extrato'); // ✅ existe
it('match origem api atualiza tabela extrato');                 // ✅ existe
it('ignorar origem api atualiza tabela extrato');               // ✅ existe
it('match api respeita business id tier0');                     // ✅ existe (ADR 0093)

// Modules/Financeiro/Tests/Feature/ConciliacaoUploadDedupeTest.php
it('upload duplicado double click e idempotente');             // ✅ existe (anti-race)
```

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-19 | Onda 19 #49 | Tela criada (Conciliação OFX MVP) — sem charter na época. |
| 2026-05-31 | Opus + Wagner | Charter criado na Fase 1 ADR 0236 (tela passou a ler extrato API + coluna Origem). |
