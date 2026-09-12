<?php
// app/Modules/GradeTracking/Http/Requests/SubmitNotesRequest.php

namespace App\Modules\GradeTracking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEnseignant() ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'array'],
            'notes.*.etudiant_id' => ['required', 'integer', 'exists:etudiants,id'],
            'notes.*.valeur' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'notes.*.absent' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Les notes sont requises.',
            'notes.array' => 'Les notes doivent être un tableau.',
            'notes.*.etudiant_id.required' => "L'étudiant est requis.",
            'notes.*.etudiant_id.integer' => "L'étudiant doit être un entier.",
            'notes.*.etudiant_id.exists' => "Un des étudiants envoyés n'existe pas.",
            'notes.*.valeur.numeric' => 'La note doit être un nombre.',
            'notes.*.valeur.min' => 'Une note ne peut pas être inférieure à 0.',
            'notes.*.valeur.max' => "Une note ne peut pas dépasser 20.",
            'notes.*.absent.boolean' => "Le champ absent doit être un booléen.",
        ];
    }

    /**
     * Vérifie la cohérence valeur/absent que les règles de base ne couvrent pas :
     * un étudiant absent ne doit pas avoir de valeur, et inversement.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('notes', []) as $index => $note) {
                $absent = $note['absent'] ?? false;
                $valeur = $note['valeur'] ?? null;

                if ($absent && $valeur !== null) {
                    $validator->errors()->add(
                        "notes.$index.valeur",
                        "Un étudiant marqué absent ne doit pas avoir de note."
                    );
                }
            }
        });
    }
}