<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class ProfileController extends BaseController
{
    /**
     * Display the profile page.
     */
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        return view('auth/profile', [
            'user' => $user,
        ]);
    }

    /**
     * Update profile details (username).
     */
    public function update(): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $userId = $user->id;

        $rules = [
            'username' => "required|alpha_numeric_space|min_length[3]|max_length[30]|is_unique[users.username,id,{$userId}]",
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user->username = $this->request->getPost('username');

        $users = auth()->getProvider();
        if ($users->save($user)) {
            return redirect()->back()->with('message', 'تم تحديث الملف الشخصي بنجاح! ✅');
        }

        return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء حفظ التحديثات.');
    }

    /**
     * Change user password.
     */
    public function changePassword(): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Validate the current password
        $users = auth()->getProvider();
        $credentials = [
            'email'    => $user->email,
            'password' => $this->request->getPost('current_password'),
        ];

        if (!$users->validateCredentials($credentials)) {
            return redirect()->back()->withInput()->with('error', 'كلمة المرور الحالية غير صحيحة.');
        }

        // Set and save new password
        $user->password = $this->request->getPost('new_password');
        if ($users->save($user)) {
            return redirect()->back()->with('message', 'تم تغيير كلمة المرور بنجاح! 🔒');
        }

        return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث كلمة المرور.');
    }
}
