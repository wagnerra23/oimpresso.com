<?php

declare(strict_types=1);

namespace Modules\SelftestVerde\Services;

// Fixture (G1b verde): basta existir no disco pra US-SLFV-001 ser anchored_ok.
class VerdeService
{
    public function ok(): bool
    {
        return true;
    }
}
