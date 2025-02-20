<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    //
    use HasFactory;
    protected $table = 'categories';
    protected $fillable = ['name', 'slug', 'description', 'is_active'];
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('slug', $slug)->exists()) {
        $slug = $originalSlug. '-'. $counter++;
        $counter++;
        }
        return $slug;
    }
}
