<?php

declare(strict_types=1);

namespace Modules\Fisio\Tests\Support;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Services\PortalTokenService;
use Tests\TestCase;

/**
 * Fixtures dos contratos da F1 "Constância" do Modules/Fisio (ADR 0437).
 *
 * POR QUE UMA CLASSE, E NÃO FUNÇÕES SOLTAS NOS ARQUIVOS DE TESTE
 * ------------------------------------------------------------
 * Treze arquivos Pest da MESMA suíte precisam do mesmo arranjo (tenant + usuário + contact +
 * paciente + programa + token + consentimento). Função global repetida em dois arquivos
 * colide ("cannot redeclare"); copiá-las com nomes diferentes faz as cópias drifarem. Mesmo
 * idioma de Modules/Repair/Tests/Support/JobSheetFixtures.php.
 *
 * Mora em `Tests/Support/` (e não em `Tests/Feature/Support/`) de propósito: o phpunit.xml
 * varre `Tests/Feature` recursivamente e um arquivo sem teste lá dentro vira warning.
 *
 * TENANT: o SEMEADO (biz=98, `seededTenant()`) contra o 99 (`seededSupportClientTenant()`),
 * ADR 0358. NUNCA biz=4 (cliente real), nunca biz=1 (empresa real). `App\Business` não tem
 * factory no repo — por isso o tenant não é fabricado aqui.
 *
 * ANTI-TAUTOLOGIA: a fixture fabrica só o ESTADO INICIAL (paciente, programa, check-ins
 * passados, consentimento dado na clínica). O que o teste mede — adesão, atenção, XP,
 * idempotência, registro de telemonitoramento — sai do código sob teste, nunca daqui.
 * Os passos que o PORTAL faz (PIN, consentimento de teleatendimento, check-in) passam pelas
 * rotas públicas reais, não por INSERT direto.
 *
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0437-modulo-fisio-vertical-fisioterapia.md
 */
final class FisioFixtures
{
    public const PACOTE = 'fisio_module';

    public const PERM_ACESSO = 'fisio.access';

    public const PERM_EDITAR = 'fisio.editar';

    public const CREFITO = 'CREFITO-10/123456-F';

    /** Valores de XP fixados pelo teste (o serviço DEVE ler `config('fisio.xp.*')`). */
    public const XP_EXERCICIO = 10;

    public const XP_CHECKIN = 5;

    public const TABELAS = [
        'business', 'contacts', 'users',
        'fisio_patients', 'fisio_records', 'fisio_consents', 'fisio_exercises', 'fisio_programs',
        'fisio_program_items', 'fisio_checkins', 'fisio_game_ledger', 'fisio_portal_tokens',
    ];

    /**
     * Guard de ambiente comum: schema UltimatePOS (business/contacts/users) e os triggers
     * append-only são MySQL-only; a lane sqlite `:memory:` roda sem migrate.
     * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
     *
     * @param  list<string>  $extras  tabelas além das do módulo (ex.: whatsapp_business_phones)
     */
    public static function motivoDeSkip(array $extras = []): ?string
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                return 'SQLite-incompatível: schema MySQL do UltimatePOS + triggers append-only (ADR 0358).';
            }
            foreach ([...self::TABELAS, ...$extras] as $t) {
                if (! Schema::hasTable($t)) {
                    return "Tabela {$t} ausente — migrations do Modules/Fisio (ou dependência) não rodaram.";
                }
            }
        } catch (\Throwable $e) {
            return 'Sem DB booted: '.$e->getMessage();
        }

        return null;
    }

    /** Configuração determinística que os testes assumem (o código lê de config/fisio.php). */
    public static function configurar(): void
    {
        config([
            'fisio.xp.exercicio_concluido' => self::XP_EXERCICIO,
            'fisio.xp.checkin' => self::XP_CHECKIN,
            'fisio.consent_versions.teleatendimento' => 'tele-v1',
            'fisio.consent_versions.whatsapp' => 'wa-v1',
            'fisio.portal.max_tentativas' => 5,
            'fisio.portal.lockout_minutos' => 30,
            'fisio.pain_alert_threshold_padrao' => 7,
            'fisio.red_flags' => ['dormencia_progressiva', 'perda_controle_esfincter', 'febre', 'dor_noturna_intensa', 'queda'],
        ]);
    }

    /**
     * Simula o pacote do superadmin SÓ para `fisio_module`, por business. O resto do
     * ModuleUtil roda de verdade (partialMock — idioma do ProducaoApontamentoTelaTest).
     *
     * @param  list<int>  $bizComPacote
     */
    public static function pacote(array $bizComPacote): void
    {
        // `partialMock()` do TestCase é protected — aqui o idioma do ClienteVeiculosModuleGateTest
        // (Mockery partial + app()->instance). Exige que o Fisio resolva o ModuleUtil pelo
        // container (injeção/app()), nunca `new ModuleUtil()` (ver README §Suposições).
        $GLOBALS['fisio_pacote'] = array_map('intval', $bizComPacote);
        $fake = \Mockery::mock(ModuleUtil::class)->makePartial();
        $fake->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $perm) => $perm === self::PACOTE && in_array((int) $biz, $GLOBALS['fisio_pacote'], true));
        app()->instance(ModuleUtil::class, $fake);
    }

    /** Liga/desliga o pacote de um business depois do mock montado. */
    public static function trocarPacote(int $biz, bool $ativo): void
    {
        $lista = array_values(array_diff($GLOBALS['fisio_pacote'] ?? [], [$biz]));
        if ($ativo) {
            $lista[] = $biz;
        }
        $GLOBALS['fisio_pacote'] = $lista;
    }

    /** Autentica e fixa a empresa da sessão (o global scope lê `user.business_id`). */
    public static function como(TestCase $t, User $user): void
    {
        $t->actingAs($user);
        session(['user.business_id' => (int) $user->business_id]);
    }

    public static function contato(int $biz, int $createdBy, string $nome = 'Paciente Fixture', ?string $mobile = '48999990000'): int
    {
        // contacts.created_by é FK NOT NULL -> users.id (medido no CT 100 pelo ClienteVeiculosModuleGateTest).
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $biz,
            'type' => 'customer',
            'name' => $nome.' '.uniqid(),
            'mobile' => $mobile,
            'contact_status' => 'active',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $attrs */
    public static function paciente(int $biz, int $createdBy, array $attrs = []): Patient
    {
        $contactId = $attrs['contact_id'] ?? self::contato($biz, $createdBy);
        unset($attrs['contact_id']);

        // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
        return Patient::withoutGlobalScopes()->create(array_merge([
            'business_id' => $biz,
            'contact_id' => $contactId,
            'main_complaint' => 'Lombalgia mecânica',
            'goals' => ['voltar a correr 5 km'],
            'sessions_per_week' => 3,
            'status' => 'ativo',
            'condition_flags' => [],
            'blocked_at' => null,
        ], $attrs));
    }

    /** @param  array<string, mixed>  $attrs  business_id null = biblioteca global */
    public static function exercicio(?int $biz, string $nome = 'Ponte glútea', array $attrs = []): Exercise
    {
        // SUPERADMIN: exercício global (business_id null) e de duas empresas; escopo provado nos asserts.
        return Exercise::withoutGlobalScopes()->create(array_merge([
            'business_id' => $biz,
            'name' => $nome.' '.uniqid(),
            'region' => 'lombar',
            'type' => 'fortalecimento',
            'pose' => 'deitado',
            'steps' => ['Deite de barriga para cima', 'Eleve o quadril', 'Desça devagar'],
            'default_sets' => 3,
            'default_reps' => 12,
            'default_hold_s' => 0,
            'times_per_day' => 1,
            'safety_notes' => ['Não prenda a respiração'],
            'contraindication_flags' => [],
            'needs_support' => false,
            'is_custom' => $biz !== null,
        ], $attrs));
    }

    /**
     * Programa ATIVO com os exercícios na ordem dada (position 1..n).
     *
     * @param  list<Exercise>  $exercicios
     * @param  array<string, mixed>  $attrs
     */
    public static function programa(Patient $p, array $exercicios, int $createdBy, array $attrs = []): Program
    {
        // SUPERADMIN: fixture; escopo provado nos asserts.
        $prog = Program::withoutGlobalScopes()->create(array_merge([
            'business_id' => $p->business_id,
            'patient_id' => $p->id,
            'phase' => 'fase_1',
            'status' => 'ativo',
            'origin' => 'manual',
            'stop_instruction' => 'Pare os exercícios de hoje, aplique gelo 15 min e me mande mensagem.',
            'pain_alert_threshold' => null,
            'created_by' => $createdBy,
            'starts_at' => now()->toDateString(),
        ], $attrs));

        foreach (array_values($exercicios) as $i => $ex) {
            ProgramItem::query()->create([
                'program_id' => $prog->id,
                'exercise_id' => $ex->id,
                'sets' => $ex->default_sets,
                'reps' => $ex->default_reps,
                'hold_s' => $ex->default_hold_s,
                'times_per_day' => $ex->times_per_day ?? 1,
                'notes' => null,
                'position' => $i + 1,
            ]);
        }

        return $prog->fresh();
    }

    /** @return list<int> ids dos itens na ordem de position */
    public static function itens(Program $prog): array
    {
        // SUPERADMIN: helper de arranjo/verificação — o UC-FPS-01 lê os itens do programa da 98 com a
        // sessão da 99 para provar que nada mudou. O ProgramItem tem escopo próprio (US-FISIO-003).
        return ProgramItem::withoutGlobalScopes()->where('program_id', $prog->id)->orderBy('position')->pluck('id')->map('intval')->all();
    }

    /**
     * Check-in de HISTÓRICO (dias passados) — estado inicial do painel. Check-in do dia, no
     * teste de portal, passa sempre pela rota pública.
     *
     * @param  list<int>  $doneItemIds
     * @param  list<string>  $redFlags
     */
    public static function checkin(Patient $p, ?Program $prog, string $date, array $doneItemIds, ?int $pain = null, string $effort = 'adequado', array $redFlags = []): Checkin
    {
        // SUPERADMIN: fixture de histórico; quem lê é o painel, sob escopo.
        return Checkin::withoutGlobalScopes()->create([
            'business_id' => $p->business_id,
            'patient_id' => $p->id,
            'program_id' => $prog?->id,
            'date' => $date,
            'done_item_ids' => $doneItemIds,
            'pain' => $pain,
            'effort' => $effort,
            'note' => null,
            'red_flags' => $redFlags,
        ]);
    }

    /** Consentimento dado na clínica (não pelo portal) — ex.: WhatsApp antes do 1º envio. */
    public static function consentir(Patient $p, string $purpose, ?string $version = null, ?string $revokedAt = null): int
    {
        return (int) DB::table('fisio_consents')->insertGetId([
            'business_id' => $p->business_id,
            'patient_id' => $p->id,
            'purpose' => $purpose,
            'version' => $version ?? (string) config("fisio.consent_versions.{$purpose}"),
            'granted_at' => now(),
            'revoked_at' => $revokedAt,
            'ip' => '127.0.0.1',
            'channel' => 'clinica',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Prop (deferida ou não) pedida como o browser pede num partial reload. Idioma do
     * ProducaoRoteirosContratoTest — funciona com `Inertia::defer` e com prop comum.
     */
    public static function props(TestCase $t, User $user, string $rota, string $componente, string $prop): array
    {
        self::como($t, $user);
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $r = $t->get($rota, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => $componente]);
        $r->assertOk();

        return (array) $r->json('props.'.$prop);
    }

    /** Props completas da página pública (visita Inertia, sem auth). */
    public static function portalProps(TestCase $t, string $token): array
    {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $r = $t->get('/fisio/p/'.$token, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao]);
        $r->assertOk();
        expect($r->json('component'))->toBe('Fisio/Public/Portal');

        return (array) $r->json('props');
    }

    /**
     * Emite token+PIN pelo serviço real (o mesmo que o job do WhatsApp usa).
     *
     * @return array{token: string, pin: string}
     */
    public static function emitirPortal(Patient $p): array
    {
        $r = app(PortalTokenService::class)->emitir($p);

        return ['token' => (string) $r['token'], 'pin' => (string) $r['pin']];
    }

    /**
     * Portal pronto para check-in: token emitido, PIN aceito e consentimento de
     * teleatendimento dado — pelas ROTAS públicas.
     */
    public static function portalPronto(TestCase $t, Patient $p): string
    {
        $g = self::emitirPortal($p);
        $t->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();
        $t->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'teleatendimento', 'aceito' => true])->assertCreated();

        return $g['token'];
    }

    /** Número outbound do WhatsApp do business (resolvido por handles_outbound_default, como no Oficina). */
    public static function telefoneWhatsapp(int $biz): int
    {
        return (int) DB::table('whatsapp_business_phones')->insertGetId([
            'business_id' => $biz,
            'phone_uuid' => (string) Str::uuid(),
            'label' => 'Fisio teste',
            'driver' => 'meta_cloud',
            'fallback_driver' => 'meta_cloud',
            'handles_outbound_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Soma do ledger de um paciente (XP é DERIVADO do ledger, nunca coluna). */
    public static function xp(Patient $p): int
    {
        return (int) DB::table('fisio_game_ledger')->where('business_id', $p->business_id)->where('patient_id', $p->id)->sum('xp');
    }
}
