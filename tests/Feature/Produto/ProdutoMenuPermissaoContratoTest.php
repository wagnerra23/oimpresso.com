<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Menu Produtos — a guarda do item é a MESMA da tela (playbook Produto · thread 08).
 *
 * O recibo `_saida-08.md` entregou o menu (#8812) com `php -l` como única prova. Os testes de
 * sidebar que já existem leem o FONTE do middleware (presença de string, LC-11). Este roda o
 * caminho real: a requisição passa pelo `AdminSidebarMenu`, o `ShellMenuBuilder` serializa o
 * menu no prop compartilhado `shell.menu`, e o assert lê o que o React recebe.
 *
 * Contrato (fonte: `_saida-08.md`, tabela "O que mudou"; guarda = 1ª checagem do método):
 *   - Imprimir etiquetas → /labels/show, guarda `print_labels.access` (LabelsController::show)
 *   - Atualizar preço → /update-product-price, guarda `product.update`
 *   - as 6 tabelas de apoio → /units?aba=<aba>, só as abas que o usuário vê
 *   - nenhum item aponta pras rotas Blade aposentadas
 *
 * ⛔ Tenant 98 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Middleware/AdminSidebarMenu.php  bloco //Products dropdown
 */
uses(DatabaseTransactions::class);

function pmenuUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Menu T08', 'username' => 'pmenu_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function pmenuLogin(object $test, User $user): object
{
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);

    return $test->actingAs($user);
}

/** Todos os hrefs do menu que a tela recebeu, achatados (pai + filhos). */
function pmenuHrefs(\Illuminate\Testing\TestResponse $resp): array
{
    $page = $resp->viewData('page');
    $menu = (array) data_get($page, 'props.shell.menu', []);
    $hrefs = [];
    $walk = function (array $itens) use (&$walk, &$hrefs) {
        foreach ($itens as $item) {
            if (! empty($item['href'])) {
                $hrefs[] = (string) $item['href'];
            }
            $walk((array) ($item['children'] ?? []));
        }
    };
    $walk($menu);

    return $hrefs;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
});

it('T08 · Imprimir etiquetas e Atualizar preço aparecem pela guarda da tela; o menu nunca mostra link que dá 403 [T0]', function () {
    $etiquetas = pmenuUsuario($this->biz->id, ['print_labels.access']);
    $resp = pmenuLogin($this, $etiquetas)->get('/labels/show');
    $resp->assertOk();
    $hrefs = pmenuHrefs($resp);

    // controle positivo: o menu foi serializado (senão os "não contém" abaixo passariam no vácuo)
    $this->assertNotEmpty($hrefs, 'shell.menu chegou vazio — o assert não estaria medindo nada');
    $this->assertContains('/labels/show', $hrefs, 'quem tem print_labels.access vê Imprimir etiquetas');
    $this->assertNotContains('/update-product-price', $hrefs, 'sem product.update o item Atualizar preço não aparece');
    pmenuLogin($this, $etiquetas)->get('/update-product-price')->assertForbidden();

    $preco = pmenuUsuario($this->biz->id, ['product.update']);
    $resp = pmenuLogin($this, $preco)->get('/update-product-price');
    $resp->assertOk();
    $hrefs = pmenuHrefs($resp);

    $this->assertContains('/update-product-price', $hrefs, 'quem tem product.update vê Atualizar preço');
    $this->assertNotContains('/labels/show', $hrefs, 'sem print_labels.access o item Imprimir etiquetas não aparece');
    pmenuLogin($this, $preco)->get('/labels/show')->assertForbidden();
});

it('T08 · tabelas de apoio viram deep-link de aba, só das abas visíveis, e nenhum item aponta pra Blade aposentada', function () {
    $garantia = pmenuUsuario($this->biz->id, ['warranty.view']);
    $resp = pmenuLogin($this, $garantia)->get('/units?aba=garantias');
    $resp->assertOk();
    $hrefs = pmenuHrefs($resp);

    $this->assertContains('/units?aba=garantias', $hrefs, 'warranty.view abre o deep-link da aba Garantias');
    $this->assertContains('/units', $hrefs, 'Cadastros de apoio aparece quando alguma aba é visível');
    $this->assertNotContains('/units?aba=marcas', $hrefs, 'sem brand.* a aba Marcas não entra no menu');

    $tudo = pmenuUsuario($this->biz->id, [
        'product.view', 'product.create', 'product.update', 'product.opening_stock', 'print_labels.access',
        'unit.view', 'brand.view', 'category.view', 'variation.view', 'warranty.view',
    ]);
    $resp = pmenuLogin($this, $tudo)->get('/units');
    $resp->assertOk();
    $hrefs = pmenuHrefs($resp);

    foreach (['variacoes', 'grupos', 'unidades', 'categorias', 'marcas', 'garantias'] as $aba) {
        $this->assertContains('/units?aba=' . $aba, $hrefs, "deep-link da aba {$aba}");
    }
    foreach (['/brands', '/taxonomies', '/warranties', '/variation-templates', '/selling-price-group'] as $blade) {
        $vazou = array_values(array_filter($hrefs, fn ($h) => $h === $blade || str_starts_with($h, $blade . '?')));
        $this->assertSame([], $vazou, "nenhum item do menu aponta pra rota Blade aposentada {$blade}");
    }
});
