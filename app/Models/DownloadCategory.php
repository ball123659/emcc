<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DownloadCategory extends Model
{
    use HasBilingualFields, LogsActivity;

    protected $fillable = ['slug', 'name_th', 'name_en', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DownloadCategory $category): void {
            if (! $category->sort_order) {
                $category->sort_order = ((int) static::query()->max('sort_order')) + 1;
            }
        });

        static::saving(function (DownloadCategory $category): void {
            $category->slug = Str::slug((string) ($category->slug ?: $category->name_en ?: Str::ascii((string) $category->name_th))) ?: Str::lower(Str::random(6));
        });
    }

    /** หมวดที่ยังมีไฟล์ (รวมที่ถูกลบแบบกู้คืนได้) ลบไม่ได้ */
    public function hasDownloads(): bool
    {
        return $this->downloads()->withTrashed()->exists();
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
