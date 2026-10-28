<?php

namespace Modules\Crm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Menu;
use Modules\Crm\Services\PortalContatoMenu;

class ContactSidebarMenu
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->ajax()) {
            return $next($request);
        }

        Menu::create('contact-sidebar-menu', function ($menu) {
            // A condição de cada item vem do PortalContatoMenu, que a casca React do portal
            // (thread Crm/18) também lê. Aqui só se escolhe rótulo, ícone e ordem da Blade.
            $fonte = app(PortalContatoMenu::class);
            $contact = $fonte->contatoDoUsuario();
            $enabled_modules = ! empty(session('business.enabled_modules')) ? session('business.enabled_modules') : [];
            $ativo = fn (string $segmento) => request()->segment(1) == 'contact' && request()->segment(2) == $segmento;

            $blade = [
                'painel' => [__('home.home'), 'fa fas fa-tachometer-alt', $ativo('contact-dashboard'), 1],
                'compras' => [__('purchase.list_purchase'), 'fa fas fa-list', $ativo('contact-purchases'), 2],
                'vendas' => [__('lang_v1.all_sales'), 'fa fas fa-list', $ativo('contact-sells'), 3],
                'pedidos' => [__('crm::lang.order_request'), 'fa fas fa-arrow-circle-up', $ativo('order-request'), 4],
                'extrato' => [__('lang_v1.ledger'), 'fas fa-scroll', $ativo('contact-ledger'), 3],
                'agendamentos' => [__('restaurant.bookings'), 'fas fa fa-calendar-check', request()->segment(1) == 'bookings', 3],
            ];

            foreach ($fonte->itens($contact, $enabled_modules) as $item) {
                [$rotulo, $icone, $ativa, $ordem] = $blade[$item['chave']];
                $menu->url($item['href'], $rotulo, ['icon' => $icone, 'active' => $ativa])->order($ordem);
            }
        });

        return $next($request);
    }
}
