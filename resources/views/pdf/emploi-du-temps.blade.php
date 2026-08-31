<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Emploi du temps</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 16px; margin: 0; }
        .header p { margin: 2px 0; font-size: 11px; }
        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 3px 6px; font-size: 10px; }
        .meta-table strong { color: #333; }
        table.seances { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.seances th, table.seances td {
            border: 1px solid #999; padding: 5px 6px; text-align: left; font-size: 10px;
        }
        table.seances th { background-color: #f0f0f0; }
        .jour-title { font-size: 12px; font-weight: bold; margin: 12px 0 4px; }
        .footer { margin-top: 20px; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Emploi du Temps — {{ $emploiDuTemps->filiere->nom }}</h1>
        <p>Division Formation Continue et Perfectionnement (RdivFC)</p>
        <p>Semestre {{ $emploiDuTemps->semestre }} — Semaine du
            {{ $emploiDuTemps->date_debut_semaine->format('d/m/Y') }}
            au {{ $emploiDuTemps->date_fin_semaine->format('d/m/Y') }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Responsable :</strong> {{ $emploiDuTemps->contact_responsable_nom ?? '—' }}</td>
            <td><strong>Contact :</strong> {{ $emploiDuTemps->contact_responsable_tel ?? '—' }}</td>
        </tr>
        @if($emploiDuTemps->observation)
        <tr>
            <td colspan="2"><strong>Observation :</strong> {{ $emploiDuTemps->observation }}</td>
        </tr>
        @endif
    </table>

    @forelse($seancesParJour as $jour => $seances)
        <div class="jour-title">{{ ucfirst($jour) }}</div>
        <table class="seances">
            <thead>
                <tr>
                    <th>Horaire</th>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Enseignant</th>
                    <th>Salle</th>
                </tr>
            </thead>
            <tbody>
                @foreach($seances as $seance)
                <tr>
                    <td>{{ $seance->heure_debut }} – {{ $seance->heure_fin }}</td>
                    <td>{{ $seance->module->intitule }}</td>
                    <td>{{ strtoupper($seance->type) }}</td>
                    <td>{{ $seance->enseignant->name }}</td>
                    <td>{{ $seance->salle ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>Aucune séance programmée sur cette semaine.</p>
    @endforelse

    <div class="footer">
        Généré automatiquement — CAP Module Emploi du Temps
    </div>
</body>
</html>