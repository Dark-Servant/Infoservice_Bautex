<?
namespace Infoservice\Bautex\Helpers;

use \Bitrix\Main\Config\Option;

abstract class Options
{
    const OPTION_NAME = 'installed';

    /**
     * При создании дополнительных модулей с наследованием от текущего
     * переменные $params и $moduleId надо обязательно объявить в
     * дочернем Options
     */
    protected static $params;
    protected static $moduleId;

    /**
     * Возвращает код модуля, к которому относится текущий класс
     * 
     * @return string
     */
    protected static function getModuleId()
    {
        return (
            static::$moduleId ??
            static::$moduleId = strtolower(implode('.', array_slice(preg_split('/\W+/', get_called_class()), 0, -2)))
        );
    }

    /**
     * Загрузка всех параметров модуля
     * 
     * @return array
     */
    protected static function &loadParams()
    {
        if (!static::$params) {
            $data = Option::get(
                        static::getModuleId(),
                        static::OPTION_NAME, false,
                        \CSite::GetDefSite()
                    );
            static::$params = $data ? json_decode($data, true) : [];
        }
        return static::$params;
    }

    /**
     * Получение всех параметров модуля
     * 
     * @return array
     */
    public static function getParams()
    {
        return static::loadParams();
    }

    /**
     * Сохранение всех параметров в модуле
     * 
     * @return void
     */
    public static function save()
    {
        Option::set(static::getModuleId(), static::OPTION_NAME, json_encode(static::getParams()));
    }

    /**
     * Общий для всех статических get/set/add-методов
     * 
     * @param $method - название метода
     * @param $params - параметры метода
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        if (!preg_match('/^([sg]et|add)(\w+)$/i', $method, $methodParts)) return;
        list(, $actionName, $paramGroupName) = $methodParts;
        $paramCount = count($params);

        switch (strtolower($actionName)) {
            /**
             * Обработчик методов set<Название группы>. Метод полностью перезаписывает данные
             * конкретной группы
             */
            case 'set':
                if (!$paramCount) return null;
                
                // сохраняет последний переданный параметр
                return $resultValue = static::loadParams()[$paramGroupName] = end($params);

            /**
             * Обработчик методов add<Название группы>. Метод добавляет данные к конкретной группы
             */
            case 'add':
                if (!$paramCount) return null;
                
                // берем первый параметр и запоминаем его как возвращаемое значение
                $resultValue = $firstParam = current($params);
                if (is_array($firstParam)) { // если этот параметр массив
                    // то только его данные дописываем к конкретной группе
                    static::loadParams()[$paramGroupName] = array_replace(static::loadParams()[$paramGroupName], $firstParam);

                } elseif ($paramCount < 2) { // если первый параметр единственный переданный параметр
                    // то добавляем его к параметрам конкретной группы
                    static::loadParams()[$paramGroupName][] = $firstParam;

                // если переданно несколько параметров, и первый либо целочисленное значение или непустая строка
                } elseif (is_numeric($firstParam) || (is_string($firstParam) && !empty($firstParam))) {
                    /**
                     * то в конкретной группе переписываем параметр с "ключом" равным значению первого параметра
                     * на значение последнего параметра
                     */
                    $resultValue = end($params);
                    static::loadParams()[$paramGroupName][$firstParam] = $resultValue;

                } else {
                    return null;
                }
                return $resultValue;

            /**
             * По-умолчанию, обработчик методов get<Название группы>. Метод берет данные из конкретной группы
             */
            default:
                /**
                 * Получаем данные конкретной группы, и если не было переданно ни одного параметра,
                 * то возвращаем данные этой группы
                 */
                $group = static::getParams()[$paramGroupName];
                if (!$paramCount) return $group;

                /**
                 * Если были указанны параметры, то берем те данные, которые хранятся под "ключами", названия
                 * которых указаны в параметрах
                 */
                $resultValue = [];
                foreach ($params as $paramName) {
                    if (!is_numeric($paramName) && (!is_string($paramName) || empty($paramName))) continue;

                    $resultValue[$paramName] = $group[$paramName];
                }

                /**
                 * Если параметров было переданно больше одно, то возвращаем весь собранный результат,
                 * иначе только значение первого параметра
                 */
                return $paramCount > 1 ? $resultValue : current($resultValue);
        }
    }
}