<?php

namespace App\Filament\Resources\Concerns;

use App\Support\ActivityLogger;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * ลากเรียงลำดับอย่างปลอดภัย
 * Filament เรียงเฉพาะรายการที่เห็นอยู่ (1..n) — ถ้ากำลังกรองด้วยแท็บ/ตัวกรอง เลขลำดับจะซ้ำกับรายการที่ซ่อนอยู่
 * ตัวนี้จัดลำดับใหม่ทั้งตาราง: รายการที่ลากสลับกันเฉพาะในตำแหน่งเดิมของมัน รายการอื่นคงตำแหน่ง แล้วเรียงเลข 1..N ใหม่
 * และบันทึก activity log ว่าใครเปลี่ยนลำดับ
 */
class SafeReorder
{
    public static function configure(Table $table, string $label, string $column = 'sort_order'): Table
    {
        $snapshot = [];

        return $table
            ->reorderable($column)
            ->beforeReordering(function (Table $table) use (&$snapshot, $column): void {
                $snapshot = self::allKeysInOrder($table->getModel(), $column);
            })
            ->afterReordering(function (Table $table, array $order) use (&$snapshot, $column, $label): void {
                $modelClass = $table->getModel();
                $moved = array_map('strval', array_values($order));
                $movedSet = array_flip($moved);

                // ใส่รายการที่ลาก ลงในตำแหน่งที่รายการกลุ่มนี้เคยอยู่ ตามลำดับใหม่
                $final = [];
                $next = 0;

                foreach ($snapshot as $key) {
                    $final[] = isset($movedSet[$key]) ? $moved[$next++] : $key;
                }

                foreach ($final as $index => $key) {
                    self::query($modelClass)->whereKey($key)->update([$column => $index + 1]);
                }

                /** @var Model $model */
                $model = new $modelClass;
                ActivityLogger::log('reorder', $model, "เรียงลำดับ{$label}ใหม่", [
                    'new' => array_map(fn ($key) => '#'.$key, $moved),
                ]);
            });
    }

    /** @return list<string> */
    private static function allKeysInOrder(string $modelClass, string $column): array
    {
        $model = new $modelClass;

        return self::query($modelClass)
            ->orderBy($column)
            ->orderBy($model->getKeyName())
            ->pluck($model->getKeyName())
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    private static function query(string $modelClass)
    {
        $query = $modelClass::query();

        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $query->withoutGlobalScope(SoftDeletingScope::class)
            : $query;
    }
}
