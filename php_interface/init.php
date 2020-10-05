<?
use Bitrix\Main\Loader;

foreach (glob(__DIR__ . '/../modules/infoservice.*') as $modulePath) {
    if (!is_dir($modulePath)) continue;
    
    Loader::includeModule(basename($modulePath));
}