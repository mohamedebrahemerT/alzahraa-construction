<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'إنشاء حساب مدير الموقع الأول أو حساب مدير إضافي دون كلمة مرور افتراضية';

    public function handle(): int
    {
        $name = $this->ask('اسم المدير');
        $email = $this->ask('البريد الإلكتروني');
        $password = $this->secret('كلمة مرور قوية (12 حرفًا على الأقل)');
        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:200'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'manager', 'is_active' => true]);
        $this->info('تم إنشاء حساب المدير. يمكنك تسجيل الدخول من /admin/login.');

        return self::SUCCESS;
    }
}
