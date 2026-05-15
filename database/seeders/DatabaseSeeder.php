<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call([BarcodesTableSeeder::class,
            PermissionsTableSeeder::class,
            CurrenciesTableSeeder::class,
            // US-SELL-015 — marca 6 candidatos saudáveis OfficeImpresso com
            // legacy_origin='officeimpresso' pra default de Grade Avançada
            // (ADR 0136). Idempotente: skipa se coluna ausente ou já marcado.
            BusinessLegacyOriginSeeder::class,
            // US-UI-SIDEBAR-001 — configura `sidebar_hidden_groups` por business
            // pra esconder módulos não usados (caso piloto: Martinho Caçambas
            // — oficina mecânica não-técnica). Idempotente: WHERE IS NULL.
            BusinessSidebarConfigSeeder::class,
        ]);
    }
}
