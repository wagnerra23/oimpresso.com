<?php

namespace App\Utils;

use App\Contact;
use App\Utils\TransactionUtil;
use App\Transaction;
use DB;

class ContactUtil extends Util
{

    /**
     * Returns Walk In Customer for a Business
     *
     * @param int $business_id
     *
     * @return array/false
     */
    public function getWalkInCustomer($business_id, $array = true)
    {
        $contact = Contact::whereIn('type', ['customer', 'both'])
                    ->where('business_id', $business_id)
                    ->where('is_default', 1)
                    ->first();

        if (!empty($contact)) {
            $output = $array ? $contact->toArray() : $contact;
            return $output;
        } else {
            return null;
        }
    }

    /**
     * Returns the customer group
     *
     * @param int $business_id
     * @param int $customer_id
     *
     * @return array
     */
    public function getCustomerGroup($business_id, $customer_id)
    {
        $cg = [];

        if (empty($customer_id)) {
            return $cg;
        }

        $contact = Contact::leftjoin('customer_groups as CG', 'contacts.customer_group_id', 'CG.id')
            ->where('contacts.id', $customer_id)
            ->where('contacts.business_id', $business_id)
            ->select('CG.*')
            ->first();

        return $contact;
    }

    /**
     * Returns the contact info
     *
     * @param int $business_id
     * @param int $contact_id
     *
     * @return array
     */
    public function getContactInfo($business_id, $contact_id)
    {
        $contact = Contact::where('contacts.id', $contact_id)
                    ->where('contacts.business_id', $business_id)
                    ->join('transactions AS t', 'contacts.id', '=', 't.contact_id')
                    ->with(['business'])
                    ->select(
                        DB::raw("rua, numero"),
                        DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                        DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                        DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                        DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                        DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
                        'contacts.*',
                    )->first();

        return $contact;
    }

    public function createNewContact($input)
    {
        //Check Contact id
        $count = 0;
        if (!empty($input['contact_id'])) {
            $count = Contact::where('business_id', $input['business_id'])
                            ->where('contact_id', $input['contact_id'])
                            ->count();
        }
        if ($count == 0) {
            //Update reference count
            $ref_count = $this->setAndGetReferenceCount('contacts', $input['business_id']);

            if (empty($input['contact_id'])) {
                //Generate reference number
                $input['contact_id'] = $this->generateReferenceNumber('contacts', $ref_count, $input['business_id']);
            }

            $opening_balance = isset($input['opening_balance']) ? $input['opening_balance'] : 0;
            if (isset($input['opening_balance'])) {
                unset($input['opening_balance']);
            }

            $contact = Contact::create($input);

            //Add opening balance
            if (!empty($opening_balance)) {
                $transactionUtil = new TransactionUtil();
                $transactionUtil->createOpeningBalanceTransaction($contact->business_id, $contact->id, $opening_balance, $contact->created_by, false);
            }

            $output = ['success' => true,
                        'data' => $contact,
                        'msg' => __("contact.added_success")
                    ];
            return $output;
        } else {
            throw new \Exception("Error Processing Request", 1);
        }
    }

    public function updateContact($input, $id, $business_id)
    {
        $count = 0;
        //Check Contact id
        if (!empty($input['contact_id'])) {
            $count = Contact::where('business_id', $business_id)
                    ->where('contact_id', $input['contact_id'])
                    ->where('id', '!=', $id)
                    ->count();
        }

        if ($count == 0) {
            //Get opening balance if exists
            $ob_transaction =  Transaction::where('contact_id', $id)
                                    ->where('type', 'opening_balance')
                                    ->first();
            $opening_balance = isset($input['opening_balance']) ? $input['opening_balance'] : 0;

            if (isset($input['opening_balance'])) {
                unset($input['opening_balance']);
            }
            
            $contact = Contact::where('business_id', $business_id)->findOrFail($id);

            if (date_create($input['office_oimpresso_updated_at'])) {
                $office_updated_at = date_format(date_create($input['office_oimpresso_updated_at']), 'Y-m-d H:i:s');    
            } else {
                $office_updated_at = 0;
            }

            if (date_create($input['office_oimpresso_updated_at'])) {
                $oimpresso_updated_at = date_format(date_create($contact['updated_at']), 'Y-d-m H:i:s');   
            } else {
                $oimpresso_updated_at = 0;
            }
            
            if ($office_updated_at == $oimpresso_updated_at) {
                $input['is_sincronizado'] = 1;
            };

            //unset($input['office_oimpresso_updated_at']);

            foreach ($input as $key => $value) {
                if (isset($contact->$key))
                    $contact->$key = $value;
            }

            $contact->save();
            
            $transactionUtil = new TransactionUtil();
            if (!empty($ob_transaction)) {
                $opening_balance_paid = $transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (!empty($opening_balance_paid)) {
                    $opening_balance += $opening_balance_paid;
                }
                
                $ob_transaction->final_total = $opening_balance;
                $ob_transaction->save();
                //Update opening balance payment status
                $transactionUtil->updatePaymentStatus($ob_transaction->id, $ob_transaction->final_total);
            } else {
                //Add opening balance
                if (!empty($opening_balance)) {
                    $transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance, $contact->created_by, false);
                }
            }

            $output = ['success' => true,
                        'msg' => __("contact.updated_success"),
                        'data' => $contact
                        ];
        } else {
            throw new \Exception("Error Processing Request", 1);
        }

        return $output;
    }

    public function getContactQuery($business_id, $type, $contact_ids = [])
    {
        $query = Contact::leftjoin('transactions AS t', 'contacts.id', '=', 't.contact_id')
                    ->leftjoin('customer_groups AS cg', 'contacts.customer_group_id', '=', 'cg.id')
                    ->where('contacts.business_id', $business_id);

        if ($type == 'supplier') {
           $query->onlySuppliers();
        } elseif ($type == 'customer') {
            $query->onlyCustomers();
        }
        if (!empty($contact_ids)) {
            $query->whereIn('contacts.id', $contact_ids);
        }

        $query->select([
            'contacts.*', 
            'cg.name as customer_group',
            DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
            DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid")
        ]);

        if (in_array($type, ['supplier', 'both'])) {
            $query->addSelect([
                DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_return_paid")
            ]);
        }

        if (in_array($type, ['customer', 'both'])) {
            $query->addSelect([
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as sell_return_paid")
            ]);
        } 
        $query->groupBy('contacts.id');

        return $query;
    }
}