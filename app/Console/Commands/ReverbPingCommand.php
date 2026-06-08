<?php

namespace App\Console\Commands;

use App\Events\ReverbPing;
use Illuminate\Console\Command;

class ReverbPingCommand extends Command
{
    protected $signature = 'reverb:ping {message=pong}';

    protected $description = 'Dispara ReverbPing no canal `reverb-test` (smoke test do broadcaster).';

    public function handle(): int
    {
        $message = (string) $this->argument('message');

        broadcast(new ReverbPing($message));

        $this->info("Broadcast enviado em 'reverb-test' (event: ping, mensagem: {$message}).");
        $this->line('Driver atual: '.config('broadcasting.default'));

        return self::SUCCESS;
    }
}
