<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/success',
        '/cancel',
        '/fail',
        '/ipn',
        '/login',
        '/logout',
        '/saveaudio',
        '/newupload',
        '/api/v1/apay/success-transaction',
        '/api/v1/apay/failed-transaction',
        '/a/apay/success-transaction',
        '/a/apay/failed-transaction',
        '/d/amarpay/success-transaction',
        '/d/amarpay/failed-transaction',
        '/affiliate/amarpay/success-transaction',
        '/affiliate/amarpay/failed-transaction',
        '/modulus/amarpay/success-transaction',
        '/modulus/amarpay/failed-transaction',
        '/uddoktapay/ipn-transaction',
        '/webhook/pathao',
        '/wa/auth/verify',
    ];


}
