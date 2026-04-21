<?php

namespace Modules\PontoWr2\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class PontoWr2DatabaseSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        // Seeders de dev — só rodam quando invocado explicitamente
        // via `php artisan module:seed PontoWr2`.
        $this->call(DevPontoSeeder::class);
    }
}
