<?php

use Bitrix\Main\{
    Localization\Loc,
    Loader,
    EventManager,
    Config\Option
};
use Infoservice\Bautex\EventHandles\Employment;

class infoservice_bautex extends CModule
{
    public $MODULE_ID;
    public $MODULE_NAME;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_DESCRIPTION;

    protected $nameSpaceValue;
    protected $subLocTitle;
    protected $optionClass;
    protected $definedContants;
    protected $moduleClass;
    protected $moduleClassPath;

    protected static $defaultSiteID;

    const SAVE_OPTIONS_WHEN_DELETED = true;
    
    /**
     * Опции, которые необходимо добавить в проект, сгруппированны по названиям, которые будут использоваться
     * в имени метода для их добавления. Опции описываются как ассоциативный массив, где "ключ" - центральная
     * часть имени метода, который будет вызван для добавления/удаления опций из каждой группы. Для того,
     * чтобы была инициализация опций в конкретной группе или их обработка перед удалением, необходимо
     * создать методы init<"Ключ">Options и remove<"Ключ">Options. В каждой группе опций, которые так же оформлены,
     * как ассоциативный массив, "ключ" - название константы, которая хранит название опции, эта константа должна
     * быть объявлена в файле include.php у модуля, под "значением" описываются настройки для инициализации каждого
     * элемента из группы опций. Итоговые данные опций после добавления будут сохранены в опциях модуля, каждый в
     * своей группе, для обращения к ним надо использовать класс Helpers\Options и методы по шаблону
     *     get<"Название группы опций">(<название конкретного элемента, необязательный параметр>)
     *
     * Если объявить в классе константу SAVE_OPTIONS_WHEN_DELETED со значением true, то все данные, добавленные
     * при установке модуля, при удалении модуля будут сохранены в системе и снова будут использоваться без
     * переустановки при новой установке модуля. Эта возможность автоматически унаследуется и для дочених модулей,
     * но эту константу можно переобъявив в дочерних модулях, изменив для тех модулей необходимость сохранения данных
     * при удалении модуля
     * 
     * ВНИМАНИЕ. Не стоит в каждой группе данных объявлять настройки для каждого нового элемента группы
     * пусть и со своим уникальным именем константы, но с тем же самым значением константы, иначе после
     * установки модуль просто потеряет все кроме последнего установленные данные, что может привести к багу, а так же
     * после удаления модуля в системе останется мусор, т.е. информация, которую модуль установил, но не
     * смог удалить при своем удалении, так как ничего о ней не знал. Опции для данных в той же самой группе
     * должны храниться под "ключом", который явялется именем константы, значение которой уникально для
     * этой группы данных, то же "значение" под любым именем константы в той же самой группе данных
     * можно будет использовать в следующем модуле
     */
    const OPTIONS = [
        /**
         * Пользовательские поля для пользователей. Значения хранят настройки пользовательского поля.
         * ENTITY_ID и FIELD_NAME не указывать. Значение FIELD_NAME должно быть объявлено в include.php как
         * константа с именем, указанным в UserFields как "ключ".
         * В настройках можно указать LANG_CODE, который используется для указания кода языковой опции, где
         * хранится название пользовательского поля.
         * Указывать тип надо не в USER_TYPE_ID, в TYPE, это более сокращено. Остальные настройки такие же,
         * какие надо передавать в Битриксе.
         * Если указан тип vote, то важно, чтобы было указано в ['SETTINGS']['CHANNEL_ID'] навазние "ключа", под которым
         * в настройках для VoteChannels указаны настройки группы опросов.
         * Если указан тип iblock_element, то важно, чтобы было указано в ['SETTINGS']['IBLOCK_ID'] навазние "ключа", под которым
         * в настройках для IBlocks указаны настройки инфоблока.
         * Если указан тип enumeration, то в параметрах можно указать параметр LIST_VALUES как массив, каждый
         * элемент которого представляет отдельное значения для списка, для каждого значения списка обязательно
         * должен быть указан LANG_CODE с именем языковой константы, в которой хранится название значения,
         * указаные элементы списка с одинаковыми значения будут созданы один раз. При наличии LANG_CODE у
         * пользовательского поля параметр LANG_CODE для значений списка надо писать в ином виде, так как
         * значение параметра у пользовательского поля будет использоваться как префикс, т.е. языковые константы
         * для значений списка должны иметь названия, начинающиеся с названия языковой константы у их
         * пользовательского поля, если такое имеется у него, и знаком подчеркивания после.
         * После создания пользовательского поля его ID будет записан в опциях модуля в группе, в которой он был
         * объявлен, т.е. для UserFields ID будет записан в опциях модуля в группе UserFields,
         * в массиве под "ключом" ID.
         * ID значений пользовательского поля типа "Список" так же будут сохранены в опциях модуля в данных своего
         * пользовательского поля.
         * Значения для SHOW_FILTER:
         *      N - не показывать
         *      I - точное совпадение
         *      E - маска
         *      S - подстрока
         */
        'UserFields' => [],
    ];

    /**
     * Описание обработчиков событий. Под "ключом" указывается название другого модуля, события которого
     * нужно обрабатывать, в "значении" указывается массив с навазниями классов этого модуля, которые
     * будут отвечать за обработку событий. Сам класс находится в папке lib модуля.
     * У названия класса не надо указывать пространство имен, кроме той части, что идет после
     * названий партнера и модуля. Для обработки конкретных событий эти классы должны иметь
     * статические и открытые методы с такими же названиями, что и события
     * Для создания обработчиков к конкретному highloadblock-у необходимо писать их названия
     * как <символьное имя highloadblock><название события>, например, для события OnAdd
     * у highloadblock с символьным именем Test такой обработчик должен называться TestOnAdd
     */
    const EVENTS_HANDLES = [];

    /**
     * Для файлов в папке www, что лежит в папке install модуля. Указываются файлы и папки, на которые надо создать
     * символьные ссылки в корневой папке сайта, игнорируются указания на все в папке local и подпапках папки bitrix
     * как activities, admin, components, modules и templates. Тут так же можно указывать файлы и папки, которых нет
     * в www модуля, при создании символьной ссылки в корневой папке сайта не будет ошибки, если на месте будет уже
     * существовать такие же файл или папка. Уже существующие файлы или папки будут просто переименованы и запомнены,
     * благодаря чему при удалении модуля снова вернутся на свое место. Из-за того, что тут можно указывать даже не
     * существующие в папке www модуля данные, можно добиться переименования существующих в корне сайта файлов или
     * папок без необходимости создавать для этого пустой файл в папке www модуля
     *
     * Обычные файлы можно объединять в группы (категории), указывая названия групп как "ключ", а пути к файлам как
     * массив в "значении", если файлов несколько, или просто имя файла в "значении". Для таких файлов согласно их
     * категориям будут вызваны свои методы, т.е. тот метод, который первый подойдет согласно этим категориям.
     * Существуют следующие категории:
     *     - add. Для такого же файла относительно корня сайта делает копию в файле
     *         local/.saved/<идентификатор модуля>/<указанный путь к файлу и имя самого файла>
     *       а затем сначала подключает эту копию в заменном файле, а потом и такой же файл из модуля
     *     - replace. Делается то же самое, что и при add, только скопированный файл не подключается
     *       в новом файле
     */
    const WWW_FILES = [];

    /**
     * Запоминает и возвращает настоящий путь к текущему классу
     * 
     * @return string
     */
    protected function getModuleClassPath()
    {
        if ($this->moduleClassPath) return $this->moduleClassPath;

        $this->moduleClass = new \ReflectionClass(get_called_class());
        // не надо заменять на __DIR__, так как могут быть дополнительные модули $this->moduleClassPath
        $this->moduleClassPath = rtrim(preg_replace('/[^\/\\\\]+$/', '', $this->moduleClass->getFileName()), '\//');
        return $this->moduleClassPath;
    }

    /**
     * Запоминает и возвращает код модуля, к которому относится текущий класс
     * 
     * @return string
     */
    protected function getModuleId()
    {
        if ($this->MODULE_ID) return $this->MODULE_ID;

        return $this->MODULE_ID = basename(dirname($this->getModuleClassPath()));
    }

    /**
     * Запоминает и возвращает название именного пространства для классов из
     * библиотеки модуля
     * 
     * @return string
     */
    protected function getNameSpaceValue()
    {
        if ($this->nameSpaceValue) return $this->nameSpaceValue;

        return $this->nameSpaceValue = preg_replace('/\.+/', '\\\\', ucwords($this->getModuleId(), '.'));
    }

    /**
     * Запоминает и возвращает название класса, используемого для установки и сохранения
     * опций текущего модуля
     * 
     * @return string
     */
    protected function getOptionsClass()
    {
        if ($this->optionClass) return $this->optionClass;

        return $this->optionClass = $this->getNameSpaceValue() . '\\Helpers\\Options';
    }

    /**
     * Запоминает и возвращает кода сайта по-умолчанию
     * 
     * @return string
     */
    protected static function getDefaultSiteID()
    {
        if (self::$defaultSiteID) return self::$defaultSiteID;

        return self::$defaultSiteID = CSite::GetDefSite();
    }

    /**
     * По переданному имени возвращает значение константы текущего класса с учетом того, что эта константа
     * точно была (пере)объявлена в этом классе модуля. Конечно, получить значение константы класса можно
     * и через <название класса>::<название константы>, но такая запись не учитывает для дочерних классов,
     * что константа не была переобъявлена, тогда она может хранить ненужные старые данные, из-за чего требуется
     * ее переобъявлять, иначе дочерние модули начнуть устанавливать то же, что и родительские, а переобъявление
     * требует дополнительного внимания к каждой константе и дополнительных строк в коде дочерних модулей
     * 
     * @param string $constName - название константы
     * @return array
     */
    protected function getModuleConstantValue(string $constName)
    {
        $constant = $this->moduleClass->getReflectionConstant($constName);
        if (
            ($constant === false)
            || ($constant->getDeclaringClass()->getName() != get_called_class())
        ) return [];

        return $constant->getValue();
    }

    function __construct()
    {
        $this->getOptionsClass();
        Loc::loadMessages($this->getModuleClassPath() . '/' . basename(__FILE__));

        $this->subLocTitle = strtoupper(get_called_class()) . '_';
        $this->MODULE_NAME = Loc::getMessage($this->subLocTitle . 'MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage($this->subLocTitle . 'MODULE_DESCRIPTION');

        include  $this->moduleClassPath . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    /**
     * Создание значений для пользовательского поля типа "Список"
     * 
     * @param int $fieldId - ID пользовательского поля
     * @param array $fieldValues - значения пользовательского поля
     * @param string $langCode - префикс к языковым константам для названий значений поля
     * @return array
     */
    protected function addListValues(int $fieldId, array $fieldValues, string $langCode)
    {
        $units = [];
        $values = [];
        $newN = 0;
        foreach ($fieldValues as $unit) {
            $value = Loc::getMessage(($langCode ? $langCode . '_' : '') . $unit['LANG_CODE']);
            if (empty($value)) continue;

            if (!in_array($value, $values)) {
                $units['n' . $newN] = ['VALUE' => $value]
                                    + array_filter($unit, function($key) {
                                                return !in_array(strtoupper($key), ['LANG_CODE', 'ID']);
                                            }, ARRAY_FILTER_USE_KEY);
                ++$newN;
            }

            $values[$unit['LANG_CODE']] = $value;
        }

        if (empty($units)) return [];

        (new CUserFieldEnum())->SetEnumValues($fieldId, $units);
        $ids = [];
        $savedUnits = CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $fieldId]);
        while ($saved = $savedUnits->Fetch()) {
            foreach ($values as $key => $value) {
                if ($value != $saved['VALUE']) continue;

                $ids['VALUES'][] = intval($saved['ID']);
                $ids[$key . '_ID'] = intval($saved['ID']);
            }
        }
        return $ids;
    }

    /**
     * Добавляет новое пользовательское поле, прежде устанавливая дополнительные свойства поля,
     * которые не были указаны в переданных данных.
     * 
     * @param string $entityId - код поля
     * @param string $constName - название константы
     * @param array $fieldData - данные нового поля
     * @return array
     * @throws
     */
    public function addUserField(string $entityId, string $constName, array $fieldData) 
    {
        global $APPLICATION;

        $fields = [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => constant($constName),
                'USER_TYPE_ID' => $fieldData['TYPE']
            ] + $fieldData + [
                'XML_ID' => '',
                'SORT' => 500,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'N',
                'EDIT_IN_LIST' => 'N',
                'IS_SEARCHABLE' => 'N',
                'SETTINGS' => []
            ];
        if (!preg_match('/^uf_/i', $fields['FIELD_NAME']))
            throw new Exception(Loc::getMessage('ERROR_BAD_USER_FIELD_NAME', ['NAME' => $constName]));

        if (!empty($fields['LANG_CODE'])) {
            $langValue = Loc::getMessage($fields['LANG_CODE']);
            unset($fields['LANG_CODE']);
            foreach ([
                        'EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL',
                        'ERROR_MESSAGE', 'HELP_MESSAGE'
                    ] as $labelUnit) {

                $fields[$labelUnit] = ['ru' => $langValue, 'en' => ''];
            }
        }
        if ($fieldData['TYPE'] == 'vote') {
            if (
                empty($fields['SETTINGS']['CHANNEL_ID'])
                || !defined($fields['SETTINGS']['CHANNEL_ID'])
                || empty($channelCode = constant($fields['SETTINGS']['CHANNEL_ID']))
                || empty($channelId = $this->optionClass::getVoteChannels($channelCode))
            ) throw new Exception(Loc::getMessage('ERROR_BAD_USER_FIELD_VOTE_CHANNEL', ['NAME' => $constName]));
            $fields['SETTINGS']['CHANNEL_ID'] = $channelId;

        } elseif ($fieldData['TYPE'] == 'iblock_element') {
            if (
                empty($fields['SETTINGS']['IBLOCK_ID'])
                || !defined($fields['SETTINGS']['IBLOCK_ID'])
                || empty($iblockICode= constant($fields['SETTINGS']['IBLOCK_ID']))
                || empty($iblockId = $this->optionClass::getIBlocks($iblockICode))
            ) throw new Exception(Loc::getMessage('ERROR_BAD_USER_FIELD_IBLOCK', ['NAME' => $constName]));
            $fields['SETTINGS']['IBLOCK_ID'] = $iblockId;

        } elseif (!in_array($fieldData['TYPE'], ['crm'])) {
            $fields['SETTINGS'] += [
                'DEFAULT_VALUE' => '',
                'SIZE' => '20',
                'ROWS' => '1',
                'MIN_LENGTH' => '0',
                'MAX_LENGTH' => '0',
                'REGEXP' => ''
            ];
        }

        $fieldEntity = new CUserTypeEntity();
        $fieldId = $fieldEntity->Add($fields);
        if (!$fieldId)
            throw new Exception(
                Loc::getMessage('ERROR_USER_FIELD_CREATING', ['NAME' => $constName]) . PHP_EOL .
                $APPLICATION->GetException()->GetString()
            );
        
        $result = ['ID' => intval($fieldId)];
        if (($fieldData['TYPE'] == 'enumeration') && !empty($fieldData['LIST_VALUES']))
            $result += $this->addListValues($result['ID'], $fieldData['LIST_VALUES'], $fieldData['LANG_CODE'] ?: '');

        return $result;
    }

    /**
     * Создание пользовательского поля для пользователей
     * 
     * @param string $constName - название константы
     * @param array $optionValue - значение опции
     * @return mixed
     */
    public function initUserFieldsOptions(string $constName, array $optionValue) 
    {
        return $this->addUserField('USER', $constName, $optionValue);
    }

    /**
     * Создание всех опций
     *
     * @return  void
     */
    public function initOptions() 
    {
        $savedData = [];
        $saveDataWhenDeleted = constant(get_called_class() . '::SAVE_OPTIONS_WHEN_DELETED') === true;
        if ($saveDataWhenDeleted)
            $savedData = json_decode(Option::get('main', 'saved.' . $this->MODULE_ID, false, \CSite::GetDefSite()), true)
                       ?: [];

        foreach ($this->getModuleConstantValue('OPTIONS') as $methodNameBody => $optionList) {
            $methodName = 'init' . $methodNameBody . 'Options';
            if (!method_exists($this, $methodName)) continue;

            foreach ($optionList as $constName => $optionValue) {
                if (!defined($constName)) return;

                $constValue = constant($constName);
                $value = empty($savedData[$methodNameBody][$constValue])
                       ? $this->$methodName($constName, $optionValue)
                       : $savedData[$methodNameBody][$constValue];
                if (!isset($value)) continue;
                $optionMethod = 'add' . $methodNameBody;
                $this->optionClass::$optionMethod($constValue, $value);
            }
        }
    }

    /**
     * Регистрация обработчиков событий
     * 
     * @return void
     */
    public function initEventHandles()
    {
        $eventManager = EventManager::getInstance();
        $eventsHandles = [];
        foreach ($this->getModuleConstantValue('EVENTS_HANDLES') as $moduleName => $classNames) {
            foreach ($classNames as $className) {
                $classNameValue = $this->nameSpaceValue . '\\' . $className;
                if (!class_exists($classNameValue)) continue;

                $registerModuleName = $moduleName == 'highloadblock' ? '' : $moduleName;
                $reflectionClass = new ReflectionClass($classNameValue);
                foreach ($reflectionClass->getMethods() as $method) {
                    if (!$method->isPublic() || !$method->isStatic()) continue;

                    $eventName = $method->getName();
                    $eventsHandles[$moduleName][$eventName][] = $className;
                    $eventManager->registerEventHandler(
                        $registerModuleName, $eventName, $this->MODULE_ID, $classNameValue, $eventName
                    );
                }
            }
        }
        $this->optionClass::setEventsHandles($eventsHandles);
    }

    /**
     * Возвращает обработынный список констант из $definedContants, в результате
     * будет <ключ> - заключенное в [] и приведенное к нижнему регистру имя ключа
     * 
     * @param array $definedContants - массив констант, где
     * <ключ> - название константы, а <значение> - значение константы
     * 
     * @return array
     */
    protected static function getPartTemplateByData(array $definedContants)
    {
        $resultDefinedContants = [];
        foreach ($definedContants as $code => $value) {
            if (!preg_match('/^\w+$/', $code)) continue;

            $resultDefinedContants['[' . strtolower($code) . ']'] = $value;
        }
        return $resultDefinedContants;
    }

    /**
     * Функция-генератор, по списку переданных файлов делает предобработку названия каждого файла
     * и возвращает  обработанное название файла, рарзделенный на части путь к файлу и его длину.
     * Благодаря второму параметру exclude, в котором указываются пути для исключений, можно отбросить
     * все переданные в списке файлы, путь к которым введен в эти пути для исключения
     * 
     * @param array $files - список файлов
     * @param array $exclude - пути для исключения файлов
     * @param array $definedContants - массив с константами, которые надо заменить в именах списка файлов.
     * Сами константы в файлах должны быть указаны как
     * [<имя константы только из букв латинского алфавита, подчеркивания и цифр>]
     * По-умолчанию, обрабатывается и константа [module_id] с заменой на идентификатор модуля
     */
    protected function getFileParts(array $files, array $exclude = [], array $definedContants = [])
    {
        $resultDefinedContants = ['[module_id]' => basename(dirname($this->moduleClassPath))]
                               + self::getPartTemplateByData($definedContants);
        $excludeFiles = array_map(
            function($eFile) use($resultDefinedContants) {
                $parts = preg_split('/[\\\\\/]+/', strtr(strtolower(trim($eFile , '\\/')), $resultDefinedContants));
                return ['count' => count($parts), 'parts' => $parts, 'path' => implode('/', $parts)];
            }, $exclude
        );
        $categories = [];
        $fileList = [];
        foreach ($files as $fileCategory => $categoryFiles) {
            $categoryData = is_array($categoryFiles) ? $categoryFiles : [$categoryFiles];
            $fileList = array_merge($fileList, $categoryData);
            if (is_string($fileCategory)) {
                $fileCategoryName = strtolower($fileCategory);
                $categories[$fileCategoryName] = array_merge($categories[$fileCategoryName] ?? [], $categoryData);
            }
        }
        $categoryCodes = array_keys($categories);

        foreach (array_unique($fileList) as $moduleFile) {
            $fileTarget =  strtolower(preg_replace('/[\\\\\/]+/', '/', trim($moduleFile , '\\/')));
            $resultFileTarget = strtr($fileTarget, $resultDefinedContants);
            $fileParts = explode('/', $resultFileTarget);
            $filePartsSize = count($fileParts);
            if (
                count(array_filter(
                    $excludeFiles,
                    function($ePath) use($resultFileTarget, $fileParts, $filePartsSize) {
                        if ($ePath['count'] <= $filePartsSize) {
                            return implode('/', array_slice($fileParts, 0, $ePath['count'])) == $ePath['path'];

                        } else {
                            return $resultFileTarget == implode('/', array_slice($ePath['parts'], 0, $filePartsSize));
                        }
                    }
                ))
            ) continue;
            yield [
                'target' => preg_replace('/\/+/', '/', preg_replace('/\[\w+\]/', '', $fileTarget)),
                'parts' => $fileParts,
                'count' => $filePartsSize,
                'categories' => array_filter(
                                    $categoryCodes,
                                    function($category) use($categories, $moduleFile) {
                                        return in_array($moduleFile, $categories[$category]);
                                    }
                                )
            ];
        }
    }

    /**
     * Для файла, что находится относительно корня сайта, но не в папке local, у которого
     * в настройках модуля указана категория add или replace, делает копирование файла
     * в файл
     *     local/.saved/<код модуля>/<путь к файлу относительно корня сайта и имя самого файла>
     * Заменяет оригинальный файл, указывая в нем подключение скопированного, если это категория
     * add, и добавляет подключение такого же файла из модуля
     * Возвращает путь к скопированному файлу
     * 
     * @param string $filePath - путь к файлу
     * @param string $fileName - имя файла
     * @param array $moduleFile - параметры файла, полученные ранее от метода getFileParts
     * @return null|string
     */
    protected function processFileAdditionCategory(string $filePath, string $fileName, array $moduleFile)
    {
        $isAdd = in_array('add', $moduleFile['categories']);
        if (!$isAdd && !in_array('replace', $moduleFile['categories'])) return;

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath . '/' . $fileName;
        if (!file_exists($fullPath) || is_dir($fullPath)) return;

        $savingFile = '/local/.saved/' . $this->MODULE_ID . $filePath;
        $fullOldFilePath = $_SERVER['DOCUMENT_ROOT'] . $savingFile;
        if (!is_dir($fullOldFilePath)) mkdir($fullOldFilePath, 0755, true);

        $savingFile .= '/' . $fileName;
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . $savingFile, file_get_contents($fullPath));

        $fullTargerPath = $this->moduleClassPath . '/www/' . $moduleFile['target'];
        if (file_exists($fullTargerPath) && !is_dir($fullTargerPath)) {
            file_put_contents($fullPath, '<?' . PHP_EOL);
            if ($isAdd)
                file_put_contents($fullPath, sprintf('require_once $_SERVER["DOCUMENT_ROOT"] . "%s";', $savingFile) . PHP_EOL, FILE_APPEND);

            file_put_contents($fullPath, sprintf('require_once "%s";', $fullTargerPath), FILE_APPEND);
        }
        return $savingFile;
    }

    /**
     * Для файла, что находится относительно корня сайта, но не в папке local, если для
     * файла указаны какие-то категории, то делает их обработку. В случае, если метод ничего
     * не вернул, значит, для указанных категорий у файла нельзя было найти подходящий метод
     * 
     * @param string $filePath - путь к файлу
     * @param string $fileName - имя файла
     * @param array $moduleFile - параметры файла, полученные ранее от метода getFileParts
     * @return mixed
     */
    protected function processFileCategories(string $filePath, string $fileName, array $moduleFile)
    {
        if (empty($moduleFile['categories'])) return;

        foreach (['processFileAdditionCategory'] as $methodname) {
            $result = $this->$methodname($filePath, $fileName, $moduleFile);
            if ($result) return $result;
        }
    }

    /**
     * Создание символьных ссылок в корне сайта, исключая папки bitrix и local,
     * оригинальные файлы сохраняются, при удалении модуля восстанавливаются
     * 
     * @return void
     */
    public function initWWWFiles()
    {
        $excludeFiles = [
            'bitrix/activities', 'bitrix/admin', 'bitrix/components',
            'bitrix/modules', 'bitrix/templates', 'local'
        ];
        $fromPath = $this->moduleClassPath . '/';
        foreach ($this->getFileParts($this->getModuleConstantValue('WWW_FILES'), $excludeFiles, $this->definedContants) as $moduleFile) {
            $lastPartNum = $moduleFile['count'] - 1;
            $result = '';
            foreach ($moduleFile['parts'] as $pathNum => $subPath) {
                $newResult = $result . '/' . $subPath;
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $newResult;
                if ($lastPartNum == $pathNum) {
                    $savingFile = $this->processFileCategories($result, $subPath, $moduleFile);
                    $optionData = ['result' => $newResult, 'old' => &$savingFile];

                    if ($savingFile) {
                        $optionData['categories'] = $moduleFile['categories'];

                    } else {
                        if (file_exists($fullPath)) {
                            $savingFile = $newResult . '.' . date('YmdHis');
                            rename($fullPath, $_SERVER['DOCUMENT_ROOT'] . $savingFile);
                        }
                        $fullTargerPath = $fromPath . 'www/' . $moduleFile['target'];
                        if (file_exists($fullTargerPath)) symlink($fullTargerPath, $fullPath);
                    }
                    
                    /**
                     * Значения $moduleFile['target'] и параметра result у массива могут совпадать,
                     * это не значит, что надо убирать result, так как в result учитываются подстановки
                     * вроде [module_id]
                     */
                    $this->optionClass::addWWWFiles($moduleFile['target'], $optionData);

                } elseif (!file_exists($fullPath)) {
                    mkdir($fullPath);

                } elseif (!is_dir($fullPath) || is_link($fullPath)) {
                    throw new Exception(Loc::getMessage('ERROR_MAIN_LINK_CREATING', ['LINK' => $moduleFile['target']]));
                }
                $result = $newResult;
            }
        }
    }

    /**
     * Подключает модуль и сохраняет созданные им константы
     * 
     * @return void
     */
    protected function initDefinedContants()
    {
        /**
         * array_keys нужен, так как в array_filter функция isset дает
         * лишнии результаты
         */
        $this->definedContants = array_keys(get_defined_constants());

        Loader::IncludeModule($this->MODULE_ID);
        $this->definedContants = array_filter(
            get_defined_constants(),
            function($key) {
                return !in_array($key, $this->definedContants);
            }, ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Выполняется основные операции по установке модуля
     *
     * @return void
     */
    protected function runInstallMethods()
    {
        $this->initOptions();
        $this->initEventHandles();
        $this->initWWWFiles();
    }

    /**
     * Устанавливает модуль, но сначала проверяет не является ли он
     * дочерним, а, если это так, то при условии, что родительские модули
     * не установлены, сначала устанавливает их
     * 
     * @return void
     */
    protected function initFullInstallation()
    {
        set_time_limit(0);
        $parentClassName = get_parent_class(get_called_class());
        if (($parentClassName != 'CModule') && !(new $parentClassName())->IsInstalled())
            (new $parentClassName())->DoInstall(false);

        RegisterModule($this->MODULE_ID);
    }

    /**
     * Проверяет у модуля наличие класса Employment в своем подпространстве имен EventHandles,
     * а так же наличие у него метода, название которого передано в параметре $methodName.
     * В случае успеха вызывает метод у своего Employment
     * 
     * @param string $methodName - название метода, который должен выступать как обработчик события
     * @return void
     */
    protected function checkAndRunModuleEvent(string $methodName)
    {
        $moduleEmployment = $this->nameSpaceValue . '\\EventHandles\\Employment';
        if (!class_exists($moduleEmployment) || !method_exists($moduleEmployment, $methodName))
            return;

        $moduleEmployment::$methodName();
    }

    /**
     * Функция, вызываемая при установке модуля
     *
     * @param bool $stopAfterInstall - указывает модулю остановить после
     * своей установки весь процесс установки
     * 
     * @return void
     */
    public function DoInstall(bool $stopAfterInstall = true) 
    {
        global $APPLICATION;
        $this->initFullInstallation();
        $this->initDefinedContants();

        try {
            if (!class_exists($this->optionClass))
                throw new Exception(Loc::getMessage('ERROR_NO_OPTION_CLASS', ['#CLASS#' => $this->optionClass]));
            Employment::setBussy();
            $this->checkAndRunModuleEvent('onBeforeModuleInstallationMethods');
            $this->runInstallMethods();
            $this->optionClass::setConstants(array_keys($this->definedContants));
            $this->optionClass::setInstallShortData([
                'INSTALL_DATE' => date('Y-m-d H:i:s'),
                'VERSION' => $this->MODULE_VERSION,
                'VERSION_DATE' => $this->MODULE_VERSION_DATE,
            ]);
            $this->optionClass::save();
            $this->checkAndRunModuleEvent('onAfterModuleInstallationMethods');
            Employment::setFree();
            if ($stopAfterInstall)
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage($this->subLocTitle . 'MODULE_WAS_INSTALLED'),
                    $this->moduleClassPath . '/step1.php'
                );

        } catch (Exception $error) {
            $this->removeAll();
            $_SESSION['MODULE_ERROR'] = $error->getMessage();
            Employment::setFree();
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage($this->subLocTitle . 'MODULE_NOT_INSTALLED'),
                $this->moduleClassPath . '/error.php'
            );
        }
    }

    /**
     * Удаление пользовательского поля
     * 
     * @param string $entityId - код поля
     * @param string $constName - название константы с символьным кодом поля
     * @return void
     */
    public function removeUserFields(string $entityId, string $constName) 
    {
        $entityField = new CUserTypeEntity();
        $userFields = CUserTypeEntity::GetList(
            [], ['ENTITY_ID' => $entityId, 'FIELD_NAME' =>  constant($constName)]
        );
        while ($field = $userFields->Fetch()) {
            $entityField->Delete($field['ID']);
        }
    }

    /**
     * Удаление пользовательского поля для пользователей
     * 
     * @param string $constName - название константы
     * @return void
     */
    public function removeUserFieldsOptions(string $constName) 
    {
        $this->removeUserFields('USER', $constName);
    }

    /**
     * Удаление всех созданных модулем данных согласно прописанным настройкам в
     * OPTIONS
     * 
     * @return void
     */
    public function removeOptions() 
    {
        $saveDataWhenDeleted = constant(get_called_class() . '::SAVE_OPTIONS_WHEN_DELETED') === true;
        $savedData = [];
        foreach (array_reverse($this->getModuleConstantValue('OPTIONS')) as $methodNameBody => $optionList) {
            $methodName = $saveDataWhenDeleted && !in_array(strtolower($methodNameBody), ['agents'])
                        ? 'get' . $methodNameBody
                        : 'remove' . $methodNameBody . 'Options';

            foreach ($optionList as $constName => $optionValue) {
                if (!defined($constName)) continue;

                if ($saveDataWhenDeleted) {
                    $constValue = constant($constName);
                    $data = $this->optionClass::$methodName($constValue);
                    if (empty($data)) continue;
                    $savedData[$methodNameBody][$constValue] = $data;

                } elseif (method_exists($this, $methodName)) {
                    $this->$methodName($constName, $optionValue);
                }
            }
        }
        if (!empty($savedData))
            Option::set('main', 'saved.' . $this->MODULE_ID, json_encode($savedData));
    }

    /**
     * Удаление всех зарегистрированных модулем обработчиков событий
     * 
     * @return void
     */
    public function removeEventHandles()
    {
        $eventManager = EventManager::getInstance();
        foreach ($this->optionClass::getEventsHandles() as $moduleName => $eventList) {
            foreach (array_keys($eventList) as $eventName) {
                foreach (
                    $eventManager->findEventHandlers(
                        strtoupper($moduleName),
                        strtoupper($eventName),
                        ['TO_MODULE_ID' => $this->MODULE_ID]
                    ) as $handle) {

                        $eventManager->unRegisterEventHandler(
                            $moduleName, $eventName, $this->MODULE_ID, $handle['TO_CLASS'], $handle['TO_METHOD']
                        );
                }
            }
        }
    }

    /**
     * Удаляет файла, а затем папку, в которой он лежит, если в ней больше ничего нет,
     * после чего по такому же принципу удаляет все родительские папки до папки local
     * 
     * @param string $fileTarget - относительный путь к файлу
     * @param string $where - начальный путь к файлу
     * @return void
     */
    protected static function deleteEmptyPath(string $fileTarget, string $where)
    {
        $result = $where . $fileTarget;
        if (is_link($result) || !is_dir($result)) {
            @unlink($result) || rmdir($result);

        } else {
            rmdir($result);
        }

        $toDelete = true;
        while ($toDelete && ($fileTarget = preg_replace('/\/?[^\/]+$/', '', $fileTarget))) {
            $result = $where . $fileTarget;
            $dUnit = opendir($result);
            while ($fUnit = readdir($dUnit)) {
                if (($fUnit == '.') || ($fUnit == '..')) continue;

                $toDelete = false;
                break;
            }
            closedir($dUnit);
            if ($toDelete) rmdir($result);
        }
    }

    /**
     * Удаляет файлы, которые были созданы модулем как символьная ссылка на такой же файл в модуле.
     * Вызывает callback-функцию, если она была передана, с обработанным названием файла
     * 
     * @param array $files - список файлов из папки модуля с установочным файлом index.php
     * @param string $from - относительный путь к подпапке из папки модуля с установочным файлом index.php, где
     * должны лежать указанные в $files файлы
     * @param string $where - путь относительно корня сайта, где будут проверяться и удаляться файлы
     * @param array $definedContants - массив с константами, которые надо заменить в именах списка файлов.
     * Сами константы в файлах должны быть указаны как
     * [<имя константы только из букв латинского алфавита, подчеркивания и цифр>]
     * По-умолчанию, обрабатывается и константа [module_id] с заменой на идентификатор модуля
     * 
     * @param $callback - необязательный обработчик для каждого файла модуля. Передаются, если будет указан,
     * два параметра - имя файла из модуля и параметры установленного файла в виде массива, где
     *     <result> - имя файла, который был установлен в системе
     *     <old> - имя файла, которые было ранее до установленного, а теперь переименовано
     * @return void
     */
    protected function removeFiles(array $files, string $from, string $where, array $definedContants, $callback = null)
    {
        $resultDefinedContants = ['[module_id]' => basename(dirname($this->moduleClassPath))]
                               + self::getPartTemplateByData($definedContants);
        $fromPath = $this->moduleClassPath  . (trim($from) ? '/' : '') . trim($from) . '/';
        $wherePath = $_SERVER['DOCUMENT_ROOT'] . (trim($where) ? '/' : '') . trim($where) . '/';
        foreach ($files as $moduleFile => $moduleResult) {
            if (file_exists($fromPath . $moduleFile) && is_link($wherePath . $moduleResult['result']))
                self::deleteEmptyPath($moduleResult['result'], $wherePath);

            if (is_callable($callback)) $callback($moduleFile, $moduleResult);
        }
    }

    /**
     * Для файлов, которые были обработаны с указанными в параметрах модуля категориями
     * как add или replace, будет проведена работа. При установке модуля были заменены
     * файлы относительно корня сайта, исключая папку local, путем копирования их содержимого
     * в файл
     *     local/.saved/<код модуля>/<путь к файлу относительно корня сайта и имя самого файла>
     * а затем созданием нового файла на месте старого с записью в нем о подключении такого
     * же файла из модуля и, возможно, подключения скопированного файла. При удалении модуля
     * метод вернет данные скопированных файлов обратно на место.
     * Метод возвращает true, если файл подходящей категории (add или replace)
     * 
     * @param $moduleResult - параметры файла из модуля, полученные от метода removeFiles
     * @return boolean
     */
    protected function checkDeletingForFileAdditionCategory($moduleResult)
    {
        if (!count(array_intersect(['add', 'replace'], $moduleResult['categories'])))
            return false;

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . $moduleResult['result'],
            file_get_contents($_SERVER['DOCUMENT_ROOT'] . $moduleResult['old'])
        );
        self::deleteEmptyPath('.saved/' . $this->MODULE_ID . $moduleResult['result'], $_SERVER['DOCUMENT_ROOT'] . '/local/');
        return true;
    }

    /**
     * Если для файла указаны какие-то категории в параметрах модуля, то для этого файла
     * будет обработка согласно этим категориям. Метод возвращает true, если у файла в
     * параметрах не указаны категории, или не нашлось метода в классе, которой обработал
     * бы данные из $moduleResult согласно указанным там категориям, или ни один из методов
     * для работы с категорияма файла не вернул true
     * 
     * @param $moduleResult - параметры файла из модуля, полученные от метода removeFiles
     * @return boolean
     */
    protected function processDeletingByFileCategories($moduleResult)
    {
        if (empty($moduleResult['categories'])) return true;

        foreach (['checkDeletingForFileAdditionCategory'] as $methodName) {
            if ($this->$methodName($moduleResult)) return false;
        }

        return true;
    }

    /**
     * Удаляет созданные модулем файлы в корневом каталоге портала, возвращает старые файлы
     * 
     * @return void
     */
    public function removeWWWFiles()
    {
        $this->removeFiles($this->optionClass::getWWWFiles() ?? [], 'www', '', $this->definedContants ?? [], function($moduleFile, $moduleResult) {
            if (empty($moduleResult['old'])) return;

            $oldFileName = $_SERVER['DOCUMENT_ROOT'] . $moduleResult['old'];
            if (!file_exists($oldFileName) || !$this->processDeletingByFileCategories($moduleResult))
                return;

            $resultFileName = $_SERVER['DOCUMENT_ROOT'] . $moduleResult['result'];
            if (file_exists($resultFileName)) return;

            rename($oldFileName, $resultFileName);
        });
    }

    /**
     * Выполняется основные операции по удалению модуля
     *
     * @return void
     */
    protected function runRemoveMethods()
    {
        $this->removeWWWFiles();
        $this->removeEventHandles();
        $this->removeOptions();
    }

    /**
     * Основной метод, очищающий систему от данных, созданных им
     * при установке
     * 
     * @return void
     */
    public function removeAll()
    {
        if (class_exists($this->optionClass)) $this->runRemoveMethods();
        UnRegisterModule($this->MODULE_ID); // удаляем модуль
    }

    /**
     * Проверяет, есть ли у модуля дочернии модули среди установленных.
     * Если такие есть, то сначала удаляются они
     * 
     * @return void
     */
    protected function killAllChildren()
    {
        $className = get_called_class();
        $modules = self::GetList();
        while ($module = $modules->Fetch()) {
            $childClass = str_replace('.', '_', $module['ID']);
            if (!class_exists($childClass) || (get_parent_class($childClass) != $className))
                continue;

            (new $childClass())->DoUninstall(false);
        }
    }

    /**
     * Функция, вызываемая при удалении модуля
     *
     * @param bool $stopAfterDeath - указывает модулю остановить после
     * своего удаления весь процесс удаления
     * 
     * @return void
     */
    public function DoUninstall(bool $stopAfterDeath = true) 
    {
        global $APPLICATION;
        $this->killAllChildren();
        Loader::IncludeModule($this->MODULE_ID);
        Employment::setBussy();
        $this->checkAndRunModuleEvent('onBeforeModuleRemovingMethods');
        $this->definedContants = array_fill_keys($this->optionClass::getConstants() ?? [], '');
        array_walk($this->definedContants, function(&$value, $key) { $value = constant($key); });
        $this->removeAll();
        Option::delete($this->MODULE_ID);
        $this->checkAndRunModuleEvent('onAfterModuleRemovingMethods');
        Employment::setFree();
        if ($stopAfterDeath)
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage($this->subLocTitle . 'MODULE_WAS_DELETED'),
                $this->moduleClassPath . '/unstep1.php'
            );
    }
}