<?php
$filieres = App\Models\Filiere::all();
foreach ($filieres as $filiere) {
    App\Models\User::firstOrCreate(
        ['email' => 'delegue.' . strtolower($filiere->code) . '@cap.test'],
        [
            'name' => 'Delegue ' . $filiere->nom,
            'password' => Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'delegue',
            'filiere_id' => $filiere->id,
            'telephone' => '+229 97 ' . fake()->numerify('## ## ##'),
        ]
    );
}
echo "Delegues created\n";

