<?php

use App\Business;
use App\Contact;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Tier 0 (ADR 0093) — o business_id de uma linha de activity_log é o do REGISTRO
 * auditado (subject), nunca o da sessão.
 *
 * Origem (medido 2026-10-01, run 36806843685 da lane auditoria-pest):
 *   - o trait LogsActivity não setava business_id → Contact/Transaction gravavam NULL
 *     e sumiam da tela /auditoria (AuditEntryService filtra por business_id);
 *   - Util::activityLog lia a SESSÃO antes do subject → superadmin com sessão do
 *     negócio A editando registro do B gravava a trilha do B no tenant A.
 *
 * Tenants fictícios 98 × 99 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('activity_log') || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (activity_log + contacts).');
    }

    $this->biz98 = $this->seededTenant();
    $this->biz99 = $this->seededSupportClientTenant();

    $novoContato = function (Business $biz, string $nome): Contact {
        $id = DB::table('contacts')->insertGetId([
            'business_id' => $biz->id,
            'type' => 'customer',
            'name' => $nome,
            'mobile' => '',
            'contact_id' => 'AUD-T0-'.uniqid(),
            'created_by' => $biz->owner_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Contact::query()->withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: fixture cross-tenant do teste
    };

    $this->contato98 = $novoContato($this->biz98, 'Contato T0 98');
    $this->contato99 = $novoContato($this->biz99, 'Contato T0 99');
});

/** Última linha de log do subject, sem filtro de tenant (o tenant é o que se mede). */
function t0_ultimoLog($subject): ?Activity
{
    return Activity::query()
        ->where('subject_type', get_class($subject))
        ->where('subject_id', $subject->getKey())
        ->latest('id')
        ->first();
}

function t0_sessaoDoNegocio(Business $biz): void
{
    session(['business' => $biz, 'user.business_id' => $biz->id]);
}

it('sem sessão: o trait LogsActivity grava o business_id do registro', function () {
    session()->flush();

    $this->contato99->name = 'Contato T0 99 editado sem sessão';
    $this->contato99->save();

    $log = t0_ultimoLog($this->contato99);
    expect($log)->not->toBeNull();
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
});

it('sem sessão: Util::activityLog grava o business_id do registro', function () {
    session()->flush();

    app(Util::class)->activityLog($this->contato98, 't0_sem_sessao', null, [], false);

    $log = t0_ultimoLog($this->contato98);
    expect($log->description)->toBe('t0_sem_sessao');
    expect((int) $log->business_id)->toBe((int) $this->biz98->id);
});

it('sessão de OUTRO negócio: o trait grava o tenant do registro, não o da sessão', function () {
    t0_sessaoDoNegocio($this->biz98);

    $this->contato99->name = 'Contato T0 99 editado com sessão 98';
    $this->contato99->save();

    $log = t0_ultimoLog($this->contato99);
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
    expect((int) $log->business_id)->not->toBe((int) $this->biz98->id);
});

it('sessão de OUTRO negócio: Util::activityLog grava o tenant do registro, não o da sessão', function () {
    t0_sessaoDoNegocio($this->biz98);

    app(Util::class)->activityLog($this->contato99, 't0_sessao_alheia', null, [], false);

    $log = t0_ultimoLog($this->contato99);
    expect($log->description)->toBe('t0_sessao_alheia');
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
});

it('business_id explícito do chamador NÃO vence o tenant do registro', function () {
    t0_sessaoDoNegocio($this->biz98);

    app(Util::class)->activityLog($this->contato99, 't0_explicito_divergente', null, [], false, $this->biz98->id);

    $log = t0_ultimoLog($this->contato99);
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
});

it('subject de cada negócio: cada log cai no próprio tenant, e a tela de um não vê o outro', function () {
    t0_sessaoDoNegocio($this->biz98);

    app(Util::class)->activityLog($this->contato98, 't0_cada_um', null, [], false);
    app(Util::class)->activityLog($this->contato99, 't0_cada_um', null, [], false);

    $doTenant = fn (Business $biz) => Activity::query()
        ->where('business_id', $biz->id)
        ->where('description', 't0_cada_um')
        ->pluck('subject_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($doTenant($this->biz98))->toBe([(int) $this->contato98->id]);
    expect($doTenant($this->biz99))->toBe([(int) $this->contato99->id]);
});

it('subject que É o negócio: o tenant é o próprio Business, não a sessão', function () {
    t0_sessaoDoNegocio($this->biz98);

    app(Util::class)->activityLog($this->biz99, 't0_subject_business', null, [], false);

    $log = t0_ultimoLog($this->biz99);
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
});

it('subject SEM tenant: explícito vence a sessão; sem explícito, cai na sessão', function () {
    $moeda = \App\Currency::query()->first();
    if (! $moeda) {
        $this->markTestSkipped('Sem currency no schema.');
    }
    t0_sessaoDoNegocio($this->biz98);

    app(Util::class)->activityLog($moeda, 't0_sem_tenant_explicito', null, [], false, $this->biz99->id);
    expect((int) t0_ultimoLog($moeda)->business_id)->toBe((int) $this->biz99->id);

    app(Util::class)->activityLog($moeda, 't0_sem_tenant_sessao', null, [], false);
    expect((int) t0_ultimoLog($moeda)->business_id)->toBe((int) $this->biz98->id);
});

/**
 * Log da PLATAFORMA (licenças do Officeimpresso): nunca recebe tenant de cliente.
 * [W] 2026-10-01: "as licenças são minhas, eu controlo as máquinas dos clientes. eles
 * não precisam ver isso". Antes do conserto ~24 linhas de licença já tinham saído com o
 * business_id de clientes (171, 216, 170...) pelo Util::activityLog.
 */
function t0_licencaDoCliente(Business $biz): \Modules\Officeimpresso\Entities\Licenca_Computador
{
    $lic = new \Modules\Officeimpresso\Entities\Licenca_Computador();
    $lic->setAttribute('id', 987654);
    $lic->setAttribute('business_id', $biz->id);

    return $lic;
}

it('licença (log da plataforma): Util::activityLog com tenant explícito do cliente grava SEM business_id', function () {
    t0_sessaoDoNegocio($this->biz98);
    $lic = t0_licencaDoCliente($this->biz99);

    app(Util::class)->activityLog($lic, 't0_licenca_util', null, [], false, $this->biz99->id);

    $log = Activity::query()->where('description', 't0_licenca_util')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->business_id)->toBeNull();
});

it('licença (log da plataforma): activity()->performedOn() também fica SEM business_id', function () {
    t0_sessaoDoNegocio($this->biz99);
    $lic = t0_licencaDoCliente($this->biz99);

    activity()->performedOn($lic)->log('t0_licenca_helper');

    $log = Activity::query()->where('description', 't0_licenca_helper')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->business_id)->toBeNull();
    // Controle: o mesmo caminho com um registro de negócio continua recebendo o tenant.
    activity()->performedOn($this->contato99)->log('t0_licenca_controle');
    expect((int) Activity::query()->where('description', 't0_licenca_controle')->latest('id')->value('business_id'))
        ->toBe((int) $this->biz99->id);
});

it('a lista de logs da plataforma aponta para classes que existem (rename não cala a regra)', function () {
    foreach (\App\Observers\ActivityCauserKindObserver::LOGS_DA_PLATAFORMA as $classe) {
        expect(class_exists($classe))->toBeTrue("classe da lista de plataforma sumiu: {$classe}");
    }
    expect(\App\Observers\ActivityCauserKindObserver::LOGS_DA_PLATAFORMA)->toHaveCount(2);
});

/**
 * Log SEM subject (activity()->withProperties([...])->log(), sem performedOn): não há
 * registro auditado, então vale o tenant que o CHAMADOR declarou. Origem (medido
 * 2026-10-01): os 16 logs `nfe.certificado` de prod saíram com business_id NULL porque o
 * CertificadoController só punha o tenant em properties — e sumiam da /auditoria.
 */
it('sem subject: o business_id declarado em properties vira a coluna', function () {
    t0_sessaoDoNegocio($this->biz98); // sessão de OUTRO negócio: não pode vencer o declarado

    activity('t0.sem_subject')->withProperties(['business_id' => $this->biz99->id])->log('t0.sem_subject.props');

    $log = Activity::query()->where('description', 't0.sem_subject.props')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect((int) $log->business_id)->toBe((int) $this->biz99->id);
});

it('sem subject: coluna setada pelo chamador vence o que está em properties', function () {
    activity('t0.sem_subject')
        ->withProperties(['business_id' => $this->biz99->id])
        ->tap(fn (Activity $a) => $a->setAttribute('business_id', $this->biz98->id))
        ->log('t0.sem_subject.coluna');

    $log = Activity::query()->where('description', 't0.sem_subject.coluna')->latest('id')->first();
    expect((int) $log->business_id)->toBe((int) $this->biz98->id);
});

it('sem subject e sem declaração: fica NULL (nunca cai na sessão aqui)', function () {
    t0_sessaoDoNegocio($this->biz98);

    activity('t0.sem_subject')->withProperties(['outra' => 'coisa'])->log('t0.sem_subject.nada');

    $log = Activity::query()->where('description', 't0.sem_subject.nada')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->business_id)->toBeNull();
});
