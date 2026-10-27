<?php

namespace App\Listeners;

use App\AccountTransaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;

class DeleteAccountTransaction
{
    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        //Add contact advance if exists
        if ($event->transactionPayment->method == 'advance') {
            // Espelho do AddAccountTransaction: apagar o vale-troca tira do saldo o que ele pôs.
            $sentido = \App\Services\Sells\ValeTrocaService::ehVale($event->transactionPayment) ? 'deduct' : 'add';
            $this->transactionUtil->updateContactBalance($event->transactionPayment->payment_for, $event->transactionPayment->amount, $sentido);
        }

        if (! $this->moduleUtil->isModuleEnabled('account')) {
            return true;
        }

        AccountTransaction::where('account_id', $event->transactionPayment->account_id)
                        ->where('transaction_payment_id', $event->transactionPayment->id)
                        ->delete();
    }
}
