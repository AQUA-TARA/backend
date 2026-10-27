<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Auth is optional for report submission (DEC-005, DEC-006).
     * Anonymous and authenticated citizens are both allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Source of truth: API_CONTRACT.md §13, docs/DECISIONS.md DEC-014, DEC-015
     *
     * OPEN ITEMS (not locked by source of truth):
     * - photo: max file size → OPEN (API_CONTRACT §28, not specified)
     * - photo: MIME type enforcement → OPEN (DEC-014 states camera capture validation
     *   cannot be fully enforced server-side; backend validates MIME best-effort)
     * - latitude/longitude: decimal precision constraints → OPEN
     * - description: max character length → OPEN
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Photo is required. MIME types: image/* accepted.
            // Photo is required. Validated: JPEG, PNG, WebP with max 5MB (5120 KB)
            'photo'       => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],

            // Auto-geolocation coordinates — required per DEC-015 and API_CONTRACT §13.
            // Numeric validation only; precision constraints are OPEN.
            'latitude'    => ['required', 'numeric'],
            'longitude'   => ['required', 'numeric'],

            // Description is required per product decision (DEC-014 / reports.description NOT NULL).
            'description' => ['required', 'string'],

            // Report category per technical specification
            'report_type' => ['required', 'in:kebocoran,air_keruh,lainnya'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'photo.required'       => 'Foto laporan wajib disertakan.',
            'photo.file'           => 'Foto harus berupa file.',
            'photo.image'          => 'Foto harus berupa file gambar.',
            'photo.mimes'          => 'Format foto harus berupa JPEG, PNG, atau WebP.',
            'photo.max'            => 'Ukuran foto maksimal adalah 5 MB.',
            'latitude.required'    => 'Koordinat lokasi wajib disertakan.',
            'latitude.numeric'     => 'Nilai latitude harus berupa angka.',
            'longitude.required'   => 'Koordinat lokasi wajib disertakan.',
            'longitude.numeric'    => 'Nilai longitude harus berupa angka.',
            'description.required' => 'Deskripsi laporan wajib diisi.',
            'description.string'   => 'Deskripsi laporan harus berupa teks.',
            'report_type.required' => 'Kategori laporan wajib dipilih.',
            'report_type.in'       => 'Kategori laporan tidak valid. Pilihan yang diperbolehkan: kebocoran, air_keruh, lainnya.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Returns 422 JSON response without exposing internal stack trace.
     * Source: API_CONTRACT.md §13 Validation Rules.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
