<?php

/**
 * Instalar / desinstalar / atualizar módulo só por POST + CSRF — trait InstalacaoSoPorPost.
 *
 * Generaliza o padrão do Connector (#8344), Arquivos (#8359) e Officeimpresso (#8367): o GET
 * só mostra uma confirmação sem efeito (form POST + CSRF pra MESMA URL); a ação roda no POST.
 * A tela /manage-modules (#8363) troca o link por form sozinha quando o router aceita POST.
 *
 * DB-less: controller falso (prova o comportamento do trait), router (prova as rotas de cada
 * módulo migrado) e render da view com layout dublê. Roda na lane sqlite.
 */

use App\Http\Controllers\Concerns\InstalacaoSoPorPost;
use App\Http\Controllers\Install\ModulesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Módulos já migrados pro trait. Cada lote acrescenta os seus. */
const INSTALACAO_SO_POR_POST_MODULOS = [
    'AssetManagement', 'Cms', 'Crm', 'Manufacturing', 'ProductCatalogue',
    'Forja', 'Jana', 'KB', 'Ponto', 'Repair', 'Spreadsheet',
];

abstract class InstalacaoSoPorPostBaseFalsa
{
    public array $executou = [];

    public function index()
    {
        $this->executou[] = 'index';

        return 'executou';
    }

    public function uninstall()
    {
        $this->executou[] = 'uninstall';

        return 'executou';
    }

    public function update()
    {
        $this->executou[] = 'update';

        return $this->index();
    }
}

class InstalacaoSoPorPostControllerFalso extends InstalacaoSoPorPostBaseFalsa
{
    use InstalacaoSoPorPost;

    protected function telaConfirmacaoInstalacao(string $acao)
    {
        return 'confirmacao:'.$acao;
    }
}

function instalacaoSoPorPostRequisicao(string $metodo, string $uri = '/modulo/install'): void
{
    app()->instance('request', Request::create($uri, $metodo));
}

function instalacaoSoPorPostSuperadmin(bool $pode = true): void
{
    test()->actingAs(new \App\User());
    Gate::before(fn () => $pode);
}

it('GET nas 3 ações devolve a confirmação e NÃO executa a ação', function () {
    instalacaoSoPorPostSuperadmin();
    instalacaoSoPorPostRequisicao('GET');

    foreach (['index' => 'install', 'uninstall' => 'uninstall', 'update' => 'update'] as $metodo => $acao) {
        $c = new InstalacaoSoPorPostControllerFalso();

        expect($c->{$metodo}())->toBe('confirmacao:'.$acao);
        expect($c->executou)->toBe([]);
    }
});

it('POST chega na ação — index, uninstall e update executam como antes', function () {
    instalacaoSoPorPostSuperadmin();
    instalacaoSoPorPostRequisicao('POST');

    $c = new InstalacaoSoPorPostControllerFalso();
    expect($c->index())->toBe('executou');
    expect($c->executou)->toBe(['index']);

    $c = new InstalacaoSoPorPostControllerFalso();
    expect($c->uninstall())->toBe('executou');
    expect($c->executou)->toBe(['uninstall']);

    // update() do base chama $this->index(): no POST a cadeia segue inteira.
    $c = new InstalacaoSoPorPostControllerFalso();
    expect($c->update())->toBe('executou');
    expect($c->executou)->toBe(['update', 'index']);
});

it('GET sem superadmin é 403 e não executa', function () {
    instalacaoSoPorPostSuperadmin(false);
    instalacaoSoPorPostRequisicao('GET');

    $c = new InstalacaoSoPorPostControllerFalso();

    expect(fn () => $c->uninstall())->toThrow(HttpException::class);
    expect($c->executou)->toBe([]);
});

it('a confirmação real renderiza form POST + CSRF pra mesma URL', function () {
    // Layout dublê: layouts.app real exige sessão de negócio; aqui só interessa o conteúdo.
    $dir = sys_get_temp_dir().'/instalacao-so-por-post-'.getmypid();
    @mkdir($dir.'/layouts', 0777, true);
    file_put_contents($dir.'/layouts/app.blade.php', "@yield('content')");
    app('view')->getFinder()->prependLocation($dir);

    instalacaoSoPorPostSuperadmin();
    instalacaoSoPorPostRequisicao('GET', url('cms/install/uninstall'));

    $controller = new class extends InstalacaoSoPorPostBaseFalsa
    {
        use InstalacaoSoPorPost;

        protected function moduleName(): string
        {
            return 'Cms';
        }
    };

    $html = $controller->uninstall()->getContent();

    expect($html)->toContain('Desinstalar o módulo Cms');
    expect($html)->toContain('method="POST"');
    expect($html)->toContain('action="'.url('cms/install/uninstall').'"');
    expect($html)->toContain('name="_token"');
    expect($controller->executou)->toBe([]);
});

it('módulo migrado usa o trait e aceita POST nas 3 URLs', function (string $modulo) {
    $classe = 'Modules\\'.$modulo.'\\Http\\Controllers\\InstallController';

    expect(class_uses_recursive($classe))->toContain(InstalacaoSoPorPost::class);

    foreach (['index', 'uninstall', 'update'] as $metodo) {
        $url = action('\\'.$classe.'@'.$metodo);
        expect(ModulesController::aceitaPost($url))->toBeTrue("{$modulo}@{$metodo} sem POST");

        // O GET da mesma URL cai no mesmo método (que só confirma).
        $get = Route::getRoutes()->match(Request::create($url, 'GET'));
        expect($get->getActionName())->toBe($classe.'@'.$metodo);
    }
})->with(INSTALACAO_SO_POR_POST_MODULOS);
