<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Gera o link assinado do canal público de feedback — US-INFRA-002 · ADR 0105 · ADR 0334.
 *
 * O [W] roda e manda a URL pro cliente (WhatsApp, e-mail, rodapé de nota). A partir daí o
 * cliente reporta SOZINHO — que é o ponto todo do órgão sensor da 0334: hoje o sinal só
 * entra quando alguém ouve e transcreve.
 *
 * Por que URL assinada e não tabela de token (o SPEC dizia "token único por biz, expira
 * 30d"): `temporarySignedRoute` entrega os dois requisitos nativamente — HMAC com APP_KEY
 * amarra o business à URL (adulterar ?biz dá 403) e a expiração é embutida. Zero estado
 * novo pra manter, migrar ou vazar. Revogar = rodar de novo (link novo) ou rotacionar
 * APP_KEY (invalida todos).
 */
class FeedbackLinkCommand extends Command
{
    protected $signature = 'feedback:link
                            {business_id : ID do business (ex: 4 = ROTA LIVRE)}
                            {--dias=30 : Validade do link em dias (ADR 0105: 30d)}
                            {--detail : Mostra o passo-a-passo e o que o cliente vai ver}';

    protected $description = 'Gera o link assinado do canal público de feedback pra um business';

    public function handle(): int
    {
        $businessId = (int) $this->argument('business_id');
        $dias = (int) $this->option('dias');

        if ($dias < 1) {
            $this->error('--dias precisa ser >= 1.');

            return self::FAILURE;
        }

        // SUPERADMIN: CLI sem sessão — o operador escolhe o business pelo argumento.
        $business = Business::withoutGlobalScopes()->find($businessId);

        if (! $business) {
            $this->error("Business {$businessId} não existe.");

            return self::FAILURE;
        }

        $url = URL::temporarySignedRoute(
            'feedback.form',
            now()->addDays($dias),
            ['biz' => $businessId],
        );

        $this->newLine();
        $this->line("  <fg=cyan>Canal de feedback — {$business->name}</> (biz={$businessId})");
        $this->newLine();
        $this->line("  {$url}");
        $this->newLine();
        $this->line("  <fg=gray>Válido por {$dias} dias (até ".now()->addDays($dias)->format('d/m/Y').').</>');
        $this->newLine();

        if ($this->option('detail')) {
            $this->line('  <fg=yellow>Como usar</>');
            $this->line('    1. Mande a URL pro cliente (WhatsApp, e-mail, rodapé de nota).');
            $this->line('    2. Ele abre, escreve a dor dele e diz o quanto dói (0-4).');
            $this->line('    3. O sinal cai em clients_feedbacks com canal=web_form.');
            $this->line('    4. Aparece em /atendimento/feedback junto do canal whatsapp.');
            $this->newLine();
            $this->line('  <fg=yellow>Notas</>');
            $this->line('    · O business está ASSINADO na URL: adulterar ?biz dá 403.');
            $this->line('    · Recorrência do mesmo sinal em 90d bumpa o contador, não duplica.');
            $this->line('    · Revogar: rode de novo (link novo) ou rotacione a APP_KEY (mata todos).');
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
