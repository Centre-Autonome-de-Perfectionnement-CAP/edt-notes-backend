<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifiant' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifiant = $request->input('identifiant');
        $identifiant = preg_replace('/\s+/', '', $identifiant);

        $fieldType = filter_var($identifiant, FILTER_VALIDATE_EMAIL) ? 'email' : 'telephone';

        if ($fieldType === 'telephone') {
            if (str_starts_with($identifiant, '+229')) {
                $identifiant = substr($identifiant, 4);
            } elseif (str_starts_with($identifiant, '00229')) {
                $identifiant = substr($identifiant, 5);
            }
        }

        \Illuminate\Support\Facades\Log::info('Login attempt', [
            'identifiant' => $identifiant,
            'type' => $fieldType,
            'ip' => $request->ip(),
        ]);

        if (!Auth::attempt([$fieldType => $identifiant, 'password' => $request->password])) {
            \Illuminate\Support\Facades\Log::warning('Login failed for: ' . $identifiant);
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 422);
        }

        \Illuminate\Support\Facades\Log::info('Login succeeded for: ' . $identifiant);

        $user = User::where($fieldType, $identifiant)->first();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'role' => $user->role,
                'filiere_id' => $user->filiere_id,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
