<?php

namespace App\Controllers\Backend;

use App\Libraries\Storage;
use App\Libraries\Uploads\UploadCheck;
use App\Libraries\Uploads\UploadRejected;
use App\Models\SiteSettingModel;

/**
 * ตั้งค่าเว็บไซต์ (เบอร์โทร, อีเมล, ที่อยู่, โซเชียล)
 * ฟอร์มสร้างจากแถวในตาราง site_settings ตาม input_type — เพิ่มช่องใหม่ได้โดยเพิ่มแถว (SiteSettingSeeder)
 */
class SiteSettings extends BackendController
{
    public const GROUP_ICONS = ['general' => 'globe-alt', 'contact' => 'phone', 'social' => 'share'];

    private function settings(): array
    {
        return (new SiteSettingModel())->orderBy('sort_order')->orderBy('id')->findAll();
    }

    public function edit()
    {
        $groups = [];

        foreach ($this->settings() as $setting) {
            $groups[$setting['group']][] = $setting;
        }

        uksort($groups, static function ($a, $b) {
            $order = array_keys(SiteSettingModel::GROUPS);
            $ia    = array_search($a, $order, true);
            $ib    = array_search($b, $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        $tab = (string) $this->request->getGet('tab');

        return view('backend/site_settings', [
            'title'  => 'ตั้งค่าเว็บไซต์',
            'groups' => $groups,
            'active' => isset($groups[$tab]) ? $tab : (string) array_key_first($groups),
        ]);
    }

    public function update()
    {
        $settings = $this->settings();
        $values   = (array) $this->request->getPost('settings');
        $errors   = [];
        $changes  = [];
        $created  = [];
        $obsolete = [];

        foreach ($settings as $setting) {
            $key  = $setting['key'];
            $th   = trim((string) ($values[$key]['th'] ?? ''));
            $en   = trim((string) ($values[$key]['en'] ?? ''));
            $name = "settings.{$key}.th";

            switch ($setting['input_type']) {
                case 'url':
                    if ($th !== '' && (mb_strlen($th) > 500 || ! self::isHttpUrl($th))) {
                        $errors[$name] = mb_strlen($th) > 500 ? 'ลิงก์ยาวได้ไม่เกิน 500 ตัวอักษร' : 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://';
                    }
                    $en = '';
                    break;

                case 'email':
                    if ($th !== '' && (mb_strlen($th) > 191 || filter_var($th, FILTER_VALIDATE_EMAIL) === false)) {
                        $errors[$name] = 'รูปแบบอีเมลไม่ถูกต้อง ตัวอย่าง info@example.com';
                    }
                    $en = '';
                    break;

                case 'phone':
                    if (mb_strlen($th) > 100) {
                        $errors[$name] = 'ยาวได้ไม่เกิน 100 ตัวอักษร';
                    } elseif (! preg_match('/^[0-9+\-\s\/,()]*$/', $th)) {
                        $errors[$name] = 'ใส่ได้เฉพาะตัวเลข ขีด (-) ทับ (/) และช่องว่าง';
                    }
                    $en = '';
                    break;

                case 'image':
                    $file = $this->request->getFile("setting_image_{$key}");
                    $th   = (string) $setting['value_th'];
                    $en   = '';

                    if (UploadCheck::chosen($file)) {
                        try {
                            $stored    = service('imageProcessor')->storeUpload($file, 'settings');
                            $created[] = $stored->path;
                            $obsolete[] = $setting['value_th'];
                            $th        = $stored->path;
                        } catch (UploadRejected $e) {
                            $errors["setting_image_{$key}"] = $e->getMessage();
                        }
                    } elseif ($this->request->getPost("remove_setting_image_{$key}") === '1') {
                        $obsolete[] = $setting['value_th'];
                        $th         = '';
                    }
                    break;

                case 'textarea':
                    foreach (['th' => $th, 'en' => $en] as $lang => $value) {
                        if (mb_strlen($value) > 2000) {
                            $errors["settings.{$key}.{$lang}"] = 'ยาวได้ไม่เกิน 2000 ตัวอักษร';
                        }
                    }
                    break;

                default:
                    foreach (['th' => $th, 'en' => $en] as $lang => $value) {
                        if (mb_strlen($value) > 500) {
                            $errors["settings.{$key}.{$lang}"] = 'ยาวได้ไม่เกิน 500 ตัวอักษร';
                        }
                    }
            }

            $changes[$setting['id']] = ['value_th' => $th === '' ? null : $th, 'value_en' => $en === '' ? null : $en];
        }

        if ($errors !== []) {
            Storage::delete(...$created);

            return $this->backWithErrors($errors);
        }

        $model = new SiteSettingModel();
        $db    = db_connect();
        $db->transStart();

        foreach ($settings as $setting) {
            $new = $changes[$setting['id']];

            if ($new['value_th'] !== $setting['value_th'] || $new['value_en'] !== $setting['value_en']) {
                $model->update($setting['id'], $new + ['updated_by' => $this->auth->id()]);
            }
        }

        $db->transComplete();
        Storage::delete(...array_filter($obsolete));
        $this->toast('บันทึกการตั้งค่าแล้ว');

        $tab = (string) $this->request->getPost('_tab');

        return redirect()->to(site_url('backend/site-settings' . (isset(SiteSettingModel::GROUPS[$tab]) ? '?tab=' . $tab : '')));
    }
}
