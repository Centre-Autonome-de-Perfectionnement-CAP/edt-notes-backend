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
            'module_id.required' => 'Le module est requis.',
            'module_id.integer' => 'Le module doit être un entier.',
            'module_id.exists' => "Ce module n'existe pas.",
            'enseignant_id.required' => "L'enseignant est requis.",
            'enseignant_id.integer' => "L'enseignant doit être un entier.",
            'enseignant_id.exists' => "Cet enseignant n'existe pas.",
            'date.required' => 'La date est requise.',
            'date.date' => 'La date doit être une date valide.',
            'heure_debut.required' => "L'heure de début est requise.",
            'heure_debut.date_format' => "Le format de l'heure de début doit être H:i.",
            'heure_fin.required' => "L'heure de fin est requise.",
            'heure_fin.date_format' => "Le format de l'heure de fin doit être H:i.",
            'heure_fin.after' => "L'heure de fin doit être postérieure à l'heure de début.",
            'salle.string' => 'La salle doit être une chaîne de caractères.',
            'salle.max' => 'La salle ne peut pas dépasser 255 caractères.',
            'type.required' => 'Le type est requis.',
            'type.in' => 'Le type doit être cours, td ou tp.',
            'statut.required' => 'Le statut est requis.',
            'statut.in' => 'Le statut doit être planifie, annule ou reporte.',
        ];
    }
}