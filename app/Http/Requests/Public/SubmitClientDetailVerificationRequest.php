<?php

namespace App\Http\Requests\Public;

use App\Support\ClientDetailVerificationFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitClientDetailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('fields_json') && ! $this->filled('fields')) {
            $decoded = json_decode((string) $this->input('fields_json'), true);
            $this->merge([
                'fields' => is_array($decoded) ? $decoded : [],
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*.key' => ['required', 'string'],
            'fields.*.status' => ['required', 'string'],
            'fields.*.requested_value' => ['nullable', 'string', 'max:2000'],
            'fields.*.current_value' => ['nullable', 'string', 'max:2000'],
            'fields.*.note' => ['nullable', 'string', 'max:2000'],
            'declaration' => ['accepted'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $check = ClientDetailVerificationFields::validateSubmittedFields(
                    $this->input('fields', [])
                );

                if (! $check['ok']) {
                    $validator->errors()->add('fields', $check['message'] ?? 'Invalid verification payload.');
                }
            },
        ];
    }
}
