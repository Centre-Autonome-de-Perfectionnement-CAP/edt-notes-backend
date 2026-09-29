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
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        \Illuminate\Support\Facades\Log::info('Login attempt', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            \Illuminate\Support\Facades\Log::warning('Login failed for: ' . $request->email);
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 422);
        }

        \Illuminate\Support\Facades\Log::info('Login succeeded for: ' . $request->email);

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
