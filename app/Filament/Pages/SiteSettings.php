<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPagePermission;
use App\Filament\Forms\ImageUpload;
use App\Models\SiteSetting;
use App\Services\Images\ImageProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * ตั้งค่าเว็บไซต์ (pages.code = site_settings)
 * ฟอร์มสร้างจากแถวในตาราง site_settings ตาม input_type — เพิ่มช่องใหม่ได้โดยเพิ่มแถว (SiteSettingSeeder)
 */
class SiteSettings extends Page
{
    use ChecksPagePermission;

    protected static string $pageCode = 'site_settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'ตั้งค่า';

    protected static ?string $navigationLabel = 'ตั้งค่าเว็บไซต์';

    protected static ?string $title = 'ตั้งค่าเว็บไซต์';

    protected static ?string $slug = 'site-settings';

    protected static ?int $navigationSort = 1;

    /** ชื่อกลุ่มที่แสดง */
    public const GROUPS = [
        'general' => 'ข้อมูลทั่วไป',
        'contact' => 'ข้อมูลติดต่อ',
        'social' => 'โซเชียลมีเดีย',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $data = [];

        foreach ($this->settings() as $setting) {
            $data[$setting->key] = [
                'th' => $setting->input_type === 'image'
                    ? array_filter([$setting->value_th])
                    : $setting->value_th,
                'en' => $setting->value_en,
            ];
        }

        $this->form->fill($data);
    }

    /** @return Collection<int, SiteSetting> */
    protected function settings(): Collection
    {
        return SiteSetting::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function form(Schema $schema): Schema
    {
        $sections = $this->settings()
            ->groupBy('group')
            ->sortBy(fn ($items, string $group) => array_search($group, array_keys(self::GROUPS), true) === false ? 99 : array_search($group, array_keys(self::GROUPS), true))
            ->map(fn (Collection $items, string $group) => Section::make(self::GROUPS[$group] ?? $group)
                ->columns(2)
                ->schema($items->flatMap(fn (SiteSetting $setting) => $this->fieldsFor($setting))->all()))
            ->values()
            ->all();

        return $schema->components($sections)->statePath('data');
    }

    /** @return array<Component> */
    protected function fieldsFor(SiteSetting $setting): array
    {
        $key = $setting->key;
        $label = $setting->label_th;

        return match ($setting->input_type) {
            'textarea' => [
                Textarea::make("{$key}.th")->label("{$label} (ไทย)")->rows(3)->maxLength(2000),
                Textarea::make("{$key}.en")->label("{$label} (อังกฤษ)")->rows(3)->maxLength(2000)
                    ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน'),
            ],
            'url' => [
                TextInput::make("{$key}.th")->label($label)->url()->maxLength(500)->placeholder('https://')
                    ->rules(['url:http,https'])
                    ->validationMessages(['url' => 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://'])
                    ->columnSpanFull(),
            ],
            'email' => [
                TextInput::make("{$key}.th")->label($label)->email()->maxLength(191)
                    ->validationMessages(['email' => 'รูปแบบอีเมลไม่ถูกต้อง ตัวอย่าง info@example.com'])
                    ->columnSpanFull(),
            ],
            'phone' => [
                TextInput::make("{$key}.th")->label($label)->tel()->maxLength(100)
                    ->regex('/^[0-9+\-\s\/,()]*$/')
                    ->helperText('ใส่ได้หลายเบอร์ คั่นด้วย / เช่น 038-683933 / 081-7323485')
                    ->validationMessages(['regex' => 'ใส่ได้เฉพาะตัวเลข ขีด (-) ทับ (/) และช่องว่าง'])
                    ->columnSpanFull(),
            ],
            'image' => [
                ImageUpload::make("{$key}.th", 'settings', 'ตามการใช้งาน')->label($label)->columnSpanFull(),
            ],
            default => [
                TextInput::make("{$key}.th")->label("{$label} (ไทย)")->maxLength(500),
                TextInput::make("{$key}.en")->label("{$label} (อังกฤษ)")->maxLength(500)
                    ->helperText('เว้นว่างได้ — หน้าเว็บภาษาอังกฤษจะใช้ภาษาไทยแทน'),
            ],
        };
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('บันทึก')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data): void {
            foreach ($this->settings() as $setting) {
                Gate::authorize('update', $setting);

                $value = $data[$setting->key] ?? [];
                $th = $value['th'] ?? null;

                if ($setting->input_type === 'image') {
                    $th = is_array($th) ? (array_values($th)[0] ?? null) : $th;
                    $old = $setting->value_th;

                    if ($old && $old !== $th) {
                        ImageProcessor::delete($old);
                    }
                }

                $th = is_string($th) ? trim($th) : $th;
                $en = in_array($setting->input_type, ['text', 'textarea'], true) && is_string($value['en'] ?? null)
                    ? trim($value['en'])
                    : null;

                $setting->fill([
                    'value_th' => filled($th) ? $th : null,
                    'value_en' => filled($en) ? $en : null,
                ])->save();
            }
        });

        Notification::make()->success()->title('บันทึกการตั้งค่าแล้ว')->send();
    }
}
