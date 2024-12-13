<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    protected $guarded = [];
    
    public function products():HasMany
    {
        return $this->hasMany(Product::class);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (self::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . Str::random(5);
        }
        return $slug;
    }
}
