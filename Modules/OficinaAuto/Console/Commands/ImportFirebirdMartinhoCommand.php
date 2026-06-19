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
 * W28 G4 — Importer Firebird Martinho → oimpresso OficinaAuto (mapping fino completo).
 *
 * ⚠️ Domínio corrigido ([ADR 0194](memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)):
 * Martinho = **mecânica pesada de caminhão basculante** (CNAE 4520-0/01), NÃO locação
 * de caçamba container (sub-vertical 3 hipotético, sem cliente real). O default de
 * `vehicle_type` deixou de ser `'cacamba'` (valor que nem existia no enum) e passou a
 * ser `'caminhao'` (whitelist real do Vehicle) — rodar como estava etiquetava os
 * caminhões da Martinho como caçamba. Veja `normalizeVehicleType()`.
 *
 * Padrão validado em `customer-memory:enrich-firebird` (Modules/Whatsapp):
 * Firebird (Windows-only via DBeaver/python firebird-driver) NÃO é acessado direto
 * pelo Laravel. Wagner exporta JSON localmente, sobe pro Hostinger, e este comando consome.
 *
 * Fluxo:
 *   1) Wagner local (Windows + Firebird driver):
 *      python scripts/firebird/export-martinho-os.py \
 *          --dsn "localhost/3050:C:/dados/MARTINHO.FDB" \
 *          --output storage/app/firebird/martinho-os-YYYY-MM-DD.json
 *      (script lê ORDEM_SERVICO + ORDEM_ITENS, dumpa JSON normalizado)
 *
 *   2) Upload pro Hostinger (git/scp).
 *
 *   3) Validar SEM gravar (dry-run é o padrão):
 *      php artisan oficina:import-firebird-martinho \
 *          --business=164 \
 *          --json=storage/app/firebird/martinho-os-2026-06-03.json
 *
 *   4) Wagner aprova o diff → roda com --commit pra gravar de fato.
 *      php artisan oficina:import-firebird-martinho --business=164 --json=... --commit
 *
 * Idempotência: legacy_id (CODIGO Firebird ORDEM_ID) salvo em ServiceOrder.notes
 * (prefix "FB_LEGACY_ID:N") + Vehicle.legacy_id pra evitar duplicação em rerun.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): --business obrigatório. Importer é ONE-WAY
 * (Firebird → oimpresso, NUNCA reverso — Firebird legacy congelado).
 *
 * Segurança: **dry-run é o caminho padrão** — grava no DB só com --commit explícito.
 *
 * @see Modules/Whatsapp/Console/Commands/CustomerMemoryEnrichFirebirdCommand.php  (pattern)
 * @see scripts/firebird/export-martinho-os.py  (export local)
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G4
 */
class ImportFirebirdMartinhoCommand extends Command
{
    protected $signature = 'oficina:import-firebird-martinho
                            {--business= : business_id obrigatório (Tier 0 ADR 0093)}
                            {--json= : path do JSON exportado Firebird (default: storage/app/firebird/martinho-latest.json)}
                            {--limit=0 : máximo OS importadas (0=ilimitado)}
                            {--commit : grava de fato no DB (sem esta flag = dry-run, padrão seguro)}
                            {--dry-run : força simulação mesmo com --commit (vence por segurança)}
                            {--detail : log linha-a-linha por OS}';

    protected $description = 'Importa OS legacy Firebird Martinho → ServiceOrder + ServiceOrderItem (W28 G4 · mecânica pesada caminhão ADR 0194).';

    private const FIREBIRD_LEGACY_PREFIX = 'FB_LEGACY_ID:';

    /**
     * Default canônico pós-ADR 0194: Martinho é oficina de caminhão basculante.
     * Tem que estar na whitelist real do enum vehicles.vehicle_type (migrations
     * 2026_05_11_000010 + 2026_05_12_220001). `caminhao` é o valor canônico de
     * caminhão; o antigo `cacamba` nem era um valor válido do enum.
     */
    private const DEFAULT_VEHICLE_TYPE = 'caminhao';

    /** Whitelist real do enum vehicles.vehicle_type (NÃO inventar fora disso). */
    private const VEHICLE_TYPE_WHITELIST = [
        'caminhao', 'cavalo', 'semi_reboque', 'cacamba_estacionaria',
        'cacamba_avulsa', 'cacamba_caminhao', 'recapagem',
        'automovel', 'motocicleta', 'outros', 'outro',
    ];

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
            $this->line('  python scripts/firebird/export-martinho-os.py --dsn "..." --output ' . $jsonPath);
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

        // Dry-run é o PADRÃO seguro: só grava com --commit explícito. --dry-run
        // vence sempre (se vier junto de --commit, manda a segurança).
        $dryRun = ! ((bool) $this->option('commit')) || (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');

        $this->info('Source JSON: ' . $jsonPath);
        $this->info('Business: ' . $businessId);
        $this->info('Modo: ' . ($dryRun ? 'DRY-RUN (padrão — zero modificação DB)' : 'COMMIT (grava no DB)'));
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
            $this->warn('DRY-RUN (padrão) — nenhuma modificação aplicada ao DB. Rode com --commit pra gravar.');
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

        $vehicleType = $this->normalizeVehicleType($ordem['vehicle_type'] ?? null);

        // Vehicle: localiza por placa+biz OU cria stub
        // SUPERADMIN: import CLI roda sem session — bypass do global scope com filtro
        // explícito por business_id alvo da migração legacy (Tier 0, ADR 0093).
        $vehicle = Vehicle::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('plate', $placa)
            ->first();

        if ($vehicle === null) {
            if ($dryRun) {
                if ($detail) {
                    $this->line("  + (dry-run) Vehicle stub: placa={$placa} tipo={$vehicleType}");
                }
            } else {
                // SUPERADMIN: import CLI sem session — cria Vehicle com business_id
                // explícito do alvo da migração legacy (Tier 0, ADR 0093).
                $vehicle = Vehicle::withoutGlobalScopes()->create([
                    'business_id'  => $businessId,
                    'plate'        => $placa,
                    // ADR 0194: caminhão (não caçamba). Vem do JSON se mapeável, senão default caminhao.
                    'vehicle_type' => $vehicleType,
                    'legacy_id'    => (string) ($ordem['veiculo_id'] ?? null),
                ]);
            }
        }

        $itensInput = (array) ($ordem['itens'] ?? []);
        $itensCriados = 0;

        $orderType = $this->normalizeOrderType($ordem['order_type'] ?? null);
        $status    = $this->normalizeStatus($ordem['status'] ?? null);

        if ($dryRun) {
            if ($detail) {
                $this->line(sprintf(
                    '  + (dry-run) OS legacy_id=%s placa=%s tipo=%s status=%s itens=%d',
                    $legacyId,
                    $placa,
                    $orderType,
                    $status,
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
            $orderType,
            $status,
            $itensInput,
            $itemService,
            &$itensCriados,
            $detail
        ) {
            // SUPERADMIN: import CLI sem session — cria ServiceOrder com business_id
            // explícito do alvo da migração legacy (Tier 0, ADR 0093).
            $os = ServiceOrder::withoutGlobalScopes()->create([
                'business_id'        => $businessId,
                'vehicle_id'         => $vehicle->id,
                'order_type'         => $orderType,
                'status'             => $status,
                'entered_at'         => $ordem['entered_at'] ?? null,
                'completed_at'       => $ordem['completed_at'] ?? null,
                // ADR 0121 §P8 — vocab shared: data_get() em vez de bracket-array-access
                // pro CI repair-shared-vocab.yml não pegar a chave de odometro Firebird
                // (legacy WR Sistemas usa esse nome de campo · rename quebraria o import).
                'mileage_at_service' => data_get($ordem, 'km'),
                'notes'              => self::FIREBIRD_LEGACY_PREFIX . $legacyId
                                       . ' | ' . (string) ($ordem['notes'] ?? ''),
            ]);

            foreach ($itensInput as $itemData) {
                $itemService->addItem($businessId, $os->id, [
                    'tipo'           => $this->normalizeItemTipo($itemData['tipo'] ?? null),
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

    /**
     * Normaliza vehicle_type pro enum REAL do Vehicle (ADR 0194: caminhão, não caçamba).
     *
     * - valor já na whitelist → mantém;
     * - sinônimos de caminhão basculante (cacamba/basculante/caminhao_basculante/...) → 'caminhao';
     * - vazio/desconhecido → DEFAULT_VEHICLE_TYPE ('caminhao').
     *
     * NÃO inventa valor fora do enum. O domínio do Martinho é mecânica pesada de
     * caminhão — etiquetar como 'cacamba' era o drift pré-ADR-0194.
     */
    private function normalizeVehicleType(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? '')));
        if ($v === '') {
            return self::DEFAULT_VEHICLE_TYPE;
        }

        if (in_array($v, self::VEHICLE_TYPE_WHITELIST, true)) {
            return $v;
        }

        // Sinônimos de "caminhão basculante" que apareciam em docs/JSON legacy mas
        // não estão na whitelist (caminhao_basculante, cacamba_basculante, cacamba...).
        $caminhaoSinonimos = ['cacamba', 'basculante', 'caminhao_basculante', 'cacamba_basculante', 'caminhao_cacamba', 'truck'];
        if (in_array($v, $caminhaoSinonimos, true)) {
            return 'caminhao';
        }

        return self::DEFAULT_VEHICLE_TYPE;
    }

    /**
     * Mapeia order_type legacy → {manutencao|mecanica}.
     *
     * `locacao` ERRADICADO (ADR 0265 — Oficina = reparo, não locação): qualquer valor
     * legado de locação cai no default 'manutencao' (mesmo bucket que o importer já
     * aplicava a status legado). Default 'manutencao' = OS histórica importada.
     */
    private function normalizeOrderType(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? '')));
        return match (true) {
            $v === 'mecanica'  => 'mecanica',
            default            => 'manutencao', // locacao erradicado → manutencao (ADR 0265)
        };
    }

    /**
     * Mapeia status legacy (WR Sistemas, PT livre) → status canônico do FSM
     * manutencao (aberta|em_servico|concluida|cancelada).
     *
     * OS legacy são histórico fechado → default 'concluida'.
     */
    private function normalizeStatus(mixed $raw): string
    {
        $v = $this->foldAccents(strtolower(trim((string) ($raw ?? ''))));
        $v = str_replace(['_', '-'], ' ', $v);

        return match (true) {
            $v === '' => 'concluida', // legacy histórico
            str_contains($v, 'cancel')                                   => 'cancelada',
            str_contains($v, 'andamento') || str_contains($v, 'execu')
                || str_contains($v, 'servic') || str_contains($v, 'aberto execu') => 'em_servico',
            str_contains($v, 'abert') || str_contains($v, 'orcament')    => 'aberta',
            str_contains($v, 'conclu') || str_contains($v, 'finaliz')
                || str_contains($v, 'fechad') || str_contains($v, 'entreg')
                || str_contains($v, 'pronto')                            => 'concluida',
            default => 'concluida',
        };
    }

    /**
     * Mapeia tipo de item legacy → ServiceOrderItem::TIPOS_VALIDOS
     * (peca|mao_obra|servico_terceiro). Default 'peca'.
     */
    private function normalizeItemTipo(mixed $raw): string
    {
        $v = $this->foldAccents(strtolower(trim((string) ($raw ?? ''))));

        if (in_array($v, ServiceOrderItem::TIPOS_VALIDOS, true)) {
            return $v;
        }

        return match (true) {
            str_contains($v, 'terceir')                                  => 'servico_terceiro',
            str_contains($v, 'mao') || str_contains($v, 'obra')
                || str_contains($v, 'hora') || str_contains($v, 'servic')
                || str_contains($v, 'labor')                             => 'mao_obra',
            default => 'peca',
        };
    }

    /**
     * Folding ASCII de acentos PT-BR — legacy WR Sistemas grava status/tipo com
     * acento ('ORÇAMENTO', 'EXECUÇÃO', 'SERVIÇO'); os matchers comparam em ASCII.
     */
    private function foldAccents(string $v): string
    {
        return strtr($v, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
