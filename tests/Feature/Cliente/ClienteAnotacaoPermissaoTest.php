<?php

declare(strict_types=1);
// Cobre UC-CIDX-23 (Index.casos.md) — anotações e documentos conferem permissão.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * /note-documents (DocumentAndNoteController) não conferia permissão nenhuma: qualquer usuário
 * logado da empresa lia, criava, editava e apagava anotações de qualquer contato ou usuário, e o
 * tipo do registro vinha do pedido sem lista fechada. Ver, editar, salvar e apagar pelo número
 * também ignoravam a anotação privada de outro usuário (só a lista filtrava).
 *
 * Cada recusa tem o controle do lado aceito. Tenant de teste (ADR 0358). Transação revertida.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('document_and_notes')) {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $this->business = $this->seededTenant();
});

function anoEntrar($test, $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => $test->business->id,
        'user.id' => $user->id,
        'business.id' => $test->business->id,
    ]);
}

function anoContato($test, int $criadoPor, string $type = 'customer'): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $test->business->id, 'type' => $type, 'name' => 'CAP-' . uniqid(),
        'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function anoPost($test, string $url, array $campos)
{
    return $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post($url, $campos);
}

function anoNotas(int $notableId): int
{
    return DB::table('document_and_notes')->where('notable_id', $notableId)->count();
}

test('UC-CIDX-23 sem permissão de ver contatos não cria nem lista anotação do cliente', function () {
    $dono = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $semPermissao = $this->usuarioComPermissoes(['product.view'], $this->business);
    $cliente = anoContato($this, (int) $dono->id);
    anoEntrar($this, $semPermissao);

    $r = anoPost($this, '/note-documents', [
        'notable_id' => $cliente, 'notable_type' => 'App\\Contact', 'description' => 'CAP sem permissão',
    ]);
    expect($r->status())->toBe(403)
        ->and(anoNotas($cliente))->toBe(0);

    $lista = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/note-documents?notable_id=' . $cliente . '&notable_type=' . urlencode('App\\Contact'));
    expect($lista->status())->toBe(403);
});

test('UC-CIDX-23 controle: quem vê clientes cria a anotação', function () {
    $user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $cliente = anoContato($this, (int) $user->id);
    anoEntrar($this, $user);

    $r = anoPost($this, '/note-documents', [
        'notable_id' => $cliente, 'notable_type' => 'App\\Contact', 'description' => 'CAP com permissão',
    ]);

    expect($r->status())->toBe(200)
        ->and($r->json('success'))->toBeTrue()
        ->and(anoNotas($cliente))->toBe(1);
});

test('UC-CIDX-23 tipo de registro fora da lista (App\\Contact, App\\User) responde 404 e não grava', function () {
    $user = $this->usuarioComPermissoes(['customer.view', 'user.view'], $this->business);
    anoEntrar($this, $user);
    $antes = DB::table('document_and_notes')->count();

    $r = anoPost($this, '/note-documents', [
        'notable_id' => $this->business->id, 'notable_type' => 'App\\Business', 'description' => 'CAP tipo livre',
    ]);

    expect($r->status())->toBe(404)
        ->and(DB::table('document_and_notes')->count())->toBe($antes);
});

test('UC-CIDX-23 só-próprios não anota o cliente de outro vendedor', function () {
    $outro = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $vendedor = $this->usuarioComPermissoes(['customer.view_own'], $this->business);
    $doOutro = anoContato($this, (int) $outro->id);
    $meu = anoContato($this, (int) $vendedor->id);
    anoEntrar($this, $vendedor);

    expect(anoPost($this, '/note-documents', [
        'notable_id' => $doOutro, 'notable_type' => 'App\\Contact', 'description' => 'CAP outro',
    ])->status())->toBe(403)
        ->and(anoNotas($doOutro))->toBe(0)
        ->and(anoPost($this, '/note-documents', [
            'notable_id' => $meu, 'notable_type' => 'App\\Contact', 'description' => 'CAP meu',
        ])->json('success'))->toBeTrue();
});

test('UC-CIDX-23 anotação privada de outro usuário não abre, não salva e não apaga pelo número', function () {
    $autor = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $colega = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $cliente = anoContato($this, (int) $autor->id);
    anoEntrar($this, $autor);
    $id = (int) anoPost($this, '/note-documents', [
        'notable_id' => $cliente, 'notable_type' => 'App\\Contact',
        'description' => 'CAP privada', 'is_private' => 1,
    ])->json('note.id');
    expect($id)->toBeGreaterThan(0);

    anoEntrar($this, $colega);
    $q = '?notable_id=' . $cliente . '&notable_type=' . urlencode('App\\Contact');
    expect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get("/note-documents/{$id}{$q}")->status())->toBe(404);

    $salvar = anoPost($this, "/note-documents/{$id}", [
        '_method' => 'PUT', 'notable_id' => $cliente, 'notable_type' => 'App\\Contact', 'description' => 'CAP alterada',
    ]);
    expect($salvar->status())->toBe(404)
        ->and(DB::table('document_and_notes')->where('id', $id)->value('description'))->toBe('CAP privada');

    $apagar = anoPost($this, "/note-documents/{$id}", [
        '_method' => 'DELETE', 'notable_id' => $cliente, 'notable_type' => 'App\\Contact',
    ]);
    expect($apagar->status())->toBe(404)
        ->and(DB::table('document_and_notes')->where('id', $id)->exists())->toBeTrue();

    // Controle: o autor segue editando a própria anotação privada.
    anoEntrar($this, $autor);
    expect(anoPost($this, "/note-documents/{$id}", [
        '_method' => 'PUT', 'notable_id' => $cliente, 'notable_type' => 'App\\Contact',
        'description' => 'CAP do autor', 'is_private' => 1,
    ])->json('success'))->toBeTrue();
});

test('UC-CIDX-23 anotação de usuário exige user.view', function () {
    $semPermissao = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $comPermissao = $this->usuarioComPermissoes(['user.view'], $this->business);
    anoEntrar($this, $semPermissao);

    expect(anoPost($this, '/note-documents', [
        'notable_id' => $comPermissao->id, 'notable_type' => 'App\\User', 'description' => 'CAP usuario',
    ])->status())->toBe(403);

    anoEntrar($this, $comPermissao);
    expect(anoPost($this, '/note-documents', [
        'notable_id' => $semPermissao->id, 'notable_type' => 'App\\User', 'description' => 'CAP usuario',
    ])->json('success'))->toBeTrue();
});

test('UC-CIDX-23 lead do Crm: quem atende o lead anota, quem não atende recebe 403', function () {
    if (! Schema::hasTable('crm_lead_users')) {
        $this->markTestSkipped('Tabela crm_lead_users ausente nesta lane.');
    }
    $atende = $this->usuarioComPermissoes(['crm.access_own_leads'], $this->business);
    $naoAtende = $this->usuarioComPermissoes(['crm.access_own_leads'], $this->business);
    $criador = $this->usuarioComPermissoes(['crm.access_all_leads'], $this->business);
    $lead = anoContato($this, (int) $criador->id, 'lead');
    DB::table('crm_lead_users')->insert(['contact_id' => $lead, 'user_id' => $atende->id]);

    anoEntrar($this, $naoAtende);
    expect(anoPost($this, '/note-documents', [
        'notable_id' => $lead, 'notable_type' => 'App\\Contact', 'description' => 'CAP lead outro',
    ])->status())->toBe(403);

    anoEntrar($this, $atende);
    expect(anoPost($this, '/note-documents', [
        'notable_id' => $lead, 'notable_type' => 'App\\Contact', 'description' => 'CAP lead meu',
    ])->json('success'))->toBeTrue();
});
