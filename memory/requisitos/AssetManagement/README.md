# Modules/AssetManagement — Gestão de Ativos

> Wave 27 polish 2026-05-17 — README "como cliente usa" (D5 customer journey).
> Módulo UltimatePOS herdado, instrumentado D9.a OTel + D7.b LogsActivity + D7.c retention LGPD.

## Missão

Cadastro + alocação + manutenção de **bens / ativos físicos** (impressoras gráficas, máquinas costura, equipamentos oficina) da empresa cliente. Inclui controle de garantia + histórico de manutenção + alocação a funcionários/projetos.

## Fluxo de ponta a ponta — o ciclo de um bem

> Medido em `origin/main` 7d997591d7 (2026-11-05). Rotas em `Modules/AssetManagement/Routes/web.php`
> (prefixo `/asset`); telas em `resources/js/Pages/Patrimonio/`. Cada passo cita o UC que é o
> contrato executável dele (`<Tela>.casos.md`). Os cenários A–D mais abaixo são o texto da
> Wave 27 (2026-05-17), mantido porque o `Wave27AssetManagementPolishTest` o lê.

```
cadastro (+ garantia) ──► alocação ──► devolução (parcial ou total)
        └──► manutenção (aberta → fechada)   — independente da alocação: o saldo do bem
                                                só conta alocações e devoluções (`saldoLivre`, `AssetAllocationService.php:182`)
garantia: acompanhada no bem, no painel e no recorte "Garantia crítica"
baixa: NÃO EXISTE (wish US-ASSET-W03) — o que há é excluir o bem
```

| # | Passo | Onde o usuário faz | Rota e código | O que grava | Contrato |
|---|---|---|---|---|---|
| 1 | **Cadastrar o bem** (com garantias) | Bens → "Adicionar recurso" (`Bens.tsx:629`), drawer `_shared/CadastroBemDrawer.tsx` | `GET /asset/assets/create` → `AssetController@create` (`:714`); `POST /asset/assets` → `@store` (`:736`) → `AssetService::criar` (`Services/AssetService.php:36`) | `assets` + `asset_warranties` do bem (`AssetService.php:66-68`); código do bem gerado pelo prefixo se vier vazio | UC-BENS-05 |
| 2 | **Alocar a um colaborador** | Alocações → drawer "Alocar recurso" (`_alocacoes/Drawers.tsx:177`) | `GET /asset/allocation/create?asset_id=` → `AssetAllocationController@create` (`:333`); `POST /asset/allocation` → `@store` (`:385`) → `AssetAllocationService::criar` | `asset_transactions` com `transaction_type=allocate`; recusa se a quantidade passa do saldo do bem (`AssetAllocationService.php:52`, `garantirSaldo` `:203`) | UC-ALOC-06, UC-ALOC-07 |
| 3 | **Devolver** (parcial ou total) | Alocações → drawer "Devolver" (`_alocacoes/Drawers.tsx:289`) | `POST /asset/revocation` → `RevokeAllocatedAssetController@store` (`:159`) | `asset_transactions` com `transaction_type=revoke` e `parent_id` = a alocação; a alocação-pai vem da empresa da sessão, e não se devolve mais do que falta voltar (`:168-183`). Excluir devolução: `DELETE /asset/revocation/{id}`, só de tipo `revoke` (`:246-263`) | UC-ALOC-08 |
| 4 | **Enviar pra manutenção** | Linha do bem em Bens (UC-BENS-12) ou "+ Enviar bem pra manutenção" em Manutenções (`Manutencoes.tsx:841-849`) | `GET /asset/asset-maintenance/create` → `AssetMaitenanceController@create` (`:310`); `POST /asset/asset-maintenance` → `@store` (`:369`) → `AssetMaintenanceService::criar` (`Services/AssetMaintenanceService.php:33`) | `asset_maintenances`; e-mail de "enviado pra manutenção" (`AssetMaintenanceService.php:58`), se ligado nas Configurações | UC-BENS-12, UC-MANU-06 |
| 5 | **Acompanhar e fechar a manutenção** | Manutenções → lápis da linha (drawer) | `PUT /asset/asset-maintenance/{id}` → `@update` (`:469`) → `AssetMaintenanceService::atualizar` (`:70`) | `status`, `priority`, `details`, `assigned_to`; atribuir responsável manda o e-mail de designação (`:89`). Conta como aberta só `new` e `in_progress` (`contarAbertas`, `:129-132`) — é o que alimenta "Em manutenção" em Bens | UC-MANU-05, UC-MANU-06, UC-BENS-07 |
| 6 | **Garantia** | No cadastro/edição do bem (passo 1); leitura em Bens ("Garantia crítica") e no painel | edição: `PUT /asset/assets/{id}` → `AssetService::atualizar` (`AssetService.php:85`) | `asset_warranties` não tem `business_id`: o escopo vem do bem (`AssetService.php:104`). Vale a garantia mais recente de cada bem | UC-BENS-06, UC-BENS-08, UC-PAT-10 |
| 7 | **Fim de vida** | Bens → "Excluir" | `DELETE /asset/assets/{id}` → `AssetController@destroy` (`:863`) → `AssetService::remover` (`:163`) | apaga o bem e as garantias dele (`AssetService.php:166-170`). **Não há baixa** (motivo + data, mantendo o histórico): é o wish US-ASSET-W03 do SPEC | UC-BENS-11 |

**Valor residual (painel):** linear pela vida útil do próprio bem (`Services/DepreciacaoGerencial.php`,
US-ASSET-009, UC-PAT-12). Prazo padrão por categoria não existe (US-ASSET-011).

**O que este fluxo não cobre:** transferência entre filiais (W02), QR code (W04), manutenção
preventiva com próxima data (US-ASSET-010). Nenhum deles tem código.

## Cenários canônicos (como cliente usa)

### Cenário A — Cadastrar ativo novo (ex: impressora Epson L1300)

1. Operador acessa `/asset/assets` (botão "Adicionar recurso"; até 2026-11-05 este passo citava `/assetmanagement/assets`, rota que não existe)
2. Preenche `asset_code` (auto-gerado se vazio — prefix configurável), `name`, `serial_no`, `model`, `purchase_date`, `unit_price`, `depreciation` (% ao ano)
3. Marca `is_allocatable=1` se ativo pode ser alocado a usuário (impressora vs prédio)
4. (Opcional) Adiciona garantias na sub-tabela `warranties` (start_date + months → end_date calculado)
5. Submit → `AssetService::criar()` valida + persiste em transação atômica
   - Span OTel: `assetmanagement.asset.criar`
   - Audit log: `activity_log` (LogsActivity) com PII redactada

### Cenário B — Alocar ativo a funcionário (ex: notebook Maria/RH)

1. Operador acessa `/asset/allocation` (drawer "Alocar recurso"; até 2026-11-05 este passo citava `/assetmanagement/asset-allocations`, rota que não existe)
2. Seleciona asset disponível (verifica `quantity - allocated_qty + revoked_qty`)
3. Preenche `receiver` (user_id), `transaction_datetime`, `reason`, `allocated_upto` (data limite)
4. Submit → `AssetAllocationService::criar()` cria `asset_transactions` type=`allocate`
   - Span OTel: `assetmanagement.allocation.criar`

### Cenário C — Abrir ordem de manutenção (ex: impressora travou)

1. Operador acessa `/asset/asset-maintenance` (botão "+ Enviar bem pra manutenção"; até 2026-11-05 este passo citava `/assetmanagement/asset-maintenances`, rota que não existe)
2. Seleciona asset, define `priority` (low/medium/high/urgent), `status=pending`, `maintenance_note`
3. Submit → `AssetMaintenanceService::criar()` cria OS + dispara notification
   - Span OTel: `assetmanagement.maintenance.criar`
   - Notifica via `assetUtil->sendAssetSentForMaintenanceNotification($id)`

### Cenário D — Verificar garantias ativas (relatório executivo)

1. Cliente quer saber quantos ativos têm garantia vigente
2. API/Controller chama `AssetWarrantyService::contagemAtivas($assetId, $businessId)` (Wave 27)
3. Service retorna count com span `assetmanagement.warranty.count_active` instrumentado
4. Útil pra widget dashboard: "73 garantias ativas / 12 expirando em 30d / 4 expiradas"

## Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)

- **`assets`**, **`asset_transactions`**, **`asset_maintenances`** têm `business_id` direto. **Nenhum dos 4 Models aplica escopo automático de empresa** (sem `addGlobalScope` nem trait de escopo em `Modules/AssetManagement/Entities/`, medido em `origin/main` 2026-11-05): o filtro é escrito à mão em cada consulta — ex. `Entities/Asset.php:77`, `Services/AssetAllocationService.php:89`, `Services/AssetWarrantyService.php:32`. Por isso os 4 estão em `grandfathered` em `governance/multi-tenant-scope-baseline.json:8-11`. ⛔ Não remova um `where('business_id', …)` achando que o Model filtra sozinho: ele não filtra.
- Até 2026-11-05 este README dizia que as três tabelas tinham "HasBusinessScope global scope". Era falso desde a criação do texto (2026-05-17).
- **`asset_warranties`** scope via parent Asset (`whereHas('asset', …)`, `Services/AssetWarrantyService.php:64` — não tem `business_id` próprio)
- Os nomes acima são os do schema (`database/schema/mysql-schema.sql`). Até 2026-10-13 este README dizia `am_*`, prefixo que nenhuma migration cria.
- Job assíncrono SEMPRE recebe `$businessId` no constructor — Service NUNCA chama `session()`

## D7 — Retenção LGPD + Audit (Wave 23/25)

- `Config/retention.php` declara 3 tabelas (chaves com os nomes reais desde 2026-10-13):
  - `assets`: 3650d / 10 anos (fiscal CC Art. 206)
  - `asset_transactions`: 1825d / 5 anos (CTN Art. 195)
  - `asset_maintenances`: 1825d / 5 anos
  - `asset_warranties` saiu: não tem coluna que aponte para pessoa
- **Sem executor, por decisão [W] de 2026-10-13:** retenção no Patrimônio não existe. A [ADR 0430](../../decisions/0430-retencao-lgpd-patrimonio-anonimiza-campo-pessoal.md), que a reabria, foi recusada, e vale a lápide de 2026-07-27 em `memory/licoes-rejeitadas.md` (o controle é por permissão de acesso). O arquivo segue `enabled=false`.
- Entities têm `LogsActivity` (Spatie Activitylog) — mudanças críticas auditadas em `activity_log`

## D9 — Observabilidade OtelHelper canônico

| Service | Métodos instrumentados | Spans |
|---|---|---|
| `AssetService` | criar/atualizar/remover | 3 |
| `AssetAllocationService` | criar/atualizar/remover | 3 |
| `AssetMaintenanceService` | criar/atualizar/remover | 3 |
| `AssetWarrantyService` | adicionar/revogar/ativas/contagemAtivas/contagemExpiradas | 5 (W27 +2) |

Total: **14 spans** com `business_id` + `module='AssetManagement'` Tier 0 propagado.

Zero-cost overhead quando `otel.enabled=false` (default Hostinger — só CT 100 com `OTEL_EXPORTER_OTLP_ENDPOINT` configurado emite).

## Health check

```bash
php artisan assetmanagement:health           # output tabela 8 checks
php artisan assetmanagement:health --json    # output JSON parseable
php artisan assetmanagement:health --alert   # exit code != 0 se FAIL
php artisan assetmanagement:health --detail  # expande JSON após tabela
```

8 checks: schema_canon · catalog_global · assets_orphan · allocations_consistency · maintenances_open_per_business · warranties_expired · audit_trail_present · `retention_config_present` (Wave 25 NEW)

## ADRs centrais

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) Tests biz=1
- [ADR 0155](../../decisions/0155-module-grade-rubrica-v3.md) Module Grade v3 (D9.a/c)
- [ADR 0159](../../decisions/0159-wave-25-polish-modulos-saturated.md) Wave 25 polish

## SCOPE / não fazer aqui

Ver [SCOPE.md](SCOPE.md). Em resumo:
- ❌ Conhecimento canônico (ADRs, sessions) → `Modules/KB`
- ❌ Tasks Jira-style → `Modules/Forja`
- ❌ MCP server admin → `Modules/TeamMcp`
- ❌ Lançamento contábil de depreciação → `Modules/Accounting` (downstream consumer)
