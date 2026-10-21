<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Arquivos\Services\ArquivosService;
use Modules\OficinaAuto\Entities\OsAssinatura;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * Assinatura do cliente na entrega da OS — UC-FLX-22 · US-OFICINA-048.
 *
 * A regra do gate (`StageGateEvaluator`, requisito `assinatura` na ação `entregar`) só entra quando a
 * empresa liga `business.common_settings[CHAVE]` — **desligada por padrão**, no mesmo formato do
 * `compras_requisicao_estoque_minimo`. Desligada, o checklist da entrega é o de antes (só a foto).
 *
 * O PNG segue o contrato da entrega do pedido no app (`PedidoEntregaController`): data URL
 * `data:image/png;base64,…`, PNG válido de até 200 KB. Fica no módulo Arquivos, anexado à linha de
 * `oficina_os_assinaturas` — nunca à OS, cujo anexo é foto do laudo e conta como foto do serviço.
 *
 * Tier 0 (ADR 0093): o business vem da OS, e toda consulta filtra por ele (o gate também roda pelo
 * app, sem sessão).
 */
class AssinaturaEntrega
{
    public const CHAVE = 'oficina_entrega_exige_assinatura';

    /** Tamanho máximo da assinatura decodificada (o mesmo da entrega do pedido no app). */
    public const MAX_BYTES = 200 * 1024;

    public function __construct(private ArquivosService $arquivos)
    {
    }

    public function exigida(int $businessId): bool
    {
        return (bool) (($this->ler($businessId)[self::CHAVE] ?? [])['ativo'] ?? false);
    }

    public function definir(int $businessId, bool $ativo): void
    {
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = ['ativo' => $ativo];

        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($settings)]);
    }

    /** A OS já tem a assinatura do cliente (linha do mesmo business, com o PNG guardado). */
    public function colhida(ServiceOrder $order): bool
    {
        $bizId = (int) $order->business_id;

        return DB::table('oficina_os_assinaturas as a')
            ->join('arquivos as f', function ($j) {
                $j->on('f.arquivable_id', '=', 'a.id')->where('f.arquivable_type', '=', OsAssinatura::class);
            })
            ->where('a.business_id', $bizId)
            ->where('f.business_id', $bizId)
            ->where('a.service_order_id', $order->id)
            ->whereNull('f.deleted_at')
            ->exists();
    }

    /** PNG decodificado do data URL, ou null se não for PNG válido de até 200 KB. */
    public static function decodificarPng(string $texto): ?string
    {
        $base64 = preg_replace('#^data:image/png;base64,#', '', $texto, 1, $trocas);
        if ($trocas !== 1 || $base64 === null) {
            return null;
        }
        if (strlen($base64) > (int) ceil(self::MAX_BYTES * 4 / 3) + 4) {
            return null;
        }
        $bin = base64_decode($base64, true);
        if ($bin === false || strlen($bin) > self::MAX_BYTES || ! str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $bin;
    }

    /**
     * Grava (ou troca) a assinatura da OS. Assinar de novo substitui a anterior: o anexo antigo vai
     * para a lixeira do Arquivos e o novo fica na mesma linha.
     */
    public function registrar(ServiceOrder $order, string $png, ?string $recebidoPor, ?int $userId): OsAssinatura
    {
        $bizId = (int) $order->business_id;

        return DB::transaction(function () use ($order, $png, $recebidoPor, $userId, $bizId) {
            $assinatura = OsAssinatura::withoutGlobalScopes() // business filtrado explícito abaixo
                ->where('business_id', $bizId)
                ->where('service_order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($assinatura === null) {
                $assinatura = OsAssinatura::create([
                    'business_id' => $bizId,
                    'service_order_id' => $order->id,
                    'recebido_por' => $recebidoPor,
                    'user_id' => $userId,
                ]);
            } else {
                foreach ($assinatura->arquivos()->get() as $antigo) {
                    $this->arquivos->softDelete($antigo);
                }
                $assinatura->update(['recebido_por' => $recebidoPor, 'user_id' => $userId]);
                $assinatura->touch();
            }

            $caminho = tempnam(sys_get_temp_dir(), 'osa');
            if ($caminho === false) {
                throw new \RuntimeException('Sem arquivo temporário para a assinatura.');
            }
            try {
                file_put_contents($caminho, $png);
                $arquivo = new UploadedFile($caminho, 'assinatura-os-' . $order->id . '.png', 'image/png', null, true);
                $this->arquivos->attachNoNegocio($assinatura, $arquivo, $bizId);
            } finally {
                @unlink($caminho);
            }

            return $assinatura->fresh() ?? $assinatura;
        });
    }

    /** @return array<string, mixed> */
    private function ler(int $businessId): array
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = json_decode((string) $bruto, true);

        return is_array($settings) ? $settings : [];
    }
}
