<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\File;

class StoreBestellungAngebotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var array<int, string> $allowedExtensions */
        $allowedExtensions = config('intranet-app-bestellungen.api.allowed_extensions', ['pdf']);

        return [
            'file' => [
                'required',
                File::types($allowedExtensions)->max((int) config('intranet-app-bestellungen.api.max_upload_kb', 10240)),
            ],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        /** @var array<int, string> $allowedMimes */
        $allowedMimes = config('intranet-app-bestellungen.api.allowed_mimes', ['application/pdf']);

        $validator->after(function (Validator $validator) use ($allowedMimes): void {
            if (! $this->hasFile('file')) {
                return;
            }

            $mimeType = $this->file('file')?->getMimeType();
            if ($mimeType === null || ! in_array($mimeType, $allowedMimes, true)) {
                $validator->errors()->add('file', 'Die Datei muss dem erlaubten MIME-Typ entsprechen.');
            }
        });
    }
}
