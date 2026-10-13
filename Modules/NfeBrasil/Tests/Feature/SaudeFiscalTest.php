<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — "Filtro Suspeitas" (playbook Fiscal thread 14 · D-SUPORTE).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-24 · 25

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\SaudeFiscalService;

uses(Tests\TestCase::class);

/**
 * Os casos derivam do aceite da thread 14 (UC-NFTR-11/13 do playbook, renumerados para 24/25 porque
 * 11 e 13 já existiam no casos.md) — não do service. MySQL-only · tenant 98, adversário 2
 * (ADR 0358). Fixtures marcadas: produtos SKU `SAUDE14-*`, notas série `914`, certificados com
 * cnpj `91400000000000`. O estado do tenant que o teste altera (config e certificados ativos) é
 * guardado antes e devolvido no afterEach.
 */

function sfBiz(): int
{
    return test()->seededTenant()->id;
}

function sfTipos(int $biz): array
{
    return collect(app(SaudeFiscalService::class)->avaliar($biz)['itens'])->keyBy('tipo')->all();
}

function sfLimpar(): void
{
    DB::table('products')->where('sku', 'like', 'SAUDE14-%')->delete();
    DB::table('nfe_emissoes')->where('serie', '914')->delete();
    DB::table('nfe_certificados')->where('cnpj_titular', '91400000000000')->delete();
}

function sfProduto(string $ncm): int
{
    $biz = sfBiz();

    return DB::table('products')->insertGetId([
        'name' => 'Produto saude fiscal', 'business_id' => $biz, 'type' => 'single', 'tax_type' => 'exclusive',
        'sku' => 'SAUDE14-' . uniqid(), 'ncm' => $ncm, 'is_inactive' => 0,
        'created_by' => DB::table('business')->where('id', $biz)->value('owner_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function sfCertificado(int $diasAteVencer): void
{
    DB::table('nfe_certificados')->insert([
        'business_id' => sfBiz(), 'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'cnpj_titular' => '91400000000000', 'valido_ate' => now()->addDays($diasAteVencer)->toDateString(),
        'encrypted_password' => 'x', 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function sfNcmPadrao(string $ncm): void
{
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => sfBiz()],
        ['tributacao_default' => json_encode(['ncm_default' => $ncm, 'cfop' => '5102']), 'updated_at' => now()],
    );
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: REGEXP do filtro de NCM e as tabelas nfe_* vêm das migrações do módulo.');
    }
    if (! Schema::hasTable('nfe_certificados') || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Migrações do NfeBrasil não rodaram.');
    }
    $biz = sfBiz();
    $this->sfConfig = DB::table('nfe_business_configs')->where('business_id', $biz)->first();
    $this->sfCertsAtivos = DB::table('nfe_certificados')->where('business_id', $biz)->where('ativo', true)->pluck('id')->all();
    DB::table('nfe_certificados')->whereIn('id', $this->sfCertsAtivos)->update(['ativo' => false]);
    sfLimpar();
    sfNcmPadrao('49111090');
    sfCertificado(200);
});

afterEach(function () {
    sfLimpar();
    DB::table('nfe_certificados')->whereIn('id', $this->sfCertsAtivos ?? [])->update(['ativo' => true]);
    if (property_exists($this, 'sfConfig')) {
        DB::table('nfe_business_configs')->where('business_id', sfBiz())->delete();
        if ($this->sfConfig !== null) {
            DB::table('nfe_business_configs')->insert((array) $this->sfConfig);
        }
    }
});

it('UC-NFTR-24 · pendência aparece e some ao corrigir', function () {
    $biz = sfBiz();

    // Controle positivo: com NCM padrão válido e certificado longe de vencer, nenhum dos dois aparece.
    $t = sfTipos($biz);
    expect($t)->not->toHaveKey('ncm_padrao_invalido')->not->toHaveKey('certificado_invalido')
        ->not->toHaveKey('certificado_vencendo');

    // NCM padrão 00000000 (auditoria 2026-05 bug #3) → aparece, com a rota que resolve; corrige → some.
    sfNcmPadrao('00000000');
    $t = sfTipos($biz);
    expect($t['ncm_padrao_invalido']['bloqueante'])->toBeTrue();
    expect($t['ncm_padrao_invalido']['rota'])->toBe('nfe-brasil.tributacao.config.show');
    sfNcmPadrao('49111090');
    expect(sfTipos($biz))->not->toHaveKey('ncm_padrao_invalido');

    // Certificado a 10 dias → aviso com os dias; renovado → some.
    DB::table('nfe_certificados')->where('cnpj_titular', '91400000000000')->delete();
    sfCertificado(10);
    expect(sfTipos($biz)['certificado_vencendo']['contagem'])->toBe(10);
    sfCertificado(400);
    expect(sfTipos($biz))->not->toHaveKey('certificado_vencendo');

    // Produto ativo sem NCM → aparece pelo id; recebe NCM → some.
    $p = sfProduto('0');
    expect(sfTipos($biz)['produto_sem_ncm']['ids'])->toContain($p);

    // A nota saiu com o NCM padrão para esse produto → aparece também como emitido.
    DB::table('nfe_emissoes')->insert([
        'business_id' => $biz, 'modelo' => '65', 'serie' => '914', 'numero' => 914001, 'status' => 'autorizada',
        'valor_total' => 10, 'metadata' => json_encode(['itens_ncm_padrao' => [['sell_line_id' => null, 'product_id' => $p]]]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(sfTipos($biz)['emitido_com_ncm_padrao']['ids'])->toBe([$p]);

    DB::table('products')->where('id', $p)->update(['ncm' => '49111090']);
    $t = sfTipos($biz);
    expect($t)->not->toHaveKey('emitido_com_ncm_padrao');
    expect($t['produto_sem_ncm']['ids'] ?? [])->not->toContain($p);

    // Isolamento: o produto e a nota do 98 não aparecem para o business 2.
    $p2 = sfProduto('0');
    $outro = app(SaudeFiscalService::class)->avaliar(2);
    $idsOutro = collect($outro['itens'])->pluck('ids')->flatten()->all();
    expect($idsOutro)->not->toContain($p2)->not->toContain($p);

    // Zero escrita: avaliar não muda nenhuma contagem das tabelas lidas.
    $antes = [DB::table('products')->count(), DB::table('nfe_emissoes')->count(), DB::table('nfe_business_configs')->count()];
    app(SaudeFiscalService::class)->avaliar($biz);
    expect([DB::table('products')->count(), DB::table('nfe_emissoes')->count(), DB::table('nfe_business_configs')->count()])
        ->toBe($antes);
});

it('UC-NFTR-25 · pronto pra emitir', function () {
    $biz = sfBiz();
    $svc = app(SaudeFiscalService::class);

    // Controle positivo: empresa configurada (NCM válido + certificado) nasce pronta, mesmo com aviso.
    sfProduto('0');
    $r = $svc->avaliar($biz);
    expect($r['pronto'])->toBeTrue();
    expect($r['bloqueantes'])->toBe(0);
    expect(collect($r['itens'])->pluck('tipo'))->toContain('produto_sem_ncm');

    // Duas bloqueantes (NCM padrão inválido + certificado vencido) → não pronto, com a contagem.
    sfNcmPadrao('00000000');
    DB::table('nfe_certificados')->where('cnpj_titular', '91400000000000')->update(['valido_ate' => now()->subDay()->toDateString()]);
    $r = $svc->avaliar($biz);
    expect($r['pronto'])->toBeFalse();
    expect($r['bloqueantes'])->toBe(2);

    // Resolvo as duas → pronto de novo.
    sfNcmPadrao('49111090');
    sfCertificado(200);
    expect($svc->avaliar($biz)['pronto'])->toBeTrue();
});
