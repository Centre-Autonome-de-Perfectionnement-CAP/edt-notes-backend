<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du Temps - {{ $emploiDuTemps->filiere->nom }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm 8mm 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 6px;
        }
        .header-left {
            width: 48%;
            vertical-align: top;
            text-align: left;
        }
        .header-right {
            width: 52%;
            vertical-align: top;
            text-align: right;
        }
        .inst-republique {
            font-size: 9px;
            font-weight: bold;
            color: #4b5563;
            letter-spacing: 0.5px;
        }
        .inst-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .inst-bold {
            font-size: 10.5px;
            font-weight: bold;
            color: #111827;
        }
        .inst-sub {
            font-size: 9.5px;
            color: #374151;
            font-weight: 600;
        }
        .edt-badge {
            display: inline-block;
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 3px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .filiere-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 2px;
        }
        .periode-title {
            font-size: 10px;
            font-weight: 600;
            color: #1f2937;
        }
        .responsable-info {
            font-size: 9px;
            color: #4b5563;
            margin-top: 2px;
        }

        /* Grille principale */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 8px;
            table-layout: fixed;
        }
        .grid-table th, .grid-table td {
            border: 1px solid #9ca3af;
            padding: 5px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .grid-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 6px 4px;
        }
        .grid-table th.horaire-header {
            width: 13%;
            background-color: #0f172a;
        }
        .horaire-cell {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 10px;
            color: #1f2937;
            white-space: nowrap;
        }
        .seance-cell {
            color: #ffffff;
            text-align: center;
            padding: 6px 4px;
            border: 1px solid #4b5563;
        }
        .seance-module {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            line-height: 1.2;
            text-shadow: 0 1px 1px rgba(0,0,0,0.3);
        }
        .seance-type-salle {
            font-size: 9.5px;
            font-weight: 600;
            margin-bottom: 3px;
            background: rgba(0,0,0,0.18);
            padding: 2px 4px;
            border-radius: 2px;
            display: inline-block;
        }
        .seance-enseignant {
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1.2;
        }
        .seance-tel {
            font-size: 8.5px;
            opacity: 0.95;
        }
        .empty-cell {
            background-color: #f9fafb;
            color: #9ca3af;
            font-size: 11px;
        }
        .no-seances-box {
            padding: 20px;
            text-align: center;
            background-color: #f3f4f6;
            border: 1px dashed #9ca3af;
            font-size: 12px;
            color: #4b5563;
            margin: 15px 0;
        }

        /* Légende & Observations */
        .bottom-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .legend-container {
            width: 60%;
            vertical-align: top;
            padding-right: 15px;
        }
        .signatures-container {
            width: 40%;
            vertical-align: top;
        }
        .legend-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .legend-list {
            width: 100%;
            border-collapse: collapse;
        }
        .legend-list td {
            padding: 2px 4px;
            font-size: 9px;
            vertical-align: middle;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            display: inline-block;
            border: 1px solid #4b5563;
            vertical-align: middle;
            margin-right: 4px;
            border-radius: 2px;
        }
        .observation-box {
            font-size: 8.5px;
            color: #4b5563;
            margin-top: 4px;
            font-style: italic;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-box {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 4px;
        }
        .sig-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 35px;
        }
        .sig-space {
            height: 30px;
        }
        .sig-name {
            font-size: 9px;
            font-weight: 600;
            color: #374151;
            border-top: 1px dotted #9ca3af;
            padding-top: 3px;
            display: inline-block;
            min-width: 120px;
        }
    </style>
</head>
<body>

    <!-- En-tête officiel -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="inst-republique">RÉPUBLIQUE DU BÉNIN</div>
                <div class="inst-title">UNIVERSITÉ D'ABOMEY-CALAVI</div>
                <div class="inst-bold">ÉCOLE POLYTECHNIQUE D'ABOMEY-CALAVI</div>
                <div class="inst-bold">CENTRE AUTONOME DE PERFECTIONNEMENT</div>
                <div class="inst-sub">UAC/EPAC/CAP-{{ $emploiDuTemps->division ?? 'RdivFC' }}</div>
            </td>
            <td class="header-right">
                <div class="edt-badge">EMPLOI DU TEMPS</div>
                <div class="filiere-title">{{ $emploiDuTemps->filiere->nom }}</div>
                <div class="periode-title">
                    Semestre {{ $emploiDuTemps->semestre }} — Semaine du {{ $emploiDuTemps->date_debut_semaine->format('d/m/Y') }} au {{ $emploiDuTemps->date_fin_semaine->format('d/m/Y') }}
                </div>
                @if($emploiDuTemps->contact_responsable_nom || $emploiDuTemps->contact_responsable_tel)
                <div class="responsable-info">
                    Responsable : <strong>{{ $emploiDuTemps->contact_responsable_nom ?? '—' }}</strong>
                    @if($emploiDuTemps->contact_responsable_tel)
                        (Tél : {{ $emploiDuTemps->contact_responsable_tel }})
                    @endif
                </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Grille dynamique jours x horaires -->
    @if($seances->isNotEmpty() && count($jours) > 0 && count($creneaux) > 0)
    <table class="grid-table">
        <thead>
            <tr>
                <th class="horaire-header">HORAIRES</th>
                @foreach($jours as $jour)
                    <th>
                        <div>{{ $jour['jour_nom'] }}</div>
                        <div style="font-size: 8.5px; font-weight: normal; opacity: 0.9;">{{ $jour['jour_date'] }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($creneaux as $creneau)
            <tr>
                <td class="horaire-cell">{{ $creneau['label'] }}</td>
                @foreach($jours as $jour)
                    @php
                        $seance = $grille[$creneau['key']][$jour['date']] ?? null;
                    @endphp
                    @if($seance)
                        <td class="seance-cell" style="background-color: {{ $seance->module->couleur ?? '#3B82F6' }};">
                            <div class="seance-module">{{ $seance->module->intitule }}</div>
                            <div class="seance-type-salle">
                                {{ strtoupper($seance->type ?? 'COURS') }}
                                @if($seance->salle)
                                    — Salle : {{ $seance->salle }}
                                @endif
                            </div>
                            <div class="seance-enseignant">{{ $seance->enseignant->name ?? '—' }}</div>
                            @if(!empty($seance->enseignant?->telephone))
                                <div class="seance-tel">Tél : {{ $seance->enseignant->telephone }}</div>
                            @endif
                        </td>
                    @else
                        <td class="empty-cell">—</td>
                    @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-seances-box">
        Aucune séance programmée sur cette semaine.
    </div>
    @endif

    <!-- Bas de page : Légende & Signatures -->
    <table class="bottom-section">
        <tr>
            <!-- Légende -->
            <td class="legend-container">
                @if($modules->isNotEmpty())
                <div class="legend-title">Légende des modules :</div>
                <table class="legend-list">
                    @foreach($modules->chunk(2) as $chunk)
                    <tr>
                        @foreach($chunk as $mod)
                        <td style="width: 50%;">
                            <span class="legend-color" style="background-color: {{ $mod->couleur }};"></span>
                            <strong>{{ $mod->intitule }}</strong>
                            <span style="color: #6b7280;">({{ $mod->volume_horaire }}h)</span>
                        </td>
                        @endforeach
                        @if($chunk->count() < 2)
                        <td style="width: 50%;"></td>
                        @endif
                    </tr>
                    @endforeach
                </table>
                @endif

                @if($emploiDuTemps->observation)
                <div class="observation-box">
                    <strong>Observation :</strong> {{ $emploiDuTemps->observation }}
                </div>
                @endif
            </td>

            <!-- Signatures -->
            <td class="signatures-container">
                <table class="signatures-table">
                    <tr>
                        <td class="signature-box">
                            <div class="sig-title">Le Responsable Division<br>Formation Continue</div>
                            <div class="sig-name">{{ $emploiDuTemps->contact_responsable_nom ?? 'Le Responsable' }}</div>
                        </td>
                        <td class="signature-box">
                            <div class="sig-title">Le Chef<br>CAP</div>
                            <div class="sig-name">Direction du CAP</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>