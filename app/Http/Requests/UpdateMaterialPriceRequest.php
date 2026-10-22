<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterialPriceRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna memiliki otorisasi untuk melakukan request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kurs_idr' => ['required', 'numeric', 'gt:0'],
            'hr_adj_mt_usd' => ['required', 'numeric'],
            'cr_adj_mt_usd' => ['required', 'numeric'],
        ];
    }

    /**
     * Dapatkan nama atribut kustom untuk pesan validasi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kurs_idr' => 'Nilai Tukar Rupiah (Kurs IDR)',
            'hr_adj_mt_usd' => 'Penyesuaian Harga HR ($/MT)',
            'cr_adj_mt_usd' => 'Penyesuaian Harga CR ($/MT)',
        ];
    }

    /**
     * Dapatkan pesan kesalahan kustom untuk validasi.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kurs_idr.required' => 'Nilai kurs IDR wajib diisi.',
            'kurs_idr.numeric' => 'Nilai kurs IDR harus berupa angka numerik.',
            'kurs_idr.gt' => 'Nilai kurs IDR harus lebih besar dari 0.',
            'hr_adj_mt_usd.required' => 'Penyesuaian harga HR (USD/MT) wajib diisi.',
            'hr_adj_mt_usd.numeric' => 'Penyesuaian harga HR (USD/MT) harus berupa angka.',
            'cr_adj_mt_usd.required' => 'Penyesuaian harga CR (USD/MT) wajib diisi.',
            'cr_adj_mt_usd.numeric' => 'Penyesuaian harga CR (USD/MT) harus berupa angka.',
        ];
    }
}
