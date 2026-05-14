<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Session;
use Hash;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     *
     * Set the validation message
     *
     * @return array
     */
    public function messages()
    {
        return [
            'phone.regex' => 'Invalid phone number.',
            'phone.max' => 'Invalid phone number.',
            'email.email' => 'Invalid email address.',
            'password.required' => 'Password is required.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'phone' => ['sometimes', 'nullable', 'regex:/(^([+]{1}[8]{2}[0]{1}|[8]{2}[0]{1}|[0]{1})?(1){1}[3-9]{1}\d{8})$/', 'max:14'],
            'email' => ['sometimes', 'nullable', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();

        $country_code = $this->input('country_code') ?? "BD";

        $emailOrPhone = $this->input('email_or_phone') ?? "";
        $password = $this->input('password') ?? "";

        if (is_numeric($emailOrPhone)) {
            $isEmail = false;
        } else {
            $isEmail = true;
        }

        $rules = [
            'email_or_phone' => ['required'],
            'password' => ['required', 'string'],
        ];

        if ($isEmail) {
            $rules['email_or_phone'][] = 'email';
        } else {
            $rules['email_or_phone'][] = 'phone:' . $country_code; // Basic phone validation

            \Illuminate\Support\Facades\Validator::extend('email_or_phone', function ($attribute, $value, $parameters, $validator) {
                $country = $parameters[0] ?? 'the country';
                return phone($value, [$country]); // This uses the phone validation logic
            }, 'The phone number must be a valid phone number for :country.');

            \Illuminate\Support\Facades\Validator::replacer('phone', function ($message, $attribute, $rule, $parameters) use ($country_code) {
                $countryName = getCountryName($country_code);
                return str_replace(':country', $countryName, $message);
            });
        }

        $message = [
            'email_or_phone.required' => 'Email/Phone is required.',
            'password.required' => 'Password is required.',
            'email_or_phone.email' => 'Enter a valid email address.',
        ];

        // Prepare input data for validation
        $data = [
            'email_or_phone' => $emailOrPhone,
            'password' => $password,
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            throw ValidationException::withMessages($errors);
            return back()->withInput();
        }

        if (!$isEmail) {
            // Parse the phone number to get only the local number (without country code)
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsedNumber = $phoneUtil->parse($emailOrPhone, $country_code);
            $emailOrPhone = $phoneUtil->getNationalSignificantNumber($parsedNumber);
//            $visitorInfo = getVisitorInfo();
//            if (isset($visitorInfo->countryCode) && $visitorInfo->countryCode == "BD") {
//                $emailOrPhone = '0' . $emailOrPhone;
//            }
            if ($country_code == "BD") {
                $emailOrPhone = '0' . $emailOrPhone;
            }
            $this->merge(['email_or_phone' => $emailOrPhone]);
        }

        $user = User::where(function ($q) use ($isEmail) {
            if ($isEmail) {
                $q->where('email', $this->input('email_or_phone'));
            } else {
                $q->where('phone', $this->input('email_or_phone'));
            }
        })->where(function ($q) {
            $q->where('type', 'admin')->orWhere('type', 'affiliate')->orWhere('type', 'superadmin')->orWhere('type', 'dropshipper');
        })->first();

        if (isset($user)) {
            if (!Hash::check($this->input('password'), $user->password)) {
                RateLimiter::hit($this->throttleKey());

                if ($isEmail) {
                    throw ValidationException::withMessages([
                        'email_or_phone' => trans('Invalid email or password'),
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        'email_or_phone' => trans('Invalid phone or password'),
                    ]);
                }
                return back()->withInput();
            } else {
                Auth::login($user, $this->has('remember'));
                RateLimiter::clear($this->throttleKey());
            }
        } else {
            RateLimiter::clear($this->throttleKey());
            if ($isEmail) {
                throw ValidationException::withMessages([
                    'email_or_phone' => trans('Invalid email or password'),
                ]);
            } else {
                throw ValidationException::withMessages([
                    'email_or_phone' => trans('Invalid phone or password'),
                ]);
            }
            return back()->withInput();
        }

        RateLimiter::clear($this->throttleKey());

    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited()
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'phone' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey()
    {
        return Str::lower($this->input('phone')) . '|' . $this->ip();
    }
}
