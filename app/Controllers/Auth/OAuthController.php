<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use League\OAuth2\Client\Provider\Google;
use Config\Google as GoogleConfig;
use CodeIgniter\Events\Events;

class OAuthController extends BaseController
{
    protected GoogleConfig $googleConfig;

    public function __construct()
    {
        $this->googleConfig = config('Google');
    }

    /**
     * Get the Google OAuth provider instance
     */
    private function getProvider(): ?Google
    {
        if (empty($this->googleConfig->clientId) || empty($this->googleConfig->clientSecret)) {
            return null;
        }

        return new Google([
            'clientId'     => $this->googleConfig->clientId,
            'clientSecret' => $this->googleConfig->clientSecret,
            'redirectUri'  => $this->googleConfig->redirectUri,
        ]);
    }

    /**
     * Redirect the user to Google login page
     */
    public function googleLogin()
    {
        $provider = $this->getProvider();
        if ($provider === null) {
            return redirect()->to(route_to('login'))
                ->with('error', 'تهيئة Google OAuth غير مكتملة. يرجى إضافة google.clientId و google.clientSecret في ملف .env');
        }

        // Get authorization URL
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);

        // Save state to session to prevent CSRF
        session()->set('oauth2state', $provider->getState());

        return redirect()->to($authUrl);
    }

    /**
     * Handle the callback redirect from Google
     */
    public function googleCallback()
    {
        $provider = $this->getProvider();
        if ($provider === null) {
            return redirect()->to(route_to('login'))
                ->with('error', 'تهيئة Google OAuth غير مكتملة.');
        }

        $state = $this->request->getGet('state');
        $sessionState = session()->get('oauth2state');

        // Check state validation to prevent CSRF
        if (empty($state) || ($state !== $sessionState)) {
            session()->remove('oauth2state');
            return redirect()->to(route_to('login'))->with('error', 'حالة مصادقة OAuth غير صالحة. الرجاء المحاولة مجدداً.');
        }

        $code = $this->request->getGet('code');
        if (empty($code)) {
            return redirect()->to(route_to('login'))->with('error', 'لم يتم توفير رمز المصادقة من Google.');
        }

        try {
            // Get Access Token using Authorization Code
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            // Get User Details
            $googleUser = $provider->getResourceOwner($token);
            $email = $googleUser->getEmail();
            
            if (empty($email)) {
                return redirect()->to(route_to('login'))->with('error', 'لا يمكن الحصول على البريد الإلكتروني من حساب Google.');
            }

            // Find user in database by email
            $userModel = new \App\Models\UserModel();
            
            // Check if user exists using Shield's built-in findByCredentials
            $user = $userModel->bypassTenant()->findByCredentials(['email' => $email]);

            if ($user === null) {
                // Generate a unique username
                $usernameBase = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
                if (strlen($usernameBase) < 3) {
                    $usernameBase = 'user' . rand(100, 999);
                }
                $username = $usernameBase;

                // Ensure username uniqueness
                $count = 1;
                while ($userModel->bypassTenant()->where('username', $username)->countAllResults() > 0) {
                    $username = $usernameBase . $count;
                    $count++;
                }

                // Create user in Shield with a random password
                $userEntity = new \CodeIgniter\Shield\Entities\User([
                    'username' => $username,
                    'email'    => $email,
                    'password' => bin2hex(random_bytes(16)),
                ]);

                // Save user (bypassing tenant scoping)
                $userModel->bypassTenant()->save($userEntity);

                // Fetch full user object with database ID
                $user = $userModel->bypassTenant()->findById($userModel->getInsertID());

                // Add to default group
                $userModel->addToDefaultGroup($user);

                // Trigger registration events (this will auto-create the tenant using App/Config/Events.php)
                Events::trigger('register', $user);

                // Deactivate user (set active = 0) so they require admin approval
                $userModel->bypassTenant()->update($user->id, ['active' => 0]);

                return redirect()->to(route_to('login'))
                    ->with('message', 'تم إنشاء حسابك بنجاح عبر Google! ⏳ حسابك الآن في وضعية الانتظار ولن تتمكن من الدخول حتى يتم قبوله وتفعيله من قبل المشرف أو الأدمن.');
            }

            // Check if user is inactive (pending approval)
            if (empty($user->active)) {
                return redirect()->to(route_to('login'))
                    ->with('error', 'حسابك حالياً في وضعية الانتظار ولم يتم قبوله وتفعيله بعد من قبل المشرف أو الأدمن. ⏳');
            }

            // Log user in
            $authenticator = auth('session')->getAuthenticator();
            $authenticator->login($user);

            return redirect()->to('/')->with('message', 'تم تسجيل الدخول بنجاح باستخدام Google.');

        } catch (\Exception $e) {
            return redirect()->to(route_to('login'))->with('error', 'حدث خطأ أثناء الاتصال بـ Google: ' . $e->getMessage());
        }
    }
}
