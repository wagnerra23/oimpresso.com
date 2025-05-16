<?php

use Illuminate\Database\Seeder;
use App\User;
use App\Business;
class BusinesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        User::create(
          [
            'surname' => 'Marcos',
            'first_name' => 'Bueno',
            'username' => 'mbueno',
            'password' => '$2y$10$TlFVghpRGG4uWaEeYs1SYeb2yR9/S0X0jCI78lW0t5jQSg.RJBpge'
          ]
        );

        Business::create(
          [
              'name' => 'Slym',
              'currency_id' => 18,
              'tax_number_1' => '1',
              'tax_label_1' => '1',
              'owner_id' => 1
          ]
        );
    }
}
