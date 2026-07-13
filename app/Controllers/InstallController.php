<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Events\Events;

class InstallController extends Controller
{
    protected $helpers = ['form', 'url'];

    /**
     * Step 1: Welcome & Requirements Check
     */
    public function index()
    {
        $phpVersion = PHP_VERSION;
        $phpOk = PHP_VERSION_ID >= 80100;
        
        $writablePath = WRITEPATH;
        $writableOk = is_writable($writablePath);

        // Required extensions
        $extensionsToCheck = ['intl', 'mbstring', 'curl', 'pdo', 'json'];
        $extensions = [];
        $extensionsOk = true;

        foreach ($extensionsToCheck as $ext) {
            $loaded = extension_loaded($ext);
            $extensions[$ext] = $loaded;
            if (!$loaded) {
                $extensionsOk = false;
            }
        }

        // Database extension (either pgsql or mysqli)
        $dbExts = ['pgsql', 'pdo_pgsql', 'mysqli', 'pdo_mysql'];
        $dbExtLoaded = false;
        foreach ($dbExts as $ext) {
            if (extension_loaded($ext)) {
                $dbExtLoaded = true;
                break;
            }
        }
        $extensions['database (MySQL / PostgreSQL)'] = $dbExtLoaded;

        $requirementsMet = $phpOk && $writableOk && $extensionsOk && $dbExtLoaded;

        return view('install/index', [
            'title'            => 'نظام التثبيت - فحص المتطلبات',
            'step'             => 1,
            'php_version'      => $phpVersion,
            'php_ok'           => $phpOk,
            'writable_ok'      => $writableOk,
            'extensions'       => $extensions,
            'requirements_met' => $requirementsMet,
        ]);
    }

    /**
     * Step 2: Database Settings Form
     */
    public function database()
    {
        return view('install/database', [
            'title' => 'نظام التثبيت - إعداد قاعدة البيانات',
            'step'  => 2,
        ]);
    }

    /**
     * Save Database settings (POST)
     */
    public function saveDatabase()
    {
        $rules = [
            'driver'   => 'required',
            'hostname' => 'required',
            'database' => 'required',
            'username' => 'required',
            'port'     => 'required|numeric',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح.');
        }

        $driver = $this->request->getPost('driver');
        $hostname = $this->request->getPost('hostname');
        $database = $this->request->getPost('database');
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        $port = $this->request->getPost('port');

        // Verify database connection
        try {
            $config = [
                'hostname' => $hostname,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'DBDriver' => $driver,
                'port'     => (int)$port,
                'charset'  => 'utf8',
                'DBDebug'  => false, // Prevent throwing exception handler directly, let us catch it
            ];

            $db = \Config\Database::connect($config);
            $db->initialize();
            
            if (!$db->connID) {
                throw new \Exception('تعذر تأسيس اتصال نشط مع قاعدة البيانات.');
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
        }

        // Save settings to .env file
        $dbData = [
            'driver'   => $driver,
            'hostname' => $hostname,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'port'     => $port,
        ];

        if (!$this->updateEnvFile($dbData)) {
            return redirect()->back()->withInput()->with('error', 'فشل حفظ الإعدادات في ملف .env، يرجى التحقق من صلاحيات الملف.');
        }

        return redirect()->to(base_url('install/migrate'));
    }

    /**
     * Step 3: Run Database Migrations
     */
    public function migrate()
    {
        // Fetch current settings from config to display to the user
        $db = \Config\Database::connect();
        
        $driver = $db->DBDriver;
        $hostname = $db->hostname;
        $port = $db->port;
        $database = $db->database;

        $run = $this->request->getGet('run');

        if ($run === 'true') {
            try {
                $migrate = \Config\Services::migrations();
                
                // 1. Run Shield migrations first to ensure core tables (users, etc.) exist
                $migrate->setNamespace('CodeIgniter\Shield')->latest();
                
                // 2. Run App migrations next
                $migrate->setNamespace('App')->latest();
                
                // 3. Run any other namespaces (excluding core CodeIgniter / Shield / App which are already run)
                $namespaces = array_keys(service('autoloader')->getNamespace());
                foreach ($namespaces as $ns) {
                    if (in_array($ns, ['CodeIgniter\Shield', 'App', 'CodeIgniter'], true)) {
                        continue;
                    }
                    try {
                        $migrate->setNamespace($ns)->latest();
                    } catch (\Throwable $e) {
                        // Silence errors for third-party namespaces that do not have migration files
                    }
                }
                
                // Successfully migrated, go to step 4
                return redirect()->to(base_url('install/admin'));
            } catch (\Throwable $e) {
                return view('install/migrate', [
                    'title'    => 'نظام التثبيت - خطأ في المزامنة',
                    'step'     => 3,
                    'driver'   => $driver,
                    'port'     => $port,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return view('install/migrate', [
            'title'    => 'نظام التثبيت - مزامنة قاعدة البيانات',
            'step'     => 3,
            'driver'   => $driver,
            'port'     => $port,
        ]);
    }

    /**
     * Step 4: Superadmin Form
     */
    public function admin()
    {
        return view('install/admin', [
            'title' => 'نظام التثبيت - إنشاء حساب المدير العام',
            'step'  => 4,
        ]);
    }

    /**
     * Save Superadmin Account (POST)
     */
    public function saveAdmin()
    {
        $rules = [
            'username'         => 'required|min_length[3]|max_length[30]',
            'email'            => 'required|valid_email',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $username = $this->request->getPost('username');
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        try {
            $userModel = new \App\Models\UserModel();
            $userModel->bypassTenant(); // Disable Tenant scoping for checking existence during installation

            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $username,
                'email'    => $email,
                'password' => $password,
            ]);

            if (!$userModel->save($user)) {
                return redirect()->back()->withInput()->with('errors', $userModel->errors());
            }

            $userId = $userModel->getInsertID();
            $user = $userModel->findById($userId);

            // Assign to superadmin group
            $user->addGroup('superadmin');

            // Manually trigger the 'register' event to generate the Tenant Workspace
            Events::trigger('register', $user);

        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errors', [$e->getMessage()]);
        }

        return redirect()->to(base_url('install/complete'));
    }

    /**
     * Step 5: Completion Page
     */
    public function complete()
    {
        // Write the installation lock file
        $installedFile = WRITEPATH . 'installed.txt';
        file_put_contents($installedFile, date('Y-m-d H:i:s') . "\n");

        return view('install/complete', [
            'title' => 'تم التثبيت بنجاح',
            'step'  => 5,
        ]);
    }

    /**
     * Update the .env file helper
     */
    private function updateEnvFile(array $dbData): bool
    {
        $envPath = ROOTPATH . '.env';
        
        // If .env doesn't exist, try to copy it from .env.local or env template
        if (!file_exists($envPath)) {
            if (file_exists(ROOTPATH . '.env.local')) {
                copy(ROOTPATH . '.env.local', $envPath);
            } elseif (file_exists(ROOTPATH . 'env')) {
                copy(ROOTPATH . 'env', $envPath);
            } else {
                file_put_contents($envPath, "");
            }
        }

        $lines = file($envPath);
        $settings = [
            'database.default.hostname' => $dbData['hostname'],
            'database.default.database' => $dbData['database'],
            'database.default.username' => $dbData['username'],
            'database.default.password' => $dbData['password'],
            'database.default.DBDriver' => $dbData['driver'],
            'database.default.port'     => $dbData['port'],
        ];

        $updated = [];
        foreach ($lines as &$line) {
            foreach ($settings as $key => $val) {
                // Matches optional hash, optional space, the key, optional space, equals sign, optional space
                if (preg_match("/^#?\s*" . preg_quote($key, '/') . "\s*=/i", $line)) {
                    $line = "{$key} = '{$val}'\n";
                    $updated[$key] = true;
                }
            }
        }

        // For any settings not already in the file, append them
        foreach ($settings as $key => $val) {
            if (!isset($updated[$key])) {
                $lines[] = "{$key} = '{$val}'\n";
            }
        }

        return file_put_contents($envPath, implode('', $lines)) !== false;
    }

    /**
     * Run migrations on demand (e.g., for updates)
     */
    public function updateDatabaseSchema()
    {
        // 1. Authenticate that the user is an admin or superadmin
        if (!auth()->loggedIn() || !auth()->user()->inGroup('superadmin', 'admin')) {
            $token = $this->request->getGet('token');
            $appSecret = env('DB_UPDATE_KEY') ?: 'madaqbio_update_secret_2026';
            if ($token !== $appSecret) {
                return $this->response->setStatusCode(403)->setBody('غير مسموح بالوصول. يجب تسجيل الدخول كمسؤول أو تقديم رمز الأمان الصحيح (token).');
            }
        }

        try {
            $migrate = \Config\Services::migrations();
            
            // Run Shield migrations
            $migrate->setNamespace('CodeIgniter\Shield')->latest();
            
            // Run App migrations
            $migrate->setNamespace('App')->latest();
            
            // Run other namespaces
            $namespaces = array_keys(service('autoloader')->getNamespace());
            foreach ($namespaces as $ns) {
                if (in_array($ns, ['CodeIgniter\Shield', 'App', 'CodeIgniter'], true)) {
                    continue;
                }
                try {
                    $migrate->setNamespace($ns)->latest();
                } catch (\Throwable $e) {
                    // Silence errors for third-party namespaces
                }
            }
            
            return $this->response->setBody('<h1>تمت تحديثات قاعدة البيانات بنجاح!</h1><p>تمت مزامنة جميع الجداول وتطبيق الهجرات (migrations) الجديدة.</p><p><a href="' . base_url('/') . '">العودة للوحة التحكم</a></p>');
        } catch (\Throwable $e) {
            return $this->response->setBody('<h1>فشل تحديث قاعدة البيانات</h1><p>الخطأ: ' . esc($e->getMessage()) . '</p>');
        }
    }
}
