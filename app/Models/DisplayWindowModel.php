<?php

namespace App\Models;

abstract class DisplayWindowModel extends BaseModel
{
    public const STATUS_SHOWING   = 'showing';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_DISABLED  = 'disabled';

    public const STATUS_LABELS = [
        self::STATUS_SHOWING   => 'กำลังแสดง',
        self::STATUS_SCHEDULED => 'รอแสดง',
        self::STATUS_EXPIRED   => 'หมดอายุ',
        self::STATUS_DISABLED  => 'ปิด',
    ];

    public const STATUS_COLORS = [
        self::STATUS_SHOWING   => 'success',
        self::STATUS_SCHEDULED => 'info',
        self::STATUS_EXPIRED   => 'gray',
        self::STATUS_DISABLED  => 'danger',
    ];

    protected $useSoftDeletes = true;
    protected bool $tracksUser   = true;
    protected bool $logsActivity = true;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->beforeInsert[] = 'appendSortOrder';
    }

    protected function appendSortOrder(array $data): array
    {
        if (! isset($data['data']['sort_order'])) {
            $max                        = $this->db->table($this->table)->selectMax('sort_order')->get()->getRowArray();
            $data['data']['sort_order'] = (int) ($max['sort_order'] ?? 0) + 1;
        }

        return $data;
    }

    public static function displayStatus(array $row): string
    {
        $today = date('Y-m-d');

        return match (true) {
            ! (bool) $row['is_active'] => self::STATUS_DISABLED,
            ! empty($row['start_date']) && $row['start_date'] > $today => self::STATUS_SCHEDULED,
            ! empty($row['end_date']) && $row['end_date'] < $today     => self::STATUS_EXPIRED,
            default                                                    => self::STATUS_SHOWING,
        };
    }

    public static function daysUntilExpiry(array $row): ?int
    {
        if (empty($row['end_date'])) {
            return null;
        }

        return (int) round((strtotime($row['end_date']) - strtotime(date('Y-m-d'))) / 86400);
    }

    public function whereStatus(?string $status): static
    {
        $today = date('Y-m-d');

        match ($status) {
            self::STATUS_SHOWING => $this->where('is_active', 1)
                ->groupStart()->where('start_date IS NULL')->orWhere('start_date <=', $today)->groupEnd()
                ->groupStart()->where('end_date IS NULL')->orWhere('end_date >=', $today)->groupEnd(),
            self::STATUS_SCHEDULED => $this->where('is_active', 1)->where('start_date >', $today),
            self::STATUS_EXPIRED   => $this->where('is_active', 1)->where('end_date <', $today),
            self::STATUS_DISABLED  => $this->where('is_active', 0),
            default                => null,
        };

        return $this;
    }

    public function visible(): static
    {
        return $this->whereStatus(self::STATUS_SHOWING)->orderBy('sort_order')->orderBy('id');
    }
}
