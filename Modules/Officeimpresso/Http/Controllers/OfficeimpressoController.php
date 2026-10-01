<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController;

class OfficeimpressoController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Porta de entrada do módulo — `GET /officeimpresso` (sem sufixo).
     *
     * O prefixo `officeimpresso` nunca teve rota na raiz (verificado em 16.499
     * commits: os 21 commits que tocaram o Routes/web.php só registraram rotas
     * com sufixo), então quem digitava a URL "óbvia" levava 404 — os 6 links do
     * menu sempre apontaram pras telas internas.
     *
     * Manda cada nível pra primeira tela que ele CONSEGUE abrir, espelhando o
     * `$baseUrl` de DataController::modifyAdminMenu — sem isso um redirect fixo
     * pra /computadores jogaria o atendente (que só tem `clientes.liberar`)
     * direto num 403, exatamente o que o #5044 evitou no menu.
     *
     * Não usa Closure na rota de propósito: Closure quebra `php artisan
     * route:cache` (mesma pegadinha do name colidente já anotada no web.php).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function home()
    {
        $user = auth()->user();

        if ($user->can('superadmin') || $user->can('officeimpresso.access')) {
            return redirect()->action([LicencaComputadorController::class, 'computadores']);
        }

        if ($user->can('officeimpresso.clientes.liberar')) {
            return redirect()->action([ClientController::class, 'index']);
        }

        // Sem nenhuma permissão do módulo: 403 aqui é mais honesto que redirecionar
        // pra uma tela que vai negar do mesmo jeito. Espelha os `abort_unless()`
        // dos controllers de licença.
        abort(403, 'Unauthorized action.');
    }

    /**
     * `GET /officeimpresso/catalogue/{business_id}/{location_id}` — APOSENTADA.
     *
     * Decisão [W] 2026-10-01 (D3 do playbook Officeimpresso, thread 08): o catálogo
     * tem dono, o `Modules/ProductCatalogue`. Esta tela era cópia dele — mesma query
     * (business_id + location + ProductForSales), mesmos descontos vigentes, mesmas
     * views (`diff -r` das duas pastas `catalogue/` sem diferença em 2026-10-01) — e o
     * QR gerado aqui já apontava pra rota pública do ProductCatalogue. Redireciona pra
     * lá preservando parâmetros e query string. Não tira acesso de ninguém: aqui exigia
     * login, o destino é público.
     */
    public function index($business_id, $location_id): RedirectResponse
    {
        return $this->paraProductCatalogue('index', [$business_id, $location_id]);
    }

    /**
     * `GET /officeimpresso/show-catalogue/{business_id}/{product_id}` — APOSENTADA.
     *
     * Mesma decisão da `index()`. A query string importa: o `?location_id=` decide
     * quais descontos entram no detalhe, então ela vai junto.
     */
    public function show($business_id, $id): RedirectResponse
    {
        return $this->paraProductCatalogue('show', [$business_id, $id]);
    }

    /**
     * 302 (não 301: decisão recente, reversível sem cache de browser preso) pra ação
     * equivalente do ProductCatalogue, com a query string original.
     */
    private function paraProductCatalogue(string $acao, array $params): RedirectResponse
    {
        $url = action([ProductCatalogueController::class, $acao], $params);
        $query = request()->getQueryString();

        return redirect()->to($query ? $url.'?'.$query : $url);
    }

    /**
     * Gerador de QR do catálogo — FICA AQUI (thread 08, opção (b)).
     *
     * Não redireciona pro `/product-catalogue/catalogue-qr` porque os gates diferem:
     * aqui basta a assinatura `officeimpresso_module`; lá é exigida
     * `productcatalogue_module`. Um negócio só com a primeira passaria a levar 403.
     * Aposentar esta é decisão [W] pendente (aceitar o 403, ou ajustar os pacotes no
     * superadmin) — ver `_saida-08.md` do playbook Officeimpresso.
     */
    public function generateQr()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'officeimpresso_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);
        $business = Business::findOrFail($business_id);

        return view('officeimpresso::catalogue.generate_qr')
                    ->with(compact('business_locations', 'business'));
    }
}
