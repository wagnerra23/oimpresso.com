<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * Por onde o Fisio manda link, programa e lembrete (tela Configurações › WhatsApp, protótipo
 * `ConfigWhatsApp.dc.html`). A clínica ESCOLHE; quem conecta o número, troca o driver e cuida
 * da saúde do canal continua sendo o módulo `Whatsapp` (US-FISIO-009). O Fisio não tem driver.
 *
 *  - `proprio`: o Fisio nunca usa o número do módulo — sempre `wa.me` para o profissional enviar.
 *  - `oficial`: só envia automático se o número de saída do módulo for `meta_cloud`.
 *  - `qr`: só envia automático com o aceite de risco registrado (quem, quando, versão do termo)
 *    e com número de saída não oficial. Sem aceite, cai no manual.
 *  - sem escolha gravada: comportamento de antes desta tela (usa o número do módulo, se houver).
 *
 * Teto por hora e horário de silêncio podem ser da clínica; sem valor gravado, vale
 * `config('fisio.whatsapp')`.
 */
class CanalDaClinica
{
    public const PROPRIO = 'proprio';

    public const OFICIAL = 'oficial';

    public const QR = 'qr';

    public const CANAIS = [self::PROPRIO, self::OFICIAL, self::QR];

    /** Mudar o texto do aviso de risco = subir esta versão (o aceite antigo deixa de valer). */
    public const TERMO_QR_VERSAO = 'qr-v1';

    public const TETO_MIN = 5;

    public const TETO_MAX = 60;

    public function __construct(private ConfiguracaoClinica $config) {}

    public function escolha(int $businessId): ?string
    {
        $canal = $this->config->ler($businessId, 'whatsapp.canal');

        return in_array($canal, self::CANAIS, true) ? $canal : null;
    }

    /** @return array{user_id: int, em: string, versao: string}|null */
    public function aceiteQr(int $businessId): ?array
    {
        $aceite = $this->config->ler($businessId, 'whatsapp.qr_risco_aceito');

        return is_array($aceite) && ($aceite['versao'] ?? null) === self::TERMO_QR_VERSAO ? $aceite : null;
    }

    /** O número de saída do módulo serve para envio automático do Fisio, pela escolha da clínica? */
    public function permiteNumero(int $businessId, WhatsappBusinessPhone $telefone): bool
    {
        return match ($this->escolha($businessId)) {
            null => true,
            self::PROPRIO => false,
            self::OFICIAL => (string) $telefone->driver === 'meta_cloud',
            self::QR => (string) $telefone->driver !== 'meta_cloud' && $this->aceiteQr($businessId) !== null,
        };
    }

    /** @return array{teto: int, silencio_inicio: string, silencio_fim: string} */
    public function ritmo(int $businessId): array
    {
        $cfg = (array) config('fisio.whatsapp', []);
        $gravado = $this->config->varias($businessId, ['whatsapp.teto_por_hora', 'whatsapp.silencio']);
        $silencio = is_array($gravado['whatsapp.silencio'] ?? null) ? $gravado['whatsapp.silencio'] : (array) ($cfg['silencio'] ?? []);

        return [
            'teto' => (int) ($gravado['whatsapp.teto_por_hora'] ?? $cfg['teto_por_hora'] ?? 30),
            'silencio_inicio' => (string) ($silencio['inicio'] ?? '21:00'),
            'silencio_fim' => (string) ($silencio['fim'] ?? '07:00'),
        ];
    }
}
