---
name: "SUPERFÍCIE — NfeBrasil"
description: "Índice GERADO dos artefatos do módulo NfeBrasil reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: NfeBrasil
---

# 🗺️ Superfície de código — NfeBrasil

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs NfeBrasil --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/NfeBrasil/**` + `resources/js/Pages/NfeBrasil/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 152 arquivos em 17 papéis.

## Controllers — 11

- [CertificadoController.php](../../../Modules/NfeBrasil/Http/Controllers/CertificadoController.php)
- [ConfigDefaultController.php](../../../Modules/NfeBrasil/Http/Controllers/ConfigDefaultController.php)
- [DataController.php](../../../Modules/NfeBrasil/Http/Controllers/DataController.php)
- [ImportRegrasController.php](../../../Modules/NfeBrasil/Http/Controllers/ImportRegrasController.php)
- [InstallController.php](../../../Modules/NfeBrasil/Http/Controllers/InstallController.php)
- [ManifestacaoController.php](../../../Modules/NfeBrasil/Http/Controllers/ManifestacaoController.php)
- [NfeBrasilController.php](../../../Modules/NfeBrasil/Http/Controllers/NfeBrasilController.php)
- [NfeEmissaoController.php](../../../Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php)
- [NfeInutilizacaoController.php](../../../Modules/NfeBrasil/Http/Controllers/NfeInutilizacaoController.php)
- [NfeStatusController.php](../../../Modules/NfeBrasil/Http/Controllers/NfeStatusController.php)
- [TributacaoController.php](../../../Modules/NfeBrasil/Http/Controllers/TributacaoController.php)

## Requests (validação) — 6

- [CancelarNfeRequest.php](../../../Modules/NfeBrasil/Http/Requests/CancelarNfeRequest.php)
- [ImportRegrasCsvRequest.php](../../../Modules/NfeBrasil/Http/Requests/ImportRegrasCsvRequest.php)
- [StoreEmissaoRequest.php](../../../Modules/NfeBrasil/Http/Requests/StoreEmissaoRequest.php)
- [UploadCertificadoRequest.php](../../../Modules/NfeBrasil/Http/Requests/UploadCertificadoRequest.php)
- [UpsertConfigDefaultRequest.php](../../../Modules/NfeBrasil/Http/Requests/UpsertConfigDefaultRequest.php)
- [UpsertRegraTributariaRequest.php](../../../Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php)

## Services — 15

- [CertificadoService.php](../../../Modules/NfeBrasil/Services/CertificadoService.php)
- [DanfeService.php](../../../Modules/NfeBrasil/Services/DanfeService.php)
- [DistribuicaoDfeService.php](../../../Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php)
- [ManifestacaoService.php](../../../Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php)
- [MotorTributarioService.php](../../../Modules/NfeBrasil/Services/MotorTributarioService.php)
- [NfeCartaCorrecaoService.php](../../../Modules/NfeBrasil/Services/NfeCartaCorrecaoService.php)
- [NfeInutilizacaoService.php](../../../Modules/NfeBrasil/Services/NfeInutilizacaoService.php)
- [NfeService.php](../../../Modules/NfeBrasil/Services/NfeService.php)
- [NfseCancelService.php](../../../Modules/NfeBrasil/Services/NfseCancelService.php)
- [AbrasfV204CancelDriver.php](../../../Modules/NfeBrasil/Services/NfseDrivers/AbrasfV204CancelDriver.php)
- [SefazConsultaCadastroService.php](../../../Modules/NfeBrasil/Services/SefazConsultaCadastroService.php)
- [ImportRegrasCsvService.php](../../../Modules/NfeBrasil/Services/Tributacao/ImportRegrasCsvService.php)
- [ProdutoFiscalContext.php](../../../Modules/NfeBrasil/Services/Tributacao/ProdutoFiscalContext.php)
- [TributacaoTemplateService.php](../../../Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php)
- [TributoCalculado.php](../../../Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php)

## Models / Entities — 12

- [NfeBusinessConfig.php](../../../Modules/NfeBrasil/Models/NfeBusinessConfig.php)
- [NfeCertificado.php](../../../Modules/NfeBrasil/Models/NfeCertificado.php)
- [NfeDfeEvento.php](../../../Modules/NfeBrasil/Models/NfeDfeEvento.php)
- [NfeDfeItem.php](../../../Modules/NfeBrasil/Models/NfeDfeItem.php)
- [NfeDfeNsuState.php](../../../Modules/NfeBrasil/Models/NfeDfeNsuState.php)
- [NfeDfeRecebido.php](../../../Modules/NfeBrasil/Models/NfeDfeRecebido.php)
- [NfeEmissao.php](../../../Modules/NfeBrasil/Models/NfeEmissao.php)
- [NfeEvento.php](../../../Modules/NfeBrasil/Models/NfeEvento.php)
- [NfeFiscalRule.php](../../../Modules/NfeBrasil/Models/NfeFiscalRule.php)
- [NfeInutilizacao.php](../../../Modules/NfeBrasil/Models/NfeInutilizacao.php)
- [NfseEmissao.php](../../../Modules/NfeBrasil/Models/NfseEmissao.php)
- [NfseEventoCancelamento.php](../../../Modules/NfeBrasil/Models/NfseEventoCancelamento.php)

## Jobs — 5

- [BuscarDfesRecebidosJob.php](../../../Modules/NfeBrasil/Jobs/BuscarDfesRecebidosJob.php)
- [CancelarNfeJob.php](../../../Modules/NfeBrasil/Jobs/CancelarNfeJob.php)
- [CancelarNfseJob.php](../../../Modules/NfeBrasil/Jobs/CancelarNfseJob.php)
- [EmitirNFSeJob.php](../../../Modules/NfeBrasil/Jobs/EmitirNFSeJob.php)
- [EmitirNfceJob.php](../../../Modules/NfeBrasil/Jobs/EmitirNfceJob.php)

## Events / Listeners — 10

- [FiscalRuleCreated.php](../../../Modules/NfeBrasil/Events/FiscalRuleCreated.php)
- [FiscalRuleDeleted.php](../../../Modules/NfeBrasil/Events/FiscalRuleDeleted.php)
- [FiscalRuleUpdated.php](../../../Modules/NfeBrasil/Events/FiscalRuleUpdated.php)
- [NFCeAutorizada.php](../../../Modules/NfeBrasil/Events/NFCeAutorizada.php)
- [NFeAutorizada.php](../../../Modules/NfeBrasil/Events/NFeAutorizada.php)
- [EmitirNFeAoReceberPagamento.php](../../../Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php)
- [EmitirNfceAoFinalizarVenda.php](../../../Modules/NfeBrasil/Listeners/EmitirNfceAoFinalizarVenda.php)
- [EnviarDanfeNFCePorEmail.php](../../../Modules/NfeBrasil/Listeners/EnviarDanfeNFCePorEmail.php)
- [EnviarDanfePorEmail.php](../../../Modules/NfeBrasil/Listeners/EnviarDanfePorEmail.php)
- [SyncFiscalRuleToTaxRate.php](../../../Modules/NfeBrasil/Listeners/SyncFiscalRuleToTaxRate.php)

## Console / Commands — 3

- [MigrateCertFromBusiness.php](../../../Modules/NfeBrasil/Console/Commands/MigrateCertFromBusiness.php)
- [NfeHealthCommand.php](../../../Modules/NfeBrasil/Console/Commands/NfeHealthCommand.php)
- [PuxarDfesRecebidosCommand.php](../../../Modules/NfeBrasil/Console/Commands/PuxarDfesRecebidosCommand.php)

## Providers — 2

- [NfeBrasilServiceProvider.php](../../../Modules/NfeBrasil/Providers/NfeBrasilServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/NfeBrasil/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/NfeBrasil/Routes/api.php)
- [web.php](../../../Modules/NfeBrasil/Routes/web.php)

## Migrations (schema) — 17

- [2026_05_06_002000_create_nfe_certificados_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002000_create_nfe_certificados_table.php)
- [2026_05_06_002001_create_nfe_emissoes_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002001_create_nfe_emissoes_table.php)
- [2026_05_06_002002_create_nfe_eventos_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002002_create_nfe_eventos_table.php)
- [2026_05_06_002003_create_nfe_inutilizacoes_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_002003_create_nfe_inutilizacoes_table.php)
- [2026_05_06_010000_create_nfe_fiscal_rules_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_010000_create_nfe_fiscal_rules_table.php)
- [2026_05_06_010001_create_nfe_business_configs_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_010001_create_nfe_business_configs_table.php)
- [2026_05_06_020000_create_nfe_fiscal_rule_tax_rate_links_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_06_020000_create_nfe_fiscal_rule_tax_rate_links_table.php)
- [2026_05_08_000000_add_auto_emission_enabled_to_nfe_business_configs.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_08_000000_add_auto_emission_enabled_to_nfe_business_configs.php)
- [2026_05_09_100000_create_nfe_dfe_recebidos_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_09_100000_create_nfe_dfe_recebidos_table.php)
- [2026_05_09_100001_create_nfe_dfe_itens_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_09_100001_create_nfe_dfe_itens_table.php)
- [2026_05_09_100002_create_nfe_dfe_eventos_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_09_100002_create_nfe_dfe_eventos_table.php)
- [2026_05_09_100003_create_nfe_dfe_nsu_state_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_09_100003_create_nfe_dfe_nsu_state_table.php)
- [2026_05_10_120000_alter_nfe_emissoes_status_enum_add_enviando_erro_envio.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_10_120000_alter_nfe_emissoes_status_enum_add_enviando_erro_envio.php)
- [2026_05_11_150001_create_nfse_emissoes_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_11_150001_create_nfse_emissoes_table.php)
- [2026_05_12_120000_create_nfse_eventos_cancelamento_table.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_12_120000_create_nfse_eventos_cancelamento_table.php)
- [2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules.php](../../../Modules/NfeBrasil/Database/Migrations/2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules.php)
- [2026_07_03_000000_add_reforma_tributaria_modo_to_nfe_business_configs.php](../../../Modules/NfeBrasil/Database/Migrations/2026_07_03_000000_add_reforma_tributaria_modo_to_nfe_business_configs.php)

## Seeders — 1

- [NfeBrasilDatabaseSeeder.php](../../../Modules/NfeBrasil/Database/Seeders/NfeBrasilDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/NfeBrasil/Config/config.php)
- [retention.php](../../../Modules/NfeBrasil/Config/retention.php)

## Views (Blade) — 4

- 4 arquivos em [Modules/NfeBrasil/Resources/views/](../../../Modules/NfeBrasil/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 6

- [Index.tsx](../../../resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx)
- [NfceStatus.tsx](../../../resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx)
- [ConfigDefault.tsx](../../../resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx)
- [ImportCsv.tsx](../../../resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.tsx)
- [Index.tsx](../../../resources/js/Pages/NfeBrasil/Tributacao/Index.tsx)
- [RegraForm.tsx](../../../resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx)

## Componentes / apoio de tela — 3

- [LinkedFornecedor.tsx](../../../resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedFornecedor.tsx)
- [LinkedHistorico.tsx](../../../resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedHistorico.tsx)
- [LinkedItens.tsx](../../../resources/js/Pages/NfeBrasil/Manifestacao/_components/LinkedItens.tsx)

## Charters (lei da tela) — 6

- [Index.charter.md](../../../resources/js/Pages/NfeBrasil/Manifestacao/Index.charter.md)
- [NfceStatus.charter.md](../../../resources/js/Pages/NfeBrasil/Transactions/NfceStatus.charter.md)
- [ConfigDefault.charter.md](../../../resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.charter.md)
- [ImportCsv.charter.md](../../../resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.charter.md)
- [Index.charter.md](../../../resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md)
- [RegraForm.charter.md](../../../resources/js/Pages/NfeBrasil/Tributacao/RegraForm.charter.md)

## Testes (Pest) — 47

- 47 arquivos em [Modules/NfeBrasil/Tests/Feature/](../../../Modules/NfeBrasil/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 4

- [NfseCancelDriverInterface.php](../../../Modules/NfeBrasil/Contracts/NfseCancelDriverInterface.php)
- [NcmObrigatorioException.php](../../../Modules/NfeBrasil/Exceptions/NcmObrigatorioException.php)
- [TributacaoNaoConfiguradaException.php](../../../Modules/NfeBrasil/Exceptions/TributacaoNaoConfiguradaException.php)
- [DanfeNotaFiscalMail.php](../../../Modules/NfeBrasil/Mail/DanfeNotaFiscalMail.php)

