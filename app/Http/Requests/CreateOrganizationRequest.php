<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreateOrganizationRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->hasRole('super admin') || auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name'                      => ['required', 'string', 'max:164', Rule::unique('organizations')->whereNull('deleted_at')],
            'email'                     => ['required', 'email', 'max:164', Rule::unique('organizations')->whereNull('deleted_at')],
            'phone'                     => 'required|string|max:24',
            'person_in_charge_name'     => 'required|string|max:164',
            'address'                   => 'required|string',
            'billing_address'           => 'required|string',
            'shipping_address'          => 'required|string',
            'img'                       => 'required|string|max:255',
            'country'                   => 'required|string|max:64',
        ];
    }
}
