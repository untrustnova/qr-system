<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        Log::info('auth.login.attempt', [
            'ip' => $request->ip(),
            'login' => $request->input('login'),
        ]);

        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['nullable', 'string'], // Password optional for NISN login
        ]);

        // Try to find user by username or email first
        $user = User::query()
            ->where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

        // If not found, try to find student by NISN
        if (! $user) {
            $studentProfile = \App\Models\StudentProfile::where('nisn', $data['login'])
                ->orWhere('nis', $data['login'])
                ->first();

            if ($studentProfile) {
                $user = $studentProfile->user;
            }
        }

        // If user not found at all
        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials'],
            ]);
        }

        // For students with NISN login, skip password check
        $isStudentNisnLogin = $user->user_type === 'student' &&
                              (! isset($data['password']) || empty($data['password']));

        // Check password only if not NISN login
        if (! $isStudentNisnLogin && ! Hash::check($data['password'] ?? '', $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials'],
            ]);
        }

        if (! $user->active) {
            throw ValidationException::withMessages([
                'login' => ['Account inactive'],
            ]);
        }

        if ($user->user_type === 'admin' && ! $user->adminProfile) {
            $user->adminProfile()->create(['type' => 'waka']);
        }

        $token = $user->createToken('api')->plainTextToken;

        Log::info('auth.login.success', [
            'user_id' => $user->id,
            'user_type' => $user->user_type,
            'login_method' => $isStudentNisnLogin ? 'nisn' : 'password',
        ]);

        return response()->json([
            'token' => $token,
            'user' => $user->load(['adminProfile', 'teacherProfile', 'studentProfile']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load(['adminProfile', 'teacherProfile', 'studentProfile'])
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        Log::info('auth.logout', [
            'user_id' => $request->user()->id,
            'user_type' => $request->user()->user_type,
        ]);

        return response()->json(['message' => 'Logged out']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:2048'], // Max 2MB
        ]);

        if (!empty($data['password'])) {
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            ]);
        }
        
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile-photos', 'public');
            
            // Update photo path in specific profile table
            if ($user->user_type === 'student' && $user->studentProfile) {
                $user->studentProfile->update(['photo' => $path]);
            } elseif ($user->user_type === 'teacher' && $user->teacherProfile) {
                $user->teacherProfile->update(['photo' => $path]);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }
}
