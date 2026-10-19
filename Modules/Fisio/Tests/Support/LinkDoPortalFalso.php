<?php

declare(strict_types=1);

namespace Modules\Fisio\Tests\Support;

use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Entities\Patient;

/**
 * Falso do portal do paciente (US-FISIO-007a ainda fora do main) para os testes do WhatsApp.
 * Conta as emissões por paciente: emitir revoga o acesso anterior, então emitir à toa (ex.: num
 * envio que não vai sair) é defeito que o teste precisa enxergar.
 */
final class LinkDoPortalFalso implements LinkDoPortal
{
    public const PIN = '4821';

    /** @var array<int, int> patient_id => nº de emissões */
    public array $emissoes = [];

    public function emitir(Patient $paciente): ?array
    {
        $n = ($this->emissoes[(int) $paciente->id] ?? 0) + 1;
        $this->emissoes[(int) $paciente->id] = $n;

        return ['url' => url('/fisio/p/tok'.$paciente->id.'x'.$n), 'pin' => self::PIN];
    }
}