<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeldeFehlendenLieferantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::meldungFieldRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function meldungFieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:2000'],
            'iban' => ['nullable', 'string', 'max:64'],
            'webseite' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function livewireValidationRules(): array
    {
        $rules = self::meldungFieldRules();

        return [
            'fehlenderLieferantName' => $rules['name'],
            'fehlenderLieferantAdresse' => $rules['adresse'],
            'fehlenderLieferantIban' => $rules['iban'],
            'fehlenderLieferantWebseite' => $rules['webseite'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie den Namen des Lieferanten an.',
            'fehlenderLieferantName.required' => 'Bitte geben Sie den Namen des Lieferanten an.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationAttributes(): array
    {
        return [
            'name' => 'Name',
            'adresse' => 'Adresse',
            'iban' => 'IBAN',
            'webseite' => 'Webseite',
            'fehlenderLieferantName' => 'Name',
            'fehlenderLieferantAdresse' => 'Adresse',
            'fehlenderLieferantIban' => 'IBAN',
            'fehlenderLieferantWebseite' => 'Webseite',
        ];
    }
}
