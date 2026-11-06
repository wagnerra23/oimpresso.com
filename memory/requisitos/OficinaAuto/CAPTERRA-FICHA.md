---
id: requisitos-oficina-auto-capterra-ficha
---

# CAPTERRA-FICHA — OficinaAuto

> **Errata 2026-11-05 (auditoria do módulo, lote 5.6):** esta ficha é de 2026-05 (Wave 23) e
> vendia "dual mode locação + manutenção" como diferencial. A [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)
> erradicou locação do domínio: o diferencial foi retirado abaixo. O módulo é piloto LIVE do
> Martinho desde 2026-05-13 (ADR 0171), não "V0 em construção". A nota e os gaps abaixo não foram
> remedidos; o estado atual está no [BRIEFING](BRIEFING.md). Recalibrar a ficha é pendência da
> ADR 0194 (checklist pós-aceite).

> Ficha canônica de benchmark do módulo vertical OficinaAuto.
> **Bucket**: `vertical_client_facing` ([ADR 0160](../../decisions/0160-governance-v4-scoped-scorecards-buckets.md))
> **Wave 23** — saturação 63 → ≥85 (rubrica scoped vertical_client_facing.yaml)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7 + [0137](../../decisions/0137-modules-oficinaauto-qualificada.md)

---

## Identidade do módulo

- **Nome interno**: `OficinaAuto`
- **CNAEs**: 4520-0/01 (manutenção/reparação automotiva), 2212-9/00 (reforma pneus), (4581-4/00 removido — locação erradicada, ADR 0265)
- **Estado lifecycle** (ADR 0121): **piloto LIVE** (Martinho desde 2026-05-13, ADR 0171). _Até 2026-11-05: "V0 em construção — Vargas + Martinho"._
- **Candidato piloto**: Martinho Caçambas LTDA biz=164 **Tubarão SC Humaitá de Cima** (Discovery 2026-05-13 · endereço confirmado smoke prod 2026-05-26 errata — anterior dizia Capivari de Baixo) — **mecânica pesada caminhão basculante + loja peça hidráulica + oficina autorizada** (sub-vertical 4 CNAE 4520 · ADR 0194 — pré-correção dizia "modo aluguel + manutenção caçambas")
- **Diferencial-chave**: FSM canon multi-stage + WhatsApp aprovação token+PIN. _(O "dual mode locação + manutenção" saiu em 2026-11-05: ADR 0265.)_

## Concorrentes-alvo

| Concorrente | Pricing/mês | Foco | Lacuna oimpresso preenche |
|---|---|---|---|
| **Mecânico** | R$ [redacted Tier 0]-600 | oficinas pequenas | sem locação, FSM básico |
| **Auto Manager** | R$ [redacted Tier 0]-900 | oficinas médias | UI legacy, sem IA, mobile fraco |
| **Lokoz** | R$ [redacted Tier 0]-700 | locadoras | sem manutenção integrada |
| **Bling Oficina** | R$ [redacted Tier 0]-400 | horizontal raso | sem profundidade veicular |
| **GP Soft Auto** | R$ [redacted Tier 0]-800 | regional | UI legacy jQuery |

## Capacidades V0 baseline (entregue 2026-Q2 Sprint 1-2)

```yaml
capacidades_v0:
  - us: US-OFICINA-001
    nome: "CRUD Vehicle multi-tenant (placa, chassis, renavam)"
    score: P0
    onde: "VehicleController + Vehicle entity (HasBusinessScope)"

  - us: US-OFICINA-002
    nome: "CRUD ServiceOrder (order_type manutencao | mecanica — locacao erradicada, ADR 0265)"
    score: P0
    onde: "ServiceOrderController + ServiceOrder entity"
    evidencia: "Schema::hasColumn fallback Wave 5-A (order_type/daily_rate/expected_return_date)"

  - us: US-OFICINA-003
    nome: "FSM canon multi-stage service_order (orçamento→aprovação→produção→entrega)"
    score: P0
    onde: "app/Domain/Fsm/ (ADR 0143)"
    evidencia: "FsmTransitionTest.php"

  - us: US-OFICINA-006
    nome: "WhatsApp aprovação cliente via token + PIN (paridade Repair)"
    score: P0
    onde: "WhatsappApprovalTokenTest pendente schema (V0 placeholder)"
    evidencia: "AprovacaoOsTokenTest.php + WhatsAppAprovacaoPinTest.php"
```

## Top 5 gaps P0 (pra subir nota ≥85)

| US | Capacidade | Esforço | ROI estimado | Concorrente que tem |
|----|------------|---------|--------------|---------------------|
| US-OFICINA-006 | Schema PIN/token + UI pública aprovação | 16h | alto (paridade Repair + redução chamada) | Mecânico (parcial) |
| US-OFICINA-008 | Checklist visual veículo entrada (foto chassis/lateral) | 24h | alto (proteção legal + dispute) | Auto Manager |
| US-OFICINA-009 | Catálogo peças com integração fornecedor (estoque just-in-time) | 32h | alto (margem +10%) | Mecânico, Auto Manager |
| US-OFICINA-010 | OS multi-mecânico + comissão por hora trabalhada | 20h | médio (gestão time) | Auto Manager |
| US-OFICINA-011 | Histórico veicular (manutenções anteriores na placa) | 12h | médio (upsell/fidelidade) | Auto Manager, GP Soft |

## Diferenciais oimpresso vs concorrentes

1. ~~Dual mode locação + manutenção em UI única~~ — retirado em 2026-11-05: locação erradicada do domínio (ADR 0265)
2. **FSM canon multi-stage** (ADR 0143) — auditoria append-only de cada transição
3. **WhatsApp aprovação cliente token+PIN** (paridade Repair) — reduz chamada
4. **Multi-tenant Tier 0** com PII redactor (plate/chassis/renavam) — LGPD Art. 18 III
5. **Jana IA conversacional** — "quais OS abertas + valor médio?"
6. **Stack moderna** Laravel 13.6 + React 19 + Pest 4 vs concorrentes Delphi/jQuery

## Score Capterra W22 → W23

| Dimensão (scoped vertical_client_facing) | W22 | W23 alvo |
|------------------------------------------|-----|----------|
| V1 Pest E2E Customer Journey | 7/15 | **13/15** |
| V2 Code Quality FormRequests | 9/10 | 9/10 |
| V3 Perf UX (Inertia::defer) | 5/10 | 6/10* |
| V4 LGPD retention canon | 9/15 | **14/15** |
| V5 Docs canon (BRIEFING/CHANGELOG/CAPTERRA) | 8/20 | **18/20** |
| V6 Capterra ROI Top 5 + MATRIZ-ROI | 5/10 | 8/10 |
| **Total scoped** | **63/100** (médio) | **≥85/100** |

*V3 — Inertia::defer rollback PR #963 (quebrava Pages render). Mantém Inertia::render eager até refactor seguro.

## Status lifecycle (ADR 0121)

- ✅ `V0 em construção` — Sprint 1-2 entregue (CRUD + FSM placeholder + WhatsApp tests)
- ✅ `piloto` — Martinho LIVE desde 2026-05-13 (ADR 0171); a meta "2026-Q3" desta linha ficou para trás
- ⏳ `ativo` (meta 2027) — 3+ clientes pagantes (Vargas + Martinho + 1)

## Anti-padrões (Tier 0 IRREVOGÁVEIS)

- ⛔ Tabela `service_orders/vehicles` sem `business_id` indexed + FK + global scope (ADR 0093)
- ⛔ PII plate/chassis/renavam em log sem PiiRedactor
- ⛔ Smoke `business_id=cliente_real` — usar biz=1 ou biz=99 (ADR 0101)
- ⛔ Inertia::defer no ServiceOrderController.index() sem teste E2E React (PR #963 quebrou)
- ⛔ Migrar Martinho sem qualificação ADR 0105 (sinal real ≠ wish)

## Referências

- [SPEC.md](SPEC.md) — US-OFICINA-001..011
- [BRIEFING.md](BRIEFING.md) — estado consolidado
- [OficinaAuto.charter.md](OficinaAuto.charter.md) — module charter
- [ROADMAP.md](ROADMAP.md) — roadmap 12 meses
- [MATRIZ-ROI.md](MATRIZ-ROI.md) — ROI top capacidades V6.b
- [demo-martinho-2026-05-13/](demo-martinho-2026-05-13/) — discovery + script demo

---

**Próxima revisão**: 2026-08-16 (trimestre) ou quando piloto fechar.
**Wave**: 23 (saturação bucket vertical_client_facing — ADR 0160).
