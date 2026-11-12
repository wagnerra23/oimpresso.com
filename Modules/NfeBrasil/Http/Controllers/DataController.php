<?php

namespace Modules\NfeBrasil\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo NfeBrasil.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\NfeBrasil\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Observações importantes do contexto deste módulo:
 *
 *  - `Modules/NfeBrasil/Resources/lang/` está vazio (apenas .gitkeep).
 *    Usamos strings literais em PT-BR. Quando os lang files forem
 *    criados, trocar pelas chaves `nfebrasil::lang.*` (ou
 *    `nfebrasil::nfebrasil.*` conforme a convenção escolhida).
 *
 *  - Rotas web: o mapa vivo é Modules/NfeBrasil/Routes/web.php (e o
 *    `url_prefixes` do SCOPE.md). Este docblock não repete a lista, para
 *    não envelhecer de novo. Fato datado: até 2026-11-11 ele dizia que só
 *    havia o install e um `resource nfebrasil` vazio, e que "Emitir NF-e",
 *    "Consultar" e "SPED" eram placeholders. O resource saiu em 2026-10-27
 *    e as entradas de sidebar saíram em 2026-05-25 (ver o fim de
 *    modifyAdminMenu). Auditoria NfeBrasil, lote 5.3 (N2-08).
 *
 * @see Modules/PontoWr2/Http/Controllers/DataController.php   (template)
 * @see Modules/NfeBrasil/module.json                          (descrição)
 */
class DataController extends Controller
{
    /**
     * Natureza fiscal do produto para o cadastro (UC-PEDIT-14 · CU-PROD-19).
     *
     * O `ProductController` (núcleo) pede via `ModuleUtil::getModuleData('produto_natureza_fiscal')`
     * para não importar este módulo (DependencyDirectionTest: app/ não depende de Modules/).
     * O `business_id` vem da sessão de quem chama; produto de outra empresa devolve null.
     *
     * @param  array{business_id?: int, product_id?: int}  $args
     * @return array{natureza: string, codigo_servico: string|null}|null
     */
    public function produto_natureza_fiscal($args = [])
    {
        $businessId = (int) ($args['business_id'] ?? 0);
        $productId = (int) ($args['product_id'] ?? 0);
        if ($businessId <= 0 || $productId <= 0) {
            return null;
        }

        return app(\Modules\NfeBrasil\Services\Tributacao\NaturezaFiscalService::class)->daProduto($businessId, $productId);
    }

    /**
     * Feature flag do módulo para Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'nfebrasil_module',
                'label'   => 'Módulo NF-e Brasil',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'nfebrasil.access',
                'label'   => 'NF-e Brasil: acesso ao módulo',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.emit.manage',
                'label'   => 'NF-e Brasil: emitir notas fiscais',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.consult.view',
                'label'   => 'NF-e Brasil: consultar notas emitidas',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.sped.view',
                'label'   => 'NF-e Brasil: gerar/exportar SPED',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.settings.manage',
                'label'   => 'NF-e Brasil: gerenciar configurações fiscais',
                'default' => false,
            ],
            [
                'value'   => 'nfe.configuracao.manage',
                'label'   => 'NF-e Brasil: configurar certificado A1',
                'default' => false,
            ],
            [
                'value'   => 'nfe.tributacao.manage',
                'label'   => 'NF-e Brasil: gerenciar tributação (regras NCM + default)',
                'default' => false,
            ],
            [
                // Thread 15a · D-CONTADOR: aceite do responsável técnico. Separada de propósito e
                // fora do papel de administrador — o dono não aceita pelo contador.
                'value'   => 'nfe.tributacao.aceitar',
                'label'   => 'NF-e Brasil: aceitar regras tributárias (contador)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.view',
                'label'   => 'NF-e Brasil: ver NF-e recebidas (manifestação)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.manage',
                'label'   => 'NF-e Brasil: manifestar NF-e recebidas (Confirmação/Ciência/Desconhecimento/Não Realizada)',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NfeBrasil');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'nfebrasil_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('nfebrasil.access')
            || auth()->user()->can('nfebrasil.emit.manage')
            || auth()->user()->can('nfebrasil.consult.view')
            || auth()->user()->can('nfe.configuracao.manage')
            || auth()->user()->can('nfe.tributacao.manage')
            || auth()->user()->can('nfe.manifestacao.view')
            || auth()->user()->can('nfe.manifestacao.manage');

        if (! $usuario_pode_ver) {
            return;
        }

        // 2026-05-25 — Onda ESTABILIZAR Fiscal: NfeBrasil NÃO publica mais entries
        // no sidebar. Wagner apontou em sessão 2026-05-25 que "fiscal manifestação
        // certificado tem telas competindo" — as 3 entries que viviam aqui foram
        // movidas pra Modules/Fiscal/DataController (cockpit Fiscal vira hub
        // canon). NfeBrasil continua sendo o motor real (Services + rotas backend
        // /nfe-brasil/* ainda existem pra superadmin troubleshooting + ações
        // cross-fiscal que delegam ManifestacaoService/CertificadoService).
        //
        // As rotas /nfe-brasil/* seguem de pé, sem link no sidebar. Em 2026-11-11:
        // o GET /nfe-brasil/configuracao/certificado só redireciona (302) para
        // /fiscal/config; os POSTs de certificado são chamados pela tela do Fiscal.
        // /nfe-brasil/manifestacao responde com permissão nfe.manifestacao.view.
        // Todo /nfe-brasil/* com login exige o pacote fiscal_module (lote 3.3, #10732).
    }
}
