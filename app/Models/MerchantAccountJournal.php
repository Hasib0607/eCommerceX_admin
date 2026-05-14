<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MerchantAccountJournal extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Save journal
     *
     * @param $order
     * @return void
     */
    public static function saveJournal($order)
    {
        if (isset($order) && !is_null($order->order_id)) {
            $self = new self();
            $store_id = $order->store_id;
            $order_id = $order->order_id;
            $transaction_id = $order->transactionId;

            $ifRawExists = $self->getAccountJournal($store_id, $order_id, $transaction_id);
            if (is_null($ifRawExists)) {
                $store = Store::where('id', $store_id)->first();
                if (isset($store)) {
                    $user_id = $store->user_id ?? "";
                    $order_transaction_id = $order->id ?? "";
                    $order_amount = $order->amountPaid ?? "";
                    $commission_percent = $order->merchant_processing_ratio ?? "";
                    $commission_amount = $order->merchant_processing_charge ?? "";
                    $store_amount = $order->merchant_amount ?? "";
                    $note = "Order Place amount";

                    $dr = 0.00;
                    $cr = $store_amount;
                    $AJBalance = self::getAccountBalance($store_id);
                    $balance = $AJBalance + $store_amount;

                    $self->insertJournal($user_id, $store_id, $order_id, $transaction_id, $order_transaction_id, $order_amount, $commission_percent, $commission_amount, $store_amount, $note, $dr, $cr, $balance);
                }
            }
        }

    }


    public static function saveWithdrawRequest($MerchantPaymentWithdraw)
    {
        if (isset($MerchantPaymentWithdraw) && !is_null($MerchantPaymentWithdraw->store_id)) {
            $self = new self();
            $store_id = $MerchantPaymentWithdraw->store_id;

            $store = Store::where('id', $store_id)->first();
            if (isset($store)) {
                $user_id = $store->user_id ?? "";
                $order_id = null;
                $transaction_id = null;
                $order_transaction_id = null;
                $order_amount = 0.00;
                $commission_percent = 0.00;
                $commission_amount = 0.00;
                $store_amount = 0.00;
                $note = "Merchant Payment Withdraw Amount";

                $dr = $MerchantPaymentWithdraw->withdraw_amount;
                $cr = 0.00;
                $AJBalance = self::getAccountBalance($store_id);
                $balance = (float)$AJBalance - (float)$MerchantPaymentWithdraw->withdraw_amount;

                $self->insertJournal($user_id, $store_id, $order_id, $transaction_id, $order_transaction_id, $order_amount, $commission_percent, $commission_amount, $store_amount, $note, $dr, $cr, $balance);
            }

        }

    }

    /**
     * Get account journal
     *
     * @param $store_id
     * @param $order_id
     * @param $transaction_id
     * @return mixed
     */
    public function getAccountJournal($store_id, $order_id, $transaction_id)
    {
        return MerchantAccountJournal::where('store_id', $store_id)->where('order_id', $order_id)->where('transaction_id', $transaction_id)->first();
    }


    /**
     * Get account balnace
     *
     * @param $store_id
     * @return int|mixed
     */
    public static function getAccountBalance($store_id)
    {
        // Use the query builder to calculate the balance amount
        $balance = DB::table('merchant_account_journals')
            ->select(DB::raw('SUM(dr) - SUM(cr) as balance_amount'))
            ->where('store_id', $store_id)
            ->value('balance_amount');

        // If the balance is null, set it to 0
        return abs($balance) ?? 0;
    }

    /**
     * Insert journal
     *
     * @param $user_id
     * @param $store_id
     * @param $order_id
     * @param $transaction_id
     * @param $order_transaction_id
     * @param $order_amount
     * @param $commission_percent
     * @param $commission_amount
     * @param $store_amount
     * @param $note
     * @param $dr
     * @param $cr
     * @param $balance
     * @return void
     */
    private function insertJournal($user_id, $store_id, $order_id, $transaction_id, $order_transaction_id, $order_amount, $commission_percent, $commission_amount, $store_amount, $note, $dr, $cr, $balance)
    {
        $self = new self();
        $voucher = $self::createVoucher();

        $dropship = new MerchantAccountJournal();
        $dropship->voucher = $voucher;
        $dropship->user_id = $user_id;
        $dropship->store_id = $store_id;
        $dropship->order_id = $order_id;
        $dropship->transaction_id = $transaction_id;
        $dropship->order_transaction_id = $order_transaction_id;
        $dropship->order_amount = $order_amount;
        $dropship->commission_percent = $commission_percent;
        $dropship->commission_amount = $commission_amount;
        $dropship->store_amount = $store_amount;
        $dropship->note = $note;
        $dropship->dr = $dr;
        $dropship->cr = $cr;
        $dropship->balance = $balance;
        $dropship->save();
    }

    /**
     *
     * Create voucher
     *
     * @return string
     */
    public static function createVoucher()
    {
        $self = new self();

        // Fetch the max batch_no from the account_journals table
        $maxVoucherNo = DB::table('merchant_account_journals')->max('voucher');

        // Set a default value if no records exist
        $maxVoucherNo = $maxVoucherNo ?? 'MPH000000000';

        // Generate the next ID based on the last max batch number
        $newVoucherNo = $self->generateID('MPH', $maxVoucherNo, 11);

        return $newVoucherNo;
    }

    /**
     *  Generate voucher ID
     *
     * @param $prefix
     * @param $maxId
     * @param $len
     * @return string
     */
    public function generateID($prefix, $maxId, $len)
    {
        // Remove the prefix from the maxId and convert to an integer
        $nextIdNum = intval(str_replace($prefix, '', $maxId)) + 1;

        // Calculate the padding length needed to maintain the desired total length
        $padlen = $len - (strlen($prefix) + strlen($nextIdNum));

        // Generate the next ID with the appropriate padding
        $nextID = $prefix . str_pad($nextIdNum, $padlen, "0", STR_PAD_LEFT);

        // Check if the generated ID meets the length requirement
        if (strlen($nextID) <= $len) {
            return $nextID;
        } else {
            return "";
        }
    }


    public function store()
    {
        return $this->belongsTo(Store::class, "store_id", "id");
    }

    public function order()
    {
        return $this->belongsTo(Order::class, "order_id", "id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }


}
