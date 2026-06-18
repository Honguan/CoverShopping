<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request, CartService $cartService)
    {
        $credentials = $request->validate([
            'account' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['account' => '帳號或密碼錯誤'])->onlyInput('account');
        }

        $request->session()->regenerate();

        if ($request->user()->status !== 'active') {
            Auth::logout();
            return back()->withErrors(['account' => '帳號尚未啟用或已停權']);
        }

        $cartService->mergeSessionCart($request->user(), $request->session()->getId());

        return redirect()->intended(route('catalog.index'));
    }

    public function register(Request $request, CartService $cartService)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'account' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:users,account'],
            'email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'birthday' => ['nullable', 'date'],
        ]);

        $user = User::create($data + [
            'role' => 'customer',
            'status' => 'active',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $cartService->mergeSessionCart($user, $request->session()->getId());

        return redirect()->route('catalog.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalog.index');
    }
}
