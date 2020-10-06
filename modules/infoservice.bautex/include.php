<?
// Основные константы
define('INFS_BAUTEX_MODULE_ID', basename(__DIR__));

// Данные о версии модуля
require __DIR__ . '/install/version.php';
foreach ($arModuleVersion as $key => $value) {
    define('INFS_BAUTEX_' . $key, $value);
}

// Пользовательское поле для пользователей, чтобы хранить индивидуальный язык сайта
define('INFS_USER_LANG_FIELD', 'UF_USER_LANG');