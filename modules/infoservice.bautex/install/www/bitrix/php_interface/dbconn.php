<?
$moduleIncludeFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/infoservice.bautex/include.php';
if (file_exists($moduleIncludeFile)) {
    session_start();
    require_once $moduleIncludeFile;

    if (!empty($_SESSION[INFS_USER_LANG_FIELD])) define('LANGUAGE_ID', $_SESSION[INFS_USER_LANG_FIELD]);
}