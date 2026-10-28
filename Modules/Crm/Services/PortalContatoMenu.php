<?php

namespace Modules\Crm\Services;

use App\Business;
use App\Contact;

/**
 * Quais telas do portal o contato logado pode abrir — a fonte única das duas cascas.
 *
 * O `ContactSidebarMenu` (Blade) e a casca React do portal (D6, thread Crm/18) leem daqui.
 * Antes a regra só existia dentro do `Menu::create` do middleware, que volta cedo em requisição
 * ajax — e toda visita Inertia é ajax. Cada casca escolhe o próprio rótulo e ícone; a condição
 * de cada item é uma só.
 *
 * Escopo: o contato vem do `business_id` do usuário e do `crm_contact_id` dele (Tier 0).
 */
class PortalContatoMenu
{
    /**
     * @return array<int, array{chave: string, href: string}> na ordem da Blade
     */
    public function itens(Contact $contato, array $modulosLigados): array
    {
        $crm = Business::where('id', $contato->business_id)->value('crm_settings');
        $crm = ! empty($crm) ? json_decode($crm, true) : [];

        $ehFornecedor = in_array($contato->type, ['supplier', 'both']);
        $ehCliente = in_array($contato->type, ['customer', 'both']);

        $itens = [['chave' => 'painel', 'href' => action([\Modules\Crm\Http\Controllers\DashboardController::class, 'index'])]];
        if ($ehFornecedor) {
            $itens[] = ['chave' => 'compras', 'href' => action([\Modules\Crm\Http\Controllers\PurchaseController::class, 'getPurchaseList'])];
        }
        if ($ehCliente) {
            $itens[] = ['chave' => 'vendas', 'href' => action([\Modules\Crm\Http\Controllers\SellController::class, 'getSellList'])];
            if (! empty($crm['enable_order_request'])) {
                $itens[] = ['chave' => 'pedidos', 'href' => action([\Modules\Crm\Http\Controllers\OrderRequestController::class, 'index'])];
            }
        }
        $itens[] = ['chave' => 'extrato', 'href' => action([\Modules\Crm\Http\Controllers\LedgerController::class, 'index'])];
        if (in_array('booking', $modulosLigados)) {
            $itens[] = ['chave' => 'agendamentos', 'href' => action([\Modules\Crm\Http\Controllers\ContactBookingController::class, 'index'])];
        }

        return $itens;
    }

    /**
     * Dado da casca do contato (prop `shell.portal`): a loja, o contato e as telas que ele pode abrir.
     * As Pages do portal substituem a prop `shell` inteira por `['portal' => casca()]`, então o menu
     * do ERP não desce para quem é contato (UC-CRMPO-03).
     */
    public function casca(Contact $contato, array $modulosLigados): array
    {
        $usuario = auth()->user();

        return [
            'empresa' => (string) (Business::where('id', $contato->business_id)->value('name') ?? ''),
            'contato' => ['nome' => (string) $contato->name, 'pessoa' => (string) trim($usuario->first_name.' '.$usuario->last_name), 'tipo' => (string) $contato->type],
            'menu' => $this->itens($contato, $modulosLigados),
            'perfil' => action([\Modules\Crm\Http\Controllers\ManageProfileController::class, 'getProfile']),
            'novo_pedido' => action([\Modules\Crm\Http\Controllers\OrderRequestController::class, 'create']),
            'agendar' => action([\Modules\Crm\Http\Controllers\ContactBookingController::class, 'create']),
        ];
    }

    /** O contato do usuário logado, neste negócio — ou 404. */
    public function contatoDoUsuario(): Contact
    {
        return Contact::where('business_id', auth()->user()->business_id)
            ->findOrFail(auth()->user()->crm_contact_id);
    }
}
