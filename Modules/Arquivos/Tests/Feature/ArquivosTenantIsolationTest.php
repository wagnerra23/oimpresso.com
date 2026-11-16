<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Isolamento multi-tenant Tier 0 do Arquivos pelo caminho HTTP (ADR 0093, Garantia 4).
 * Nasceu como esqueleto `->todo()` na auditoria RBAC de 2026-11-14; ativado em 2026-11-16
 * (Auditoria Arquivos 2026-11-05, lote L1).
 *
 * Contrato:
 * - ADR 0093 (sessão de uma empresa nunca lê nem escreve na outra);
 * - `Index.casos.md` UC-INDEX-01 ("Regressão que defende: vazamento cross-tenant no
 *   acervo");
 * - SPEC US-ARQ-008 (download por signed URL; o `find` aplica o scope da sessão).
 *
 * Cada caso tem o par: controle POSITIVO (a 98 vê / baixa / exclui o que é dela, com o
 * MESMO usuário e a MESMA rota) + controle NEGATIVO (a 98 não alcança o da 99). Sem o
 * positivo, um 403/404 em tudo passaria verde. No download, os DOIS arquivos existem no
 * disco: assim o 404 do lado da 99 só pode vir do escopo, nunca de "arquivo ausente".
 *
 * TENANT: 98 canônico × 99 vizinho (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * DatabaseTransactions: nada fica gravado no CT 100. Sem o stack de sessão do
 * UltimatePOS (padrão do ArquivosSuperadminOnlyTest): a sessão vai por `withSession`.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const ATI_DISK = 'fixture-tenant-isolation';

if (! function_exists('atiSemStackDeSessao')) {
    function atiSemStackDeSessao(): array
    {
        return [
            App\Http\Middleware\IsInstalled::class,
            App\Http\Middleware\SetSessionData::class,
            App\Http\Middleware\AdminSidebarMenu::class,
            App\Http\Middleware\Language::class,
            App\Http\Middleware\Timezone::class,
        ];
    }
}

function atiArquivo(int $businessId, string $nome): int
{
    $path = "biz-{$businessId}/isolamento/{$nome}";
    Storage::disk(ATI_DISK)->put($path, "conteudo-ficticio-{$businessId}");

    return DB::table('arquivos')->insertGetId([
        'business_id'   => $businessId,
        'disk'          => ATI_DISK,
        'storage_path'  => $path,
        'original_name' => $nome,
        'mime_type'     => 'text/plain',
        'size_bytes'    => 20,
        'md5'           => md5("ati-{$businessId}-{$nome}"),
        'bucket'        => 'active',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

/** @return list<int> ids da prop deferida `acervo` vista pela sessão atual. */
function atiIdsDoAcervo($t): array
{
    $ids = [];
    $t->withoutMiddleware(atiSemStackDeSessao())
        ->actingAs($t->user)
        ->withSession($t->sessao)
        ->get('/arquivos?per_page=100')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Arquivos/Index')
            ->loadDeferredProps(function (AssertableInertia $r) use (&$ids) {
                $ids = array_map(fn ($l) => (int) $l['id'], $r->toArray()['props']['acervo']['data']);
            }));

    return $ids;
}

beforeEach(function () {
    foreach (['arquivos', 'arquivos_audit_log', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — roda na lane MySQL do Arquivos.");
        }
    }

    Storage::fake(ATI_DISK);

    $this->biz = $this->seededTenant()->id;
    $this->vizinho = $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['arquivos.access']);
    $this->sessao = ['user' => ['business_id' => $this->biz, 'id' => $this->user->id]];

    $this->id98 = atiArquivo($this->biz, 'da-98.txt');
    $this->id99 = atiArquivo($this->vizinho, 'da-99.txt');
});

it('Arquivos · listagem da empresa 98 não traz registro da 99 (com controle positivo)', function () {
    $ids = atiIdsDoAcervo($this);

    expect($ids)->toContain($this->id98);
    expect($ids)->not->toContain($this->id99);
})->group('arquivos', 'multi-tenant');

it('Arquivos · leitura por id de registro da 99 com sessão da 98 dá 404 (com controle positivo)', function () {
    $baixar = fn (int $id) => $this->withoutMiddleware(atiSemStackDeSessao())
        ->actingAs($this->user)
        ->withSession($this->sessao)
        ->get(URL::temporarySignedRoute('arquivos.download', now()->addMinutes(5), ['arquivo' => $id]));

    // Positivo: a 98 baixa o próprio arquivo pela mesma rota assinada.
    $baixar($this->id98)->assertOk();

    // Negativo: URL assinada válida para o id da 99 — o arquivo existe no disco, então o
    // 404 só pode vir do global scope da sessão.
    $baixar($this->id99)->assertNotFound();

    // E a leitura recusada não deixa rastro de consumo na trilha da 99.
    expect(DB::table('arquivos_audit_log')
        ->where('arquivo_id', $this->id99)
        ->where('action', 'signed_url_consumed')
        ->count())->toBe(0);
})->group('arquivos', 'multi-tenant');

it('Arquivos · escrita com id da 99 vindo do request não altera nada na 99 (com controle positivo)', function () {
    $excluir = fn (int $id) => $this->withoutMiddleware(atiSemStackDeSessao())
        ->actingAs($this->user)
        ->withSession($this->sessao)
        ->post("/arquivos/{$id}/excluir", ['reason' => 'teste de isolamento']);

    // Negativo: a Request recusa (o arquivo não é da empresa da sessão) e a linha da 99
    // segue intacta, sem trilha de exclusão.
    $excluir($this->id99)->assertForbidden();
    expect(DB::table('arquivos')->where('id', $this->id99)->value('deleted_at'))->toBeNull();
    expect(DB::table('arquivos_audit_log')
        ->where('arquivo_id', $this->id99)
        ->where('action', 'soft_delete')
        ->count())->toBe(0);

    // Positivo: o mesmo usuário, pela mesma rota, exclui o arquivo da 98 — o 403 acima
    // não veio de falta de permissão.
    $excluir($this->id98)->assertRedirect();
    expect(DB::table('arquivos')->where('id', $this->id98)->value('deleted_at'))->not->toBeNull();
})->group('arquivos', 'multi-tenant');
