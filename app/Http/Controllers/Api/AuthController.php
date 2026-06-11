<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::with('roles')->find(Auth::id());

            if (!$user->isWithinAllowedLoginTime()) {
                Auth::logout();
                return response()->json(['message' => 'Your shift time is completed. Please contact the Super Admin to extend your time.'], 403);
            }

            // Build user response with permissions
            $userData = $user->toArray();
            $userData['permissions'] = $user->getAllPermissions()->pluck('name')->values();

            return response()->json([
                'token' => $user->createToken('API Token')->plainTextToken,
                'user' => $userData
            ]);
        }
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('roles');
        $userData = $user->toArray();
        $userData['permissions'] = $user->getAllPermissions()->pluck('name')->values();
        return response()->json($userData);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password does not match our records.'], 422);
        }

        $user->update([
            'password' => $request->new_password
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}