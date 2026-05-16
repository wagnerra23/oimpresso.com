<?php

/**
 * Política de retenção LGPD — Modules/OficinaAuto (CNAE 4520-0/01 + 2212-9/00 + 4581-4/00).
 *
 * Governança LGPD (Lei 13.709/2018):
 *  - Art. 15 — término do tratamento quando finalidade alcançada (mas há exceções legais)
 *  - Art. 16 — conservação obrigatória pra cumprimento de obrigação legal/regulatória
 *  - Art. 37 — registro das operações de tratamento (audit trail — Spatie LogsActivity)
 *  - Art. 18 §6 — direito de eliminação (titular pode requerer, salvo retenção legal)
 *
 * Exceções legais que JUSTIFICAM retenção 5 anos pra OficinaAuto:
 *  1. CTN Art. 174 — prescrição tributária 5 anos (ICMS por serviço, ISS por mão-de-obra)
 *  2. Código Civil Art. 206 §5 II — prescrição cobrança valores via documento 5 anos
 *  3. SEFAZ/CONFAZ SINIEF 07/2005 — guarda NFe/NFS-e por 5 anos
 *  4. CDC Art. 26 §3 — vício oculto reparação automotiva
 *  5. CONTRAN/DENATRAN — histórico oficina pra recall/garantia veicular
 *
 * Modelos cobertos:
 *  - Vehicle (vehicles table) — placa + chassi + renavam + contact_id (CPF/CNPJ via FK)
 *  - ServiceOrder (service_orders table) — delivery_address + transaction_id (fiscal)
 *  - activity_log entries (Spatie) com log_name='oficinaauto.*'
 *
 * Política operacional:
 *  - Hot retention: 5 anos no MySQL principal
 *  - Cold retention: pós-5-anos — exportar pra S3 Glacier + soft-purge no DB
 *  - Right to be forgotten (Art. 18): job dedicado anonimiza Contact mas preserva
 *    Vehicle/ServiceOrder com vehicle.contact_id=null + plate hash (audit fiscal sobrevive)
 *
 * Cron de purge: NÃO IMPLEMENTADO V0. Implementação em US-OFICINA-LGPD-PURGE.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
return [
    'default_days' => 1825,

    'per_model' => [
        \Modules\OficinaAuto\Entities\Vehicle::class      => 1825,
        \Modules\OficinaAuto\Entities\ServiceOrder::class => 1825,
    ],

    'activity_log_names' => [
        'oficinaauto.vehicle'       => 2555,
        'oficinaauto.service_order' => 2555,
    ],

    'anonymize_fields' => [
        \Modules\OficinaAuto\Entities\Vehicle::class => [
            'plate'             => 'hash',
            'secondary_plate'   => 'hash',
            'chassis'           => 'null',
            'secondary_chassis' => 'null',
            'renavam'           => 'null',
            'contact_id'        => 'null',
            'notes'             => 'null',
        ],
        \Modules\OficinaAuto\Entities\ServiceOrder::class => [
            'delivery_address' => 'null',
            'notes'            => 'null',
            'contact_id'       => 'null',
        ],
    ],

    'purge_enabled' => false,
];
