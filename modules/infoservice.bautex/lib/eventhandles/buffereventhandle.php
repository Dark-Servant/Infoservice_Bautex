<?
namespace Infoservice\Bautex\EventHandles;

abstract class BufferEventHandle
{
    /**
     * Проверка какой язык установлен для сайта у текущего пользователя.
     * Если был установлен новый, то запоминает в сессии и перезагружает
     * страницу
     * 
     * @return void
     */
    protected static function checkUserLanguage()
    {
        global $USER;
        $currentUserId = $USER->GetId();
        if (!$currentUserId) {
            unset($_SESSION[INFS_USER_LANG_FIELD]);
            return;
        }

        $user = \CUser::GetList(
                    $field = 'ID', $dir = 'ASC',
                    ['ID' => $currentUserId],
                    ['FIELDS' => ['ID', 'NAME', 'LAST_NAME'], 'SELECT' => [INFS_USER_LANG_FIELD]]
                )->Fetch();
        $langCode = '';
        if (
            $user[INFS_USER_LANG_FIELD]
            && !empty($langParam = \CUserFieldEnum::GetList([], ['ID' => $user[INFS_USER_LANG_FIELD]])->Fetch())
        ) $langCode = $langParam['XML_ID'];

        if ($_SESSION[INFS_USER_LANG_FIELD] == $langCode) return;

        $_SESSION[INFS_USER_LANG_FIELD] = $langCode;
    }

    /**
     * Обработчик события В НАЧАЛЕ ВИЗУАЛЬНОЙ ЧАСТИ пролога сайта.
     *
     * @return void
     */
    public static function OnProlog()
    {
        if (!Employment::setBussy()) return;
        self::checkUserLanguage();
        Employment::setFree();
    }
}