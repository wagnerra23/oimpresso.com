<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alçada de valor do pedido de compra — US-COM-032 (decisão [W] 2026-10-26: no pedido de compra, com
 * limites de valor configuráveis por empresa — comprador, gerente, diretoria).
 *
 * Configuração em `business.common_settings['compras_alcada']` = `{ativo, comprador_ate, gerente_ate}`,
 * DESLIGADA por padrão: ninguém muda no deploy.
 *   total ≤ comprador_ate            → não precisa de aprovação
 *   comprador_ate < total ≤ gerente_ate → nível `gerente`  (permissão `compras.alcada.gerente`)
 *   total > gerente_ate              → nível `diretoria` (permissão `compras.alcada.diretoria`)
 * A diretoria também aprova o nível gerente. Quem fez o pedido e já tem a permissão do nível o aprova
 * na hora (fica registrado como aprovado por ele).
 *
 * O pedido pendente ou recusado não vira compra recebida (`bloqueiaRecebimento`). Valores comparados
 * em dez-milésimos inteiros, sem float. Tier 0: tudo pelo `$businessId` recebido.
 */
final class AlcadaDoPedido
{
    public const CHAVE = 'compras_alcada';

    public const PERM_GERENTE = 'compras.alcada.gerente';

    public const PERM_DIRETORIA = 'compras.alcada.diretoria';

    /** @return array{ativo: bool, comprador_ate: string, gerente_ate: string} */
    public function config(int $businessId): array
    {
        $c = ($this->settings($businessId)[self::CHAVE] ?? []);

        return [
            'ativo' => (bool) ($c['ativo'] ?? false),
            'comprador_ate' => (string) ($c['comprador_ate'] ?? '0'),
            'gerente_ate' => (string) ($c['gerente_ate'] ?? '0'),
        ];
    }

    public function definir(int $businessId, bool $ativo, string $compradorAte, string $gerenteAte): void
    {
        if (self::dm($compradorAte) < 0 || self::dm($gerenteAte) < self::dm($compradorAte)) {
            throw new InvalidArgumentException('O limite do gerente precisa ser maior ou igual ao do comprador.');
        }
        $s = $this->settings($businessId);
        $s[self::CHAVE] = ['ativo' => $ativo, 'comprador_ate' => $compradorAte, 'gerente_ate' => $gerenteAte];
        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($s)]);
    }

    /** Nível exigido para um total; null quando não precisa de aprovação (ou a alçada está desligada). */
    public function nivelExigido(int $businessId, string $total): ?string
    {
        $c = $this->config($businessId);
        if (! $c['ativo'] || self::dm($total) <= self::dm($c['comprador_ate'])) {
            return null;
        }

        return self::dm($total) <= self::dm($c['gerente_ate']) ? 'gerente' : 'diretoria';
    }

    /** Chamado quando o pedido nasce. Devolve o status gravado, ou null se não precisou. */
    public function registrar(int $businessId, int $pedidoId, int $userId): ?string
    {
        $pedido = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase_order')
            ->where('id', $pedidoId)->first(['id', 'final_total']);
        if ($pedido === null) {
            return null;
        }
        $nivel = $this->nivelExigido($businessId, (string) $pedido->final_total);
        if ($nivel === null) {
            return null;
        }
        $autor = User::where('business_id', $businessId)->find($userId);
        $jaPode = $autor !== null && $this->podeAprovar($autor, $nivel);
        $agora = now();
        DB::table('compras_aprovacoes_pedido')->updateOrInsert(
            ['business_id' => $businessId, 'transaction_id' => $pedidoId],
            ['valor' => (string) $pedido->final_total, 'nivel' => $nivel, 'status' => $jaPode ? 'aprovado' : 'pendente',
                'pedido_por' => $userId, 'decidido_por' => $jaPode ? $userId : null, 'decidido_em' => $jaPode ? $agora : null,
                'created_at' => $agora, 'updated_at' => $agora],
        );

        return $jaPode ? 'aprovado' : 'pendente';
    }

    public function decidir(int $businessId, int $pedidoId, User $quem, bool $aprovar, ?string $motivo = null): void
    {
        $linha = DB::table('compras_aprovacoes_pedido')->where('business_id', $businessId)->where('transaction_id', $pedidoId)->first();
        if ($linha === null || $linha->status !== 'pendente') {
            throw new InvalidArgumentException('Este pedido não está aguardando aprovação.');
        }
        if ((int) $quem->business_id !== $businessId || ! $this->podeAprovar($quem, (string) $linha->nivel)) {
            throw new InvalidArgumentException('Você não tem alçada para este valor.');
        }
        if (! $aprovar && trim((string) $motivo) === '') {
            throw new InvalidArgumentException('Diga o motivo da recusa.');
        }
        DB::table('compras_aprovacoes_pedido')->where('id', $linha->id)->update([
            'status' => $aprovar ? 'aprovado' : 'recusado', 'decidido_por' => $quem->id, 'decidido_em' => now(),
            'motivo' => $aprovar ? null : trim((string) $motivo), 'updated_at' => now(),
        ]);
    }

    /** Ids dos pedidos, dentre os dados, que ainda não podem virar compra recebida. */
    public function bloqueiaRecebimento(int $businessId, array $pedidoIds): array
    {
        if ($pedidoIds === []) {
            return [];
        }

        return DB::table('compras_aprovacoes_pedido')->where('business_id', $businessId)
            ->whereIn('transaction_id', array_map('intval', $pedidoIds))
            ->whereIn('status', ['pendente', 'recusado'])
            ->pluck('transaction_id')->map(fn ($id) => (int) $id)->all();
    }

    private function podeAprovar(User $u, string $nivel): bool
    {
        return $u->can(self::PERM_DIRETORIA) || ($nivel === 'gerente' && $u->can(self::PERM_GERENTE));
    }

    private function settings(int $businessId): array
    {
        $s = json_decode((string) DB::table('business')->where('id', $businessId)->value('common_settings'), true);

        return is_array($s) ? $s : [];
    }

    private static function dm(string $v): int
    {
        $v = trim($v) === '' ? '0' : trim($v);
        $neg = str_starts_with($v, '-');
        [$i, $f] = array_pad(explode('.', ltrim($v, '-'), 2), 2, '');
        $n = (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);

        return $neg ? -$n : $n;
    }
}
