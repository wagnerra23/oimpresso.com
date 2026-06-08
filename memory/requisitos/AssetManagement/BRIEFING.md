# BRIEFING — Modules/AssetManagement

> Estado consolidado executivo (1 página) — atualizado por PR que toque o módulo. Skill `brief-update` Tier B regenera automaticamente.

## O que faz

Gestão de **ativos físicos** corporativos (notebooks, impressoras, servidores, móveis, veículos): cadastro com código único per-business, alocação a colaboradores, devolução, log de manutenções (preventiva/corretiva), garantia, e notificações por email.

## Para quem

Qualquer business multi-tenant que precisa controlar inventário de equipamentos depreciáveis. Aplicável transversalmente em todos os módulos verticais (Vestuario, ComunicacaoVisual, OficinaAuto).

## Diferenciais hoje

- ✅ **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) via filtro manual + tests cross-tenant biz=1/biz=99
- ✅ **Append-only audit trail** em `asset_transactions` (allocate/revoke histórico preservado)
- ✅ **Notificações Laravel** (Mail) integradas com `assigned_to` user
- ✅ **Settings per-business** (prefix `asset_code`, notification toggle)
- ✅ **Garantia rastreável** com accessor `is_in_warranty` calculado em runtime

## Gaps conhecidos

- 🟡 Frontend Blade legacy (não migrou pra Inertia/React MWART)
- 🟡 Sem `BusinessScope` global — isolamento depende de Controller respeitar filtro manual
- 🔒 Sem depreciação contábil automática (feature wish — aguardando sinal ADR 0105)
- 🔒 Sem transferência entre BusinessLocations
- 🔒 Sem baixa/disposal com motivo
- 🔒 Sem QR code físico + scan mobile

## Métricas

- 8 User Stories (todas ✅ done — núcleo legacy estável)
- 7 Controllers + 4 Entities + 6 Migrations
- 3 Tests Feature (Wave B + Wave I-W cross-tenant)
- Cobertura test: scaffold + multi-tenant isolation Asset/AssetMaintenance

## Próximos passos sugeridos (sem prazo — feature wish)

1. Migrar listagem `asset.index` pra Inertia/React (skill `mwart-process` ADR 0104)
2. Implementar depreciação linear quando cliente real solicitar
3. Adicionar `BusinessScope` global em `Asset` + `AssetMaintenance` (reduzir risco de Controller esquecer filtro)

## Refs

- [SPEC.md](SPEC.md)
- [Modules/AssetManagement/](../../../Modules/AssetManagement/)
