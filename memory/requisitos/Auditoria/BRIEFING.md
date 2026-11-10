---
id: requisitos-auditoria-briefing
module: Auditoria
status: parcial
status_nota: "Trilha por-registro em produção (Index + Detail). Reverter está codificado e desligado da rota; decisão D1 de [W] pendente."
updated_at: "2026-11-09"
owner: W
piloto: N/A (governança transversal — todos businesses sem cliente externo direto)
parent_adr: 0127-modules-auditoria-undo-activity-log
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0107-emendation-0104-visual-comparison-gate-f3, 0127-modules-auditoria-undo-activity-log, 0358-doutrina-de-teste-tenant-98-supersede-0101]
---

# Modules/Auditoria — BRIEFING

> 1-pager executivo. Aponta para os donos e não recopia SPEC, SCOPE nem contratos de tela.

## O que é

Interface humana da trilha por-registro do ERP. Cada módulo grava em `activity_log` (`spatie/laravel-activitylog`, trait `LogsActivity` no Model) e este módulo mostra quem mudou o quê e quando, com diff `old` × `attributes`, sempre dentro do business do usuário. Materializa o Art. 9 da Constituição v2 (auditabilidade).

## Por que existe

- **Compliance LGPD/CLT/CONFAZ:** trilha de quem alterou o quê e quando.
- **Substitui `/reports/activity-log`:** a rota legada redireciona com 301, mantendo a querystring.
- **Confiança na Jana (desenho):** separar ação humana de ação de IA por `causer_kind`. Ver o estado abaixo.

## Estado atual

Medido em 2026-11-09, no `main` em `844a13c`. É fato datado; a tabela completa, com o comando de cada linha, está no [SPEC §Estado real](SPEC.md#estado-real-medido-em-2026-11-09).

| Capacidade | Estado |
|---|---|
| Listar e filtrar por origem, evento e entidade | Entregue (`Index.casos.md`, 3 UCs) |
| Ver o diff de uma alteração | Entregue (`Detail.casos.md`, 2 UCs) |
| `LogsActivity` nos Models do piloto | Entregue (US-AUDIT-001..004) |
| `business_id` do log gravado pelo tenant do registro | Entregue, com backfill (ver abaixo) |
| Separar usuário de IA | Parcial: `user`, `system` e `api` são resolvidos pelo observer. `agent` não tinha produtor, porque nenhum código de produção fazia o bind `jana.agent_run_id` |
| Reverter | Desligado: `POST /auditoria/{id}/revert` respondia 501 e a tela não tinha botão. `RevertService` existe e não é chamado por HTTP |
| Bloqueio de irreversíveis | No serviço, com as classes reais desde 2026-11-10 ([#10674](https://github.com/wagnerra23/oimpresso.com/pull/10674)). Em 2026-11-09 só 1 de 5 chaves tinha efeito. Também nega registro de outra empresa, segunda reversão e campo de valor ou estoque (#10675, #10676). A rota respondia 501 nessa data, logo nada disso era alcançável por HTTP |
| Permissão `auditoria.view` | Só escondia o item de menu |
| Notas, export e ação em lote | Sem rota |
| Expurgo por prazo | Nenhum: `activitylog:clean` não roda. Num ERP o dado tem dever de guarda (decisão [W] de 2026-07-27, `proibicoes.md` §5) |

**Decisão pendente de [W] (D1):** a ADR 0127 prevê reverter dentro de `/auditoria`; `E-AUDIT-REVERT` (delegada em 2026-10-05) diz que a Auditoria é só leitura e que reverter acontece na tela de origem.

## Componentes

| Peça | Path | Responsabilidade |
|---|---|---|
| Trait `LogsActivity` | Models de cada módulo (`App\Contact`, `App\Product`, `App\Transaction`, `Modules\Financeiro\Models\Titulo` etc.) | Emite o log |
| `ActivityCauserKindObserver` | [`app/Observers/ActivityCauserKindObserver.php`](../../../app/Observers/ActivityCauserKindObserver.php) | Resolve `causer_kind` |
| `AuditEntryService` | [`Modules/Auditoria/Services/AuditEntryService.php`](../../../Modules/Auditoria/Services/AuditEntryService.php) | Listagem, filtro e detalhe |
| `AuditoriaController` | [`Modules/Auditoria/Http/Controllers/AuditoriaController.php`](../../../Modules/Auditoria/Http/Controllers/AuditoriaController.php) | `index`, `show`, `revert` (501) |
| `RevertService` e `RevertCheck` | [`Modules/Auditoria/Services/`](../../../Modules/Auditoria/Services/RevertService.php) | Regras e undo, sem rota que os chame |
| Telas Inertia | `resources/js/Pages/Auditoria/Index.tsx` e `Detail.tsx` | Filtros e diff |
| Permissões Spatie | `auditoria.view`, `auditoria.revert.own`, `.any`, `.unlimited` | Cadastradas em `DataController` |

## Comandos

Os dois são manuais e não têm agendamento (medido em 2026-11-09).

- `php artisan auditoria:health [--alert] [--json]`: 4 sinais (tabela `activity_log`, atividade em 24h, colunas de revert, taxa de revert em 7 dias).
- `php artisan auditoria:backfill-business-id [--apply]`: preenche `business_id` NULL pelo registro auditado. Sem `--apply`, só dry-run.

## Backfill do `business_id` NULL no `activity_log` (2026-10-01)

Fecha a dívida deixada pelo bug corrigido no [#8384](https://github.com/wagnerra23/oimpresso.com/pull/8384), em que o trait `LogsActivity` não gravava o tenant. O comando é `php artisan auditoria:backfill-business-id` ([#8406](https://github.com/wagnerra23/oimpresso.com/pull/8406)). Sem `--apply` ele só faz dry-run. O tenant vem sempre do **registro auditado**, nunca da sessão (ADR 0093). Os números abaixo são fato datado de 2026-10-01; para o estado de hoje, rode o dry-run de novo.

- **Núcleo** (`Transaction`, `TransactionSellLine`, `TransactionPayment`, `Contact`, `Product`, `Variation`, `VariationLocationDetails`, `PurchaseLine`): o `--apply` foi rodado por outra sessão antes desta conferência. O que sobrou NULL (655 linhas) aponta para registros já apagados, sem caminho até um tenant.
- **Demais módulos:** 6.322 linhas preenchidas com aprovação [W]. Primeiro `App\Unit` (30) como amostra, depois o resto (KB, Woocommerce, Whatsapp, Financeiro, OficinaAuto, RecurringBilling e outros).
- **Licenças** (`LicencaLog`, `Licenca_Computador`) são log da plataforma, por decisão [W] registrada no #8406: ficam NULL. As 146 linhas que haviam saído com tenant foram limpas.
- **Conferência pós-apply:** 40.284 logs das classes gravadas foram comparados com o `business_id` do registro auditado, e nenhum diverge. As 30.619 linhas do núcleo também foram conferidas, com 0 divergentes. Hoje nenhuma licença tem tenant, e o dry-run seguinte dá 0 resolvíveis.
- **Logs sem subject** (mesmo dia, [#8449](https://github.com/wagnerra23/oimpresso.com/pull/8449)): os 16 `nfe.certificado` estavam NULL porque o `CertificadoController` chama `activity()` sem `performedOn` e põe o tenant só em `properties`. Mais 6 pontos do código faziam o mesmo. O observer passou a usar o `business_id` declarado pelo chamador quando não há subject (nunca a sessão). O backfill grava os antigos só quando o valor declarado confere com o tenant do usuário causador: foram 16 conferidos, 16 gravados e 0 divergentes, com aprovação [W].
- **Continua NULL (~93 mil), por desenho:** licenças da plataforma; `McpTask`, `HealthNarrative` e outros tipos da Jana sem tenant; e registros apagados. O comando não inventa tenant.

## Riscos vivos

- `properties` é TEXT (não JSON nativo): os filtros da tela usam só colunas indexadas (`subject_type`, `event`, `business_id`).
- O volume de `activity_log` só cresce: nada é expurgado por prazo. O valor `delete_records_older_than_days=365` em `config/activitylog.php` não tem efeito sem `activitylog:clean`.
- PII em `properties` por `logOnly` mal configurado: `PiiLeakActivityLogEnforceTest` cobre, e em 2026-11-09 estava fora das lanes de PR (`node scripts/governance/test-lane-coverage.mjs`).

## Portas canônicas

- **Requisitos e estado real:** [SPEC.md](SPEC.md)
- **Arquitetura e casos de uso:** [SDD-auditoria-v1.0.md](SDD-auditoria-v1.0.md)
- **Fronteira:** [SCOPE.md](SCOPE.md)
- **Superfície derivada de código:** [SUPERFICIE.md](SUPERFICIE.md)
- **Telas:** `resources/js/Pages/Auditoria/`, com charters e casos ao lado
- **Decisões:** [ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md) (mãe) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (`business_id`) · [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (Art. 9) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) (teste no tenant 98) · [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) (gate visual)
- **Padrão de `LogsActivity`:** [`Modules/Financeiro/Models/Titulo.php`](../../../Modules/Financeiro/Models/Titulo.php)
