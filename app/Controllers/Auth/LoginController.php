<?php

namespace App\Controllers\Auth;

use CodeIgniter\Shield\Controllers\LoginController as ShieldLogin;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class LoginController extends ShieldLogin
{
    /**
     * Attempts to log the user in.
     */
    public function loginAction(): RedirectResponse
    {
        // 1. Determine if we are validating email or username from the single "login" field
        $loginInput = $this->request->getPost('login');
        $isEmail = filter_var($loginInput, FILTER_VALIDATE_EMAIL);

        // 2. Set dynamic validation rules
        $validationRules = new \CodeIgniter\Shield\Validation\ValidationRules();
        $rules = [
            'login' => [
                'label' => 'اسم المستخدم أو البريد الإلكتروني',
                'rules' => $isEmail ? 'required|valid_email' : 'required|min_length[3]|max_length[30]',
            ],
            'password' => $validationRules->getPasswordRules(),
        ];

        // Validate input data
        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // 3. Build credentials array based on input type
        $credentials = [];
        if ($isEmail) {
            $credentials['email'] = $loginInput;
        } else {
            $credentials['username'] = $loginInput;
        }
        $credentials['password'] = $this->request->getPost('password');
        
        $remember = (bool) $this->request->getPost('remember');

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Attempt to login
        $result = $authenticator->remember($remember)->attempt($credentials);
        if (! $result->isOK()) {
            return redirect()->route('login')->withInput()->with('error', $result->reason());
        }

        // If an action has been defined for login, start it up.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show')->withCookies();
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }
}
