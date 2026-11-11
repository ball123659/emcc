<?php

namespace App\Models;

use App\Models\Concerns\HasBilingualFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * หน้า/เมนูหลังบ้าน — ใช้กำหนดสิทธิ์รายหน้า
 */
class Page extends Model
{
    use HasBilingualFields;

    protected $fillable = [
        'code',
        'parent_id',
        'name_th',
        'name_en',
        'icon',
        'sort_order',
        'is_active',
        'admin_only',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'admin_only' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // สำรองกรณี trigger trg_pages_after_insert ใช้ไม่ได้
        static::created(fn (Page $page) => UserPagePermission::createDefaultsForPage($page));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_page_permissions')
            ->using(UserPagePermission::class)
            ->withPivot(['can_view', 'updated_by', 'updated_at']);
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
