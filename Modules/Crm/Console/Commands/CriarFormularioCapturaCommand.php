<?php

declare(strict_types=1);

namespace Modules\Crm\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Modules\Crm\Entities\FormularioCaptura;

/**
 * Cria um formulário público de captura de lead para UMA empresa (US-CRM-096) e imprime a URL
 * do `action`. O responsável precisa ser usuário da mesma empresa. Tela de gestão fica para
 * quando houver empresa usando (ADR 0105).
 */
class CriarFormularioCapturaCommand extends Command
{
    protected $signature = 'crm:formulario-captura {business_id : empresa dona} {owner_user_id : responsável pelos negócios} {nome : nome do formulário} {--obrigado= : URL de agradecimento}';

    protected $description = 'Cria formulário público de captura de lead (origem + UTM no funil)';

    public function handle(): int
    {
        $biz = (int) $this->argument('business_id');
        $dono = (int) $this->argument('owner_user_id');

        if (! User::query()->whereKey($dono)->where('business_id', $biz)->exists()) {
            $this->error('Responsável não pertence a esta empresa.');

            return self::FAILURE;
        }

        $obrigado = $this->option('obrigado');
        if ($obrigado !== null && ! filter_var($obrigado, FILTER_VALIDATE_URL)) {
            $this->error('URL de agradecimento inválida.');

            return self::FAILURE;
        }

        $form = FormularioCaptura::query()->create([
            'business_id' => $biz,
            'chave' => FormularioCaptura::novaChave(),
            'nome' => (string) $this->argument('nome'),
            'owner_user_id' => $dono,
            'obrigado_url' => $obrigado,
            'ativo' => true,
        ]);

        $this->info('Formulário criado: '.url('/api/crm/captura/'.$form->chave));

        return self::SUCCESS;
    }
}
