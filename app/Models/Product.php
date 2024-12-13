<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // public function orderProducts(): HasMany
    // {
    //     return $this->hasMany(OrderProduct::class);
    // }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (self::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . Str::random(5);
        }
        return $slug;
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? url('storage/' . $this->image) : null;
    }

    // public function scopeSearch($query, $value)
    // {
    //     $query->where("name", "like", "%{$value}%");
    // }

    public function scopeSearch($query, $value)
    {
        Log::info($value);
        $query->where('name', 'like', "%{$value}%")
            ->orWhere('stock', 'like', "%{$value}%")
            ->orWhere('price', 'like', "%{$value}%");
    }
}
