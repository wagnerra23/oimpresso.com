<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\ServiceOrderItemService;
use Throwable;

/**
 * W27 G4 — Importer ESQUELETO Firebird Martinho Caçambas → oimpresso OficinaAuto.
 *
 * Padrão validado em `customer-memory:enrich-firebird` (Modules/Whatsapp):
 * Firebird (Windows-only via DBeaver/python firebird-driver) NÃO é acessado direto
 * pelo Laravel. Wagner exporta JSON localmente, sobe pro Hostinger, e este comando consome.
 *
 * Fluxo previsto:
 *   1) Wagner local (Windows + Firebird driver):
 *      python scripts/firebird/export-martinho-os.py \
 *          --output storage/app/firebird/martinho-os-YYYY-MM-DD.json
 *      (script lê ORDEM_SERVICO + ORDEM_ITENS, dumpa JSON normalizado)
 *
 *   2) Upload pro Hostinger (git/scp).
 *
 *   3) php artisan oficina:import-firebird-martinho \
 *          --business=1 \
 *          --json=storage/app/firebird/martinho-os-2026-05-17.json \
 *          --dry-run
 *
 *   4) Wagner aprova diff → roda sem --dry-run.
 *
 * Idempotência: legacy_id (CODIGO Firebird ORDEM_ID) salvo em ServiceOrder.notes
 * (prefix "FB_LEGACY_ID:N") + Vehicle.legacy_id pra evitar duplicação em rerun.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): --business obrigatório. Importer é ONE-WAY
 * (Firebird → oimpresso, NUNCA reverso — Firebird legacy congelado).
 *
 * Status: ESQUELETO W27 — script python de export + mapping fino entram em W28.
 *
 * @see Modules/Whatsapp/Console/Commands/CustomerMemoryEnrichFirebirdCommand.php  (pattern)
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G4
 */
class ImportFirebirdMartinhoCommand extends Command
{
    protected $signature = 'oficina:import-firebird-martinho
                            {--business= : business_id obrigatório (Tier 0 ADR 0093)}
                            {--json= : path do JSON exportado Firebird (default: storage/app/firebird/martinho-latest.json)}
                            {--limit=0 : máximo OS importadas (0=ilimitado)}
                            {--dry-run : simula sem modificar DB}
                            {--detail : log linha-a-linha por OS}';

    protected $description = 'Importa OS legacy Firebird Martinho → ServiceOrder + ServiceOrderItem (W27 G4 esqueleto).';

    private const FIREBIRD_LEGACY_PREFIX = 'FB_LEGACY_ID:';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N obrigatório (Tier 0 ADR 0093).');
            return Command::INVALID;
        }

        $jsonPath = (string) ($this->option('json')
            ?: storage_path('app/firebird/martinho-latest.json'));

        if (! file_exists($jsonPath)) {
            $this->error("JSON não encontrado: {$jsonPath}");
            $this->warn('Rode primeiro o export Python local (Windows + firebird-driver):');
            $this->line('  python scripts/firebird/export-martinho-os.py --output ' . $jsonPath);
            return Command::FAILURE;
        }

        if (! Schema::hasTable('oficina_service_order_items')) {
            $this->error('Tabela oficina_service_order_items ausente. Rode migration primeiro.');
            return Command::FAILURE;
        }

        $payload = $this->loadJson($jsonPath);
        if ($payload === null) {
            return Command::FAILURE;
        }

        $ordens = (array) ($payload['ordens'] ?? []);
        if (empty($ordens)) {
            $this->warn('JSON sem chave "ordens" ou array vazio. Nada a importar.');
            return Command::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $ordens = array_slice($ordens, 0, $limit);
        }

        $dryRun = (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');

        $this->info('Source JSON: ' . $jsonPath);
        $this->info('Business: ' . $businessId);
        $this->info('Dry-run: ' . ($dryRun ? 'SIM (zero modificação DB)' : 'NÃO (commit real)'));
        $this->info('OS a processar: ' . count($ordens));

        $stats = [
            'processadas'    => 0,
            'criadas'        => 0,
            'puladas_existe' => 0,
            'itens_criados'  => 0,
            'erros'          => 0,
        ];

        $itemService = app(ServiceOrderItemService::class);

        foreach ($ordens as $ordem) {
            $stats['processadas']++;
            try {
                $resultado = $this->processaOrdem($ordem, $businessId, $itemService, $dryRun, $detail);
                $stats['criadas']        += $resultado['criada'] ? 1 : 0;
                $stats['puladas_existe'] += $resultado['pulada'] ? 1 : 0;
                $stats['itens_criados']  += $resultado['itens'];
            } catch (Throwable $e) {
                $stats['erros']++;
                $this->error(sprintf(
                    'ERRO ordem legacy_id=%s: %s',
                    $ordem['ordem_id'] ?? '?',
                    $e->getMessage()
                ));
            }
        }

        $this->table(
            ['processadas', 'criadas', 'puladas (já existem)', 'itens criados', 'erros'],
            [[
                $stats['processadas'],
                $stats['criadas'],
                $stats['puladas_existe'],
                $stats['itens_criados'],
                $stats['erros'],
            ]]
        );

        if ($dryRun) {
            $this->warn('DRY-RUN — nenhuma modificação aplicada ao DB. Rode sem --dry-run pra commit real.');
        }

        return $stats['erros'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array{ordens?: array<int, array<string, mixed>>}|null
     */
    private function loadJson(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error("Falha ao ler JSON: {$path}");
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->error('JSON inválido (não decodificou pra array).');
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $ordem
     * @return array{criada:bool, pulada:bool, itens:int}
     */
    private function processaOrdem(
        array $ordem,
        int $businessId,
        ServiceOrderItemService $itemService,
        bool $dryRun,
        bool $detail
    ): array {
        $legacyId = (string) ($ordem['ordem_id'] ?? '');
        if ($legacyId === '') {
            throw new \RuntimeException('ordem_id ausente no JSON');
        }

        // Idempotência: notes LIKE "FB_LEGACY_ID:N%"
        $existe = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: idempotência cross-tenant check
            ->where('business_id', $businessId)
            ->where('notes', 'like', self::FIREBIRD_LEGACY_PREFIX . $legacyId . '%')
            ->exists();

        if ($existe) {
            if ($detail) {
                $this->line("  ↺ pulada (já existe): legacy_id={$legacyId}");
            }
            return ['criada' => false, 'pulada' => true, 'itens' => 0];
        }

        $placa = (string) ($ordem['placa'] ?? '');
        if ($placa === '') {
            throw new \RuntimeException("placa ausente legacy_id={$legacyId}");
        }

        // Vehicle: localiza por placa+biz OU cria stub
        $vehicle = Vehicle::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('plate', $placa)
            ->first();

        if ($vehicle === null) {
            if ($dryRun) {
                if ($detail) {
                    $this->line("  + (dry-run) Vehicle stub: placa={$placa}");
                }
            } else {
                $vehicle = Vehicle::withoutGlobalScopes()->create([
                    'business_id'  => $businessId,
                    'plate'        => $placa,
                    'vehicle_type' => 'cacamba', // Martinho-specific
                    'legacy_id'    => (string) ($ordem['veiculo_id'] ?? null),
                ]);
            }
        }

        $itensInput = (array) ($ordem['itens'] ?? []);
        $itensCriados = 0;

        if ($dryRun) {
            if ($detail) {
                $this->line(sprintf(
                    '  + (dry-run) OS legacy_id=%s placa=%s itens=%d',
                    $legacyId,
                    $placa,
                    count($itensInput)
                ));
            }
            return ['criada' => true, 'pulada' => false, 'itens' => count($itensInput)];
        }

        // Commit real dentro de transaction (rollback em qualquer erro)
        DB::transaction(function () use (
            $ordem,
            $businessId,
            $vehicle,
            $legacyId,
            $itensInput,
            $itemService,
            &$itensCriados,
            $detail
        ) {
            $os = ServiceOrder::withoutGlobalScopes()->create([
                'business_id'        => $businessId,
                'vehicle_id'         => $vehicle->id,
                'order_type'         => (string) ($ordem['order_type'] ?? 'manutencao'),
                'status'             => (string) ($ordem['status'] ?? 'concluida'), // legacy = histórico
                'entered_at'         => $ordem['entered_at'] ?? null,
                'completed_at'       => $ordem['completed_at'] ?? null,
                'mileage_at_service' => $ordem['km'] ?? null,
                'notes'              => self::FIREBIRD_LEGACY_PREFIX . $legacyId
                                       . ' | ' . (string) ($ordem['notes'] ?? ''),
            ]);

            foreach ($itensInput as $itemData) {
                $itemService->addItem($businessId, $os->id, [
                    'tipo'           => (string) ($itemData['tipo'] ?? 'peca'),
                    'descricao'      => (string) ($itemData['descricao'] ?? 'Sem descrição'),
                    'quantidade'     => (float) ($itemData['quantidade'] ?? 1),
                    'valor_unitario' => (float) ($itemData['valor_unitario'] ?? 0),
                    'notes'          => isset($itemData['legacy_item_id'])
                        ? 'FB_LEGACY_ITEM_ID:' . $itemData['legacy_item_id']
                        : null,
                ]);
                $itensCriados++;
            }

            if ($detail) {
                $this->line(sprintf(
                    '  ✓ OS criada id=%d legacy_id=%s placa=%s itens=%d',
                    $os->id,
                    $legacyId,
                    $vehicle->plate,
                    $itensCriados
                ));
            }
        });

        return ['criada' => true, 'pulada' => false, 'itens' => $itensCriados];
    }
}
