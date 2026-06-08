<?php

namespace Modules\Vestuario\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

/**
 * vestuario:settings — CLI pra inspecionar/editar settings do vertical Vestuario.
 *
 * Sprint 2 ADR 0121 §P7. Substituto do acesso direto ao DB pra operações de
 * troubleshoot e configuração de clientes novos CNAE 4781.
 *
 * Uso:
 *   php artisan vestuario:settings list --business=4
 *   php artisan vestuario:settings get --business=4 --key=format_date_shift_hours
 *   php artisan vestuario:settings set --business=4 --key=format_date_shift_hours --value=3 --type=int
 *
 * Multi-tenant Tier 0 (ADR 0093): --business obrigatório pois CLI roda sem
 * session web (substituto do session('user.business_id')).
 *
 * @see Modules/Vestuario/Services/VestuarioSettingsResolver.php
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class VestuarioSettingsCommand extends Command
{
    protected $signature = 'vestuario:settings
        {action : Ação a executar: list|get|set}
        {--business= : business_id obrigatório (CLI roda sem session — ADR 0093)}
        {--key= : Chave dot notation pra get/set (ex: feature.x.threshold)}
        {--value= : Valor a setar (pra action=set)}
        {--type=string : Tipo do valor: string|int|bool|json (default: string)}';

    protected $description = 'Inspecionar/editar settings do vertical Vestuario por business — list/get/set.';

    private const VALID_ACTIONS = ['list', 'get', 'set'];
    private const VALID_TYPES   = ['string', 'int', 'bool', 'json'];

    public function handle(): int
    {
        // Verificar tabela existe
        if (! Schema::hasTable('vestuario_settings')) {
            $this->error('vestuario_settings table missing — rode php artisan migrate primeiro.');
            return 1;
        }

        // Validar --business obrigatório (ADR 0093 — CLI não tem session)
        $businessId = $this->option('business');
        if ($businessId === null || $businessId === '') {
            $this->error('--business é obrigatório. CLI roda sem session web (ADR 0093 multi-tenant Tier 0).');
            $this->line('Exemplo: php artisan vestuario:settings list --business=4');
            return 1;
        }
        $businessId = (int) $businessId;

        // Validar action
        $action = strtolower((string) $this->argument('action'));
        if (! in_array($action, self::VALID_ACTIONS, true)) {
            $this->error("Action inválida: '{$action}'. Use: list, get ou set.");
            return 1;
        }

        $resolver = app(VestuarioSettingsResolver::class)->forBusiness($businessId);

        return match ($action) {
            'list' => $this->actionList($resolver, $businessId),
            'get'  => $this->actionGet($resolver),
            'set'  => $this->actionSet($resolver, $businessId),
        };
    }

    /**
     * list — exibe todas as key:value pairs do business em tabela.
     */
    private function actionList(VestuarioSettingsResolver $resolver, int $businessId): int
    {
        // Carrega settings brutas via DB pra exibir todas as keys flat
        $row = \Illuminate\Support\Facades\DB::table('vestuario_settings')
            ->where('business_id', $businessId)
            ->first();

        if ($row === null) {
            $this->warn("Nenhuma setting encontrada pra business_id={$businessId}. Use 'set' pra criar.");
            return 0;
        }

        $settings = json_decode($row->settings ?? '{}', true) ?? [];

        if (empty($settings)) {
            $this->warn("business_id={$businessId} existe mas settings está vazio.");
            return 0;
        }

        $rows = [];
        $this->flattenDotNotation($settings, '', $rows);

        $this->info("Settings — business_id={$businessId}:");
        $this->table(['Chave', 'Valor', 'Tipo'], $rows);

        return 0;
    }

    /**
     * get — recupera uma key específica (suporta dot notation).
     */
    private function actionGet(VestuarioSettingsResolver $resolver): int
    {
        $key = $this->option('key');
        if ($key === null || $key === '') {
            $this->error('--key é obrigatório pra action=get.');
            $this->line('Exemplo: php artisan vestuario:settings get --business=4 --key=format_date_shift_hours');
            return 1;
        }

        $value = $resolver->get($key);

        if ($value === null) {
            $this->warn("Chave '{$key}' não encontrada (retornou null).");
            return 0;
        }

        $displayValue = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) $value;

        $this->line("<comment>{$key}</comment> = <info>{$displayValue}</info>");

        return 0;
    }

    /**
     * set — salva uma key com cast de tipo.
     */
    private function actionSet(VestuarioSettingsResolver $resolver, int $businessId): int
    {
        $key = $this->option('key');
        if ($key === null || $key === '') {
            $this->error('--key é obrigatório pra action=set.');
            $this->line('Exemplo: php artisan vestuario:settings set --business=4 --key=format_date_shift_hours --value=3 --type=int');
            return 1;
        }

        $rawValue = $this->option('value');
        if ($rawValue === null) {
            $this->error('--value é obrigatório pra action=set.');
            return 1;
        }

        $type = strtolower((string) $this->option('type'));
        if (! in_array($type, self::VALID_TYPES, true)) {
            $this->error("--type inválido: '{$type}'. Use: string, int, bool ou json.");
            return 1;
        }

        // Cast do valor conforme --type
        $castedValue = $this->castValue($rawValue, $type);
        if ($castedValue === false && $type !== 'bool') {
            // json inválido — castValue retorna false explicitamente
            $this->error("--value inválido pra --type=json: JSON parse falhou.");
            $this->line("Valor recebido: {$rawValue}");
            return 1;
        }

        $resolver->set($key, $castedValue);

        $displayValue = is_array($castedValue)
            ? json_encode($castedValue, JSON_UNESCAPED_UNICODE)
            : var_export($castedValue, true);

        $this->info("OK — business_id={$businessId} | {$key} = {$displayValue} ({$type})");

        return 0;
    }

    /**
     * Cast valor string conforme tipo informado.
     * Retorna mixed. Para json inválido retorna a sentinel false (string).
     */
    private function castValue(string $rawValue, string $type): mixed
    {
        return match ($type) {
            'int'    => (int) $rawValue,
            'bool'   => $this->castBool($rawValue),
            'json'   => $this->castJson($rawValue),
            default  => $rawValue, // string
        };
    }

    /**
     * Aceita: true/false/1/0/yes/no/sim/não.
     */
    private function castBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'sim', 'on'], true);
    }

    /**
     * JSON decode com validação. Retorna array ou false se inválido.
     */
    private function castJson(string $value): array|false
    {
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        return is_array($decoded) ? $decoded : false;
    }

    /**
     * Flatten nested array pra dot notation pra exibição na tabela.
     */
    private function flattenDotNotation(array $data, string $prefix, array &$rows): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if (is_array($value)) {
                $this->flattenDotNotation($value, $fullKey, $rows);
            } else {
                $type = gettype($value);
                $display = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $rows[] = [$fullKey, $display, $type];
            }
        }
    }
}
