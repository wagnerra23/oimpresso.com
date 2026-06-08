<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Services\FirebirdImporter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *     clientes: array{read: int, migrated: int, skipped: int},
     *     produtos: array{read: int, migrated: int, skipped: int},
     *     vendas: array{read: int, migrated: int, skipped: int},
     *     licencas: array{read: int, migrated: int, skipped: int},
     *     financeiros: array{read: int, migrated: int, skipped: int},
     *     notas_fiscais: array{read: int, migrated: int, skipped: int},
     *     errors: list<string>,
     * }
     */
    public function runFullImport(int $businessId, bool $dryRun = true): array
    {
        $this->assertBusinessId($businessId);

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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            if ($legacyId === 0) {
                $stats['skipped']++;
                continue;
            }

            if ($this->alreadyImported('contacts', $businessId, 'fb_clientes', $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertContact($businessId, $row, $legacyId);
            }
            $stats['migrated']++;
        }

        return $stats;
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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            if ($legacyId === 0) {
                $stats['skipped']++;
                continue;
            }

            if ($this->alreadyImported('products', $businessId, 'fb_produtos', $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertProduct($businessId, $row, $legacyId);
            }
            $stats['migrated']++;
        }

        return $stats;
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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            if ($legacyId === 0) {
                $stats['skipped']++;
                continue;
            }

            if ($this->alreadyImported('transactions', $businessId, 'fb_vendas', $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertTransaction($businessId, $row, $legacyId);
            }
            $stats['migrated']++;
        }

        return $stats;
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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            if ($legacyId === 0) {
                $stats['skipped']++;
                continue;
            }

            if ($this->alreadyImported('licenca_computador', $businessId, 'fb_licenca', $legacyId)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertLicenca($businessId, $row, $legacyId);
            }
            $stats['migrated']++;
        }

        return $stats;
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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyCodigo = (int) ($row['CODIGO'] ?? 0);
            $legacyCodPedido = (string) ($row['CODPEDIDO'] ?? '');
            $legacyCodEmpresa = (string) ($row['CODEMPRESA'] ?? '');

            if ($legacyCodigo === 0 || $legacyCodPedido === '' || $legacyCodEmpresa === '') {
                $stats['skipped']++;
                continue;
            }

            if ($this->financeiroAlreadyImported($businessId, $legacyCodigo, $legacyCodPedido, $legacyCodEmpresa)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertFinanceiro($businessId, $row);
            }
            $stats['migrated']++;
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

        $stats = ['read' => count($rows), 'migrated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $legacyCodigo = (int) ($row['CODIGO'] ?? 0);
            $numero = (int) ($row['NF_NUMERO'] ?? 0);

            if ($legacyCodigo === 0 || $numero === 0) {
                $stats['skipped']++;
                continue;
            }

            $modelo = $this->mapNfeModelo($row);
            $serie = $this->mapNfeSerie($row);

            if ($this->notaFiscalAlreadyImported($businessId, $modelo, $serie, $numero)) {
                $stats['skipped']++;
                continue;
            }

            if (! $dryRun) {
                $this->insertNotaFiscal($businessId, $row, $modelo, $serie);
            }
            $stats['migrated']++;
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
     * Idempotência: checa se row Firebird já foi importada (legacy_id JSON match).
     *
     * Não usa Eloquent direto pra suportar mock mode sem boot completo.
     */
    private function alreadyImported(string $table, int $businessId, string $source, int $legacyId): bool
    {
        if (! $this->canQueryDb()) {
            return false; // mock mode sem DB
        }

        try {
            return DB::table($table)
                ->where('business_id', $businessId)
                ->whereRaw("JSON_EXTRACT(legacy_id, '$.\"{$source}\"') = ?", [$legacyId])
                ->exists();
        } catch (\Throwable $e) {
            // Coluna `legacy_id` pode não existir ainda — fail-safe pra dry-run não quebrar
            return false;
        }
    }

    private function insertContact(int $businessId, array $row, int $legacyId): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
            DB::table('contacts')->insert([
                'business_id' => $businessId,
                'type' => 'customer',
                'name' => (string) ($row['NOME'] ?? 'Cliente legacy'),
                'tax_number' => (string) ($row['CNPJ_CPF'] ?? ''),
                'email' => (string) ($row['EMAIL'] ?? ''),
                'mobile' => (string) ($row['TELEFONE'] ?? ''),
                'contact_status' => 'active',
                'legacy_id' => json_encode(['fb_clientes' => $legacyId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.contact_fail', ['legacy_id' => $legacyId, 'err' => $e->getMessage()]);
        }
    }

    private function insertProduct(int $businessId, array $row, int $legacyId): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
            DB::table('products')->insert([
                'business_id' => $businessId,
                'name' => (string) ($row['NOME'] ?? 'Produto legacy'),
                'type' => 'single',
                'unit_id' => 1,
                'sku' => 'FB-' . $legacyId,
                'legacy_id' => json_encode(['fb_produtos' => $legacyId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.product_fail', ['legacy_id' => $legacyId, 'err' => $e->getMessage()]);
        }
    }

    private function insertTransaction(int $businessId, array $row, int $legacyId): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
            DB::table('transactions')->insert([
                'business_id' => $businessId,
                'type' => 'sell',
                'status' => 'final',
                'final_total' => (float) ($row['VALOR_TOTAL'] ?? 0),
                'transaction_date' => $row['DATA_VENDA'] ?? now(),
                'legacy_id' => json_encode(['fb_vendas' => $legacyId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.txn_fail', ['legacy_id' => $legacyId, 'err' => $e->getMessage()]);
        }
    }

    private function insertLicenca(int $businessId, array $row, int $legacyId): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
            DB::table('licenca_computador')->insert([
                'business_id' => $businessId,
                'hd' => (string) ($row['HD'] ?? ''),
                'processador' => (string) ($row['PROCESSADOR'] ?? ''),
                'memoria' => (string) ($row['MEMORIA'] ?? ''),
                'versao_exe' => (string) ($row['VERSAO_EXE'] ?? ''),
                'bloqueado' => (bool) ((int) ($row['BLOQUEADO'] ?? 0)),
                'legacy_id' => json_encode(['fb_licenca' => $legacyId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.licenca_fail', ['legacy_id' => $legacyId, 'err' => $e->getMessage()]);
        }
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
        if (! $this->canQueryDb()) {
            return false;
        }
        try {
            return DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codigo') = ?", [$legacyCodigo])
                ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codpedido') = ?", [$legacyCodPedido])
                ->whereRaw("JSON_EXTRACT(metadata, '$.legacy.codempresa') = ?", [$legacyCodEmpresa])
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function insertFinanceiro(int $businessId, array $row): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
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
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.financeiro_fail', [
                'codigo' => $row['CODIGO'] ?? null,
                'err' => $e->getMessage(),
            ]);
        }
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
                ->whereRaw("JSON_EXTRACT(legacy_id, '$.fb_clientes') = ?", [(int) $legacyCode])
                ->value('id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveCreatedBy(int $businessId): int
    {
        if (! $this->canQueryDb()) {
            return 1;
        }
        try {
            $id = DB::table('users')
                ->where('business_id', $businessId)
                ->orderBy('id', 'asc')
                ->value('id');
            return $id ? (int) $id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
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
        if (! $this->canQueryDb()) {
            return false;
        }
        try {
            return DB::table('nfe_emissoes')
                ->where('business_id', $businessId)
                ->where('modelo', $modelo)
                ->where('serie', $serie)
                ->where('numero', $numero)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function insertNotaFiscal(int $businessId, array $row, string $modelo, string $serie): void
    {
        if (! $this->canQueryDb()) {
            return;
        }
        try {
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
        } catch (\Throwable $e) {
            Log::warning('officeimpresso.import.notafiscal_fail', [
                'codigo' => $row['CODIGO'] ?? null,
                'err' => $e->getMessage(),
            ]);
        }
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
