<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate the request data
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Attempt to authenticate the user
        if (auth()->attempt($credentials)) {
            // Authentication passed, return a success response
            return response()->json(['message' => 'Login successful', 'user' => auth()->user(), 'token' => auth()->user()->createToken('auth_token')->plainTextToken]);
        }

        // Authentication failed, return an error response
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function index()
    {
        $users = \App\Models\User::all();
        return response()->json([
            'message' => 'Daftar semua user',
            'users'   => $users,
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the Sanctum token
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        // Return a success response
        return response()->json(['message' => 'Logout successful']);
    }

    public function register(Request $request)
    {
        // Validate the request data
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create a new user
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        // Return a success response
        return response()->json(['message' => 'Registration successful', 'user' => $user, 'token' => $user->createToken('auth_token')->plainTextToken]);
    }
}
