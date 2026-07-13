<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class UsersController extends BaseController
{
    /**
     * Display the users list page.
     */
    public function index()
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول لهذه الصفحة.');
        }

        $userModel = new \App\Models\UserModel();
        $db = \Config\Database::connect();

        // Fetch users and join with tenants to show their workspace name
        $users = $db->table('users')
            ->select('users.id, users.username, users.active, users.created_at, tenants.name as tenant_name, auth_identities.secret as email')
            ->join('tenants', 'tenants.id = users.tenant_id', 'left')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
            ->get()
            ->getResultArray();

        // Get group details for each user
        foreach ($users as &$u) {
            $userEntity = $userModel->bypassTenant()->find($u['id']);
            $u['groups'] = $userEntity ? $userEntity->getGroups() : [];
        }

        return view('admin/users', [
            'users' => $users,
        ]);
    }

    /**
     * Update user role/group.
     */
    public function updateRole(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $userId = $this->request->getPost('user_id');
        $newRole = $this->request->getPost('role');

        $allowedRoles = ['superadmin', 'admin', 'developer', 'user', 'beta'];

        if (!in_array($newRole, $allowedRoles, true)) {
            return redirect()->back()->with('error', 'دور المستخدم المحدد غير صالح.');
        }

        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->bypassTenant()->find($userId);

        if ($targetUser === null) {
            return redirect()->back()->with('error', 'المستخدم غير موجود.');
        }

        // Prevent self-demotion from superadmin
        if ((int)$targetUser->id === (int)auth()->id() && $newRole !== 'superadmin' && auth()->user()->inGroup('superadmin')) {
            return redirect()->back()->with('error', 'لا يمكنك إلغاء صلاحية Super Admin الخاصة بك بنفسك.');
        }

        // Sync groups (replaces existing groups with the new one)
        $targetUser->syncGroups($newRole);

        return redirect()->back()->with('message', 'تم تحديث دور المستخدم بنجاح! 🎓');
    }

    /**
     * Toggle user status (active/inactive).
     */
    public function toggleStatus(): RedirectResponse
    {
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            return redirect()->to('/')->with('error', 'غير مسموح لك بالوصول.');
        }

        $userId = $this->request->getPost('user_id');

        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->bypassTenant()->find($userId);

        if ($targetUser === null) {
            return redirect()->back()->with('error', 'المستخدم غير موجود.');
        }

        // Prevent self-deactivation
        if ((int)$targetUser->id === (int)auth()->id()) {
            return redirect()->back()->with('error', 'لا يمكنك تعطيل حسابك الشخصي.');
        }

        // Toggle active status
        $targetUser->active = $targetUser->active ? 0 : 1;
        $userModel->bypassTenant()->save($targetUser);

        $statusText = $targetUser->active ? 'تفعيل' : 'تعطيل';

        return redirect()->back()->with('message', "تم {$statusText} حساب المستخدم بنجاح!");
    }
}
