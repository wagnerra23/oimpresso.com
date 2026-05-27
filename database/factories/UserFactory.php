<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $password;

        // Schema UltimatePOS — sem coluna `name`. Surname é título (CHAR(10)
        // em 2018_02_26_130519_modify_users_table_for_sales_cmmsn_agnt),
        // first_name/last_name carregam o nome real.
        return [
            'surname' => 'Mr',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => $password ?: $password = Hash::make('secret'),
            'language' => 'en',
            'remember_token' => Str::random(10),
        ];
    }
}
