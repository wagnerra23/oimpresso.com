<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DummyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:dummy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the database & import dummy data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Artisan::call('cache:clear');
        Artisan::call('migrate:fresh');
        //Artisan::call('db:seed');
        Artisan::call('db:seed', ["--class" => 'DummyDataSeeder']);
    }
}
