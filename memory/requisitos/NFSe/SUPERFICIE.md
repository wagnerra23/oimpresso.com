---
name: "SUPERFÍCIE — NFSe"
description: "Índice GERADO dos artefatos do módulo NFSe reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: NFSe
---

# 🗺️ Superfície de código — NFSe

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs NFSe --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/NFSe/**` + `resources/js/Pages/NFSe/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 56 arquivos em 16 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/NFSe/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/NFSe/Http/Controllers/InstallController.php)
- [NfseController.php](../../../Modules/NFSe/Http/Controllers/NfseController.php)

## Requests (validação) — 3

- [CancelarNfseRequest.php](../../../Modules/NFSe/Http/Requests/CancelarNfseRequest.php)
- [IndexNfseRequest.php](../../../Modules/NFSe/Http/Requests/IndexNfseRequest.php)
- [StoreNfseRequest.php](../../../Modules/NFSe/Http/Requests/StoreNfseRequest.php)

## Services — 1

- [NfseEmissaoService.php](../../../Modules/NFSe/Services/NfseEmissaoService.php)

## Models / Entities — 4

- [NfseBusinessScope.php](../../../Modules/NFSe/Models/Concerns/NfseBusinessScope.php)
- [NfseCertificado.php](../../../Modules/NFSe/Models/NfseCertificado.php)
- [NfseEmissao.php](../../../Modules/NFSe/Models/NfseEmissao.php)
- [NfseProviderConfig.php](../../../Modules/NFSe/Models/NfseProviderConfig.php)

## Observers — 1

- [TransactionNfseObserver.php](../../../Modules/NFSe/Observers/TransactionNfseObserver.php)

## Jobs — 1

- [EmitirNfseJob.php](../../../Modules/NFSe/Jobs/EmitirNfseJob.php)

## Console / Commands — 2

- [ImportarCertificadoCommand.php](../../../Modules/NFSe/Console/Commands/ImportarCertificadoCommand.php)
- [NfseHealthCommand.php](../../../Modules/NFSe/Console/Commands/NfseHealthCommand.php)

## Providers — 2

- [NfseServiceProvider.php](../../../Modules/NFSe/Providers/NfseServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/NFSe/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/NFSe/Routes/api.php)
- [web.php](../../../Modules/NFSe/Routes/web.php)

## Migrations (schema) — 5

- [2026_05_01_000001_create_nfe_certificados_table.php](../../../Modules/NFSe/Database/Migrations/2026_05_01_000001_create_nfe_certificados_table.php)
- [2026_05_01_000002_create_nfse_provider_configs_table.php](../../../Modules/NFSe/Database/Migrations/2026_05_01_000002_create_nfse_provider_configs_table.php)
- [2026_05_01_000003_create_nfse_emissoes_table.php](../../../Modules/NFSe/Database/Migrations/2026_05_01_000003_create_nfse_emissoes_table.php)
- [2026_05_01_000004_add_prestador_cnpj_to_nfse_provider_configs.php](../../../Modules/NFSe/Database/Migrations/2026_05_01_000004_add_prestador_cnpj_to_nfse_provider_configs.php)
- [2026_05_03_000001_add_transaction_id_to_nfse_emissoes.php](../../../Modules/NFSe/Database/Migrations/2026_05_03_000001_add_transaction_id_to_nfse_emissoes.php)

## Seeders — 1

- [NfseSeeder.php](../../../Modules/NFSe/Database/Seeders/NfseSeeder.php)

## Config — 2

- [config.php](../../../Modules/NFSe/Config/config.php)
- [retention.php](../../../Modules/NFSe/Config/retention.php)

## Telas (Inertia/React) — 3

- [Emitir.tsx](../../../resources/js/Pages/NFSe/Emitir.tsx)
- [Index.tsx](../../../resources/js/Pages/NFSe/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/NFSe/Show.tsx)

## Charters (lei da tela) — 3

- [Emitir.charter.md](../../../resources/js/Pages/NFSe/Emitir.charter.md)
- [Index.charter.md](../../../resources/js/Pages/NFSe/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/NFSe/Show.charter.md)

## Testes (Pest) — 10

- 10 arquivos em [Modules/NFSe/Tests/Feature/](../../../Modules/NFSe/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 13

- [SnNfseAdapter.php](../../../Modules/NFSe/Adapters/SnNfseAdapter.php)
- [NfseProviderInterface.php](../../../Modules/NFSe/Contracts/NfseProviderInterface.php)
- [NfseEmissaoPayload.php](../../../Modules/NFSe/DTO/NfseEmissaoPayload.php)
- [NfseResultado.php](../../../Modules/NFSe/DTO/NfseResultado.php)
- [CertificadoInvalidoException.php](../../../Modules/NFSe/Exceptions/CertificadoInvalidoException.php)
- [CodigoServicoInvalidoException.php](../../../Modules/NFSe/Exceptions/CodigoServicoInvalidoException.php)
- [IssInvalidoException.php](../../../Modules/NFSe/Exceptions/IssInvalidoException.php)
- [NfseException.php](../../../Modules/NFSe/Exceptions/NfseException.php)
- [NfseJaCanceladaException.php](../../../Modules/NFSe/Exceptions/NfseJaCanceladaException.php)
- [PrestadorNaoAutorizadoException.php](../../../Modules/NFSe/Exceptions/PrestadorNaoAutorizadoException.php)
- [ProviderTimeoutException.php](../../../Modules/NFSe/Exceptions/ProviderTimeoutException.php)
- [RpsDuplicadoException.php](../../../Modules/NFSe/Exceptions/RpsDuplicadoException.php)
- [TomadorInvalidoException.php](../../../Modules/NFSe/Exceptions/TomadorInvalidoException.php)
