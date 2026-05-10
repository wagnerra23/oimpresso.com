<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use Modules\RecurringBilling\Models\Invoice;

uses(Tests\TestCase::class);

/**
 * PR #434 P0 fix-suite — `NfeService::emitirParaInvoice` validações early.
 *
 * Cobre os 3 grupos de cenários do fix:
 *   1. NCM fallback ordenado (config → business.ncm_padrao → throw)
 *   2. Invoice valida (valor>0, contact_id, tax_number 11/14)
 *   3. Business not found (id inexistente → throw)
 *
 * **NÃO toca SEFAZ** — só valida pré-condições antes de qualquer signNFe/sefazEnviaLote.
 *
 * Pattern: TransactionBuilder analog — Invoice non-persistida via `forceFill` +
 * `setRelation('contact', ...)` evita migrations/factories pesadas em SQLite.
 *
 * Skip graceful quando tabela `business` não existe (SQLite in-memory CI sem
 * UltimatePOS seed) — só `nfe_business_configs` mocked via Mockery scope.
 *
 * Wagner regra biz=1 NUNCA cliente real (biz=4 ROTA LIVRE) — tests usam biz=1
 * (Wagner WR2 SC) ou biz=99 (cross-tenant inexistente).
 */

// ── helpers ─────────────────────────────────────────────────────────────────

/**
 * Skip se schema mínimo ausente — evita falsa cobertura em CI SQLite vazio.
 */
function fallbackBootstrap(): object
{
    if (! Schema::hasTable('business')) {
        test()->markTestSkipped('Tabela business não existe — SQLite in-memory sem UltimatePOS seed.');
    }
    if (! Schema::hasTable('nfe_business_configs')) {
        test()->markTestSkipped('Tabela nfe_business_configs ausente — rode migrations NfeBrasil.');
    }
    if (! Schema::hasTable('rb_invoices')) {
        test()->markTestSkipped('Tabela rb_invoices ausente — rode migrations RecurringBilling.');
    }

    $business = DB::table('business')->where('id', 1)->first();
    if (! $business) {
        test()->markTestSkipped('Business id=1 inexistente — seed UltimatePOS antes.');
    }
    return $business;
}

/**
 * CertificadoService mock — não chamado pelos tests (validações falham antes
 * de carregarParaSefaz), mas precisa do construtor.
 */
function fakeCertSvcFallback(): CertificadoService
{
    $mock = Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'fake', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);
    return $mock;
}

/**
 * Invoice non-persistida pronta com Contact relação setada manualmente.
 *
 * @param  array<string,mixed>  $invoiceAttrs
 * @param  array<string,mixed>|null  $contactAttrs  null = sem contact (contact_id null)
 */
function fakeInvoice(array $invoiceAttrs = [], ?array $contactAttrs = []): Invoice
{
    $invoice = new Invoice;
    $invoice->forceFill(array_merge([
        'id'               => 999001,
        'business_id'      => 1,
        'numero_documento' => 'INV-FB-' . uniqid(),
        'valor'            => 100.00,
        'status'           => 'paid',
    ], $invoiceAttrs));

    if ($contactAttrs === null) {
        // Simula relação carregada como null (Invoice sem contact)
        $invoice->setRelation('contact', null);
        return $invoice;
    }

    $contact = new Contact;
    $contact->forceFill(array_merge([
        'id'                     => 555001,
        'business_id'            => 1,
        'name'                   => 'CLIENTE TESTE LTDA',
        'supplier_business_name' => 'CLIENTE TESTE LTDA',
        'tax_number'             => '12345678000199',
        'state'                  => 'SP',
        'city'                   => 'São Paulo',
        'address_line_1'         => 'Rua Teste',
        'address_line_2'         => 'Centro',
        'zip_code'               => '01001000',
        'email'                  => 'test@test.com',
    ], $contactAttrs));
    $invoice->setRelation('contact', $contact);
    return $invoice;
}

/**
 * MotorTributarioService mock — retorna tributo dummy. Tests dessa suite
 * NUNCA chegam até motor (validações fall-fast antes), mas precaução
 * caso algum cenário evolua.
 */
function fakeMotorFallback(): MotorTributarioService
{
    $mock = Mockery::mock(MotorTributarioService::class);
    $tributo = new TributoCalculado(
        cfop: '5102',
        csosn: '102',
        cst: null,
        aliquota_icms: 0.0,
        aliquota_pis: 0.0,
        aliquota_cofins: 0.0,
        aliquota_ipi: 0.0,
        valor_icms: 0.0,
        valor_pis: 0.0,
        valor_cofins: 0.0,
        valor_ipi: 0.0,
        nivel_usado: 4,
    );
    $mock->shouldReceive('calcular')->andReturn($tributo);
    return $mock;
}

/**
 * Limpa NfeBusinessConfig do biz=1 antes do test (pode ter sido criado por
 * outro test rodando antes — tabela compartilhada).
 */
function limparConfigBiz(int $businessId = 1): void
{
    NfeBusinessConfig::withoutGlobalScopes()
        ->where('business_id', $businessId)
        ->forceDelete();
}

afterEach(function () {
    Mockery::close();
});

// ── 1. NCM fallback ordenado ────────────────────────────────────────────────

it('ncm fallback A: usa nfe_business_configs.tributacao_default.ncm_default quando presente', function () {
    fallbackBootstrap();
    limparConfigBiz(1);

    NfeBusinessConfig::withoutGlobalScopes()->create([
        'business_id'           => 1,
        'regime'                => 'simples_nacional',
        'auto_emission_enabled' => false,
        'tributacao_default'    => [
            'ncm_default' => '49019900', // 8 dígitos válido
            'cfop'        => '5102',
            'csosn'       => '102',
        ],
    ]);

    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    // Invoice com valor 0 → vai falhar EM `valor <= 0`, antes de qualquer NCM check.
    // Pra testar NCM fallback indiretamente, usamos invoice válida + cert mock que
    // joga exceção depois — mas isso torna teste frágil. Estratégia: deixar
    // bater até `carregarParaSefaz`, que está mockado pra retornar fake. O test
    // se contenta em garantir que NÃO bate o RuntimeException de NCM ausente.
    $invoice = fakeInvoice(['valor' => 100.00]);

    // Esperamos que a chamada NÃO solte RuntimeException de "NCM padrão" —
    // pode soltar outras (ex: cert/SEFAZ) mas a mensagem específica do NCM
    // não deve aparecer.
    try {
        $svc->emitirParaInvoice($invoice);
    } catch (\Throwable $e) {
        expect($e->getMessage())->not->toContain('sem NCM padrão configurado');
    }

    // Cleanup
    limparConfigBiz(1);
})->group('nfe', 'pr-434');

it('ncm fallback B: usa business.ncm_padrao quando config ausente mas business tem coluna preenchida', function () {
    $business = fallbackBootstrap();
    limparConfigBiz(1);

    if (! Schema::hasColumn('business', 'ncm_padrao')) {
        test()->markTestSkipped('business.ncm_padrao não existe nesse ambiente.');
    }

    $ncmAntes = $business->ncm_padrao ?? null;

    DB::table('business')->where('id', 1)->update(['ncm_padrao' => '49019900']);

    try {
        $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());
        $invoice = fakeInvoice(['valor' => 100.00]);

        try {
            $svc->emitirParaInvoice($invoice);
        } catch (\Throwable $e) {
            expect($e->getMessage())->not->toContain('sem NCM padrão configurado');
        }
    } finally {
        // Restaura valor original
        DB::table('business')->where('id', 1)->update(['ncm_padrao' => $ncmAntes]);
    }
})->group('nfe', 'pr-434');

it('ncm fallback C: throw RuntimeException quando nem config nem business.ncm_padrao definidos', function () {
    $business = fallbackBootstrap();
    limparConfigBiz(1);

    if (! Schema::hasColumn('business', 'ncm_padrao')) {
        test()->markTestSkipped('business.ncm_padrao não existe — não dá pra simular ausência.');
    }

    $ncmAntes = $business->ncm_padrao ?? null;
    DB::table('business')->where('id', 1)->update(['ncm_padrao' => null]);

    try {
        $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());
        $invoice = fakeInvoice(['valor' => 100.00]);

        expect(fn () => $svc->emitirParaInvoice($invoice))
            ->toThrow(\RuntimeException::class, 'sem NCM padrão configurado');
    } finally {
        DB::table('business')->where('id', 1)->update(['ncm_padrao' => $ncmAntes]);
    }
})->group('nfe', 'pr-434');

// ── 2. Invoice valida ────────────────────────────────────────────────────────

it('invoice valida: throw quando valor <= 0', function () {
    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    // Sem DB — Invoice non-persistida + check valor é primeiro early exit
    $invoice = fakeInvoice(['valor' => 0.00]);

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'sem valor positivo');
})->group('nfe', 'pr-434');

it('invoice valida: throw quando valor é negativo', function () {
    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    $invoice = fakeInvoice(['valor' => -50.00]);

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'sem valor positivo');
})->group('nfe', 'pr-434');

it('invoice valida: throw quando contact relação é null (sem destinatário)', function () {
    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    // contactAttrs = null → setRelation('contact', null), simulando
    // Invoice sem contact_id ou contact_id apontando pra registro deletado.
    $invoice = fakeInvoice(['valor' => 100.00], null);

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'sem contact_id');
})->group('nfe', 'pr-434');

it('invoice valida: throw quando contact.tax_number tem dígitos inválidos (8 dígitos)', function () {
    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    $invoice = fakeInvoice(
        ['valor' => 100.00],
        ['tax_number' => '12345678'], // 8 dígitos — nem CPF (11) nem CNPJ (14)
    );

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'CPF/CNPJ válido');
})->group('nfe', 'pr-434');

it('invoice valida: throw quando contact.tax_number vazio', function () {
    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    $invoice = fakeInvoice(
        ['valor' => 100.00],
        ['tax_number' => ''],
    );

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'CPF/CNPJ válido');
})->group('nfe', 'pr-434');

it('invoice valida: aceita CPF (11 dígitos) sem soltar exception de tax_number', function () {
    fallbackBootstrap();
    limparConfigBiz(1);

    NfeBusinessConfig::withoutGlobalScopes()->create([
        'business_id'           => 1,
        'regime'                => 'simples_nacional',
        'auto_emission_enabled' => false,
        'tributacao_default'    => ['ncm_default' => '49019900', 'cfop' => '5102', 'csosn' => '102'],
    ]);

    try {
        $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

        $invoice = fakeInvoice(
            ['valor' => 100.00],
            ['tax_number' => '12345678901'], // 11 dígitos — CPF válido
        );

        try {
            $svc->emitirParaInvoice($invoice);
        } catch (\Throwable $e) {
            expect($e->getMessage())
                ->not->toContain('CPF/CNPJ válido')
                ->not->toContain('sem contact_id')
                ->not->toContain('sem valor positivo');
        }
    } finally {
        limparConfigBiz(1);
    }
})->group('nfe', 'pr-434');

// ── 3. Business not found ────────────────────────────────────────────────────

it('business not found: throw quando business_id não existe na tabela', function () {
    if (! Schema::hasTable('business')) {
        test()->markTestSkipped('Tabela business ausente.');
    }

    // Garante que biz=99 NÃO existe (Wagner convention pra cross-tenant)
    if (DB::table('business')->where('id', 99)->exists()) {
        test()->markTestSkipped('biz=99 existe nesse ambiente — quebra convention cross-tenant.');
    }

    $svc = new NfeService(fakeCertSvcFallback(), null, fakeMotorFallback());

    $invoice = fakeInvoice(
        ['business_id' => 99, 'valor' => 100.00],
        ['business_id' => 99, 'tax_number' => '12345678000199'],
    );

    expect(fn () => $svc->emitirParaInvoice($invoice))
        ->toThrow(\RuntimeException::class, 'Business 99 não encontrado');
})->group('nfe', 'pr-434');
