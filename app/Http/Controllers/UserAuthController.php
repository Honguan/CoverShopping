<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use App\Services\ShoppingCartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function loginUser(LoginUserRequest $request, ShoppingCartService $shoppingCartService)
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['account' => '帳號或密碼錯誤'])->onlyInput('account');
        }

        $request->session()->regenerate();

        if ($request->user()->status !== 'active') {
            Auth::logout();

            return back()->withErrors(['account' => '帳號目前無法使用']);
        }

        $shoppingCartService->mergeGuestCartIntoUserCart($request->user(), $request->session()->getId());

        return redirect()->intended(route('catalog.index'));
    }

    public function registerUser(RegisterUserRequest $request, ShoppingCartService $shoppingCartService)
    {
        $user = User::create($request->validated() + [
            'role' => 'customer',
            'status' => 'active',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $shoppingCartService->mergeGuestCartIntoUserCart($user, $request->session()->getId());

        return redirect()->route('catalog.index');
    }

    public function logoutUser(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalog.index');
    }
}
