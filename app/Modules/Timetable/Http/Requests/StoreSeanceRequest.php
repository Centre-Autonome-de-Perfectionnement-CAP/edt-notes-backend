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
            'module_id.required' => 'Le module est requis.',
            'module_id.integer' => 'Le module doit être un entier.',
            'module_id.exists' => "Ce module n'existe pas.",
            'enseignant_id.required' => "L'enseignant est requis.",
            'enseignant_id.integer' => "L'enseignant doit être un entier.",
            'enseignant_id.exists' => "Cet enseignant n'existe pas.",
            'date.required' => 'La date est requise.',
            'date.date' => 'La date doit être une date valide.',
            'date.after_or_equal' => "La date de la séance ne peut pas être dans le passé.",
            'heure_debut.required' => "L'heure de début est requise.",
            'heure_debut.date_format' => "Le format de l'heure de début doit être H:i.",
            'heure_fin.required' => "L'heure de fin est requise.",
            'heure_fin.date_format' => "Le format de l'heure de fin doit être H:i.",
            'heure_fin.after' => "L'heure de fin doit être postérieure à l'heure de début.",
            'salle.string' => 'La salle doit être une chaîne de caractères.',
            'salle.max' => 'La salle ne peut pas dépasser 255 caractères.',
            'type.required' => 'Le type est requis.',
            'type.in' => 'Le type doit être cours, td ou tp.',
        ];
    }
}