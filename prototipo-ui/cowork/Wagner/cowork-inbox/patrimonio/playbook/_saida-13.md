---
sessao: "13"
titulo: Saída da thread 13 — a Auditoria vira deep-link, não tela
dono: "[CL]"
medido_em: 2026-09-24
base_medida: 723d2b1e6 (origin/main fresco)
arquivos_de_producao_tocados: 1
invalida: "nada — a D-AUDITORIA foi respondida pela ADR 0414; a opção (b) da ficha foi a escolhida"
---

# 13 · Saída — a aba "Auditoria" leva ao Modules/Auditoria, já filtrada nos bens

## Decisão que destravou
**D-AUDITORIA → deep-link** ([W] 2026-09-24, [ADR 0414](../../../../../../memory/decisions/0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react.md), PR #7912).
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
- [x] Smoke em prod depois do merge (aba visível para um usuário com `auditoria.view`, e o clique abre a lista filtrada) — feito em 2026-09-30, ver adendo abaixo

## Adendo 2026-09-30 — smoke em prod
Feito em `oimpresso.com`, biz=1 (WR2 Sistemas), usuário superadmin, depois de todos os merges da frente do Patrimônio até `553aeb36f`.

- **A aba não aparece na faixa, e isso é por desenho.** O `PatrimonioSubNav` chama o `PageHeaderTabs` com `maxVisible={6}`. Com o ghost da Auditoria, o backend manda **7**, então a Auditoria cai no overflow "⋯" (`Mais 1 opções`), à direita de Configurações. Quem medir só as abas visíveis conclui, errado, que ela sumiu. Essa conclusão chegou a ser tirada nesta sessão e foi desfeita pela medição abaixo.
- **O backend manda o ghost certo.** Em `shell.menu`, a entry do módulo traz `{key: "auditoria", href: "/auditoria?subject_type=Modules%5CAssetManagement%5CEntities%5CAsset"}`. O item do overflow tem o mesmo `href`.
- **O clique abre a lista filtrada.** A URL vira `/auditoria?subject_type=…Asset` e o campo "Filtrar por tipo de entidade" vem com `Modules\AssetManagement\Entities\Asset`.
- **A lista vem vazia porque a empresa não tem bens.** A prop deferida `activities` foi pedida por partial reload. Sem filtro: 961 registros. `App\Contact`: 16. `App\Transaction`: 101. `Asset`: 0. E `bens` na mesma empresa: 0. O filtro funciona. A frase "o link mostra dados reais" (§Medições) continua **não verificada em prod**, porque lá não há bem cadastrado para gerar atividade.
- **Não medido, em lugar nenhum:** um usuário **não-superadmin** com `auditoria.view`. O smoke só cobre o ramo superadmin do gate (`isModuleInstalled`). O `MenuGhostsContratoTest` **também** usa usuário `superadmin` (`:69`), então o ramo por pacote (`hasThePermissionInSubscription` + `auditoria.view`) não tem teste nem smoke hoje.
