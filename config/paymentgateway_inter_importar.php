<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Import diário de boletos pagos no Inter (US-PG-008 · auditoria PG 2.6)
|--------------------------------------------------------------------------
| Empresa, conta bancária e janela do agendamento em app/Console/Kernel.php.
| Antes ficavam fixas no Kernel (--business=1 --conta=12 --days=15); os
| defaults abaixo são os MESMOS valores, então nada muda em produção até
| alguém definir o env. Empresa 0 = o agendamento não roda.
|
| Fica em config/ (e não no Config/config.php do módulo) porque o Larastan
| trata env() fora de config/ como erro e o baseline não pode crescer.
*/
return [
    'business_id' => (int) env('PAYMENTGATEWAY_INTER_IMPORTAR_BUSINESS_ID', 1),
    'conta_id'    => env('PAYMENTGATEWAY_INTER_IMPORTAR_CONTA_ID', 12),
    'dias'        => (int) env('PAYMENTGATEWAY_INTER_IMPORTAR_DIAS', 15),
];
