{{-- resources/views/pdf/recap-notes.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        .sous-titre { text-align: center; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .absent { color: #999; font-style: italic; }
        .qr-bloc { text-align: center; margin-top: 30px; }
        .qr-bloc p { font-size: 10px; color: #666; margin-top: 6px; }
    </style>
</head>
<body>
    <h1>Récapitulatif des notes</h1>
    <p class="sous-titre">
        {{ $submission->evaluation->module->intitule }} —
        {{ $submission->evaluation->libelle }} —
        {{ $submission->evaluation->module->filiere->nom }}
    </p>

    <p style="line-height: 1.7;">
        <strong>Enseignant :</strong> {{ $submission->enseignant->name }}<br>
        <strong>Date de soumission :</strong> {{ $dateSoumissionFormatee }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($notes as $note)
                <tr>
                    <td>{{ $note->etudiant->matricule }}</td>
                    <td>{{ $note->etudiant->nom }} {{ $note->etudiant->prenoms }}</td>
                    @if ($note->absent)
                        <td class="absent">Absent</td>
                    @else
                        <td>{{ $note->valeur }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="qr-bloc">
        <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" width="150" height="150">
        <p>À imprimer et joindre à l'enveloppe scellée — Soumission n°{{ $submission->id }}</p>
    </div>
</body>
</html>