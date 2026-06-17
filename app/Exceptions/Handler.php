<?php

namespace App\Exceptions;

use App\Support\Errors\ErrorClassifier;
use App\Support\Errors\ErrorReporter;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // Fase 1 · Plano Sustentável de Erros (E-1): carimba todo erro na origem
        // (severidade/público/dono/dedupKey) → audita → só o S0 alerta 1 humano.
        // Estende o Handler do projeto (não substitui). ErrorReporter é resiliente.
        // @see prototipo-ui/handoffs/erros-fase1-classificacao.md
        $reporter = app(ErrorReporter::class);

        $this->reportable(function (Throwable $e) use ($reporter) {
            try {
                $reporter->report($e, request());
            } catch (Throwable) {
                // Reporter nunca pode derrubar o tratamento de erro — engole e segue.
            }
        });

        // render() pro operador: mensagem de recuperação, NUNCA o trace.
        $this->renderable(function (Throwable $e, $request) {
            $c = (new ErrorClassifier)->classify($e, $request);

            if (! ErrorReporter::shouldRenderOperatorMessage($c, $request)) {
                return null; // cai no tratamento padrão do framework.
            }

            return response()->json(['message' => $c->operatorMessage], 500);
        });
    }
}
