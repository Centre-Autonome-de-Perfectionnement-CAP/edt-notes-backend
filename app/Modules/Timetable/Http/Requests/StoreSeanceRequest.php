<?php

namespace App\Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: brancher sur la policy/rôle "responsable pédagogique" une fois l'auth en place
    }

    public function rules(): array
    {
        return [
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'enseignant_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['cours', 'td', 'tp'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => "La date de la séance ne peut pas être dans le passé.",
            'heure_fin.after' => "L'heure de fin doit être postérieure à l'heure de début.",
        ];
    }
}