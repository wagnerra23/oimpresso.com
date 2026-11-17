<?php

declare(strict_types=1);

namespace App\Services\Pessoas;

use App\Contact;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de visibilidade da lista de pessoas (contacts), num lugar só: a tela web
 * (ContactController) e a API do app das lojas (Api\App\PessoasController) usam as mesmas.
 * Extraído de ContactController::applyViewOwnFilter / applyContactTypeFilter — comportamento
 * idêntico; antes as regras viviam em métodos privados do controller (#8469 corrigiu o view_own
 * lá, e uma cópia na API repetiria o defeito que o #8469 fechou).
 */
final class PessoaEscopo
{
    private const FLAGS = [
        'customer' => 'is_customer',
        'supplier' => 'is_supplier',
        'employee' => 'is_employee',
        'representative' => 'is_representative',
        // ADR 0246 (2026-06-03) — categoria "Outros" canônica
        'other' => 'is_other',
    ];

    /**
     * "Só os próprios" para quem tem `X.view_own` sem `X.view`: `contacts.created_by = usuário` OU
     * contato compartilhado em `user_contact_access`. Fornecedor usa supplier.*, os demais papéis
     * customer.* (ADR 0188); 'all' restringe se qualquer um dos dois for só-próprios.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $q
     * @return mixed
     */
    public static function viewOwn($q, string $type, ?User $u)
    {
        if (! $u) {
            return $q;
        }
        $soCliente = ! $u->can('customer.view') && $u->can('customer.view_own');
        $soFornecedor = ! $u->can('supplier.view') && $u->can('supplier.view_own');
        $soProprios = match ($type) {
            'supplier' => $soFornecedor,
            'all' => $soCliente || $soFornecedor,
            default => $soCliente,
        };
        if (! $soProprios) {
            return $q;
        }
        $uid = (int) $u->id;

        return $q->where(function ($w) use ($uid) {
            $w->where('contacts.created_by', $uid)
                ->orWhereExists(function ($e) use ($uid) {
                    $e->select(DB::raw(1))
                        ->from('user_contact_access')
                        ->whereColumn('user_contact_access.contact_id', 'contacts.id')
                        ->where('user_contact_access.user_id', $uid);
                });
        });
    }

    /**
     * A regra "só os próprios" de viewOwn() para UM contato já localizado na empresa.
     *
     * A lista aplica viewOwn(); os endereços que a ficha chama para cada contato
     * (/cliente/{id}/sales-json, anexos, IA, auditoria…) só conferiam se o usuário tinha
     * alguma permissão de ver, e quem tinha só `X.view_own` lia qualquer contato trocando o
     * número no endereço. Recusa apenas nesse caso: nenhum papel do contato é visível por
     * inteiro (`X.view`), ao menos um é só-próprios, e o contato não é dele (`created_by`) nem
     * compartilhado com ele (`user_contact_access`). Fornecedor usa supplier.*, os demais
     * papéis customer.* (ADR 0188), como em viewOwn().
     */
    public static function podeVerContato(Contact $contact, ?User $u): bool
    {
        if (! $u) {
            return false;
        }

        $temFornecedor = Schema::hasColumn('contacts', 'is_supplier')
            ? (bool) $contact->is_supplier
            : in_array($contact->type, ['supplier', 'both'], true);
        $temOutroPapel = Schema::hasColumn('contacts', 'is_customer')
            ? ($contact->is_customer || $contact->is_employee || $contact->is_representative || $contact->is_other)
            : in_array($contact->type, ['customer', 'both'], true);
        if (! $temFornecedor && ! $temOutroPapel) {
            $temOutroPapel = true;
        }

        $grupos = [];
        if ($temOutroPapel) {
            $grupos[] = 'customer';
        }
        if ($temFornecedor) {
            $grupos[] = 'supplier';
        }

        $soProprios = false;
        foreach ($grupos as $g) {
            if ($u->can("{$g}.view")) {
                return true;
            }
            if ($u->can("{$g}.view_own")) {
                $soProprios = true;
            }
        }
        if (! $soProprios) {
            // Sem view nem view_own para os papéis deste contato: a trava de entrada de cada
            // endpoint decide, como antes. Esta função só acrescenta a regra do só-próprios.
            return true;
        }

        $uid = (int) $u->id;
        if ((int) $contact->created_by === $uid) {
            return true;
        }

        return DB::table('user_contact_access')
            ->where('contact_id', $contact->id)
            ->where('user_id', $uid)
            ->exists();
    }

    /**
     * Filtro por papel canônico (ADR 0188): flags `is_X` aditivas, com fallback para o `type`
     * enum UPOS legado em ambiente pré-migration.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $q
     * @return mixed
     */
    public static function papel($q, string $type)
    {
        if ($type === 'all') {
            return $q;
        }

        $flag = self::FLAGS[$type] ?? null;
        if ($flag === null) {
            return $q->where('contacts.type', 'customer');
        }

        if (Schema::hasColumn('contacts', $flag)) {
            return $q->where("contacts.{$flag}", 1);
        }

        if ($type === 'customer') {
            return $q->whereIn('contacts.type', ['customer', 'both']);
        }

        return $q->where('contacts.type', $type);
    }
}
