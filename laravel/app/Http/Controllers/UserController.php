<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('is_active', 'desc')
            ->orderBy('role', 'asc')
            ->get();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username|max:50',
            'full_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'role' => 'required|in:admin,staff,worker,integrator',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:30'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = 1;

        $user = User::create($validated);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'create',
            'table_name' => 'users',
            'record_id' => $user->user_id,
            'new_values' => json_encode(['username' => $user->username, 'role' => $user->role, 'full_name' => $user->full_name]),
            'ip_address' => request()->ip() ?? '127.0.0.1'
        ]);

        return redirect()->route('users.index')->with('success', "User '{$user->username}' with role '{$user->role}' created successfully!");
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'role' => 'required|in:admin,staff,worker,integrator',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'is_active' => 'required|boolean'
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', "User account '{$user->username}' updated successfully!");
    }
}
