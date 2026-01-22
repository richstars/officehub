<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Link::create([
            'title' => 'Office Email',
            'url' => 'https://outlook.office.com',
            'category' => 'Tools',
            'icon' => 'envelope',
        ]);

        \App\Models\Link::create([
            'title' => 'HRIS System',
            'url' => 'https://hris.example.com',
            'category' => 'HR',
            'icon' => 'user-group',
        ]);

        \App\Models\Link::create([
            'title' => 'Company Wiki',
            'url' => 'https://confluence.example.com',
            'category' => 'Knowledge',
            'icon' => 'book-open',
        ]);

        \App\Models\File::create([
            'display_name' => 'Employee Handbook 2024',
            'file_path' => 'handbook_2024.pdf',
            'category' => 'HR',
            'size' => 1024 * 1024 * 5, // 5MB
        ]);

        \App\Models\File::create([
            'display_name' => 'IT Security Policy',
            'file_path' => 'it_security.pdf',
            'category' => 'IT',
            'size' => 1024 * 1024 * 2, // 2MB
        ]);
    }
}
