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

// ─────────────────────────────────────────────────────────────────────────────
// UC-FNFE-09 · Retransmissão PRESERVA a nota antiga (CONFAZ SINIEF 07/2005 Art. 14)
//
// Âncora: CU-FISC-11 do SDD §6.2 — os DOIS itens dele:
//   1. [reg] `forceDelete()` nunca é usado — documento fiscal é imutável
//   2. [must] status fora de {rejeitada, denegada, erro_envio} é recusado
//
// POR QUE ESTÁTICO, E O QUE ISSO NÃO PROVA
// ─────────────────────────────────────────
// O caminho de runtime não é exercitável em nenhuma lane de hoje: tanto
// `AcoesController::retransmitir` quanto `NfeService::retransmitirInterno` fazem a
// query (`firstOrFail()` / `find()`) ANTES de checar o status, e `nfe_emissoes` não
// existe nem na lane SQLite do CI nem no staging do CT 100. O teste de comportamento
// segue declarado como [BACKLOG] em `Nfe.casos.md` — esta asserção não o substitui.
//
// O que ESTE caso prova é a invariante ESTRUTURAL, e ela morde: trocar o
// `$emissao->update([...])` por um delete, ou mexer na whitelist, deixa vermelho.
// A whitelist é lida do FONTE DE PRODUÇÃO — a diferença que separa isto do
// `NfeServiceRetransmitirTest`, que declara `$whitelist = [...]` dentro do próprio
// teste e por isso continuaria verde se o Service mudasse (lápide §5 2026-06-05).
//
// Precedente da técnica no repo: `Wave28ArquivosSaturationTest` e
// `ArquivosAdminControllerTest` já assertam ausência de delete sobre o fonte real.
// ─────────────────────────────────────────────────────────────────────────────

/** Corpo-fonte de um método, localizado por reflection (não por busca de string). */
function fiscalCorpoDoMetodo(string $classe, string $metodo): string
{
    $rm = new ReflectionMethod($classe, $metodo);
    $linhas = file($rm->getFileName());
    $corpo = implode('', array_slice(
        $linhas,
        $rm->getStartLine() - 1,
        $rm->getEndLine() - $rm->getStartLine() + 1,
    ));

    // Controle positivo: se a extração falhar, o teste tem de morrer aqui — e não
    // passar por vacuidade (um `not->toContain` sobre string vazia é sempre verde).
    expect(strlen($corpo))->toBeGreaterThan(200);

    return $corpo;
}

it('UC-FNFE-09 · retransmitir PRESERVA a nota antiga — nunca deleta (CONFAZ Art. 14)', function () {
    $corpo = fiscalCorpoDoMetodo(NfeService::class, 'retransmitirInterno');

    // [reg] CONFAZ Art. 14: a NfeEmissao nunca é hard-deletada, nem soft-deletada
    // (`->delete(`), nem destruída em massa (`::destroy(`).
    //
    // ⚠️ SEM mensagem nos `toContain`: no Pest ele é VARIÁDICO (`...$needles`), então um
    // 2º argumento vira NEEDLE, não descrição — a explicação mora no comentário. Foi
    // exatamente assim que estes 2 casos nasceram vermelhos, e é a classe que o #4918 já
    // limpou 38× no repo (§5 2026-07-28).
    expect($corpo)->not->toContain('forceDelete')
        ->and($corpo)->not->toContain('->delete(')
        ->and($corpo)->not->toContain('::destroy(');

    // O que ela faz NO LUGAR de deletar: marca `inutilizada`, zera o `transaction_id`
    // (libera a UNIQUE biz+tx) e preserva o vínculo da nota antiga no metadata.
    expect($corpo)->toContain("'status' => 'inutilizada'")
        ->and($corpo)->toContain("'transaction_id' => null")
        ->and($corpo)->toContain('original_transaction_id');
});

it('UC-FNFE-09 · whitelist de status retransmissível vem do Service, não do teste', function () {
    $corpo = fiscalCorpoDoMetodo(NfeService::class, 'retransmitirInterno');
    $normalizado = preg_replace('/\s+/', ' ', $corpo);

    // [must] exatamente os 3 status retransmissíveis do CU-FISC-11 item 2, e a prova
    // de que a lista REJEITA — não fica declarada sem uso. Mexer nisto no Service
    // derruba este caso: é a diferença entre asserção e re-declaração da regra.
    expect($normalizado)->toContain("statusValidos = ['rejeitada', 'denegada', 'erro_envio']")
        ->and($normalizado)->toContain('in_array($emissao->status, $statusValidos, true)')
        ->and($normalizado)->toContain('throw new InvalidArgumentException');
});
