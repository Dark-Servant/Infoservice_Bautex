<?
namespace Infoservice\Bautex\EventHandles;

abstract class Employment
{
    private static $bussyStatus = false;
    private static $partnerEventHandleSpaces = null;

    /**
     * Устанавливает занятость для всех обработчиков событий
     *
     * @return boolean
     */
    public static function setBussy()
    {
        if (self::$bussyStatus) return false;
        
        return self::$bussyStatus = true;
    }

    /**
     * Снимает занятость для всех обработчиков событий
     *
     * @return boolean
     */
    public static function setFree()
    {
        $oldFree = self::$bussyStatus;
        self::$bussyStatus = false;
        return !$oldFree;
    }
}