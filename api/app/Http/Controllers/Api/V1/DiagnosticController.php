<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

class DiagnosticController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = $this->checkTables();
        
        $diagnostics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'openssl' => $this->checkOpenSSL(),
            'url' => config('app.url'),
            'tables' => $tables,
            'actions' => [
                'run_migrations' => url('api/v1/diagnostics/migrate'),
                'fix_tokens_manual' => url('api/v1/diagnostics/fix'),
                'test_register' => url('api/v1/diagnostics/test-register'),
            ]
        ];

        return response()->json($diagnostics);
    }

    /**
     * Check all required tables and their columns
     */
    private function checkTables(): array
    {
        $required = [
            'users' => ['company_name', 'plan_status'],
            'personal_access_tokens' => ['token'],
            'products' => ['sku', 'name'],
            'recipients' => ['name'],
            'invoices' => ['invoice_number'],
            'user_settings' => ['whatsapp_number', 'show_whatsapp_button', 'mp_access_token'],
            'subscriptions' => ['status'],
            'sessions' => ['payload'],
            'cache' => ['key'],
            'migrations' => ['migration']
        ];

        $result = [];
        foreach ($required as $table => $columns) {
            try {
                $exists = Schema::hasTable($table);
                $missing_columns = [];
                
                if ($exists) {
                    foreach ($columns as $column) {
                        if (!Schema::hasColumn($table, $column)) {
                            $missing_columns[] = $column;
                        }
                    }
                    $count = DB::table($table)->count();
                } else {
                    $count = null;
                }

                $result[$table] = [
                    'exists' => $exists,
                    'rows' => $count,
                    'missing_columns' => $missing_columns,
                    'status' => ($exists && empty($missing_columns)) ? 'OK' : 'ERROR'
                ];
            } catch (\Exception $e) {
                $result[$table] = [
                    'exists' => false,
                    'error' => $e->getMessage(),
                    'status' => 'ERROR'
                ];
            }
        }

        return $result;
    }

    /**
     * Run migrations from the browser
     */
    public function migrate(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Migraciones ejecutadas correctamente.',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Error al ejecutar migraciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fix missing tables - creates personal_access_tokens if missing
     */
    public function fix(): JsonResponse
    {
        $fixes = [];

        // Fix 1: personal_access_tokens
        if (!Schema::hasTable('personal_access_tokens')) {
            try {
                DB::statement("
                    CREATE TABLE personal_access_tokens (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        tokenable_type VARCHAR(191) NOT NULL,
                        tokenable_id BIGINT UNSIGNED NOT NULL,
                        name VARCHAR(191) NOT NULL,
                        token VARCHAR(64) NOT NULL,
                        abilities TEXT NULL,
                        last_used_at TIMESTAMP NULL,
                        expires_at TIMESTAMP NULL,
                        created_at TIMESTAMP NULL,
                        updated_at TIMESTAMP NULL,
                        UNIQUE KEY personal_access_tokens_token_unique (token),
                        INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                $fixes['personal_access_tokens'] = 'CREATED';
            } catch (\Exception $e) {
                $fixes['personal_access_tokens'] = 'ERROR: ' . $e->getMessage();
            }
        } else {
            $fixes['personal_access_tokens'] = 'ALREADY EXISTS';
        }

        // Verify after fix
        $exists = Schema::hasTable('personal_access_tokens');
        
        // Double check with raw SQL
        $rawCheck = DB::select("SHOW TABLES LIKE 'personal_access_tokens'");

        return response()->json([
            'fixes' => $fixes,
            'verification_schema' => $exists,
            'verification_raw' => count($rawCheck) > 0,
            'all_tables' => DB::select("SHOW TABLES"),
        ]);
    }

    /**
     * Test registration to see the exact error
     */
    public function testRegister(): JsonResponse
    {
        try {
            // Check users table columns
            $columns = Schema::getColumnListing('users');
            
            // Check if personal_access_tokens exists
            $patExists = Schema::hasTable('personal_access_tokens');
            
            // Try a dry-run registration
            $testResult = [
                'users_columns' => $columns,
                'personal_access_tokens_exists' => $patExists,
            ];

            // Try to actually create and then delete a test user
            DB::beginTransaction();
            try {
                $user = \App\Models\User::create([
                    'name' => 'TEST_DELETE_ME',
                    'email' => 'test_delete_' . time() . '@test.com',
                    'password' => Hash::make('test123456'),
                    'plan_status' => 'trial',
                    'trial_expires_at' => now()->addDays(7),
                ]);
                $testResult['user_creation'] = 'OK - ID: ' . $user->id;

                // Try creating settings
                $user->settings()->create([
                    'company_name' => 'Test Company',
                    'show_whatsapp_button' => true,
                ]);
                $testResult['settings_creation'] = 'OK';

                // Try creating token
                $token = $user->createToken('test_token')->plainTextToken;
                $testResult['token_creation'] = 'OK - Token generated';

            } catch (\Exception $e) {
                $testResult['error_step'] = $e->getMessage();
                $testResult['error_file'] = $e->getFile() . ':' . $e->getLine();
                $testResult['error_trace'] = array_slice(explode("\n", $e->getTraceAsString()), 0, 5);
            }
            
            // Rollback so no test data persists
            DB::rollBack();

            return response()->json($testResult);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'OK', 'database' => DB::connection()->getDatabaseName()];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage()
    {
        $writable = is_writable(storage_path('framework/views'));
        return [
            'status' => $writable ? 'OK' : 'ERROR',
            'path' => storage_path(),
            'writable' => $writable
        ];
    }

    private function checkOpenSSL()
    {
        $available = function_exists('openssl_pkey_new');
        return [
            'status' => $available ? 'OK' : 'ERROR',
            'available' => $available
        ];
    }
}
