<?php

use Illuminate\Database\Seeder;
use App\User;

class UserAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Superadmin',
            'email' => 'superadmin@example.com',
            'email_verified_at' => '2020-10-19 13:29:15',
            'password' => bcrypt(12345678),
            'role' => 'admin'
        ]);
    }
}
