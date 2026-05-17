# Session — CAPTERRA-FICHA OficinaAuto (Wave 22 governance-mega)

**Data:** 2026-05-16
**Worktree:** `.claude/worktrees/jolly-hypatia-b8741c/` branch `claude/governance-wave-21-22-mega`
**Agent:** Claude Opus 4.7 (1 de 12 Wave 22)
**Área exclusiva:** `memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md` + este session log

## O que foi feito

Geração inicial da CAPTERRA-FICHA.md canônica do módulo Modules/OficinaAuto, seguindo:

- Pattern 10 seções (RecurringBilling FICHA referência)
- Ponderação ADR 0089 (P0=4, P1=2, P2=1, P3=0,5)
- Cruzamento BRIEFING.md (V0 status — 8 peças nWidart + 8 Pages + Kanban + 3 Pest + 9 permissions + AprovacaoOsService spans D9.a)
- 6 concorrentes BR avaliados: Oficina Integrada (líder), Ultracar (premium), Lokoz (nicho borracharia), MotorSW (mid), Mecânico Pro (P3 referência técnica, não ERP), Mitchell 1 (global benchmark)
- 12 capacidades P0 + 8 P1 + 6 P2 + 3 P3 mapeadas

## Resultado

- **Nota Capterra atual: 63/100 (Bom)** — abaixo de paridade BR mínima (75)
- **Nota-alvo V1: 90,5/100 (Excelente)** com US-OFICINA-002/003/006/007/008 entregues

## Top 5 gaps priorizados (ROI × esforço)

1. **OA-003 ItemOS = peças + mão-de-obra** (P0 fatal, 8h, criar US-OFICINA-007) — desbloqueia NFSe auto, estoque-baixa, dashboard ticket médio
2. **OA-004 Página público aprovação OS WhatsApp+PIN** (P0, 6h, US-OFICINA-006) — service pronto, falta UI
3. **OA-001 FSM canônica seed Simples/Complexa** (P0, 5h, US-OFICINA-003)
4. **OA-107 Importer Firebird Martinho 91 vehs** (P0, 4h, US-OFICINA-002)
5. **OA-104 Histórico OS por veículo timeline** (P1, 4h, criar US-OFICINA-008)

## Diferenciais únicos detectados (P2)

- **Locação first-class** (`order_type=locacao` enum) — nenhum concorrente BR tem nativo
- **Multi-tenant Tier 0** nativo (concorrentes cobram caro por multi-empresa)
- **Auditoria FSM** via `sale_stage_history` ADR 0143 (concorrentes não têm)
- **OpenTelemetry 9 spans** `oficinaauto.*` em prod (Wave 18 saturação)
- **NFe-de-boleto-pago automática** via núcleo RecurringBilling+NfeBrasil

## WebSearches consultados

1. "Mecânico Pro software oficina mecânica brasileira OS WhatsApp 2026 funcionalidades"
2. "Auto Manager software oficina mecânica brasil ordem servico FSM aprovacao cliente 2026"

Insights: "Auto Manager" como produto único não confirmado — mercado BR tem dezenas de players (FpqSystem, SisMecânica, Onmotor, Manager Full, MinhaOficina, Soften, Limersoft, Enkad). FICHA usa os 4 mais citados (Oficina Integrada, Ultracar, MotorSW, Lokoz) + 1 benchmark global (Mitchell 1) + 1 P3 técnico (Mecânico Pro).

## Tier 0 honrado

- ✅ PT-BR integral
- ✅ Sem git ops (parent consolida Wave 22)
- ✅ Sem BOM (Write UTF-8 padrão)
- ✅ FSM ServiceOrder canônica preservada (referenciada via ADR 0143)
- ✅ Isolamento estrito — só `memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md` + este log

## Arquivos criados

- `D:/oimpresso.com/memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md` (10 seções, ~6kB)
- `D:/oimpresso.com/memory/sessions/2026-05-16-capterra-oficinaauto.md` (este)

## Próximos passos sugeridos (não executados — fora escopo Wave 22)

1. Criar US-OFICINA-007 ItemOS (gap #1)
2. Criar US-OFICINA-008 Histórico veículo (gap #5)
3. Skill `comparativo-do-modulo` consumir esta FICHA pra gerar CAPTERRA-INVENTARIO.md
4. Reauditoria 2026-08-16 (trimestral)

## Refs

- BRIEFING: `memory/requisitos/OficinaAuto/BRIEFING.md`
- SPEC: `memory/requisitos/OficinaAuto/SPEC.md`
- ADR 0137 (qualificação módulo)
- ADR 0089 (Capterra-driven module evolution)
- ADR 0143 (FSM LIVE prod biz=1)
- Pattern referência: `memory/requisitos/RecurringBilling/CAPTERRA-FICHA.md`
