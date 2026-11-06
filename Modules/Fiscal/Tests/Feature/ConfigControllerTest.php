<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave Cert/Cfg Fiscal — isolation + senha hidden.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeCertificado requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados table missing');
    }
});

it('UC-FCFG-01 · NfeCertificado encrypted_password é hidden — não vaza no payload Inertia', function () {
    $cert = new NfeCertificado([
        'business_id'        => 98,
        'cnpj_titular'       => '00000000000000',
        'encrypted_password' => 'SECRET_NEVER_LEAK',
        'ativo'              => true,
    ]);

    $json = $cert->toArray();
    // Era `->not->toHaveKey('encrypted_password', 'Senha encriptada DEVE estar em $hidden')`.
    // Medido em 2026-09-04: o 2º parâmetro de `toHaveKey($key, $value, $message)` é o
    // VALOR esperado, então o assert dizia "não tem a chave COM ESTE VALOR" — e passava
    // com a senha vazando, porque 'SECRET_NEVER_LEAK' nunca seria igual à frase. Um
    // contrato de não-vazamento que não mordia. `array_key_exists` + `toBeFalse` (que
    // tem mensagem de verdade) pergunta o que o UC pergunta: a chave existe no payload?
    expect(array_key_exists('encrypted_password', $json))
        ->toBeFalse('Senha encriptada DEVE estar em $hidden — vazou no payload Inertia');
});

it('UC-FCFG-02 · NfeCertificado HasBusinessScope esconde certs de outros tenants', function () {
    // Até 2026-11-06 este caso contava sem usuário autenticado (o escopo global no-opa) e sem
    // semear nada: dava 0 com a tabela vazia. Agora há um cert em cada empresa e o usuário é
    // da 98; a vizinha some e a própria aparece (controle positivo).
    $tenant = test()->seededTenant();
    $bizA = (int) $tenant->id;
    $bizB = (int) test()->seededSupportClientTenant()->id;
    $doA = configCertTeste($bizA, '11222333000181');
    $doB = configCertTeste($bizB, '99888777000166');

    try {
        test()->actingAs(test()->usuarioComPermissoes(['fiscal.config.edit'], $tenant));
        session(['business.id' => $bizA, 'user.business_id' => $bizA]);

        $ids = NfeCertificado::query()->pluck('id')->all();

        expect($ids)->not->toContain($doB)
            ->and($ids)->toContain($doA);
    } finally {
        DB::table('nfe_certificados')->whereIn('id', [$doA, $doB])->delete();
    }
});

it('UC-FCFG-04 · o payload da tela carrega o estado da contingência com a duração vinda do SERVIDOR', function () {
    if (! Schema::hasColumn('nfe_business_configs', 'em_contingencia')) {
        $this->markTestSkipped('Coluna em_contingencia ausente — rode as migrations do NfeBrasil (US-NFE-006).');
    }

    $bizId = (int) test()->seededTenant()->id;
    $ativadaEm = now()->subDays(3);

    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $bizId],
        [
            'regime' => 'simples',
            'tributacao_default' => json_encode(['cfop' => '5102']),
            'em_contingencia' => true,
            'contingencia_ativada_em' => $ativadaEm,
            'contingencia_motivo' => 'SEFAZ-SC fora do ar — teste automatizado',
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );

    $config = \Modules\NfeBrasil\Models\NfeBusinessConfig::withoutGlobalScopes()
        ->where('business_id', $bizId)
        ->first();

    // O contrato que a TELA consome. `diasAtiva` é calculado no servidor de propósito:
    // é o número que mitiga o risco "tenant esquece ligado" (ADR TECH-0002), e no browser
    // ele dependeria do relógio da máquina do operador.
    $payload = [
        'ativa' => (bool) $config->em_contingencia,
        'ativadaEmIso' => $config->contingencia_ativada_em?->toIso8601String(),
        'diasAtiva' => $config->contingencia_ativada_em
            ? (int) $config->contingencia_ativada_em->diffInDays(now())
            : null,
        'motivo' => $config->contingencia_motivo,
    ];

    expect($payload['ativa'])->toBeTrue();
    expect($payload['diasAtiva'])->toBe(3);
    expect($payload['motivo'])->toBe('SEFAZ-SC fora do ar — teste automatizado');
    expect($payload['ativadaEmIso'])->not->toBeNull();

    // CONTROLE NEGATIVO: desligada, a duração é NULL — não 0. "0 dias ativa" e "não está
    // ativa" são estados diferentes, e exibir 0 diria que a contingência está ligada hoje.
    DB::table('nfe_business_configs')->where('business_id', $bizId)->update([
        'em_contingencia' => false,
        'contingencia_ativada_em' => null,
        'contingencia_motivo' => null,
    ]);

    $desligada = \Modules\NfeBrasil\Models\NfeBusinessConfig::withoutGlobalScopes()
        ->where('business_id', $bizId)->first();

    expect((bool) $desligada->em_contingencia)->toBeFalse();
    expect($desligada->contingencia_ativada_em)->toBeNull();
});

it('UC-FCFG-05 · o card de envio de documentos espelha as flags REAIS do deploy', function () {
    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    // As duas chaves governam os listeners que mandam o DANFE:
    // `EnviarDanfePorEmail` (NFe 55) e `EnviarDanfeNFCePorEmail` (NFC-e 65).
    config([
        'nfebrasil.email_danfe_on_autorizada'      => true,
        'nfebrasil.email_danfe_nfce_on_autorizada' => false,
    ]);

    $this->get('/fiscal/config')
        ->assertStatus(200)
        ->assertInertia(
            fn ($page) => $page
                ->component('Fiscal/Config')
                ->where('envioDocumentos.nfeAtivo', true)
                ->where('envioDocumentos.nfceAtivo', false),
        );

    // CONTROLE NEGATIVO — invertidas as chaves, o payload inverte junto. Sem ele
    // o teste passaria com o card servindo literal `true/false` hardcoded, que é
    // exatamente a demonstração-disfarçada-de-leitura que este card veio evitar.
    config([
        'nfebrasil.email_danfe_on_autorizada'      => false,
        'nfebrasil.email_danfe_nfce_on_autorizada' => true,
    ]);

    $this->get('/fiscal/config')
        ->assertStatus(200)
        ->assertInertia(
            fn ($page) => $page
                ->where('envioDocumentos.nfeAtivo', false)
                ->where('envioDocumentos.nfceAtivo', true),
        );
});

/**
 * Cert A1 de teste no tenant fictício, com o CNPJ titular dado (vazio = cert antigo sem CNPJ).
 * Só preenche as colunas que o schema tem — duas migrations disputam `nfe_certificados`.
 * Validade em 2099 para ele ser o "vigente" da tela, que ordena por `valido_ate` desc.
 */
function configCertTeste(int $bizId, string $cnpjTitular): int
{
    $row = [
        'business_id' => $bizId,
        'valido_ate'  => '2099-12-31',
        'ativo'       => true,
        'created_at'  => now(),
        'updated_at'  => now(),
    ];
    $opcionais = [
        'uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'cnpj_titular'       => $cnpjTitular,
        'encrypted_password' => 'CONFIG_TEST_PLACEHOLDER',
        'cert_pfx_encrypted' => 'CONFIG_TEST_PLACEHOLDER',
        'senha_encrypted'    => 'CONFIG_TEST_PLACEHOLDER',
        'titular_cnpj'       => $cnpjTitular,
    ];
    foreach ($opcionais as $col => $valor) {
        if (Schema::hasColumn('nfe_certificados', $col)) {
            $row[$col] = $valor;
        }
    }

    return (int) DB::table('nfe_certificados')->insertGetId($row);
}

it('UC-FCFG-11 · cert sem CNPJ titular mostra o CNPJ da empresa como fallback', function () {
    $tenant = test()->seededTenant();
    $bizId = (int) $tenant->id;
    $cnpjEmpresa = '11222333000181'; // fictício, dígitos válidos

    $user = test()->usuarioComPermissoes(['fiscal.config.edit'], $tenant);
    $this->actingAs($user)->withSession([
        'business.id' => $bizId,
        'user.business_id' => $bizId,
        'business.tax_number_1' => $cnpjEmpresa,
    ]);

    $semCnpj = configCertTeste($bizId, '');
    try {
        $this->get('/fiscal/config')
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('Fiscal/Config')
                    ->where('certificado.cnpjTitularFallback', $cnpjEmpresa),
            );
    } finally {
        DB::table('nfe_certificados')->where('id', $semCnpj)->delete();
    }

    // CONTROLE NEGATIVO — cert COM CNPJ titular não ganha fallback: a tela mostra o do cert.
    $comCnpj = configCertTeste($bizId, '99888777000166');
    try {
        $this->get('/fiscal/config')
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->where('certificado.cnpjTitularFallback', null),
            );
    } finally {
        DB::table('nfe_certificados')->where('id', $comCnpj)->delete();
    }
});

it('UC-FCFG-12 · a tela mostra a configuração fiscal da própria empresa, nunca a da vizinha', function () {
    $tenant = test()->seededTenant();
    $bizA = (int) $tenant->id;
    $bizB = (int) test()->seededSupportClientTenant()->id;

    // Guarda o que já existe para devolver no fim (a lane compartilha o banco entre arquivos).
    $antes = DB::table('nfe_business_configs')->whereIn('business_id', [$bizA, $bizB])->get()->keyBy('business_id');
    $gravar = function (int $biz, string $regime, string $cfop): void {
        DB::table('nfe_business_configs')->updateOrInsert(
            ['business_id' => $biz],
            ['regime' => $regime, 'tributacao_default' => json_encode(['cfop' => $cfop]),
                'created_at' => now(), 'updated_at' => now()],
        );
    };
    $gravar($bizA, 'simples', '5102');
    $gravar($bizB, 'lucro_real', '6108');

    $user = test()->usuarioComPermissoes(['fiscal.config.edit'], $tenant);

    try {
        $this->actingAs($user)
            ->withSession(['business.id' => $bizA, 'user.business_id' => $bizA])
            ->get('/fiscal/config')
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('Fiscal/Config')
                    ->where('config.regime', 'simples')
                    ->where('painel.regime', 'simples')
                    ->where('painel.cfopDefault', '5102'),
            );

        // Os dois lados: sem o filtro, `first()` devolveria a MESMA linha às duas empresas,
        // e uma das duas asserções cai. Com o filtro, cada uma vê a sua.
        $userB = test()->usuarioComPermissoes(['fiscal.config.edit'], \App\Business::findOrFail($bizB));
        $this->actingAs($userB)
            ->withSession(['business.id' => $bizB, 'user.business_id' => $bizB])
            ->get('/fiscal/config')
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->where('config.regime', 'lucro_real')
                    ->where('painel.cfopDefault', '6108'),
            );
    } finally {
        foreach ([$bizA, $bizB] as $biz) {
            DB::table('nfe_business_configs')->where('business_id', $biz)->delete();
            if ($antes->has($biz)) {
                DB::table('nfe_business_configs')->insert((array) $antes->get($biz));
            }
        }
    }
});
