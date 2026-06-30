<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * Get all API tokens for the authenticated user
     */
    public function index(Request $request)
    {
        return response()->json([
            'tokens' => Auth::user()->tokens()->select('id', 'name', 'last_used_at', 'created_at')->get()
        ]);
    }

    /**
     * Create a new API token for the user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $token = Auth::user()->createToken($request->input('name'), ['slides-addon']);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $request->input('name')
        ], 201);
    }

    /**
     * Delete an API token
     */
    public function destroy(Request $request, $tokenId)
    {
        $token = Auth::user()->tokens()->findOrFail($tokenId);
        $token->delete();

        return response()->json(['message' => 'Token deleted successfully']);
    }

    /**
     * Revoke all tokens for the user
     */
    public function revokeAll(Request $request)
    {
        Auth::user()->tokens()->delete();

        return response()->json(['message' => 'All tokens revoked']);
    }
}
