<?
require_once __DIR__ . '/../../ru/install/index.php';

$MESS['INFOSERVICE_BAUTEX_MODULE_NAME'] = 'Module "Bautex"';
$MESS['INFOSERVICE_BAUTEX_MODULE_DESCRIPTION'] = '';
$MESS['INFOSERVICE_BAUTEX_MODULE_WAS_INSTALLED'] = $MESS['INFOSERVICE_BAUTEX_MODULE_NAME'] . ' was installed';
$MESS['INFOSERVICE_BAUTEX_MODULE_NOT_INSTALLED'] = $MESS['INFOSERVICE_BAUTEX_MODULE_NAME'] . ' not was installed';
$MESS['INFOSERVICE_BAUTEX_MODULE_WAS_DELETED'] = $MESS['INFOSERVICE_BAUTEX_MODULE_NAME'] . ' was deleted';
$MESS['ERROR_NO_OPTION_CLASS'] = 'For module class #CLASS# not exists';

$MESS['ERROR_BAD_USER_FIELD_NAME'] = 'User field NAME not begins by UF_';
$MESS['ERROR_BAD_USER_FIELD_VOTE_CHANNEL'] = 'For user field NAME at [\'SETTINGS\'][\'CHANNEL_ID\'] no '
                                           . 'constant name, which stores vote group symbolical code';
$MESS['ERROR_BAD_USER_FIELD_IBLOCK'] = 'For user field NAME at [\'SETTINGS\'][\'CHANNEL_ID\'] no '
                                     . 'constant name, which stores infoblock symbolical code';
$MESS['ERROR_USER_FIELD_CREATING'] = 'User field NAME not was added';
$MESS['ERROR_MAIN_LINK_CREATING'] = 'Symbolical link LINK not was created at root of web site';