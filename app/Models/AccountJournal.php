<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountJournal extends Model
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
        if (isset($order) && !is_null($order->id)) {
            $self = new self();
            $store_id = $order->store_id;
            $order_id = $order->id;

            $ifRawExists = $self->getAccountJournal($store_id, $order_id);
            if (is_null($ifRawExists)) {
                $store = Store::where('id', $store_id)->first();
                if (isset($store)) {
                    $user_id = $store->user_id ?? "";
                    $currency_id = $store->currency ?? 1;

                    $commission_percent = $store->dropship_commission ?? 0;
                    $product_order_amount = $order->subtotal;
                    $note = "Dropshipper order place";

                    // Calculate total commission in native currency
                    $currency_commission_amount = (((float)$product_order_amount * (float)$commission_percent) / 100);

                    $totalCommission = $self->currencyExchangeAmountINBDT($currency_commission_amount, $currency_id);
                    $dr = 0.00;
                    $cr = $totalCommission;
                    $AJBalance = self::getAccountBalance($store_id);
                    $balance = $AJBalance + $totalCommission;

                    $self->insertJournal($user_id, $store_id, $order_id, $product_order_amount, $commission_percent, $currency_id, $currency_commission_amount, $note, $dr, $cr, $balance);
                }
            }
        }

    }

    /**
     * Get account journal
     *
     * @param $store_id
     * @param $order_id
     * @return mixed
     */
    public function getAccountJournal($store_id, $order_id)
    {
        return AccountJournal::where('store_id', $store_id)->where('order_id', $order_id)->first();
    }

    /**
     * Get currency in BDT
     *
     * @param $currency_commission_amount
     * @param $currency_id
     * @return float
     */
    public function currencyExchangeAmountINBDT($currency_commission_amount, $currency_id)
    {
        $exchangeRate = Currency::where('id', $currency_id)->value('rate') ?? 1;
        $balanceInUSD = ((float)$currency_commission_amount / (float)$exchangeRate);

        $exchangeRateBDT = Currency::where('id', 1)->value('rate') ?? 1;

        return ($balanceInUSD * (float)$exchangeRateBDT) ?? 0;
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
        $balance = DB::table('account_journals')
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
     * @param $product_order_amount
     * @param $commission_percent
     * @param $note
     * @param $dr
     * @param $cr
     * @param $balance
     * @return void
     */
    private function insertJournal($user_id, $store_id, $order_id, $product_order_amount, $commission_percent, $currency_id, $currency_commission_amount, $note, $dr, $cr, $balance)
    {
        $self = new self();
        $voucher = $self::createVoucher();

        $dropship = new AccountJournal();
        $dropship->voucher = $voucher;
        $dropship->user_id = $user_id;
        $dropship->store_id = $store_id;
        $dropship->order_id = $order_id;
        $dropship->product_order_amount = $product_order_amount;
        $dropship->commission_percent = $commission_percent;
        $dropship->currency_id = $currency_id;
        $dropship->currency_commission_amount = $currency_commission_amount;
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
        $maxVoucherNo = DB::table('account_journals')->max('voucher');

        // Set a default value if no records exist
        $maxVoucherNo = $maxVoucherNo ?? 'DS000000000';

        // Generate the next ID based on the last max batch number
        $newVoucherNo = $self->generateID('DS', $maxVoucherNo, 11);

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

    public static function convertCurrency($convertAmount, $amountCurrency_id, $convertCurrency_id)
    {
        $exchangeRate = Currency::where('id', $amountCurrency_id)->value('rate') ?? 1;
        $balanceInUSD = ((float)$convertAmount / (float)$exchangeRate);

        $exchangeRate = Currency::where('id', $convertCurrency_id)->value('rate') ?? 1;

        return ($balanceInUSD * (float)$exchangeRate) ?? 0;
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

    public function currency()
    {
        return $this->belongsTo(Currency::class, "currency_id", "id");
    }


}
