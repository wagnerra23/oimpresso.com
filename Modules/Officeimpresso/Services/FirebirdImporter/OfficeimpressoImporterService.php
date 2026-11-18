<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Services\FirebirdImporter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OfficeimpressoImporterService — migra tabelas Firebird (Delphi WR Comercial
 * legado) → oimpresso (UltimatePOS Laravel).
 *
 * Wave 28-4 (G1 estado-da-arte vertical bucket, 2026-05-17). Único moat real
 * comprovado: zero competidor nacional faz Firebird → SaaS importer turnkey.
 *
 * Wave 29-1 (extensão CYCLE-06 Martinho prod, 2026-05-26) — adiciona FINANCEIRO
 * + NOTA_FISCAL ao escopo. Cobre demo Martinho Caçambas (biz=164) com Pessoas
 * (CLIENTES), Financeiro (a-receber + a-pagar) e Notas Fiscais (NFe modelo 55).
 *
 * ## Mapeamento Firebird → oimpresso (6 tabelas):
 *   - `CLIENTES`            → App\Contact            (type=customer)
 *   - `PRODUTOS`            → App\Product            (com Variation default)
 *   - `VENDAS`              → App\Transaction       (type=sell, status=final)
 *   - `LICENCA_COMPUTADOR`  → Modules\Officeimpresso\Entities\Licenca_Computador
 *   - `FINANCEIRO`          → Modules\Financeiro\Models\Titulo (fin_titulos) [W29-1]
 *   - `NOTA_FISCAL`         → Modules\NfeBrasil\Models\NfeEmissao (nfe_emissoes) [W29-1]
 *
 * ## Idempotência (Tier 0):
 *   - Hash do PK Firebird armazenado em `legacy_id` (JSON metadata) na linha oimpresso
 *   - fin_titulos: idempotência via UNIQUE (business_id, origem, origem_id, parcela_numero)
 *     + metadata->legacy_codigo (sem coluna legacy_id na tabela; preserva no JSON)
 *   - nfe_emissoes: idempotência via UNIQUE (business_id, modelo, serie, numero)
 *     + metadata->legacy_codigo
 *   - Re-run NÃO duplica: SELECT prévio por chave canônica
 *   - Skipped count reportado pra Wagner reviewer
 *
 * ## Multi-tenant Tier 0 ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *   - `$businessId` explícito em todo método (jobs assíncronos não têm session())
 *   - Inserts sempre com `business_id` setado
 *
 * ## One-way bridge (ADR 0019):
 *   - Firebird é READ-ONLY (FirebirdConnector::assertReadOnly bloqueia DML)
 *   - oimpresso NUNCA escreve de volta no .fdb
 *   - Cliente continua usando Delphi legado em paralelo (período migração ~30d)
 *
 * @see FirebirdConnector
 * @see Modules\Officeimpresso\Console\Commands\ImportOfficeimpressoCommand
 */
class OfficeimpressoImporterService
{
    private FirebirdConnector $connector;

    /** @var list<array<string, int|string|null>> Falhas da última execução (sem valores de linha). */
    private array $errors = [];

    /** @var array<string, bool> Cache de Schema::hasColumn(<tabela>, 'legacy_id'). */
    private array $temLegacyId = [];

    public function __construct(FirebirdConnector $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Roda importação completa (6 tabelas — W29-1 adicionou Financeiro + NFe).
     *
     * @return array{
     *     mode: string,
     *     dry_run: bool,
     *     clientes: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     produtos: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     vendas: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     licencas: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     financeiros: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     notas_fiscais: array{read: int, migrated: int, skipped: int, failed: int, blocked: int},
     *     errors: list<array<string, int|string|null>>,
     * }
     *
     * Contadores (2026-11-18, Lote 2 da auditoria Officeimpresso):
     *   - `migrated` só conta linha que foi gravada (ou, em dry-run, que seria gravada
     *     com a checagem de "já importado" respondida de verdade).
     *   - `failed` conta linha cujo insert OU cuja checagem de "já importado" deu erro.
     *     Antes os dois erros viravam `Log::warning` e a linha contava como migrada.
     *   - `blocked` conta linha de tabela SEM coluna `legacy_id` (products, transactions,
     *     licenca_computador): sem chave não há como saber se ela já foi importada, então
     *     a linha não é gravada. Criar a coluna em tabela core é a decisão B5 do [W].
     */
    public function runFullImport(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);
        $this->errors = [];

        $report = [
            'mode' => $this->connector->isMock() ? 'mock' : 'live',
            'dry_run' => $dryRun,
            'clientes' => $this->importClientes($businessId, $dryRun),
            'produtos' => $this->importProdutos($businessId, $dryRun),
            'vendas' => $this->importVendas($businessId, $dryRun),
            'licencas' => $this->importLicencas($businessId, $dryRun),
            'financeiros' => $this->importFinanceiros($businessId, $dryRun),
            'notas_fiscais' => $this->importNotasFiscais($businessId, $dryRun),
            'errors' => [],
        ];
        $report['errors'] = $this->errors;

        Log::info('officeimpresso.firebird_importer.full_import', [
            'business_id' => $businessId,
            'report' => $report,
        ]);

        return $report;
    }

    /**
     * Importa CLIENTES → App\Contact (type=customer).
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importClientes(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT ID, NOME, CNPJ_CPF, EMAIL, TELEFONE FROM CLIENTES',
            $this->mockClientes(),
        );

        return $this->importPorLegacyId('contacts', 'fb_clientes', $businessId, $dryRun, $rows,
            fn (array $row, int $legacyId) => $this->insertContact($businessId, $row, $legacyId));
    }

    /**
     * Importa PRODUTOS → App\Product.
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importProdutos(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT ID, NOME, PRECO_VENDA, ESTOQUE_ATUAL FROM PRODUTOS',
            $this->mockProdutos(),
        );

        return $this->importPorLegacyId('products', 'fb_produtos', $businessId, $dryRun, $rows,
            fn (array $row, int $legacyId) => $this->insertProduct($businessId, $row, $legacyId));
    }

    /**
     * Importa VENDAS → App\Transaction (type=sell).
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importVendas(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT ID, CLIENTE_ID, DATA_VENDA, VALOR_TOTAL, STATUS FROM VENDAS',
            $this->mockVendas(),
        );

        return $this->importPorLegacyId('transactions', 'fb_vendas', $businessId, $dryRun, $rows,
            fn (array $row, int $legacyId) => $this->insertTransaction($businessId, $row, $legacyId));
    }

    /**
     * Importa LICENCA_COMPUTADOR → licenca_computador.
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importLicencas(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT ID, HD, PROCESSADOR, MEMORIA, VERSAO_EXE, BLOQUEADO FROM LICENCA_COMPUTADOR',
            $this->mockLicencas(),
        );

        return $this->importPorLegacyId('licenca_computador', 'fb_licenca', $businessId, $dryRun, $rows,
            fn (array $row, int $legacyId) => $this->insertLicenca($businessId, $row, $legacyId));
    }

    /**
     * [W29-1] Importa FINANCEIRO → fin_titulos.
     *
     * Mapeia o lançamento financeiro Delphi (a-receber + a-pagar na mesma tabela)
     * para o domínio fin_titulos do oimpresso, preservando rastreabilidade
     * via metadata.legacy.codigo + metadata.legacy.codpedido + metadata.legacy.codempresa.
     *
     * SQL canônico ([Geral/Contas/Financeiro.dfm:9661]):
     *   select F.*, P.FANTASIA, P.EMAIL
     *     from FINANCEIRO F
     *     left join PESSOAS P on (P.CODIGO = F.PESSOA_RESPONSAVEL_CODIGO)
     *
     * Filtro: só STATUS like 'ATIVO%' (não migra INATIVO/cancelados).
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importFinanceiros(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT F.CODIGO, F.CODPEDIDO, F.CODEMPRESA, F.RAZAOSOCIAL, F.DOCUMENTO, '
            . 'F.NOTAFISCAL, F.HISTORICO, F.EMISSAO, F.VENCTO, F.DATAPAGTO, '
            . 'F.VALOR, F.JUROS, F.DESCONTO, F.CODPLANOCONTAS, F.CODTIPOPAGTO, '
            . 'F.TIPOPAGTO, F.CODCONDICAOPAGTO, F.CONDICAOPAGTO, F.CONTATOS, '
            . 'F.PARCELA, F.CODUSUARIO, F.TIPO, F.STATUS, F.BOLETO_NOSSO_NR, '
            . 'F.CODCONTA, F.PESSOA_RESPONSAVEL_CODIGO, F.DT_COMPETENCIA, '
            . 'P.FANTASIA, P.EMAIL '
            . 'FROM FINANCEIRO F '
            . 'LEFT JOIN PESSOAS P ON P.CODIGO = F.PESSOA_RESPONSAVEL_CODIGO '
            . "WHERE F.STATUS LIKE 'ATIVO%'",
            $this->mockFinanceiros(),
        );

        $stats = $this->statsVazio(count($rows));

        foreach ($rows as $row) {
            $legacyCodigo = (int) ($row['CODIGO'] ?? 0);
            $legacyCodPedido = (string) ($row['CODPEDIDO'] ?? '');
            $legacyCodEmpresa = (string) ($row['CODEMPRESA'] ?? '');

            if ($legacyCodigo === 0 || $legacyCodPedido === '' || $legacyCodEmpresa === '') {
                $stats['skipped']++;
                continue;
            }

            try {
                if ($this->financeiroAlreadyImported($businessId, $legacyCodigo, $legacyCodPedido, $legacyCodEmpresa)) {
                    $stats['skipped']++;
                    continue;
                }

                if (! $dryRun) {
                    $this->insertFinanceiro($businessId, $row);
                }
                $stats['migrated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->registrarFalha('fin_titulos', $legacyCodigo, $e);
            }
        }

        return $stats;
    }

    /**
     * [W29-1] Importa NOTA_FISCAL → nfe_emissoes.
     *
     * Mapeia NFe Delphi (modelo 55) para domínio nfe_emissoes do oimpresso.
     * NFCe (modelo 65) detectada via NF_TIPO se presente. NF cancelada
     * (NF_DT_CANCELAMENTO not null) migra com status=cancelada.
     *
     * @return array{read: int, migrated: int, skipped: int}
     */
    public function importNotasFiscais(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

        $rows = $this->fetchRows(
            'SELECT CODIGO, CODEMPRESA, CODVENDA, NF_DT_EMISSAO, '
            . 'NF_NATUREZA_OPERACAO, NF_NUMERO, NF_CHAVE, NF_PROTOCOLO, '
            . 'NF_AMBIENTE, NF_PROTOCOLO_CANCELAMENTO, NF_DT_CANCELAMENTO, '
            . 'NF_CSTAT, TIPO, NF_RAZAOSOCIAL, NF_SITUACAO, NF_TIPO '
            . 'FROM NOTA_FISCAL',
            $this->mockNotasFiscais(),
        );

        $stats = $this->statsVazio(count($rows));

        foreach ($rows as $row) {
            $legacyCodigo = (int) ($row['CODIGO'] ?? 0);
            $numero = (int) ($row['NF_NUMERO'] ?? 0);

            if ($legacyCodigo === 0 || $numero === 0) {
                $stats['skipped']++;
                continue;
            }

            $modelo = $this->mapNfeModelo($row);
            $serie = $this->mapNfeSerie($row);

            try {
                if ($this->notaFiscalAlreadyImported($businessId, $modelo, $serie, $numero)) {
                    $stats['skipped']++;
                    continue;
                }

                if (! $dryRun) {
                    $this->insertNotaFiscal($businessId, $row, $modelo, $serie);
                }
                $stats['migrated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->registrarFalha('nfe_emissoes', $legacyCodigo, $e);
            }
        }

        return $stats;
    }

    // ===== Helpers =====

    /**
     * Busca rows do Firebird OU retorna mocks (CI sem ext pdo_firebird).
     *
     * @param  array<int, array<string, mixed>>  $mockRows
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $sql, array $mockRows): array
    {
        if ($this->connector->isMock()) {
            return $mockRows;
        }

        return $this->connector->selectAll($sql);
    }

    /**
     * @return array{read: int, migrated: int, skipped: int, failed: int, blocked: int}
     */
    private function statsVazio(int $read): array
    {
        return ['read' => $read, 'migrated' => 0, 'skipped' => 0, 'failed' => 0, 'blocked' => 0];
    }

    /**
     * Laço das 4 tabelas cuja idempotência depende de `legacy_id` na própria tabela.
     *
     * Tabela sem a coluna → a linha conta como `blocked` e NÃO é gravada: sem chave,
     * um segundo run duplicaria tudo. Erro na checagem ou no insert → `failed`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>, int): void  $insert
     * @return array{read: int, migrated: int, skipped: int, failed: int, blocked: int}
     */
    private function importPorLegacyId(string $table, string $source, int $businessId, bool $dryRun, array $rows, callable $insert): array
    {
        $stats = $this->statsVazio(count($rows));
        $temChave = $this->temColunaLegacyId($table);

        foreach ($rows as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            if ($legacyId === 0) {
                $stats['skipped']++;
                continue;
            }

            if (! $temChave) {
                $stats['blocked']++;
                continue;
            }

            try {
                if ($this->alreadyImported($table, $businessId, $source, $legacyId)) {
                    $stats['skipped']++;
                    continue;
                }

                if (! $dryRun) {
                    $insert($row, $legacyId);
                }
                $stats['migrated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->registrarFalha($table, $legacyId, $e);
            }
        }

        if ($stats['blocked'] > 0) {
            $this->errors[] = [
                'tabela' => $table,
                'legacy_id' => null,
                'erro' => 'sem_coluna_legacy_id',
                'linhas' => $stats['blocked'],
            ];
        }

        return $stats;
    }

    private function temColunaLegacyId(string $table): bool
    {
        if (! array_key_exists($table, $this->temLegacyId)) {
            $this->temLegacyId[$table] = $this->canQueryDb() && Schema::hasColumn($table, 'legacy_id');
        }

        return $this->temLegacyId[$table];
    }

    /**
     * Valor gravado em `<tabela>.legacy_id` e usado na checagem de "já importado".
     * Comparação por igualdade exata: `contacts.legacy_id` também guarda CNPJ do
     * importer Python, e JSON_EXTRACT sobre esse valor dá erro de JSON inválido.
     */
    private function chaveLegacy(string $source, int $legacyId): string
    {
        return (string) json_encode([$source => $legacyId]);
    }

    /**
     * Registra a falha de UMA linha no relatório e no log, sem valores da linha.
     *
     * A mensagem da exceção NÃO vai pro log: a do driver MySQL traz o SQL com os
     * valores ligados (CPF/CNPJ, e-mail, telefone do cliente). Fica só o código.
     */
    private function registrarFalha(string $table, int $legacyId, \Throwable $e): void
    {
        $erro = $this->codigoErro($e);
        $this->errors[] = ['tabela' => $table, 'legacy_id' => $legacyId, 'erro' => $erro, 'linhas' => 1];

        Log::warning('officeimpresso.import.linha_falhou', [
            'tabela' => $table,
            'legacy_id' => $legacyId,
            'erro' => $erro,
        ]);
    }

    /**
     * Código do erro sem a mensagem (a mensagem do driver traz o SQL com os valores).
     */
    private function codigoErro(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $driver = $e->errorInfo[1] ?? null;

            return 'sql:' . ($driver !== null ? (string) $driver : (string) $e->getCode());
        }

        return class_basename($e);
    }

    /**
     * Idempotência: checa se a linha Firebird já foi importada (igualdade exata em legacy_id).
     *
     * Erro aqui PROPAGA: quem chama conta a linha como `failed`. Antes o erro virava
     * `false` ("não importado") e a linha seguia para o insert como se a checagem tivesse
     * respondido.
     */
    private function alreadyImported(string $table, int $businessId, string $source, int $legacyId): bool
    {
        $this->exigirDb();

        return DB::table($table)
            ->where('business_id', $businessId)
            ->where('legacy_id', $this->chaveLegacy($source, $legacyId))
            ->exists();
    }

    private function exigirDb(): void
    {
        if (! $this->canQueryDb()) {
            throw new \RuntimeException('Banco indisponível — importação não pode verificar nem gravar.');
        }
    }

    private function insertContact(int $businessId, array $row, int $legacyId): void
    {
        $this->exigirDb();

        DB::table('contacts')->insert([
            'business_id' => $businessId,
            'type' => 'customer',
            'name' => (string) ($row['NOME'] ?? 'Cliente legacy'),
            'tax_number' => (string) ($row['CNPJ_CPF'] ?? ''),
            'email' => (string) ($row['EMAIL'] ?? ''),
            'mobile' => (string) ($row['TELEFONE'] ?? ''),
            'contact_status' => 'active',
            'legacy_id' => $this->chaveLegacy('fb_clientes', $legacyId),
            'created_by' => $this->resolveCreatedBy($businessId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(int $businessId, array $row, int $legacyId): void
    {
        $this->exigirDb();

        DB::table('products')->insert([
            'business_id' => $businessId,
            'name' => (string) ($row['NOME'] ?? 'Produto legacy'),
            'type' => 'single',
            'unit_id' => 1,
            'sku' => 'FB-' . $legacyId,
            'legacy_id' => $this->chaveLegacy('fb_produtos', $legacyId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTransaction(int $businessId, array $row, int $legacyId): void
    {
        $this->exigirDb();

        DB::table('transactions')->insert([
            'business_id' => $businessId,
            'type' => 'sell',
            'status' => 'final',
            'final_total' => (float) ($row['VALOR_TOTAL'] ?? 0),
            'transaction_date' => $row['DATA_VENDA'] ?? now(),
            'legacy_id' => $this->chaveLegacy('fb_vendas', $legacyId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLicenca(int $businessId, array $row, int $legacyId): void
    {
        $this->exigirDb();

        DB::table('licenca_computador')->insert([
            'business_id' => $businessId,
            'hd' => (string) ($row['HD'] ?? ''),
            'processador' => (string) ($row['PROCESSADOR'] ?? ''),
            'memoria' => (string) ($row['MEMORIA'] ?? ''),
            'versao_exe' => (string) ($row['VERSAO_EXE'] ?? ''),
            'bloqueado' => (bool) ((int) ($row['BLOQUEADO'] ?? 0)),
            'legacy_id' => $this->chaveLegacy('fb_licenca', $legacyId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Tier 0 multi-tenant guard ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
     */
    private function assertBusinessId(int $businessId): void
    {
        if ($businessId <= 0) {
            throw new \InvalidArgumentException(
                'business_id obrigatório > 0 (Tier 0 ADR 0093 — jobs sem session()).'
            );
        }
    }

    /**
     * Detecta se Laravel DB está bootado (CI sem app() pode rodar service standalone).
     */
    private function canQueryDb(): bool
    {
        try {
            return function_exists('app') && app()->bound('db');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ===== [W29-1] Helpers Financeiro =====

    private function financeiroAlreadyImported(int $businessId, int $legacyCodigo, string $legacyCodPedido, string $legacyCodEmpresa): bool
    {
        $this->exigirDb();

        return DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codigo') = ?", [$legacyCodigo])
            ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codpedido') = ?", [$legacyCodPedido])
            ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codempresa') = ?", [$legacyCodEmpresa])
            ->exists();
    }

    private function insertFinanceiro(int $businessId, array $row): void
    {
        $this->exigirDb();

        $tipo = $this->mapFinTipo((string) ($row['TIPO'] ?? ''));
        $status = $this->mapFinStatus((string) ($row['TIPO'] ?? ''));
        $parcela = (int) ($row['PARCELA'] ?? 1);
        $valor = (float) ($row['VALOR'] ?? 0);
        $emissao = $this->dateOnly($row['EMISSAO'] ?? null) ?: now()->format('Y-m-d');
        $vencto = $this->dateOnly($row['VENCTO'] ?? null) ?: $emissao;
        $competenciaFonte = $row['DT_COMPETENCIA'] ?? $row['EMISSAO'] ?? null;
        $competencia = substr($this->dateOnly($competenciaFonte) ?: $emissao, 0, 7);

        $clienteId = $this->lookupContactByLegacy($businessId, (string) ($row['PESSOA_RESPONSAVEL_CODIGO'] ?? ''));
        $createdBy = $this->resolveCreatedBy($businessId);

        $metadata = [
            'legacy' => [
                'source' => 'wr-comercial-delphi',
                'codigo' => (int) $row['CODIGO'],
                'codpedido' => (string) $row['CODPEDIDO'],
                'codempresa' => (string) $row['CODEMPRESA'],
                'codconta' => (int) ($row['CODCONTA'] ?? 0),
                'codusuario' => (int) ($row['CODUSUARIO'] ?? 0),
                'codplanocontas' => (string) ($row['CODPLANOCONTAS'] ?? ''),
            ],
            'tipopagto' => (string) ($row['TIPOPAGTO'] ?? ''),
            'condicaopagto' => (string) ($row['CONDICAOPAGTO'] ?? ''),
            'boleto_nosso_nr' => (string) ($row['BOLETO_NOSSO_NR'] ?? ''),
            'notafiscal' => (string) ($row['NOTAFISCAL'] ?? ''),
        ];

        $historico = trim(sprintf('%s %s',
            (string) ($row['HISTORICO'] ?? ''),
            (string) ($row['CONTATOS'] ?? '') ? 'Contato: ' . (string) ($row['CONTATOS']) : ''
        ));

        DB::table('fin_titulos')->insert([
            'business_id' => $businessId,
            'numero' => (string) ($row['DOCUMENTO'] ?? $row['CODIGO']),
            'tipo' => $tipo,
            'status' => $status,
            'cliente_id' => $clienteId,
            'cliente_descricao' => (string) ($row['RAZAOSOCIAL'] ?? $row['FANTASIA'] ?? ''),
            'valor_total' => $valor,
            'valor_aberto' => $valor,
            'moeda' => 'BRL',
            'emissao' => $emissao,
            'vencimento' => $vencto,
            'competencia_mes' => $competencia,
            'origem' => $row['CODPEDIDO'] ? 'venda' : 'manual',
            'origem_id' => null, // soft FK; lookup futuro em transactions.legacy_id se necessário
            'parcela_numero' => $parcela > 0 ? $parcela : null,
            'parcela_total' => null,
            'observacoes' => $historico !== '' ? $historico : null,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mapFinTipo(string $delphiTipo): string
    {
        $t = strtoupper(trim($delphiTipo));
        return in_array($t, ['A PAGAR', 'PAGA'], true) ? 'pagar' : 'receber';
    }

    private function mapFinStatus(string $delphiTipo): string
    {
        $t = strtoupper(trim($delphiTipo));
        return in_array($t, ['PAGA', 'RECEBIDA'], true) ? 'quitado' : 'aberto';
    }

    private function lookupContactByLegacy(int $businessId, string $legacyCode): ?int
    {
        if ($legacyCode === '' || ! $this->canQueryDb()) {
            return null;
        }
        try {
            $id = DB::table('contacts')
                ->where('business_id', $businessId)
                ->where('legacy_id', $this->chaveLegacy('fb_clientes', (int) $legacyCode))
                ->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Primeiro usuário da empresa destino. Sem usuário na empresa a linha FALHA:
     * o fallback antigo era o user 1, de outra empresa (Tier 0, ADR 0093).
     */
    private function resolveCreatedBy(int $businessId): int
    {
        $this->exigirDb();

        $id = DB::table('users')
            ->where('business_id', $businessId)
            ->orderBy('id', 'asc')
            ->value('id');

        if (! $id) {
            throw new \RuntimeException('Empresa destino sem usuário para created_by.');
        }

        return (int) $id;
    }

    private function dateOnly($val): ?string
    {
        if (empty($val)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse((string) $val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ===== [W29-1] Helpers NotaFiscal =====

    private function notaFiscalAlreadyImported(int $businessId, string $modelo, string $serie, int $numero): bool
    {
        $this->exigirDb();

        return DB::table('nfe_emissoes')
            ->where('business_id', $businessId)
            ->where('modelo', $modelo)
            ->where('serie', $serie)
            ->where('numero', $numero)
            ->exists();
    }

    private function insertNotaFiscal(int $businessId, array $row, string $modelo, string $serie): void
    {
        $this->exigirDb();

        $status = $this->mapNfeStatus($row);
        $emitidoEm = $this->dateTime($row['NF_DT_EMISSAO'] ?? null);
        $valorTotal = (float) ($row['VALOR_TOTAL'] ?? 0); // pode vir 0 se NOTA_FISCAL não tem coluna de total — historicamente vem de VENDA join

        $metadata = [
            'legacy' => [
                'source' => 'wr-comercial-delphi',
                'codigo' => (int) $row['CODIGO'],
                'codvenda' => (string) ($row['CODVENDA'] ?? ''),
                'codempresa' => (int) ($row['CODEMPRESA'] ?? 0),
            ],
            'natureza_operacao' => (string) ($row['NF_NATUREZA_OPERACAO'] ?? ''),
            'razao_social' => (string) ($row['NF_RAZAOSOCIAL'] ?? ''),
            'protocolo' => (string) ($row['NF_PROTOCOLO'] ?? ''),
            'protocolo_cancelamento' => (string) ($row['NF_PROTOCOLO_CANCELAMENTO'] ?? ''),
            'dt_cancelamento' => (string) ($row['NF_DT_CANCELAMENTO'] ?? ''),
            'ambiente' => (int) ($row['NF_AMBIENTE'] ?? 1),
            'situacao_delphi' => (string) ($row['NF_SITUACAO'] ?? ''),
        ];

        DB::table('nfe_emissoes')->insert([
            'business_id' => $businessId,
            'transaction_id' => null, // soft FK; lookup futuro em transactions.legacy_id
            'modelo' => $modelo,
            'serie' => $serie,
            'numero' => (int) ($row['NF_NUMERO'] ?? 0),
            'chave_44' => (string) ($row['NF_CHAVE'] ?? '') ?: null,
            'status' => $status,
            'cstat' => (string) ($row['NF_CSTAT'] ?? '') ?: null,
            'motivo' => null, // NF_MOTIVO_STATUS é BLOB — não trazido no SELECT pra evitar overhead
            'valor_total' => $valorTotal,
            'emitido_em' => $emitidoEm,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mapNfeModelo(array $row): string
    {
        $tipoTexto = strtoupper((string) ($row['TIPO'] ?? ''));
        if (str_contains($tipoTexto, 'NFCE') || str_contains($tipoTexto, 'NFC-E') || str_contains($tipoTexto, 'CONSUMIDOR')) {
            return '65';
        }
        // NF_TIPO numérico no Delphi: 0=entrada, 1=saída; modelo SEFAZ é separado
        return '55';
    }

    private function mapNfeSerie(array $row): string
    {
        // Delphi NOTA_FISCAL não tem coluna SERIE explícita historicamente — série geralmente '1' default
        // Caso a base tenha NF_SERIE (versões mais novas), usar via fallback no SELECT
        return (string) ($row['NF_SERIE'] ?? '1');
    }

    private function mapNfeStatus(array $row): string
    {
        // Prioridade: cancelamento > cstat > situação textual
        if (! empty($row['NF_DT_CANCELAMENTO'])) {
            return 'cancelada';
        }
        $cstat = (int) ($row['NF_CSTAT'] ?? 0);
        if ($cstat === 100) {
            return 'autorizada';
        }
        if (in_array($cstat, [101, 151, 155], true)) {
            return 'cancelada';
        }
        if (in_array($cstat, [110, 301, 302], true)) {
            return 'denegada';
        }
        $situacao = strtolower(trim((string) ($row['NF_SITUACAO'] ?? '')));
        if ($situacao === 'autorizada' || str_contains($situacao, 'autoriz')) {
            return 'autorizada';
        }
        if (str_contains($situacao, 'cancel')) {
            return 'cancelada';
        }
        if (str_contains($situacao, 'denegad')) {
            return 'denegada';
        }
        if (str_contains($situacao, 'rejei')) {
            return 'rejeitada';
        }
        return 'pendente';
    }

    private function dateTime($val): ?string
    {
        if (empty($val)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse((string) $val)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ===== Mocks (CI Pest sem ext pdo_firebird) =====

    /** @return array<int, array<string, mixed>> */
    private function mockClientes(): array
    {
        return [
            ['ID' => 1, 'NOME' => 'João Silva Brasão', 'CNPJ_CPF' => '12345678901', 'EMAIL' => 'joao@example.com', 'TELEFONE' => '4899999999'],
            ['ID' => 2, 'NOME' => 'Maria Oliveira', 'CNPJ_CPF' => '98765432100', 'EMAIL' => 'maria@example.com', 'TELEFONE' => '4888888888'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function mockProdutos(): array
    {
        return [
            ['ID' => 1, 'NOME' => 'Banner PVC 1m²', 'PRECO_VENDA' => 45.00, 'ESTOQUE_ATUAL' => 0],
            ['ID' => 2, 'NOME' => 'Adesivo recorte', 'PRECO_VENDA' => 25.00, 'ESTOQUE_ATUAL' => 0],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function mockVendas(): array
    {
        return [
            ['ID' => 1, 'CLIENTE_ID' => 1, 'DATA_VENDA' => '2024-01-15 10:30:00', 'VALOR_TOTAL' => 150.00, 'STATUS' => 'F'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function mockLicencas(): array
    {
        return [
            ['ID' => 1, 'HD' => 'WD-WCC4N1234567', 'PROCESSADOR' => 'Intel i5', 'MEMORIA' => '8GB', 'VERSAO_EXE' => '6.7.4', 'BLOQUEADO' => 0],
        ];
    }

    /** [W29-1] @return array<int, array<string, mixed>> */
    private function mockFinanceiros(): array
    {
        return [
            [
                'CODIGO' => 99981,
                'CODPEDIDO' => '48196-1',
                'CODEMPRESA' => '1',
                'RAZAOSOCIAL' => 'TRANSPORTADORA ARACON LTDA',
                'DOCUMENTO' => '48196 1/1',
                'NOTAFISCAL' => '',
                'HISTORICO' => 'Mensalidade contrato 48196',
                'EMISSAO' => '2026-05-05 11:47:07',
                'VENCTO' => '2026-06-02 11:46:54',
                'DATAPAGTO' => null,
                'VALOR' => 350.00,
                'JUROS' => 0,
                'DESCONTO' => 0,
                'CODPLANOCONTAS' => '1.1.01',
                'CODTIPOPAGTO' => 35,
                'TIPOPAGTO' => 'BOLETO',
                'CODCONDICAOPAGTO' => 35,
                'CONDICAOPAGTO' => 'BOLETO',
                'CONTATOS' => 'ADEMAR',
                'PARCELA' => 1,
                'CODUSUARIO' => 13,
                'TIPO' => 'A RECEBER',
                'STATUS' => 'ATIVO',
                'BOLETO_NOSSO_NR' => '',
                'CODCONTA' => 3,
                'PESSOA_RESPONSAVEL_CODIGO' => '595',
                'DT_COMPETENCIA' => '2026-02-25',
                'FANTASIA' => 'ARACON',
                'EMAIL' => '',
            ],
            [
                'CODIGO' => 99980,
                'CODPEDIDO' => '48197-1',
                'CODEMPRESA' => '1',
                'RAZAOSOCIAL' => 'TRANSPORTADORA ARACON LTDA',
                'DOCUMENTO' => '48197 1/1',
                'NOTAFISCAL' => '',
                'HISTORICO' => 'Mensalidade contrato 48197',
                'EMISSAO' => '2026-05-05 11:45:35',
                'VENCTO' => '2026-06-02 11:45:35',
                'DATAPAGTO' => null,
                'VALOR' => 350.00,
                'JUROS' => 0,
                'DESCONTO' => 0,
                'CODPLANOCONTAS' => '1.1.01',
                'CODTIPOPAGTO' => 35,
                'TIPOPAGTO' => 'BOLETO',
                'CODCONDICAOPAGTO' => 35,
                'CONDICAOPAGTO' => 'BOLETO',
                'CONTATOS' => 'ADEMAR',
                'PARCELA' => 1,
                'CODUSUARIO' => 13,
                'TIPO' => 'A RECEBER',
                'STATUS' => 'ATIVO',
                'BOLETO_NOSSO_NR' => '',
                'CODCONTA' => 3,
                'PESSOA_RESPONSAVEL_CODIGO' => '595',
                'DT_COMPETENCIA' => '2026-02-25',
                'FANTASIA' => 'ARACON',
                'EMAIL' => '',
            ],
        ];
    }

    /** [W29-1] @return array<int, array<string, mixed>> */
    private function mockNotasFiscais(): array
    {
        return [
            [
                'CODIGO' => 24847,
                'CODEMPRESA' => 1,
                'CODVENDA' => '49364-1',
                'NF_DT_EMISSAO' => '2026-05-07 09:00:00',
                'NF_NATUREZA_OPERACAO' => 'Venda',
                'NF_NUMERO' => 24847,
                'NF_CHAVE' => '42260507000000000000550010000248471000000001',
                'NF_PROTOCOLO' => '142260000000001',
                'NF_AMBIENTE' => 1,
                'NF_CSTAT' => 100,
                'TIPO' => 'NFe',
                'NF_RAZAOSOCIAL' => 'KAYLANE CRISTINE DA CRUZ DE JESUS',
                'NF_SITUACAO' => 'Autorizada',
                'NF_TIPO' => 1,
                'VALOR_TOTAL' => 17000.00,
            ],
            [
                'CODIGO' => 24846,
                'CODEMPRESA' => 1,
                'CODVENDA' => '49366-1',
                'NF_DT_EMISSAO' => '2026-05-07 09:10:00',
                'NF_NATUREZA_OPERACAO' => 'Venda',
                'NF_NUMERO' => 24846,
                'NF_CHAVE' => '42260507000000000000550010000248461000000002',
                'NF_PROTOCOLO' => '142260000000002',
                'NF_AMBIENTE' => 1,
                'NF_CSTAT' => 100,
                'TIPO' => 'NFe',
                'NF_RAZAOSOCIAL' => 'TORK COMERCIO DE PECAS AUTO LTDA -ME',
                'NF_SITUACAO' => 'Autorizada',
                'NF_TIPO' => 1,
                'VALOR_TOTAL' => 60000.00,
            ],
        ];
    }
}
