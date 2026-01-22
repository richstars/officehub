<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {name} {email} {password} {role=admin}';

    protected $description = 'Create a new admin/author user';

    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');
        $role = $this->argument('role');

        if (\App\Models\User::where('email', $email)->exists()) {
            $this->error('User with this email already exists!');
            return;
        }

        \App\Models\User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
        ]);

        $this->info("User {$email} with role {$role} created successfully!");
    }
}
