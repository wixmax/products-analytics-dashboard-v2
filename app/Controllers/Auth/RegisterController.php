<?php

namespace App\Controllers\Auth;

use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegister;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Events\Events;

class RegisterController extends ShieldRegister
{
    /**
     * Attempts to register the user.
     */
    public function registerAction(): RedirectResponse
    {
        // 1. Get user provider & validation rules
        $users = $this->getUserProvider();

        $rules = [
            'username' => 'required|min_length[3]|max_length[30]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[auth_identities.secret]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // 2. Create User Entity
        $allowedPostFields = array_merge(setting('Auth.validFields'), ['username', 'email', 'password']);
        $user = $this->getUserEntity();
        $user->fill($this->request->getPost($allowedPostFields));
        $user->active = 0; // Explicitly set pending approval status

        // 3. Save User
        $users->save($user);

        // Fetch complete user entity from database
        $user = $users->findById($users->getInsertID());

        // Add to default group
        $users->addToDefaultGroup($user);

        // 4. Trigger registration events (creates tenant workspace)
        Events::trigger('register', $user);

        // Ensure active state remains 0 in database
        $userModel = new \App\Models\UserModel();
        $userModel->bypassTenant()->update($user->id, ['active' => 0]);

        // 5. Ensure user is logged out immediately
        auth()->logout();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session()->destroy();
        }

        // 6. Redirect to login with pending approval notice
        return redirect()->to(url_to('login'))
            ->with('message', 'تم إنشاء حسابك بنجاح! ⏳ حسابك الآن في وضعية الانتظار ولن تتمكن من الدخول حتى يتم قبوله وتفعيله من قبل المشرف أو الأدمن.');
    }
}
