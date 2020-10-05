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
        'UserFields' => [
            // Пользовательское поле для пользователей, чтобы хранить индивидуальный язык сайта
            'INFS_USER_LANG_FIELD' => [
                'LANG_CODE' => 'USER_LANG_TITLE',
                'TYPE' => 'enumeration',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'LIST_VALUES' => [
                    [
                        'LANG_CODE' => 'RU',
                        'XML_ID' => 'ru',
                        'DEF' => 'Y',
                    ],
                    [
                        'LANG_CODE' => 'EN',
                        'XML_ID' => 'en',
                    ]
                ]
            ]
        ],

        /**
         * Место для указания настроек пользовательских полей для CRM-продуктов. Настройки
         * пользовательских полей аналогичны тому, что описано и для пользовательских полей у
         * пользователей.
         * Самих пользовательких полей для этих сущностей в Битриксе24 не предусмотрено, поэтому
         * вся работа с ними должна вестись через глобальную переменную $USER_FIELD_MANAGER
         */
        'CRMProductFields' => [],

        /**
         * Настройки для создания свойств инфоблоков. В "значении" указываются параметры для создания свойств инфоблоков.
         * Обязательно нужны параметры LANG_CODE с именем языковой константы для названия и IBLOCK_ID с именем константы,
         * которая использоалась в IBlocks как "ключ", под которым хранятся настройки инфоблока, или которая объявлена
         * в include.php с идентификатором уже существующего инфоблока.
         * Если тип свойства список (PROPERTY_TYPE = L), то в параметрах свойств можно указать параметр LIST_VALUES, в
         * значении которого указан массив, где каждый элемент содержит минимум один параметр с ключом LANG_CODE для
         * языковой константы, под которой хранится значение парметра, но название константы указывается не полностью,
         * а лишь ее последняя часть, что должна идти после префикса, который указан как название языковой константы в
         * LANG_CODE у самого свойства
         */
        'IBlockProperties' => [],
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
    const EVENTS_HANDLES = [
        'main' => ['EventHandles\\BufferEventHandle']
    ];

    /**
     * Пути к файлам и папкам, что лежат в папке install модуля,  на которые необходимо создать символьные ссылки
     * относительно папки local. Игнорируются файлы из папки www. Символная ссылка будет созданна на последнюю часть
     * указанного пути, по остальным частям будут созданны папки, если их нет. При удалении модуля сивмольная ссылка
     * удалится, а затем и все папки, в которые она входит, если в них больше ничего нет, и чьи названия указаны тут.
     * Если при установке выяснится, что символьная ссылка на последнюю часть пути уже существует, или на ее месте
     * находится папа, или одна из непоследних частей пути не является папкой, то произойдет ошибка
     * В ссылках можно использовать добавление подпути в виде имени одной из констант модуля, выделенной кваратными
     * скобками, это будет работать при установке файла в систему, может потребоваться, если нужно выделить файлы
     * модуля
     * [infs_..._module_id] - пример, как надо использовать константы (многоточие это какое-то специальное слово модуля),
     * Так же по-умолчанию доступно [module_id], которое заменяется на идентификатор модуля
     */
    const FILE_LINKS = [];

    /**
     * Для файлов в папке www, что лежит в папке install модуля. Указываются файлы и папки, на которые надо создать
     * символьные ссылки в корневой папке сайта, инорируются указания на все в папке local и подпапках папки bitrix
     * как activities, admin, components, modules, templates. Тут так же можно указывать файлы и папки, которых нет
     * в www модуля, при создании символьной ссылки в корневой папке сайта не будет ошибки, если на месте будет уже
     * существовать такие же файл или папка. Уже существующие файлы или папки будут просто переименованы и запомнены,
     * благодаря чему при удалении модуля снова вернутся на свое место. Из-за того, что тут можно указывать даже не
     * существующие в папке www модуля данные, можно добиться переименования существующих в корне сайта файлов или
     * папок без необходимости создавать для этого пустой файл в папке www модуля
     */
    const WWW_FILES = ['bitrix/php_interface/dbconn.php'];

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
    {}

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
     * Выполняется основные операции по удалению модуля
     *
     * @return void
     */
    protected function runRemoveMethods()
    {}

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