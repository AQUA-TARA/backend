<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIoTMeasurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Hardware ID is checked in the controller to return a 401 instead of 403
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'turbidity' => ['required', 'numeric', 'min:0'],
            'pressure' => ['required', 'numeric'],
            'measured_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
