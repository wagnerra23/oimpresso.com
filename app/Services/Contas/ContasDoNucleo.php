<?php

namespace App\Services\Contas;

use App\Account;
use App\AccountTransaction;
use App\BusinessLocation;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Contas de pagamento do núcleo (`accounts`) — um só dono do SQL do saldo e das escritas.
 *
 * A tela `Financeiro/ContasBancarias` e o `AccountController` (enquanto a Blade existir) chamam esta classe, então
 * o saldo, o filtro por local e o saldo de abertura não têm segunda implementação (regra mestre de valor).
 * `fin_contas_bancarias` é complemento 1:1 da conta (FK única em `account_id`), não cadastro paralelo.
 * Decisão [W] 2026-10-27: a tela única de contas é a do Financeiro.
 */
class ContasDoNucleo
{
    public function __construct(private Util $util, private ModuleUtil $moduleUtil)
    {
    }

    /**
     * Contas do negócio com saldo (crédito − débito, sem lançamento apagado) e a restrição por local.
     * Sem filtro de situação nem groupBy: cada chamador põe o seu. SQL movido do AccountController sem mudança.
     */
    public function consulta(int $businessId, User $user): Builder
    {
        $accounts = Account::leftjoin('account_transactions as AT', function ($join) {
            $join->on('AT.account_id', '=', 'accounts.id')
                ->whereNull('AT.deleted_at');
        })
            ->leftjoin('account_types as ats', 'accounts.account_type_id', '=', 'ats.id')
            ->leftjoin('account_types as pat', 'ats.parent_account_type_id', '=', 'pat.id')
            ->leftJoin('users AS u', 'accounts.created_by', '=', 'u.id')
            ->where('accounts.business_id', $businessId)
            ->select(['accounts.name', 'accounts.account_number', 'accounts.note', 'accounts.id', 'accounts.account_type_id',
                'ats.name as account_type_name',
                'pat.name as parent_account_type_name',
                'accounts.account_details',
                'is_closed', DB::raw("SUM( IF(AT.type='credit', amount, -1*amount) ) as balance"),
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
            ]);

        // Sem access_all_locations e sem Admin#: só as contas habilitadas nos locais permitidos.
        $permitted_locations = $user->permitted_locations();
        $account_ids = [];
        if ($permitted_locations != 'all') {
            $locations = BusinessLocation::where('business_id', $businessId)
                ->whereIn('id', $permitted_locations)
                ->get();

            foreach ($locations as $location) {
                if (! empty($location->default_payment_accounts)) {
                    $default_payment_accounts = json_decode($location->default_payment_accounts, true);
                    foreach ($default_payment_accounts as $account) {
                        if (! empty($account['is_enabled']) && ! empty($account['account'])) {
                            $account_ids[] = $account['account'];
                        }
                    }
                }
            }

            $account_ids = array_unique($account_ids);
        }

        if (! $this->moduleUtil->is_admin($user, $businessId) && $permitted_locations != 'all') { // @phpstan-ignore argument.type (docblock do Util diz "obj"; recebe o User, como no AccountController)
            $accounts->whereIn('accounts.id', $account_ids);
        }

        return $accounts;
    }

    /**
     * Cria a conta e, se informado, o saldo de abertura (crédito `opening_balance` na data de hoje).
     * O valor passa pelo mesmo `num_uf` do cadastro Blade.
     */
    public function criar(int $businessId, int $userId, array $campos, $saldoAbertura = null): Account
    {
        return DB::transaction(function () use ($businessId, $userId, $campos, $saldoAbertura) {
            $account = Account::create(array_merge($this->soCampos($campos), [
                'business_id' => $businessId,
                'created_by' => $userId,
            ]));

            if (! empty($saldoAbertura)) {
                AccountTransaction::createAccountTransaction([
                    'amount' => $this->util->num_uf($saldoAbertura),
                    'account_id' => $account->id,
                    'type' => 'credit',
                    'sub_type' => 'opening_balance',
                    'operation_date' => \Carbon::now(),
                    'created_by' => $userId,
                ]);
            }

            return $account;
        });
    }

    /** Atualiza o cadastro. Não toca em saldo. */
    public function atualizar(int $businessId, int $accountId, array $campos): Account
    {
        $account = Account::where('business_id', $businessId)->findOrFail($accountId);
        $account->fill($this->soCampos($campos))->save();

        return $account;
    }

    /** Encerra (1) ou reativa (0). Conta não se apaga: o movimento fica. */
    public function definirEncerrada(int $businessId, int $accountId, bool $encerrada): Account
    {
        $account = Account::where('business_id', $businessId)->findOrFail($accountId);
        $account->is_closed = $encerrada;
        $account->save();

        return $account;
    }

    private function soCampos(array $campos): array
    {
        return array_intersect_key($campos, array_flip(['name', 'account_number', 'note', 'account_type_id', 'account_details']));
    }
}
