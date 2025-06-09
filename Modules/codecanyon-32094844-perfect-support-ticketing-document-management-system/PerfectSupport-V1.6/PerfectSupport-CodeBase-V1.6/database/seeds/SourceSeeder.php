<?php

use Illuminate\Database\Seeder;
use App\Source;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	Source::create([
            // Don't change this source id, it is harcoded in products & source screen
            'id' => 1,          
            'name' => 'Envato',
            'source_type' => 'envato',
            'web_url' => '',
            'woo_consumer_key' => '',
            'woo_consumer_secret' => '',
            'description' => 'For envato website',
            'envato_token' => '',
            'is_enabled' => 0,
        ]);

        Source::create([
            // Don't change this source id, it is harcoded in products & source screen
            'id' => 2,
            'name' => 'WooCommerce Licensing',
            'source_type' => 'woolicensing',
            'web_url' => '',
            'woo_consumer_key' => '',
            'woo_consumer_secret' => '',
            'description' => 'For WooCommerce website that uses WooLicensing software',
            'is_enabled' => 0,
        ]);


        // Source::create([
        //     'id' => 3,
        //     'name' => 'WooCommerce',
        //     'source_type' => 'woocommerce',
        //     'web_url' => '',
        //     'woo_consumer_key' => '',
        //     'woo_consumer_secret' => '',
        //     'description' => 'For WooCommerce website',
        //     'is_enabled' => 0,
        // ]);
    }
}
