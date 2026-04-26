<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

// Pest funcional (it/expect) nos modulos legados — batch 7 (Repair, Officeimpresso,
// Superadmin, Woocommerce, Writebot). Help foi descartado da migracao 3.7->6.7.
uses(TestCase::class)->in(
    __DIR__ . '/../Modules/Repair/Tests/Feature',
    __DIR__ . '/../Modules/Officeimpresso/Tests/Feature',
    __DIR__ . '/../Modules/Superadmin/Tests/Feature',
    __DIR__ . '/../Modules/Woocommerce/Tests/Feature',
    __DIR__ . '/../Modules/Writebot/Tests/Feature',
);
