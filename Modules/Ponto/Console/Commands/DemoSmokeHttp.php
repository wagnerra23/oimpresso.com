<?php

declare(strict_types=1);

namespace Modules\Ponto\Console\Commands;

use App\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

/**
 * Cliente HTTP em-processo do `ponto:demo-smoke`: manda a requisição pelo kernel HTTP do próprio
 * app (todos os middlewares, rotas e controllers), autenticado como o usuário dado — sem senha.
 *
 * Classe à parte porque `MakesHttpRequests::call()` colidiria com `Command::call()`.
 * O CSRF fica desligado SÓ neste processo (o formulário de login não é o que o smoke mede).
 */
final class DemoSmokeHttp
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    public function __construct(public $app)
    {
    }

    public function entrarComo(User $user): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->actingAs($user, 'web');
    }

    /**
     * Troca para o guard da API (Passport), como o app: token de acesso em processo, sem senha e
     * sem client OAuth. `Passport::actingAs` não usa Mockery (roda com o vendor de produção).
     */
    public function entrarComoApi(User $user): void
    {
        \Laravel\Passport\Passport::actingAs($user, [], 'api');
    }
}
