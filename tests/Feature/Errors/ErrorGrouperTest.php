<?php

declare(strict_types=1);

/**
 * Pest — ErrorGrouper: dedup + contador + decaimento (Fase 2 · E-2).
 *
 * Cobre "PRONTO QUANDO" do handoff erros-dedup:
 *  - 1000 exceções iguais → 1 linha em error_groups com count=1000
 *  - S0Alert dispara 1× na janela por dedup_key; reincidência só incrementa o contador
 *  - grupo sem ocorrência há N dias → arquivado (e reincidência reabre)
 *  - o alerta carrega o count
 *
 * Sem MySQL: a tabela de plataforma é criada sob demanda no beforeEach (mesmo
 * pattern do tests/Pest.php · RecurringBilling).
 *
 * @see prototipo-ui/handoffs/erros-dedup.md
 */

use App\Models\ErrorGroup;
use App\Notifications\S0Alert;
use App\Support\Errors\Audience;
use App\Support\Errors\Classification;
use App\Support\Errors\ClassifiedError;
use App\Support\Errors\ErrorClassifier;
use App\Support\Errors\ErrorGrouper;
use App\Support\Errors\ErrorReporter;
use App\Support\Errors\Severity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class GrpS0Exception extends RuntimeException implements ClassifiedError
{
    public function severity(): Severity { return Severity::S0; }

    public function audience(): Audience { return Audience::CONSTRUTOR; }

    public function owner(): string { return 'pagamento'; }

    public function operatorMessage(): string { return 'Pagamento indisponível.'; }
}

function mkClassification(string $key, Severity $sev = Severity::S1): Classification
{
    return new Classification($sev, Audience::CONSTRUTOR, 'plataforma', $key, 'Mensagem de recuperação.');
}

beforeEach(function () {
    if (! Schema::hasTable('error_groups')) {
        Schema::create('error_groups', function ($t) {
            $t->bigIncrements('id');
            $t->string('dedup_key', 64)->unique();
            $t->string('severity', 4)->index();
            $t->string('audience', 16);
            $t->string('owner', 60)->nullable();
            $t->unsignedBigInteger('count')->default(1);
            $t->string('status', 16)->default('open')->index();
            $t->timestamp('first_seen')->nullable();
            $t->timestamp('last_seen')->nullable();
            $t->json('sample_payload')->nullable();
            $t->timestamps();
        });
    }
});

it('1000 exceções iguais viram 1 linha com count=1000', function () {
    $grouper = new ErrorGrouper;
    $c = mkClassification('grp-1000');

    for ($i = 0; $i < 1000; $i++) {
        $grouper->record($c, ['exception' => 'RuntimeException', 'local' => 'X.php:10']);
    }

    expect(ErrorGroup::where('dedup_key', 'grp-1000')->count())->toBe(1)
        ->and(ErrorGroup::where('dedup_key', 'grp-1000')->first()->count)->toBe(1000);
});

it('record devolve o grupo com count corrente e amostra sem PII', function () {
    $grouper = new ErrorGrouper;
    $g1 = $grouper->record(mkClassification('grp-a'), ['exception' => 'X', 'local' => 'A.php:1']);
    $g2 = $grouper->record(mkClassification('grp-a'));

    expect($g1->count)->toBe(1)
        ->and($g2->count)->toBe(2)
        ->and($g1->sample_payload)->toBe(['exception' => 'X', 'local' => 'A.php:1']);
});

it('grupo sem ocorrência há N dias é arquivado; reincidência reabre', function () {
    $grouper = new ErrorGrouper;
    $grouper->record(mkClassification('grp-stale'));

    ErrorGroup::where('dedup_key', 'grp-stale')->update(['last_seen' => now()->subDays(20)]);

    expect($grouper->archiveStale(14))->toBe(1)
        ->and(ErrorGroup::where('dedup_key', 'grp-stale')->first()->status)->toBe('archived');

    // reincidência reabre o grupo arquivado.
    $reopened = $grouper->record(mkClassification('grp-stale'));
    expect($reopened->status)->toBe('open');
});

it('archiveStale não toca grupos recentes', function () {
    $grouper = new ErrorGrouper;
    $grouper->record(mkClassification('grp-fresh'));

    expect($grouper->archiveStale(14))->toBe(0)
        ->and(ErrorGroup::where('dedup_key', 'grp-fresh')->first()->status)->toBe('open');
});

it('S0 repetido: 1 alerta na janela, mas o grupo conta as 2 ocorrências', function () {
    config(['errors.s0_channel' => 'https://hooks.slack.com/services/T1/B2/secret']);
    Http::fake(['hooks.slack.com/*' => Http::response(['ok' => true], 200)]);

    $reporter = new ErrorReporter;
    $e = new GrpS0Exception('gateway fora');

    $reporter->report($e);
    $reporter->report($e); // reincidência na janela

    Http::assertSentCount(1); // rate-limit: 1 alerta por dedup_key/janela

    $key = (new ErrorClassifier)->dedupKey($e);
    expect(ErrorGroup::where('dedup_key', $key)->first()->count)->toBe(2);
});

it('S0Alert carrega o contador de ocorrências no payload', function () {
    $c = mkClassification('grp-count', Severity::S0);
    $payload = (new S0Alert($c, 42))->toWebhookPayload();

    expect(json_encode($payload))->toContain('42')
        ->and(json_encode($payload))->toContain('Ocorrências');
});
