<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'in:unpaid,paid,failed,refunded'],
        ];
    }
}
