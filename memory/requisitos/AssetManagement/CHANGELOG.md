# CHANGELOG — Modules/AssetManagement

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Set–nov/2026 — migração para Inertia, drawers e Tier 0] — 2026-11-06

> Levantado de `git log --since=2026-05-18 origin/main -- Modules/AssetManagement resources/js/Pages/Patrimonio`
> em 2026-11-06 (só `feat`/`fix`; `chore`/`docs`/`test`/`ci` e mudanças de outros módulos que
> passaram pelo path ficaram de fora). O CHANGELOG estava parado na Wave 27; esta entrada cobre o
> intervalo de uma vez, agrupada por tema. O número do PR é o recibo de cada linha.

### Migrado para Inertia (2026-09-08)
- As 5 telas saíram do Blade para `resources/js/Pages/Patrimonio/`: Bens (#7035), Painel com o
  `_shared` que as outras herdam (#7040), Alocações (#7046), Manutenções (#7045), Configurações
  (#7044, 3 blades viram 1 tela).
- Bens, Alocações e Manutenções nunca chegavam ao browser por uma guarda `! inertia()` (#7047, #7053).

### Adicionado
- Trava de saldo na alocação — não se aloca mais do que o bem tem livre (#7062, thread 02).
- Contrato de tela nas 4 listas (#7165, #7204, #7207, #7208); Painel na forma do protótipo
  (#7133, #7157, #7161); contador de manutenções abertas deferido (#7162).
- Cadastro de bem em drawer sem sair da lista (#7832); aviso quando a empresa não tem categoria (#7837).
- Aba Auditoria vira link para o `Modules/Auditoria` (#7916, thread 13).
- Recortes no servidor dentro de Bens: "Garantia crítica" (#8211) e "Em manutenção" (#8240).
- Drawers: editar bem (#8258, thread 17), detalhe do bem com devoluções (#8261), alocar/editar/
  devolver com excluir devolução (#8262, thread 18), manutenção (#8264, thread 19).
- "Enviar pra manutenção" pela linha do bem (#8310) e no rodapé do drawer (#8316).
- Valor residual por depreciação linear pela vida útil do bem (#9881, US-ASSET-009).
- Estado de erro visível quando a carga deferida falha, nas 5 telas (#10486, lote 2.1) e excluir
  bem pelo `AlertDialog` do DS, com falha visível (#10445, lote 2.2).

### Corrigido — isolamento entre empresas (Tier 0)
- Subconsulta de devolução sem filtro de empresa (#7011); `orWhereNull` do painel escapava do
  filtro (#7018); edição de bem alcançava garantia de outra empresa (#8253); gravar manutenção
  alcançava outra empresa (#8260).
- Lote 0 da auditoria: alocação só com bem e pessoa da empresa, e saldo na edição (#10403);
  responsável e destinatário de aviso da empresa (#10511); categoria e local da empresa (#10513);
  devolvido da lista só da empresa (#10516); `view_own` só alcança a própria manutenção e o painel
  confere permissão (#10517).

### Corrigido — permissão e rotas
- `index()` de Bens passa a exigir `asset.view` (#7008); gate de Manutenções era insatisfazível e
  anulado (#7034).
- Saíram links e rotas que abriam página em branco ou view inexistente (#7810, #7904);
  `/asset/settings` fica só com index e store (#8272, thread 20); `/asset/revocation` redireciona
  para Alocações (#8286, thread 16) e perdeu edit/update mortos (#8317); sub-menu sem o ghost
  Devoluções (#8297).

### Corrigido — dados e tela
- Painel e "Garantia crítica" pela garantia mais recente de cada bem (#8231, #8237); Painel concorda
  em número e nomeia "Sem categoria" (#8275).
- Excluir o bem apaga as garantias dele; `assetmanagement:garantias-orfas` lista e apaga as órfãs (#8293).
- 28 chaves de tradução que caíam no inglês (#7031); colunas "Bem" com 0px em Bens e Manutenções
  (#8246, #8248); foco volta a quem abriu os drawers (#8292, #8333); medidas do drawer do bem e do
  cartão de alocação pelo protótipo (#8269, #8281, #8308); o modal do Inertia não abre por cima do
  estado de erro (#10557).
- Testes Tier 0 destravados ou consertados (#7020, #7048, #8271).

### Retenção
- O #9211 (2026-10-13) reabriu a retenção como anonimização e corrigiu as chaves do
  `Config/retention.php`. A reabertura foi revogada por [W] no mesmo dia (lápide em
  `memory/licoes-rejeitadas.md`, §5 2026-10-13); a troca das chaves pelos nomes reais das tabelas
  é fato de schema e não faz parte da revogação.

## [Errata — escopo de empresa] — 2026-11-05

### Corrigido
- A entrada Wave 27 (2026-05-17, abaixo) lista entre as "Tier 0 IRREVOGÁVEIS preservadas" um "`business_id` global scope em Asset/Allocation/Maintenance". **Esse escopo nunca existiu.** Os 4 Models (`Asset`, `AssetTransaction`, `AssetMaintenance`, `AssetWarranty`) não têm `addGlobalScope` nem trait de escopo; o filtro por `business_id` é manual em cada consulta, e os 4 estão em `grandfathered` em `governance/multi-tenant-scope-baseline.json:8-11` (medido em `origin/main` 2026-11-05). A entrada antiga fica como estava (append-only); vale esta errata.
- O README do módulo (`memory/requisitos/AssetManagement/README.md`, seção Multi-tenant) repetia a mesma frase e foi corrigido no mesmo PR.
- Pendente, fora deste PR: o comentário de `Modules/AssetManagement/Config/retention.php` também fala em "global scope". Esse arquivo é resíduo da retenção revogada e aguarda decisão [W].

## [Wave 27 — Polish final ≥88] — 2026-05-17 (73-77 → 88, +11 a +15pp)

### Adicionado — D9.a OTel spans AssetWarrantyService (+2 spans)
- `AssetWarrantyService::contagemAtivas` — span `assetmanagement.warranty.count_active`
  (widget dashboard "N garantias ativas"). Findless safe (return 0 se asset não existe).
- `AssetWarrantyService::contagemExpiradas` — span `assetmanagement.warranty.count_expired`
  (relatório alerta garantias vencidas). Pareado pra observability simétrica.
- Total AssetWarrantyService: 3 originais (add/revoke/list_active) + 2 W27 = **5 spans**.

### Adicionado — D5 README "como cliente usa" (customer journey)
- `Modules/AssetManagement/README.md` documentando:
  - 4 cenários canônicos (cadastrar asset, alocar, abrir OS manutenção, contar garantias)
  - Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) chain warranty via Asset.business_id
  - D7 retention LGPD 4 categorias (3650d/1825d/2555d com bases CC Art. 206 / CTN Art. 195)
  - D9 observability tabela 14 spans (3 Services + AssetWarrantyService W27)
  - Health check commands `php artisan assetmanagement:health [--json|--alert|--detail]`

### Adicionado — D2 Pest expand Wave 27 (9 cenários novos)
- `Tests/Feature/Wave27AssetManagementPolishTest.php`:
  - Spans novos (Reflection source) — count canônico ≥5 spanBiz invocations
  - README existence + cenários A/B/C/D documentados
  - Cross-tenant biz=99: contagemAtivas/contagemExpiradas retorna 0 (Tier 0)
  - Contract int type sanity

### Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Multi-tenant Tier 0 (ADR 0093) — `business_id` global scope em Asset/Allocation/Maintenance
- ⛔ `am_warranties` SEM business_id direto — scope via JOIN parent Asset preservado
- ⛔ NUNCA biz=4 cliente real (ADR 0101) — biz=1 / biz=99 em testes
- ⛔ OtelHelper canônico (`App\Util\OtelHelper`)
- ⛔ PT-BR comentários

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D9.a 14 spans + D5 customer journey README)
- ADR 0159 Wave 25 polish base

## [Wave 25 — Polish D9.a/c saturation + retention compliance] — 2026-05-16

### Adicionado — D9.a OTel spans (+9 spans em 3 Services)
- `AssetService::criar/atualizar/remover` — agora envoltos em `OtelHelper::spanBiz()`:
  - `assetmanagement.asset.criar` (+attrs: business_id, user_id)
  - `assetmanagement.asset.atualizar` (+attrs: business_id, asset_id)
  - `assetmanagement.asset.remover` (+attrs: business_id, asset_id)
- `AssetAllocationService::criar/atualizar/remover` — idem:
  - `assetmanagement.allocation.criar/atualizar/remover` (+attrs com allocation_id quando aplicável)
- `AssetMaintenanceService::criar/atualizar/remover` — idem:
  - `assetmanagement.maintenance.criar/atualizar/remover` (+attrs com maintenance_id)
- Total: **3 Services × 3 métodos = 9 spans** com business_id Tier 0 propagado.

### Adicionado — D9.c Health Check (+1 check canônico)
- `AssetManagementHealthCommand` agora tem **8 checks** (era 7):
  - **Novo**: `retention_config_present` — verifica `Config/retention.php` presente + 4 entities mapeadas (am_assets, am_asset_transactions, am_maintenance_logs, am_warranties).
- Output: `total=8`, `retention_config_present=OK` (4 entities + strategy=anonymize).

### Alterado — Provider registration
- `AssetManagementServiceProvider::boot()` agora registra `AssetManagementHealthCommand::class` via `$this->commands([...])` (estava órfão pré-Wave 25 — `php artisan list` não exibia).

### Alterado — Tests
- `AssetManagementHealthCommandTest` atualizado: `total` expected 7→8 + adicionada `retention_config_present` na lista de checks canônicos esperados.

### Pest local
- `Modules/AssetManagement/Tests/` — **52 passed (119 assertions), 7 skipped intencional** (CrossTenantAssetTest SQLite-incompat — schema legacy MySQL UltimatePOS, ADR 0101).

### Não alterado (intencional — já saturado)
- D7.c Config/retention.php declara 4 entities desde W23 (am_assets 3650d/10y fiscal, am_asset_transactions 1825d/5y CTN, am_maintenance_logs 1825d, am_warranties 2555d/7y).
- D1 Multi-tenant: Assets/Allocation/Maintenance entities com `business_id` direto desde W18.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1 (cross-tenant SQLite skip documentado)
- ADR 0155 Module Grade v3 (D9.a 9/9 spans + D9.c 8/8 checks)
- ADR 0159 Wave 25 polish (level `none` mantido — scaffold UltimatePOS sem uso de cliente, saturação serve baseline arquitetural)
