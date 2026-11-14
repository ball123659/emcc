<?php

namespace App\Libraries;

use App\Models\UserModel;

class UserPermissions
{

    public static function assignablePageOptions(): array
    {
        $db       = db_connect();
        $pages    = $db->table('pages')->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
        $byId     = array_column($pages, null, 'id');
        $parents  = array_flip(array_filter(array_column($pages, 'parent_id')));
        $options  = [];

        foreach ($pages as $page) {
            if ((bool) $page['admin_only'] || isset($parents[$page['id']])) {
                continue;
            }

            $parent                       = $page['parent_id'] !== null ? ($byId[$page['parent_id']] ?? null) : null;
            $options[(int) $page['id']]   = $parent ? $parent['name_th'] . ' › ' . $page['name_th'] : $page['name_th'];
        }

        return $options;
    }

    public static function selectedPageIds(int $userId): array
    {
        $assignable = array_keys(self::assignablePageOptions());

        if ($assignable === []) {
            return [];
        }

        return array_map('intval', array_column(
            db_connect()->table('user_page_permissions')->select('page_id')->where('user_id', $userId)->where('can_view', 1)
                ->whereIn('page_id', $assignable)->get()->getResultArray(),
            'page_id',
        ));
    }

    public static function sync(array $user, array $selectedPageIds): void
    {
        $db         = db_connect();
        $isAdmin    = UserModel::isAdmin($user);
        $assignable = array_keys(self::assignablePageOptions());
        $selected   = $isAdmin ? [] : array_values(array_intersect($assignable, array_map('intval', $selectedPageIds)));
        $before     = self::visibleCodes((int) $user['id']);
        $now        = date('Y-m-d H:i:s');
        $pages      = $db->table('pages')->get()->getResultArray();

        foreach ($pages as $page) {
            $pageId  = (int) $page['id'];
            $canView = in_array($pageId, $selected, true);

            if (! $isAdmin && ! (bool) $page['admin_only'] && ! in_array($pageId, $assignable, true)) {

                $children = array_map('intval', array_column(array_filter($pages, static fn ($p) => (int) $p['parent_id'] === $pageId), 'id'));
                $canView  = array_intersect($children, $selected) !== [];
            }

            $values = ['can_view' => $canView ? 1 : 0, 'updated_by' => service('auth')->id(), 'updated_at' => $now];
            $exists = $db->table('user_page_permissions')->where(['user_id' => $user['id'], 'page_id' => $pageId])->countAllResults() > 0;

            if ($exists) {
                $db->table('user_page_permissions')->where(['user_id' => $user['id'], 'page_id' => $pageId])->update($values);
            } else {
                $db->table('user_page_permissions')->insert(['user_id' => $user['id'], 'page_id' => $pageId] + $values);
            }
        }

        $after = self::visibleCodes((int) $user['id']);

        if ($before !== $after) {
            ActivityLogger::log('permission_update', 'users', $user['id'], "แก้สิทธิ์รายหน้าของ {$user['username']}", ['old' => $before, 'new' => $after]);
        }
    }

    public static function visibleCodes(int $userId): array
    {
        return array_column(
            db_connect()->table('pages')->select('pages.code')->join('user_page_permissions upp', 'upp.page_id = pages.id')
                ->where('upp.user_id', $userId)->where('upp.can_view', 1)->orderBy('pages.id')->get()->getResultArray(),
            'code',
        );
    }
}
