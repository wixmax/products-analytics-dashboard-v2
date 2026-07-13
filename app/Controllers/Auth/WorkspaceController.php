<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

class WorkspaceController extends BaseController
{
    /**
     * Display the workspace/tenant settings page.
     */
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $db = \Config\Database::connect();

        // Ensure user has a tenant_id
        if (empty($user->tenant_id)) {
            // Should not normally happen, but as fallback, trigger the register event logic
            // to create a tenant for the user
            $slugBase = $user->username ?: explode('@', $user->email)[0];
            $slug = url_title($slugBase, '-', true);
            $db->table('tenants')->insert([
                'name'       => $slugBase . "'s Workspace",
                'slug'       => $slug,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $tenantId = $db->insertID('tenants_id_seq');
            $userModel = new \App\Models\UserModel();
            $userModel->bypassTenant()->update($user->id, ['tenant_id' => $tenantId]);
            $user->tenant_id = $tenantId;

            // Also insert into tenant_users as owner
            $db->table('tenant_users')->insert([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->id,
                'role'       => 'owner',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Fetch current tenant
        $tenant = $db->table('tenants')->where('id', $user->tenant_id)->get()->getRow();

        // Fetch all members of this tenant
        $userModel = new \App\Models\UserModel();
        $members = $userModel->bypassTenant()->where('tenant_id', $user->tenant_id)->findAll();

        // Fetch all workspaces this user is member of
        $workspaces = $db->table('tenant_users')
            ->select('tenants.id, tenants.name, tenants.slug, tenant_users.role')
            ->join('tenants', 'tenants.id = tenant_users.tenant_id')
            ->where('tenant_users.user_id', $user->id)
            ->get()
            ->getResultArray();

        // Safety fallback: if user's current tenant is not in the workspaces list, add it
        $hasCurrent = false;
        foreach ($workspaces as $w) {
            if ((int)$w['id'] === (int)$user->tenant_id) {
                $hasCurrent = true;
                break;
            }
        }
        if (!$hasCurrent && !empty($user->tenant_id)) {
            $currentTenantData = $db->table('tenants')->where('id', $user->tenant_id)->get()->getRowArray();
            if ($currentTenantData) {
                $workspaces[] = [
                    'id'   => $currentTenantData['id'],
                    'name' => $currentTenantData['name'],
                    'slug' => $currentTenantData['slug'],
                    'role' => 'owner'
                ];
            }
        }

        return view('auth/workspace', [
            'tenant'     => $tenant,
            'members'    => $members,
            'user'       => $user,
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Update the workspace name.
     */
    public function update(): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $rules = [
            'name' => 'required|min_length[3]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $db->table('tenants')->where('id', $tenantId)->update([
            'name'       => $this->request->getPost('name'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('message', 'تم تحديث اسم مساحة العمل بنجاح! 📁');
    }

    /**
     * Invite an existing user to join the workspace by email.
     */
    public function inviteMember(): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $rules = [
            'email' => 'required|valid_email',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = $this->request->getPost('email');

        // Find user by email using Shield's native, case-insensitive credential finder
        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->bypassTenant()->findByCredentials(['email' => $email]);

        if ($targetUser === null) {
            return redirect()->back()->withInput()->with('error', 'البريد الإلكتروني غير مسجل في النظام. يجب على المستخدم إنشاء حساب أولاً.');
        }

        if ((int)$targetUser->tenant_id === (int)$tenantId) {
            return redirect()->back()->withInput()->with('error', 'المستخدم عضو بالفعل في مساحة العمل هذه.');
        }

        // Add member to tenant_users if not already there
        $db = \Config\Database::connect();
        $isMember = $db->table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $targetUser->id)
            ->countAllResults() > 0;

        if (!$isMember) {
            $db->table('tenant_users')->insert([
                'tenant_id'  => $tenantId,
                'user_id'    => $targetUser->id,
                'role'       => 'member',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Assign the user to the current tenant
        $userModel->bypassTenant()->update($targetUser->id, ['tenant_id' => $tenantId]);

        return redirect()->back()->with('message', 'تمت إضافة العضو للمساحة بنجاح! 🤝');
    }

    /**
     * Remove a member from the workspace (moves them to a fresh personal workspace).
     */
    public function removeMember($userId): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $tenantId = $user->tenant_id;

        if ((int)$user->id === (int)$userId) {
            return redirect()->back()->with('error', 'لا يمكنك إزالة نفسك من مساحة العمل الخاصة بك.');
        }

        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->bypassTenant()->where('id', $userId)->first();

        if ($targetUser === null || (int)$targetUser->tenant_id !== (int)$tenantId) {
            return redirect()->back()->with('error', 'العضو المطلوب غير موجود في مساحة عملك.');
        }

        $db = \Config\Database::connect();

        // Create a new fresh personal tenant for the removed user
        $slugBase = $targetUser->username ?: explode('@', $targetUser->email)[0];
        $slug = url_title($slugBase, '-', true);
        $existing = $db->table('tenants')->where('slug', $slug)->get()->getRow();
        if ($existing) {
            $slug .= '-' . time();
        }

        $db->table('tenants')->insert([
            'name'       => $slugBase . "'s Workspace",
            'slug'       => $slug,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $newTenantId = $db->insertID('tenants_id_seq');

        // Delete membership from the current tenant
        $db->table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        // Add membership as owner to the new tenant
        $db->table('tenant_users')->insert([
            'tenant_id'  => $newTenantId,
            'user_id'    => $userId,
            'role'       => 'owner',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Move target user to the new workspace
        $userModel->bypassTenant()->update($targetUser->id, ['tenant_id' => $newTenantId]);

        return redirect()->back()->with('message', 'تمت إزالة العضو من مساحة العمل بنجاح ونقله لمساحة عمل مستقلة.');
    }

    /**
     * Switch active workspace/tenant.
     */
    public function switchWorkspace(): RedirectResponse
    {
        if (!auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $targetTenantId = (int)$this->request->getPost('tenant_id');

        if (empty($targetTenantId)) {
            return redirect()->back()->with('error', 'مساحة العمل غير صالحة.');
        }

        // Verify that the user is a member of the target tenant
        $db = \Config\Database::connect();
        $isMember = $db->table('tenant_users')
            ->where('tenant_id', $targetTenantId)
            ->where('user_id', $user->id)
            ->countAllResults() > 0;

        if (!$isMember) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية للوصول لمساحة العمل هذه.');
        }

        // Update user's active tenant_id
        $userModel = new \App\Models\UserModel();
        $userModel->bypassTenant()->update($user->id, ['tenant_id' => $targetTenantId]);

        return redirect()->back()->with('message', 'تم تبديل مساحة العمل بنجاح! 🔄');
    }
}
