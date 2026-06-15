# BRIEFING — ConsultaOs

Portal público (sem auth) onde o cliente final de uma gráfica/oficina consulta o estágio de produção de uma OS pelo número compartilhado pelo vendedor (orçado → aprovação → produção → acabamento → expedição → entregue). Opera **mock-only**: o backend está totalmente cabeado (Controller → Service → Repository, validação anti-enumeration, auditoria LGPD, throttle, OTel), mas a fonte de dados é um dataset estático de 4 OS fake (`MockConsultaOsRepository`) — não toca DB nem integra com `Modules/Repair`. A camada existe para validar UX/segurança antes de ligar na query real.

**Estado:** parcial (infra real construída; dados ainda mock — não entrega valor a cliente real).

## Capacidades REAIS (no código)
- Rota pública `GET /consulta-os` → `Inertia::render('ConsultaOs/Index')` (React opera client-state + `fetch`, zero props). Page real existe.
- Rota pública `GET /consulta-os/buscar` → JSON; retorna OS mock ou `404 {found:false}`. 404 opaco (mesma resposta em `not_found` e `stage_mismatch`).
- Anti-enumeration via `ConsultaPublicaRequest` (`alpha_num` + `max:20` + lista de estágios).
- Throttle `30,1` nas duas rotas públicas.
- Auditoria estruturada: número via `PiiRedactor`, IP truncado /24 (v4) ou /48 (v6), UA truncado, retenção 365d.
- Spans OTel (`consultaos.busca_publica` + `consultaos.repository.lookup`).
- SoC: `ConsultaOsMockService` + `ConsultaOsRepositoryInterface` (troca de fonte = 1 bind no Provider).
- Hooks UltimatePOS no `DataController` + Install 1-click. 11 arquivos Pest.

## Capacidades PLANEJADAS (só no SPEC/backlog — NÃO construídas)
- **US-CONSULTA-001:** substituir mock por query real em `Modules/Repair` (read-only) — mapping `invoice_no` + últimos 4 do telefone **pendente de decisão do Wagner**.
- Resolver `business_id` via lookup + rate-limit por IP na busca real.
- **US-CONSULTA-002:** canary 7d em ROTA LIVRE. Captcha (citado, não implementado).

## Dependências
- **Reais:** `Modules\Jana\Services\Privacy\PiiRedactor`; `App\Util\OtelHelper`; install nWidart/UltimatePOS. Tier 0: rota pública **NÃO** scopa por `business_id` (sem sessão) — escape comentado no código.
- **Planejada:** leitura de `transactions` do `Modules/Repair`.

**SPEC:** [SPEC.md](SPEC.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial (mock-only — backend real, dados fake). **Fonte:** código real `Modules/ConsultaOs/` verificado 2026-06-15. Nota: SPEC §16 cita 3 controllers — desatualizado; código real tem 1 (`ConsultaOsController`, 2 rotas) pós-refactor Wave 18.
