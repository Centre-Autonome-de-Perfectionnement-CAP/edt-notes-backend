<?php

namespace App\Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: brancher sur la policy/rôle "responsable pédagogique"
    }

    public function rules(): array
    {
        return [
            'module_id' => ['sometimes', 'required', 'integer', 'exists:modules,id'],
            'enseignant_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'required', 'date'],
            'heure_debut' => ['sometimes', 'required', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['cours', 'td', 'tp'])],
            'statut' => ['sometimes', 'required', Rule::in(['planifie', 'annule', 'reporte'])],
        ];
    }

    public function messages(): array
    {
        return [
            'heure_fin.after' => "L'heure de fin doit être postérieure à l'heure de début.",
        ];
    }
}