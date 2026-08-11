# Modules/ConsultaOs

Portal publico (sem auth) para cliente final acompanhar status de Ordem de Servico (OS) — fluxo orcado → aprovacao → producao → acabamento → expedicao → entregue.

## Status

**Mock-only** — 4 OS fake no `MockConsultaOsRepository`. Migrar pra `transactions` real esta no backlog (US-CONSULTA-001 em `memory/requisitos/ConsultaOs/SPEC.md`).

## Arquitetura (D4 SoC brutal — Wave 18)

```
Routes/web.php (throttle:30,1)
  → ConsultaOsController (validacao + auditoria PiiRedactor)
    → ConsultaOsMockService (orquestra busca + filtro estagio + OTel span)
      → ConsultaOsRepositoryInterface (contrato)
        → MockConsultaOsRepository (impl atual)
        → RepairConsultaOsRepository (TODO US-CONSULTA-001 — transactions real)
```

Trocar fonte = 1 linha em `ConsultaOsServiceProvider::register()`.

## Como cliente usa (portal publico completo Wave 27)

### Jornada feliz — consulta por numero

1. **Vendedor entrega numero da OS**: "Sua OS e 4821, acompanhe em https://oimpresso.com/consulta-os" (canal WhatsApp/email/SMS).
2. **Cliente acessa portal Inertia React** (`/consulta-os`) — pagina single-page, sem login, sem cookies persistentes.
3. **Digita numero** + escolhe estagio (opcional) — validacao `alpha_num + max:20` via `ConsultaPublicaRequest`.
4. **Recebe JSON estruturado**:
   - `200 {found: true, os: {client, contact, stage, items[]}}` — OS achada
   - `404 {found: false}` — opaco (NAO confirma se OS existe ou se estagio nao bate — anti-enumeration)

### Filtro por estagio (Wave 27 scaffold)

5. **Lista por estagio** (futuro US-CONSULTA-002): `ConsultaPorEstagioRequest` permite cliente ver "todas as minhas OS em producao" — validacao estagio em lista controlada + paginacao max 20/pag (anti-scraping).

### Feedback opcional (Wave 27 scaffold)

6. **Apos consultar, cliente pode enviar feedback** (futuro US-CONSULTA-002 analytics):
   - `FeedbackPublicoRequest` valida `numero_os + nota 1-5 + comentario opcional max:500`
   - Comentario passa por `PiiRedactor` ANTES de persistir (defesa em profundidade)
   - Throttle mais restritivo (5 req/min) — feedback e baixa frequencia natural

### Operacao do portal (timeline real do cliente)

7. **Recebe SMS quando OS muda estagio**: integracao com `Modules/RecurringBilling` notifica via WhatsApp Baileys/Meta API (canal valido por business). Mensagem template "Sua OS {numero} avancou para {estagio}. Consulte: https://...".
8. **Acessa portal sem precisar lembrar de senha**: ZERO auth — apenas conhecer o numero. Defesa contra enumeration brute-force via:
   - `throttle:30,1` (30 req/min por IP)
   - `404` opaco quando estagio nao bate (nao confirma existencia)
   - PiiRedactor wraps tudo logado
9. **Ve estado atual + itens da OS** — sem ver: `business_id`, `total_final`, `lucro`, `cliente_cpf`, `cliente_cnpj`, `forma_pagamento` (D7 LGPD + ADR 0093).
10. **Imprime/screenshota como comprovante** — JSON estruturado renderizado em React ja contem todas as info publicas (cliente, contato, criado em, atualizado em, items por estagio).

### Observabilidade (Wave 25 + 27 D9 — defesa em profundidade)

- **Repository span** (`consultaos.repository.lookup` — Wave 27 D9): isola latencia da fonte de dados (mock vs SQL real quando US-CONSULTA-001 entrar).
- **Service span** (`consultaos.busca_publica` — Wave 18 D9): envolve filtragem por estagio + decisao found/stage_mismatch.
- **Controller audit log** (Wave 25 D9): estruturado com PiiRedactor + IP truncado /24.
- **Health probes** (`consulta-os:health --detail` — Wave 25): 5 checks operacionais.

Nunca expoe `business_id`, `total_final`, `lucro`, `cliente_cpf`, `cliente_cnpj` (D7 LGPD + ADR 0093).

## Conformidade

- **D8 Security:** `ConsultaPublicaRequest` valida `alpha_num + max:20`; route middleware `throttle:30,1` (30 req/min anti-enumeration); `404` opaco em not_found OU stage_mismatch (nao confirma existencia).
- **D7 LGPD:** auditoria via `PiiRedactor` (cobre CPF/CNPJ/email se cliente cola no campo errado) + IP truncado /24 (ipv4) ou /48 (ipv6) — pseudonimizacao. Retencao 365d via `Config/retention.php`.
- **D9 OTel:** `ConsultaOsMockService` envolve busca em `OtelHelper::span('consultaos.busca_publica')`.

## Smoke E2E

```bash
php artisan test --filter=CustomerJourneyTest --testsuite=consultaos
```

Cobre acesso portal + busca OS conhecida + filtro estagio + 404 limpo + brute-force bloqueado + payload sem PII.

## Refs

- `memory/requisitos/ConsultaOs/SPEC.md` (US-CONSULTA-001 — migrar mock → real)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) — rubrica D1-D9
- `Modules/Repair/Routes/web.php` — padrao a imitar (`/repair-status`, `/post-repair-status`)
