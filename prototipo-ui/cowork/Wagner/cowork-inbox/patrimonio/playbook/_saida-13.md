---
sessao: "13"
titulo: Saída da thread 13 — a Auditoria vira deep-link, não tela
dono: "[CL]"
medido_em: 2026-09-24
base_medida: 723d2b1e6 (origin/main fresco)
arquivos_de_producao_tocados: 1
invalida: "nada — a D-AUDITORIA foi respondida pela ADR 0413; a opção (b) da ficha foi a escolhida"
---

# 13 · Saída — a aba "Auditoria" leva ao Modules/Auditoria, já filtrada nos bens

## Decisão que destravou
**D-AUDITORIA → deep-link** ([W] 2026-09-24, [ADR 0413](../../../../../../memory/decisions/0413-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react.md), PR #7912).
O `Modules/Auditoria` segue como dono único da trilha por registro (ADR 0127). Esta thread não cria tela, tabela nem `RevertService`.

## O que mudou
- `Modules/AssetManagement/Http/Controllers/DataController.php`: novo ghost `auditoria` → `/auditoria?subject_type=Modules%5CAssetManagement%5CEntities%5CAsset`. O `PatrimonioSubNav` deriva do `DataController`, então a aba aparece nas 5 telas sem mexer em `_shared/` (que é `nao_toca`).
- O ghost só aparece quando a tela de destino **abre**: rota `auditoria.index` existe, o módulo está no pacote (ou instalado, no caso do superadmin) e o usuário tem `auditoria.view`. São as mesmas camadas do gate do `Modules/Auditoria/Http/Controllers/DataController`. Sem isso a aba levaria a 403/404.
- `MenuGhostsContratoTest`: o cenário "Garantias **e** Auditoria não aparecem" vira "Garantias não aparece" + 2 cenários da Auditoria (com o módulo instalado aparece com o filtro que o `AuditEntryService::normalizeFilters` aceita; sem ele, some). A contagem passa de `6` fixo para `6 ou 7`.

## Medições
- `AuditEntryService::ALLOWED_FILTERS` = `causer_kind, subject_type, event`. O `list()` faz `where($key, $valor)`, então aceita **um** `subject_type`. O link mostra só `Asset`. A trilha de alocação, manutenção e garantia na mesma lista seria PR do `Modules/Auditoria` (filtro multi-valor), não réplica aqui.
- Os 4 models (`Asset`, `AssetTransaction`, `AssetMaintenance`, `AssetWarranty`) usam `LogsActivity`: o link mostra dados reais.
- Sem `morphMap` no repo: o `activity_log.subject_type` grava o FQCN. O `Auditoria/Detail.tsx:148` já monta o mesmo formato de link (`/auditoria?subject_type=<FQCN>`).
- Teste: a lane `assetmanagement-pest` do CI roda no PR. Não rodei no CT 100 porque seria preciso sobrescrever o checkout compartilhado do container.

## Checklist
- [x] Sem tela, tabela ou serviço de auditoria novos no Patrimônio
- [x] `_shared/` intocado
- [x] Multi-tenant: o filtro por `business_id` fica no `AuditEntryService::baseQuery` do dono
- [ ] Smoke em prod depois do merge (aba visível para um usuário com `auditoria.view`, e o clique abre a lista filtrada)
