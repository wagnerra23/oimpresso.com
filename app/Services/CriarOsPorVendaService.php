<?php

declare(strict_types=1);

namespace App\Services;

use App\Business;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OficinaAuto\Entities\ServiceOrder;
use RuntimeException;

/**
 * CriarOsPorVendaService — orquestra criação de Service Orders (OS) a partir
 * de uma venda (Transaction).
 *
 * Suporta os 2 modos canônicos:
 *
 *   - **single** — 1 OS cobrindo a venda inteira (transaction_sell_line_id=NULL).
 *     Caso Martinho (caçambas): vendi 3 produtos, gero 1 OS de entrega.
 *
 *   - **per_line** — 1 OS por linha de produto (loop nas sellLines).
 *     Caso ComunicacaoVisual (gráfica): 5 banners + 1 placa = 6 OS independentes.
 *
 *   - **auto** — lê `business.os_default_per_line` pra decidir.
 *
 * **Idempotente**: se já existem OS pra essa Transaction (single) ou Transaction+SellLine
 * (per_line), retorna as existentes em vez de duplicar.
 *
 * **Multi-tenant Tier 0 ([ADR 0093])**:
 *   - business_id derivado SEMPRE de `$transaction->business_id` (nunca de payload/session)
 *   - Cross-tenant guard: rejeita se `auth()->user()->business_id` ≠ `$transaction->business_id`
 *
 * **order_type / status defaults**:
 *   - V0 schema (`service_orders`) só tem `status` (string livre). order_type entra em V1.
 *   - Default status: `'aberta'` (FSM canônica chega em US-OFICINA-003 ADR 0129).
 *
 * @see Modules/OficinaAuto/Entities/ServiceOrder.php
 * @see app/Http/Controllers/SellController.php@createOs
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
class CriarOsPorVendaService
{
    public const MODE_AUTO = 'auto';
    public const MODE_SINGLE = 'single';
    public const MODE_PER_LINE = 'per_line';

    public const ALLOWED_MODES = [
        self::MODE_AUTO,
        self::MODE_SINGLE,
        self::MODE_PER_LINE,
    ];

    /**
     * Cria OS pra uma venda no modo solicitado.
     *
     * @param  Transaction  $transaction  Venda origem (já carregada do banco)
     * @param  string       $mode         'auto' | 'single' | 'per_line'
     * @return array{
     *     created: \Illuminate\Support\Collection<int, ServiceOrder>,
     *     existing: \Illuminate\Support\Collection<int, ServiceOrder>,
     *     mode_resolved: string,
     *     message: string
     * }
     *
     * @throws RuntimeException Quando mode inválido ou transaction sem business_id
     */
    public function criar(Transaction $transaction, string $mode = self::MODE_AUTO): array
    {
        if (! in_array($mode, self::ALLOWED_MODES, true)) {
            throw new RuntimeException("Modo inválido: '{$mode}'. Aceitos: " . implode(', ', self::ALLOWED_MODES));
        }

        // Tier 0: business_id SEMPRE da transaction (nunca de payload/session).
        $businessId = (int) $transaction->business_id;
        if ($businessId <= 0) {
            throw new RuntimeException('Transaction sem business_id válido — abortando criação de OS.');
        }

        // Cross-tenant guard (defesa em profundidade — ADR 0093).
        $sessionBiz = (int) (auth()->user()?->business_id ?? session('user.business_id') ?? 0);
        if ($sessionBiz > 0 && $sessionBiz !== $businessId) {
            throw new RuntimeException("Cross-tenant violation: session biz={$sessionBiz} ≠ transaction biz={$businessId}");
        }

        // Resolver mode='auto' → ler config do business.
        $modeResolved = $mode === self::MODE_AUTO
            ? $this->resolveAutoMode($businessId)
            : $mode;

        // Eager-load sellLines se ainda não carregadas (anti-N+1).
        if (! $transaction->relationLoaded('sell_lines')) {
            $transaction->load('sell_lines');
        }

        return DB::transaction(function () use ($transaction, $modeResolved, $businessId) {
            return $modeResolved === self::MODE_PER_LINE
                ? $this->criarPerLine($transaction, $businessId)
                : $this->criarSingle($transaction, $businessId);
        });
    }

    /**
     * Lê `business.os_default_per_line` pra resolver mode='auto'.
     */
    protected function resolveAutoMode(int $businessId): string
    {
        $business = Business::find($businessId);
        if (! $business) {
            return self::MODE_SINGLE; // fail-safe: default Martinho
        }

        $perLine = (bool) ($business->os_default_per_line ?? false);

        return $perLine ? self::MODE_PER_LINE : self::MODE_SINGLE;
    }

    /**
     * Modo single: 1 OS cobrindo a venda inteira.
     * Idempotente: se já existe OS com transaction_sell_line_id=NULL, retorna ela.
     */
    protected function criarSingle(Transaction $transaction, int $businessId): array
    {
        // Idempotência: busca OS existente sem sell_line vinculada.
        $existing = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: query cross-scope pra deduplicar
            ->where('business_id', $businessId)
            ->where('transaction_id', $transaction->id)
            ->whereNull('transaction_sell_line_id')
            ->get();

        if ($existing->isNotEmpty()) {
            return [
                'created'       => collect(),
                'existing'      => $existing,
                'mode_resolved' => self::MODE_SINGLE,
                'message'       => "OS já existente pra esta venda ({$existing->count()})—nenhuma criada.",
            ];
        }

        $vehicleId = $this->resolveVehicleId($transaction, $businessId);

        $os = ServiceOrder::create([
            'business_id'              => $businessId,
            'transaction_id'           => $transaction->id,
            'transaction_sell_line_id' => null,
            'vehicle_id'               => $vehicleId,
            'status'                   => 'aberta',
            'entered_at'               => now(),
            'notes'                    => "OS criada da venda #{$transaction->invoice_no} (modo: 1 OS pra venda toda).",
        ]);

        Log::info('CriarOsPorVendaService: OS single criada', [
            'business_id'    => $businessId,
            'transaction_id' => $transaction->id,
            'os_id'          => $os->id,
        ]);

        return [
            'created'       => collect([$os]),
            'existing'      => collect(),
            'mode_resolved' => self::MODE_SINGLE,
            'message'       => '1 OS criada pra venda toda.',
        ];
    }

    /**
     * Modo per_line: 1 OS por linha de produto.
     * Idempotente: pula sellLines que já têm OS associada.
     */
    protected function criarPerLine(Transaction $transaction, int $businessId): array
    {
        $sellLines = $transaction->sell_lines;
        if ($sellLines->isEmpty()) {
            return [
                'created'       => collect(),
                'existing'      => collect(),
                'mode_resolved' => self::MODE_PER_LINE,
                'message'       => 'Venda sem linhas de produto — nenhuma OS criada.',
            ];
        }

        $vehicleId = $this->resolveVehicleId($transaction, $businessId);

        // Idempotência: busca OS já criadas pra essas linhas.
        $linesIds = $sellLines->pluck('id')->all();
        $existingByLine = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: query cross-scope pra deduplicar
            ->where('business_id', $businessId)
            ->where('transaction_id', $transaction->id)
            ->whereIn('transaction_sell_line_id', $linesIds)
            ->get()
            ->keyBy('transaction_sell_line_id');

        $created = collect();
        $existing = collect();

        foreach ($sellLines as $line) {
            /** @var TransactionSellLine $line */
            if ($existingByLine->has($line->id)) {
                $existing->push($existingByLine->get($line->id));
                continue;
            }

            $productLabel = $line->product?->name ?? "Linha #{$line->id}";

            $os = ServiceOrder::create([
                'business_id'              => $businessId,
                'transaction_id'           => $transaction->id,
                'transaction_sell_line_id' => $line->id,
                'vehicle_id'               => $vehicleId,
                'status'                   => 'aberta',
                'entered_at'               => now(),
                'notes'                    => "OS criada da venda #{$transaction->invoice_no} — produto: {$productLabel} (qtde: {$line->quantity}).",
            ]);

            $created->push($os);
        }

        Log::info('CriarOsPorVendaService: OS per_line criadas', [
            'business_id'    => $businessId,
            'transaction_id' => $transaction->id,
            'created_count'  => $created->count(),
            'existing_count' => $existing->count(),
        ]);

        $msg = $created->isEmpty()
            ? "Todas as {$existing->count()} linhas já tinham OS — nenhuma criada."
            : "{$created->count()} OS criada(s) (uma por produto).";

        if ($existing->isNotEmpty() && $created->isNotEmpty()) {
            $msg .= " {$existing->count()} já existia(m).";
        }

        return [
            'created'       => $created,
            'existing'      => $existing,
            'mode_resolved' => self::MODE_PER_LINE,
            'message'       => $msg,
        ];
    }

    /**
     * Resolve vehicle_id pra OS:
     *   1. Se transaction tem `vehicle_id` (campo customizado V1), usa.
     *   2. Senão tenta achar veículo placeholder do business (ou primeiro do contact).
     *   3. Senão usa 0 (caller deve ajustar — V0 permite, schema é nullable=false mas FK CASCADE).
     *
     * NOTA V0: schema atual exige vehicle_id NOT NULL. Em produção real (Martinho)
     * a UI vai pedir veículo antes de criar OS. Este resolver é fallback defensivo.
     *
     * TODO US-OFICINA-V1: tornar vehicle_id nullable em service_orders (vendas
     * sem veículo, ex: ComunicacaoVisual gráfica que não tem frota).
     */
    protected function resolveVehicleId(Transaction $transaction, int $businessId): int
    {
        // Se transaction tem coluna `vehicle_id` populada (campo custom legacy), usa.
        if (! empty($transaction->vehicle_id ?? null)) {
            return (int) $transaction->vehicle_id;
        }

        // Fallback V0: pega primeiro veículo do contact se existir.
        if ($transaction->contact_id) {
            $vehicleId = DB::table('vehicles')
                ->where('business_id', $businessId)
                ->where('contact_id', $transaction->contact_id)
                ->whereNull('deleted_at')
                ->value('id');

            if ($vehicleId) {
                return (int) $vehicleId;
            }
        }

        // Último fallback: primeiro veículo do business (ComunicacaoVisual sem frota
        // não chega aqui — UI bloqueia ou seeder cria veículo placeholder).
        $vehicleId = DB::table('vehicles')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->value('id');

        if (! $vehicleId) {
            throw new RuntimeException(
                "Sem veículo cadastrado pro business {$businessId}. Cadastre um veículo " .
                'antes de criar OS, ou aguarde US-OFICINA-V1 (vehicle_id nullable).'
            );
        }

        return (int) $vehicleId;
    }
}
