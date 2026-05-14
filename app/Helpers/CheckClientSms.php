<?php

namespace App\Helpers;

use App\Models\AddonsExpired;

/**
 * Class CheckClientSms
 *
 * This class is responsible for checking the SMS limit for a client based on their store ID and modulus ID.
 */
class CheckClientSms
{
    /** @var $store_id The ID of the store for which SMS limit is to be checked. */
    public $store_id;

    /** @var $modulus_id The ID of the SMS modulus. */
    public $modulus_id;

    /**
     * CheckClientSms constructor.
     *
     * @param string $store_id The ID of the store for which SMS limit is to be checked.
     * @param string $modulus_id The ID of the SMS modulus.
     */
    public function __construct($storeId, $moduleId)
    {
        $this->store_id = $storeId;
        $this->modulus_id = $moduleId;
    }

    /**
     * Check the SMS limit for the client.
     *
     * @return bool Returns true if the SMS limit is reached (0 or less remaining SMS), false otherwise.
     */
    public function checkSmsLimit(): bool
    {
        // Fetch the SMS addon record based on store ID and modulus ID
        $checkSms = AddonsExpired::where('store_id', $this->store_id)->where('addons_id', $this->modulus_id)->first();

        // If no SMS addon record is found or remaining SMS count is 0 or less, return true
        if (!$checkSms || $checkSms->total - $checkSms->used >= 1) {
            return true;
        }

        // Otherwise, return false
        return false;
    }
}
