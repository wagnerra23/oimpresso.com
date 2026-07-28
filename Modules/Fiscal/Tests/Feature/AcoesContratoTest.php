<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\Fiscal\Http\Controllers\NfeCockpitController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Services\NfeService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `Fiscal/Nfe` — as regras REAIS do Controller (US-FISCAL-012/013/014).
 *
 * =====================================================================================
 * POR QUE ESTE ARQUIVO EXISTE (e por que o `AcoesControllerTest` não bastava)
 * =====================================================================================
 * O `AcoesControllerTest` tem 18 casos, e 13 deles **re-declaram a regra dentro do próprio
 * teste** em vez de exercitar o Controller:
 *
 *     $validator = validator(['motivo' => 'curto'], ['motivo' => ['min:15', ...]]);
 *     expect($validator->fails())->toBeTrue();
 *
 * Isso testa o Laravel, não o produto: se alguém trocar `min:15` por `min:5` no
 * `AcoesController`, aquele teste **continua verde**. Mesmo vício nos que assertam um array
 * literal escrito uma linha acima (`$acoesValidas = [...]; expect($acoesValidas)->toHaveCount(4)`).
 * É a lápide §5 2026-06-05 (teste que deriva do próprio teste/código é tautológico — pior que
 * ausente, porque parece cobertura).
 *
 * Aqui a asserção é o contrário: os NÚMEROS vêm da lei (CONFAZ) e as REGRAS vêm da produção —
 * invocamos `AcoesController::<metodo>` de verdade e checamos que ele rejeita/aceita. Trocar o
 * limite no Controller **quebra** estes testes.
 *
 * =====================================================================================
 * POR QUE RODA SEM BANCO (e por que isso importa)
 * =====================================================================================
 * A lane de CI roda SQLite in-memory e o staging do CT 100 não tem as migrations do NfeBrasil —
 * então todo teste que toca `nfe_emissoes` (ou `permissions`, via Spatie) **skipa nas duas**.
 * Um teste que nunca executa é cobertura de papel.
 *
 * Nos métodos deste Controller a ordem é: permissão → `validate()` → query. Trocando o gate de
 * permissão por um duplo de usuário que responde `can() === true` **sem consultar o banco**,
 * a validação passa a ser exercitada de verdade em qualquer lane. O caso "payload válido"
 * asserta apenas **NÃO ser `ValidationException`** — nunca o erro seguinte, que depende do
 * ambiente ter (ou não) as tabelas.
 *
 * @see resources/js/Pages/Fiscal/Nfe.casos.md — UC-FNFE-02/04/05/06/07
 * @see memory/requisitos/Fiscal/SPEC.md — US-FISCAL-012/013/014
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

/** Usuário autorizado SEM hit no banco (Spatie consultaria `permissions`). */
function fiscalUsuarioAutorizado(): void
{
    Auth::setUser(new class extends \App\User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });
}

/** Invoca e devolve a exceção lançada (ou null). */
function fiscalCapturar(callable $fn): ?\Throwable
{
    try {
        $fn();

        return null;
    } catch (\Throwable $e) {
        return $e;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-04 · Cancelamento exige motivo de 15 a 255 caracteres (CONFAZ SINIEF 07/2005)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-FNFE-04 · cancelarNfe REJEITA motivo com menos de 15 chars (regra do Controller)', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->cancelarNfe(
        Request::create('/x', 'POST', ['motivo' => 'curto']),
        app(NfeService::class),
        1,
    ));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($e->errors()))->toContain('motivo');
});

it('UC-FNFE-04 · cancelarNfe REJEITA motivo acima de 255 chars', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->cancelarNfe(
        Request::create('/x', 'POST', ['motivo' => str_repeat('a', 256)]),
        app(NfeService::class),
        1,
    ));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($e->errors()))->toContain('motivo');
});

it('UC-FNFE-04 · cancelarNfe ACEITA motivo válido (não barra na validação)', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->cancelarNfe(
        Request::create('/x', 'POST', ['motivo' => 'Cliente desistiu pos-emissao, refaturado']),
        app(NfeService::class),
        1,
    ));

    // Passou da validação. O que acontece depois (query/serviço) depende do ambiente.
    expect($e)->not->toBeInstanceOf(ValidationException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-05 · CC-e: texto 15–1000 e sequência 1–20 (CONFAZ SINIEF 07/2005 Art. 14)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-FNFE-05 · cartaCorrecao REJEITA texto fora de 15–1000 chars', function () {
    fiscalUsuarioAutorizado();

    foreach (['curto' => 'curto', 'longo' => str_repeat('a', 1001)] as $rotulo => $texto) {
        $e = fiscalCapturar(fn () => (new AcoesController())->cartaCorrecao(
            Request::create('/x', 'POST', ['texto_correcao' => $texto, 'n_seq_evento' => 1]),
            app(NfeCartaCorrecaoService::class),
            1,
        ));

        expect($e)->toBeInstanceOf(ValidationException::class, "texto {$rotulo} deve falhar")
            ->and(array_keys($e->errors()))->toContain('texto_correcao');
    }
});

it('UC-FNFE-05 · cartaCorrecao REJEITA n_seq_evento fora de 1–20 (máx. 20 CC-e por NF-e)', function () {
    fiscalUsuarioAutorizado();

    foreach ([0, 21, -1, 100] as $seq) {
        $e = fiscalCapturar(fn () => (new AcoesController())->cartaCorrecao(
            Request::create('/x', 'POST', [
                'texto_correcao' => 'Endereco do destinatario corrigido',
                'n_seq_evento'   => $seq,
            ]),
            app(NfeCartaCorrecaoService::class),
            1,
        ));

        expect($e)->toBeInstanceOf(ValidationException::class, "seq={$seq} deve falhar")
            ->and(array_keys($e->errors()))->toContain('n_seq_evento');
    }
});

it('UC-FNFE-05 · cartaCorrecao ACEITA texto e sequência válidos', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->cartaCorrecao(
        Request::create('/x', 'POST', [
            'texto_correcao' => 'Endereco do destinatario corrigido para Rua A, 1234',
            'n_seq_evento'   => 1,
        ]),
        app(NfeCartaCorrecaoService::class),
        1,
    ));

    expect($e)->not->toBeInstanceOf(ValidationException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-06 · Inutilização: modelo 55/65, faixa coerente, justificativa ≥15
// ─────────────────────────────────────────────────────────────────────────────

it('UC-FNFE-06 · inutilizar REJEITA modelo fora da whitelist 55/65', function () {
    fiscalUsuarioAutorizado();

    foreach (['54', '56', 'abc'] as $modelo) {
        $e = fiscalCapturar(fn () => (new AcoesController())->inutilizar(
            Request::create('/x', 'POST', [
                'modelo' => $modelo, 'serie' => '1', 'numero_de' => 1, 'numero_ate' => 5,
                'justificativa' => 'NFe rejeitada SEFAZ — inutilizando faixa',
            ]),
            app(NfeInutilizacaoService::class),
        ));

        expect($e)->toBeInstanceOf(ValidationException::class, "modelo={$modelo} deve falhar")
            ->and(array_keys($e->errors()))->toContain('modelo');
    }
});

it('UC-FNFE-06 · inutilizar REJEITA faixa invertida (numero_ate < numero_de)', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->inutilizar(
        Request::create('/x', 'POST', [
            'modelo' => '55', 'serie' => '1', 'numero_de' => 100, 'numero_ate' => 50,
            'justificativa' => 'NFe rejeitada SEFAZ — inutilizando faixa',
        ]),
        app(NfeInutilizacaoService::class),
    ));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($e->errors()))->toContain('numero_ate');
});

it('UC-FNFE-06 · inutilizar REJEITA justificativa com menos de 15 chars', function () {
    fiscalUsuarioAutorizado();

    $e = fiscalCapturar(fn () => (new AcoesController())->inutilizar(
        Request::create('/x', 'POST', [
            'modelo' => '55', 'serie' => '1', 'numero_de' => 1, 'numero_ate' => 5,
            'justificativa' => 'curto',
        ]),
        app(NfeInutilizacaoService::class),
    ));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_keys($e->errors()))->toContain('justificativa');
});

it('UC-FNFE-06 · inutilizar ACEITA payload válido nos dois modelos', function () {
    fiscalUsuarioAutorizado();

    foreach (['55', '65'] as $modelo) {
        $e = fiscalCapturar(fn () => (new AcoesController())->inutilizar(
            Request::create('/x', 'POST', [
                'modelo' => $modelo, 'serie' => '1', 'numero_de' => 100, 'numero_ate' => 105,
                'justificativa' => 'NFe rejeitada SEFAZ cstat 539 — inutilizando faixa',
            ]),
            app(NfeInutilizacaoService::class),
        ));

        expect($e)->not->toBeInstanceOf(ValidationException::class, "modelo={$modelo} deve passar");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-07 · Manifestação DF-e: whitelist de 4 ações + justificativa condicional
// ─────────────────────────────────────────────────────────────────────────────

it('UC-FNFE-07 · UC-FDFE-03 · manifestarDfe REJEITA ação fora da whitelist canon SEFAZ', function () {
    fiscalUsuarioAutorizado();

    foreach (['cancelar', 'aprovar', 'rejeitar'] as $acao) {
        $e = fiscalCapturar(fn () => (new AcoesController())->manifestarDfe(
            Request::create('/x', 'POST', []),
            app(ManifestacaoService::class),
            1,
            $acao,
        ));

        expect($e)->toBeInstanceOf(NotFoundHttpException::class, "ação {$acao} deve ser recusada");
    }
});

it('UC-FNFE-07 · UC-FDFE-04 · manifestarDfe EXIGE justificativa em desconhecer/nao_realizada', function () {
    fiscalUsuarioAutorizado();

    foreach (['desconhecer', 'nao_realizada'] as $acao) {
        $e = fiscalCapturar(fn () => (new AcoesController())->manifestarDfe(
            Request::create('/x', 'POST', []), // sem justificativa
            app(ManifestacaoService::class),
            1,
            $acao,
        ));

        expect($e)->toBeInstanceOf(ValidationException::class, "{$acao} deve exigir justificativa")
            ->and(array_keys($e->errors()))->toContain('justificativa');
    }
});

it('UC-FNFE-07 · UC-FDFE-04 · manifestarDfe NÃO exige justificativa em cienciar/confirmar', function () {
    fiscalUsuarioAutorizado();

    foreach (['cienciar', 'confirmar'] as $acao) {
        $e = fiscalCapturar(fn () => (new AcoesController())->manifestarDfe(
            Request::create('/x', 'POST', []),
            app(ManifestacaoService::class),
            1,
            $acao,
        ));

        expect($e)->not->toBeInstanceOf(ValidationException::class, "{$acao} não deve exigir justificativa");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-02 · Janela legal de cancelamento — 24h NFC-e (65) × 168h NF-e (55)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-FNFE-02 · isCancelavel do Controller respeita 24h NFC-e vs 168h NF-e', function () {
    // Invoca o método REAL do NfeCockpitController. O caso equivalente em
    // NfeCockpitMultiTenantTest re-implementa a fórmula dentro do próprio teste
    // (um clone da lógica) — passaria mesmo se o Controller mudasse a janela.
    $metodo = new ReflectionMethod(NfeCockpitController::class, 'isCancelavel');
    $metodo->setAccessible(true);
    $controller = new NfeCockpitController();

    $nota = fn (string $modelo, int $horas, string $status = 'autorizada') => new NfeEmissao([
        'modelo'     => $modelo,
        'status'     => $status,
        'emitido_em' => now()->subHours($horas),
    ]);

    expect($metodo->invoke($controller, $nota('65', 10)))->toBeTrue('NFC-e 10h < 24h')
        ->and($metodo->invoke($controller, $nota('65', 30)))->toBeFalse('NFC-e 30h > 24h')
        ->and($metodo->invoke($controller, $nota('55', 48)))->toBeTrue('NF-e 48h < 168h')
        ->and($metodo->invoke($controller, $nota('55', 200)))->toBeFalse('NF-e 200h > 168h')
        ->and($metodo->invoke($controller, $nota('55', 1, 'rejeitada')))->toBeFalse('só nota autorizada é cancelável');
});
