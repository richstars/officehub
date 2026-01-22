<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = ['display_name', 'file_path', 'category', 'size', 'description', 'is_secure', 'password'];
    
    protected $hidden = ['password'];

    protected $casts = [
        'is_secure' => 'boolean',
    ];
}
