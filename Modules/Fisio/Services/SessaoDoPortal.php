<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Http\Request;
use Modules\Fisio\Entities\PortalToken;

/**
 * Sessão do paciente no portal: o PIN aceito fica na sessão amarrado ao ID da linha do token
 * (`fisio_portal.<id>`), ou vale o aparelho lembrado deste mesmo token (cookie), que então
 * passa a valer para a sessão também (US-FISIO-007a/b). Fonte única para o PortalController e
 * para a agenda do portal (US-FISIO-016).
 */
class SessaoDoPortal
{
    public function __construct(private readonly AparelhosDoPortal $aparelhos) {}

    public function chave(PortalToken $linha): string
    {
        return 'fisio_portal.'.$linha->id;
    }

    public function pinAceito(Request $request, PortalToken $linha): bool
    {
        if ($request->session()->get($this->chave($linha)) === true) {
            return true;
        }
        if ($this->aparelhos->reconhece($linha, $request->cookie($this->aparelhos->nomeCookie($linha)))) {
            $request->session()->put($this->chave($linha), true);

            return true;
        }

        return false;
    }
}
