<?php

namespace App\Http\Requests\CRM;

use App\Models\ClientDetailVerificationField;
use App\Support\StaffClientVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptClientDetailVerificationChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $field = $this->route('field');
        if (! $field instanceof ClientDetailVerificationField) {
            return false;
        }

        return StaffClientVisibility::canAccessClientOrLead((int) $field->client_id, $this->user('admin'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
