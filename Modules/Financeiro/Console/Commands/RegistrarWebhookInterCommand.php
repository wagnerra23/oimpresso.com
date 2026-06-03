<?php

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Strategies\InterApiStrategy;

/**
 * Gera webhook_token (se não tem), monta URL pública e chama API Inter
 * pra registrar. Idempotente — rodar de novo sobrescreve o registro no Inter
 * mas preserva o token (a menos que --rotate seja passado).
 *
 * Uso típico no Hostinger (ou CT 100):
 *   php artisan financeiro:inter:registrar-webhook --conta=1
 *   php artisan financeiro:inter:registrar-webhook --conta=1 --rotate
 *   php artisan financeiro:inter:registrar-webhook --conta=1 --dry-run
 */
class RegistrarWebhookInterCommand extends Command
{
    protected $signature = 'financeiro:inter:registrar-webhook
                            {--conta= : ID da fin_contas_bancarias}
                            {--rotate : Gera novo webhook_token mesmo se já existe}
                            {--dry-run : Só mostra a URL, não chama Inter}';

    protected $description = 'Registra a URL do webhook de cobrança no Banco Inter pra uma conta bancária.';

    public function handle(InterApiStrategy $strategy): int
    {
        $contaId = (int) $this->option('conta');
        if (! $contaId) {
            $this->error('--conta=<id> é obrigatório (id de fin_contas_bancarias).');

            return self::FAILURE;
        }

        $conta = ContaBancaria::find($contaId);
        if (! $conta) {
            $this->error("ContaBancaria {$contaId} não encontrada.");

            return self::FAILURE;
        }

        if ($conta->banco_codigo !== '077') {
            $this->error("ContaBancaria {$contaId} é banco {$conta->banco_codigo}, não 077 (Inter).");

            return self::FAILURE;
        }

        if ($this->option('rotate') || ! $conta->webhook_token) {
            $conta->webhook_token = Str::random(64);
            $conta->save();
            $this->info("webhook_token " . ($this->option('rotate') ? 'rotacionado' : 'gerado') . ".");
        }

        $url = url(route('financeiro.webhook.inter', ['token' => $conta->webhook_token], false));
        $url = rtrim(config('app.url'), '/') . '/webhook/inter/' . $conta->webhook_token;

        $this->line('');
        $this->line('URL do webhook:');
        $this->line('  <info>' . $url . '</info>');
        $this->line('');

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: NÃO chamando Inter. Cole a URL acima manualmente no portal Inter se quiser.');

            return self::SUCCESS;
        }

        $this->line('Chamando PUT /cobranca/v3/cobrancas/webhook do Inter...');

        try {
            $ok = $strategy->registrarWebhook($conta, $url);
        } catch (\Throwable $e) {
            $this->error('Falhou: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $ok) {
            $this->error('Inter retornou erro (ver lib retorna false em catch interno). Verifique scopes/cert/credenciais.');

            return self::FAILURE;
        }

        $this->info('Webhook registrado no Inter com sucesso.');
        $this->line("webhook_registered_at = {$conta->fresh()->webhook_registered_at}");

        return self::SUCCESS;
    }
}
