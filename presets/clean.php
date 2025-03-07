<?php

if (!function_exists('wpkit_prest_do')) {
    function wpkit_preset_do($path, \Illuminate\Database\Connection $db)
    {
        $prefix = $db->getTablePrefix();

        $db->table('posts')->truncate();
        $tableName = $prefix . 'posts';
        $db->statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1");

        $db->table('postmeta')->truncate();
        $tableName = $prefix . 'postmeta';
        $db->statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1");

    }
}
