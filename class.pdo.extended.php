<?php
/** Расширение для класса PDO
 *
 * @version 2.0
 * @author Insys <intsystem88@gmail.com>
 * @copyright (c) 2013, Insys
 * @link https://github.com/InSys/pdo-extended
 * @license http://opensource.org/licenses/GPL-2.0 The GNU General Public License (GPL-2.0)
 */
class PDOExtended extends PDO {

	/** Только MySQL. Установить все настройки кодировок в UTF-8 (включено по умолчанию).
	 * @example PDO::setAttribute(PDOExtended::ATTR_USE_UTF, true);
	 */
	const ATTR_USE_UTF		 = 1001;

	/** Только MySQL. Включить строгий режим (включен по умолчанию).
	 * @example PDO::setAttribute(PDOExtended::ATTR_STRICT_MODE, true);
	 */
	const ATTR_STRICT_MODE	 = 1002;

	/** Только MySQL. Установить временную зону сервера
	 * @example PDO::setAttribute(PDOExtended::ATTR_TIME_ZONE, '+0:00');
	 * @example PDO::setAttribute(PDOExtended::ATTR_TIME_ZONE, null); //Отключить изменение временной зоны сервера
	 */
	const ATTR_TIME_ZONE	 = 1003;


	private $optionsOverload = array(
		self::ATTR_STRICT_MODE,
		self::ATTR_TIME_ZONE,
		self::ATTR_USE_UTF
	);
	private $optionsValues = array();

	
	public function __construct($dsn, $username = null, $password = null, $driverOptions = array())
	{
		$driverOptions = $this->initOptions($driverOptions);

		parent::__construct($dsn, $username, $password, $driverOptions);

		$this->initClass();

		$this->statisticCount	 = 0;
		$this->statisticTime	 = 0;
	}

	/** Инициализация класса/подключения
	 *
	 */
	protected function initClass()
	{
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		
		if ($driver == 'mysql') {
			if ($this->getAttribute(self::ATTR_USE_UTF)) {
				$this->exec("SET NAMES utf8 COLLATE utf8_general_ci");
				$this->exec("SET CHARACTER SET utf8");
			}

			if ($this->getAttribute(self::ATTR_STRICT_MODE)) {
				$this->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
			}

			if ($this->getAttribute(self::ATTR_TIME_ZONE)) {
				$this->exec("SET time_zone=?" , array($this->getAttribute(self::ATTR_TIME_ZONE)));
			}
		}
	}

	/** Инициализация массива настроек
	 *
	 * @param array $driverOptions
	 * @return array
	 */
	protected function initOptions($driverOptions)
	{
		$driverOptionsDefault = array(
			self::ATTR_STATEMENT_CLASS	 => array('PDOExtendedStatement', array($this)),
			self::ATTR_ERRMODE			 => self::ERRMODE_EXCEPTION,
			self::ATTR_USE_UTF			 => true,
			self::ATTR_STRICT_MODE		 => true,
			self::ATTR_TIME_ZONE		 => null,
		);

		$driverOptions = array_replace($driverOptionsDefault, $driverOptions);

		$errorLevel = isset($driverOptions[self::ATTR_ERRMODE]) ? $driverOptions[self::ATTR_ERRMODE] : self::ERRMODE_SILENT;

		foreach ($driverOptions as $driverAttr => $driverValue) {
			if (in_array($driverAttr, $this->optionsOverload)) {
				$this->optionsValues[$driverAttr] = $driverValue;
				unset($driverOptions[$driverAttr]);
				continue;
			}

			if (($driverAttr == self::ATTR_PERSISTENT) && ($driverValue == true)) {
				$this->throwError(get_class() . ' can not work with ATTR_PERSISTENT', null, $errorLevel);
				unset($driverOptions[$driverAttr]);
				continue;
			}
		}

		return $driverOptions;
	}

	/** Присвоение атрибута
	 *
	 * @param int $attribute
	 * @param mixed $value
	 * @return boolean
	 */
	public function setAttribute($attribute, $value)
	{
		if (($attribute == self::ATTR_PERSISTENT) && ($value == true)) {
			$this->throwError(get_class() . ' can not work with ATTR_PERSISTENT');
			return false;
		}

		if (in_array($attribute, $this->optionsOverload)) {
			$this->optionsValues[$attribute] = $value;
			return true;
		}

		return parent::setAttribute($attribute, $value);
	}

	/** Получение атрибута
	 *
	 * @param int $attribute
	 * @return mixed
	 */
	public function getAttribute($attribute)
	{
		if (in_array($attribute, $this->optionsOverload)) {
			if ( isset($this->optionsValues[$attribute]) ) {
				return $this->optionsValues[$attribute];
			}

			return null;
		}
		
		return parent::getAttribute($attribute);
	}

	/** Вывести ошибку в соответствии с текущими настройками
	 *
	 * @param string $message
	 * @param int $code
	 * @throws PDOException
	 */
	protected function throwError($message, $code = null, $errorLevel = null)
	{
		if (is_null($errorLevel)) {
			$errorLevel = $this->getAttribute(self::ATTR_ERRMODE);
		}

		switch ($errorLevel) {
			case self::ERRMODE_EXCEPTION:
				throw new PDOException($message, $code);
				break;

			case self::ERRMODE_WARNING:
				trigger_error(__METHOD__ . ': ' . $code . ' ' . $message, E_USER_WARNING);
				break;

			case self::ERRMODE_SILENT:
			default:
				break;
		}
	}

	/** Получить результат SQL_CALC_FOUND_ROWS
	 *
	 * @return integer
	 */
	public function calcFoundRows()
	{
		$driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);

		if ($driver == 'mysql') {
			$result = $this->query('SELECT FOUND_ROWS()');

			$rowCount = (int)$result->fetchColumn();

			return $rowCount;
		}

		$this->throwError('calcFoundRows() not implemented for ' . $driver);

		return null;
	}

	/** Возвращает ID последней вставленной строки или последовательное значение
	 * Алиас для PDO::lastInsertId();
	 *
	 * @return integer
	 */
	public function calcLastInsertId()
	{
		return $this->lastInsertId();
	}

	/** Подготавливает запрос к выполнению и возвращает ассоциированный с этим запросом объект
	 *
	 * @param string $statement
	 * @param array $driver_options
	 * @return PDOExtendedStatement
	 */
	public function prepare($statement, $driver_options = array())
	{
		$result = false;

		try {
			$result = parent::prepare($statement, $driver_options);
		} catch (PDOException $exception) {

		}

		if ($result) {
			//Возвращаем подготовленный запрос
			return $result;
		} else {
			//Генерируем пустой запрос
			return false;
		}
	}

	/** Выполнить запрос и вернуть данные
	 *
	 * @param string $statement
	 * @param array $params
	 * @return PDOExtendedStatement
	 */
	public function query($statement, $params = array())
	{
		$result = null;

		if (!is_array($params) || count($params) == 0) {
			$timerStart	 = microtime(true);
			$result		 = parent::query($statement);
			$timerFinish = microtime(true);

			$this->_statisticTimeAdd($timerFinish - $timerStart);
			$this->_statisticCountIncriment();
		} else {
			$result = $this->prepare($statement)->execute($params);
		}

		return $result;
	}

	/** Выполнить запрос и вернуть колличество измененых строк
	 *
	 * @param string $statement
	 * @param array $params
	 * @return integer
	 */
	public function exec($statement, $params = array())
	{
		$result = null;

		if (!is_array($params) || count($params) == 0) {
			$timerStart	 = microtime(true);
			$result		 = parent::exec($statement);
			$timerFinish = microtime(true);

			$this->_statisticTimeAdd($timerFinish - $timerStart);
			$this->_statisticCountIncriment();
		} else {
			$result = $this->prepare($statement)->execute($params)->rowCount();
		}

		return $result;
	}

	/** Вернуть все записи по данному запросу
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @return array
	 */
	public function getAll($statement, $params = array())
	{
		$result = $this->query($statement, $params);

		if ($result->isExecuted()) {
			return $result->fetchAll();
		} else {
			return array();
		}
	}

	/** Вернуть одну запись
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @return array
	 */
	public function getRow($statement, $params = array())
	{
		$result = $this->query($statement, $params);

		if ($result->isExecuted()) {
			return $result->fetch();
		} else {
			return false;
		}
	}

	/** Вернуть записи из указанного столбца
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @param integer $columnNumber порядковый номер столбца
	 * @return array
	 */
	public function getColumn($statement, $params = array(), $columnNumber = null)
	{
		$result = $this->query($statement, $params);

		if ($result->isExecuted()) {
			$columns = array();

			while ($column = $result->fetchColumn($columnNumber)) {
				$columns[] = $column;
			}

			return $columns;
		} else {
			return array();
		}
	}

	/** Получить одно значение
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @param string $columnName имя или порядковый номер колонки
	 *
	 * @return null|string
	 */
	public function getOne($statement, $params = array(), $columnName = 0)
	{
		$result = $this->query($statement, $params);

		if ($result->isExecuted()) {
			$data = $result->fetch(PDO::FETCH_BOTH);

			if (isset($data[$columnName])) {
				return $data[$columnName];
			} else {
				return null;
			}

		} else {
			return null;
		}
	}

	/** Суммарное время выполнение запросов
	 * @var float */
	protected $statisticTime = 0;

	/** Колличество выполенных запросов
	 * @var integer */
	protected $statisticCount = 0;

	/** Приплюсовать время выполнения.
	 * Жаль в php нет friend классов. Не хочу использовать костыли, поэтому
	 * пока этот метод публичен.
	 *
	 * @ignore
	 */
	public function _statisticTimeAdd($addTime)
	{
		$this->statisticTime += $addTime;
	}

	/** Увеличить колличество запросов на единицу.
	 * Жаль в php нет friend классов. Не хочу использовать костыли, поэтому
	 * пока этот метод публичен.
	 *
	 * @ignore
	 */
	public function _statisticCountIncriment()
	{
		$this->statisticCount++;
	}

	/** Суммарное время выполнения запросов
	 *
	 * @return float
	 */
	public function statisticTime()
	{
		return $this->statisticTime;
	}

	/** Колличество запросов к базе
	 *
	 * @return integer
	 */
	public function statisticCount()
	{
		return $this->statisticCount;
	}

}

class PDOExtendedStatement extends PDOStatement{

	const NO_MAX_LENGTH = -1;

	/** @var PDOExtended */
	protected $connection;

	/** @var array */
	protected $boundParams = array();

	protected function __construct(PDO $connection)
	{
		$this->connection = $connection;
	}

	/** @var boolean */
	private $flagError = false;

	/** Статус выполнения запроса (true - успешно, false - ошибка)
	 *
	 * @return boolean
	 */
	public function isExecuted()
	{
		return !$this->flagError;
	}

	/** Назначить массив плейсхолдеров
	 *
	 * @param array $array массив со значениями плейсхолдеров
	 * @return PDOExtendedStatement
	 */
	public function bindValueList(array $array)
	{
		if (is_array($array)) {
			foreach ($array as $item => $value) {
				$this->bindValue($item, $value, PDO::PARAM_STR);
			}
		}

		return $this;
	}

	/** Выполнить запрос
	 *
	 * @param array $input_parameters массив со значениями плейсхолдеров
	 * @return PDOExtendedStatement
	 */
	public function execute($input_parameters = null)
	{
		$timerStart = microtime(true);

		if (parent::execute($input_parameters)) {
			$this->flagError = false;
		} else {
			$this->flagError = true;
		}

		$timerFinish = microtime(true);

		$this->connection->_statisticTimeAdd($timerFinish - $timerStart);
		$this->connection->_statisticCountIncriment();

		return $this;
	}

	/** Связать значение с плейсхолдером по ссылке
	 *
	 * @param string $parameter имя плейсхолдера для именованных, или порядковый номер плейсхолдера для неименованных
	 * @param mixed $variable переменная с присваиваемым значением
	 * @param integer $type тип значения
	 * @param integer $maxlen Размер типа данных. Чтобы указать, что параметр используется для вывода данных из хранимой процедуры, необходимо явно задать его размер.
	 * @param mixed $driverdata
	 * @return PDOExtendedStatement
	 */
	public function bindParam($parameter, &$variable, $type = PDO::PARAM_STR, $maxlen = null, $driverdata = null)
	{
		$this->boundParams[$parameter] = array(
			'value'	 => &$variable,
			'type'	 => $type,
			'maxlen' => (is_null($maxlen)) ? self::NO_MAX_LENGTH : $maxlen,
		);

		parent::bindParam($parameter, $variable, $type, $maxlen, $driverdata);

		return $this;
	}

	/** Назначить значение плейсхолдеру
	 *
	 * @param string|integer $parameter имя плейсхолдера для именованных, или порядковый номер плейсхолдера для неименованных
	 * @param mixed $value устанавливаемое значение
	 * @param integer $data_type тип значения
	 * @return PDOExtendedStatement
	 */
	public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
	{
		$this->boundParams[$parameter] = array(
			'value'	 => $value,
			'type'	 => $data_type,
			'maxlen' => self::NO_MAX_LENGTH
		);

		parent::bindValue($parameter, $value, $data_type);

		return $this;
	}

	/** Сэмулировать возможный SQL запрос. Работает только с именованными плейсхолдерами
	 *
	 * @param array $values
	 * @return string
	 */
	public function buildSQL($values = array())
	{
		$sql = $this->queryString;

		/**
		 * param values
		 */
		if (sizeof($values) > 0) {
			foreach ($values as $key => $value) {
				$sql = str_replace(':' . $key, $this->connection->quote($value), $sql);
			}
		}

		/**
		 * or already bounded values
		 */
		if (sizeof($this->boundParams)) {
			foreach ($this->boundParams as $key => $param) {
				$value = $param['value'];

				if (!is_null($param['type'])) {
					$value = self::cast($value, $param['type']);
				}

				if ($param['maxlen'] && $param['maxlen'] != self::NO_MAX_LENGTH) {
					$value = self::truncate($value, $param['maxlen']);
				}

				if (!is_null($value)) {
					$sql = str_replace($key, $this->connection->quote($value), $sql);
				} else {
					$sql = str_replace($key, 'NULL', $sql);
				}
			}
		}
		return $sql;
	}

	static protected function cast($value, $type)
	{
		switch ($type) {
			case PDO::PARAM_BOOL:
				return (bool)$value;

			case PDO::PARAM_NULL:
				return null;

			case PDO::PARAM_INT:
				return (int)$value;

			case PDO::PARAM_STR:
			default:
				return $value;
		}
	}

	static protected function truncate($value, $length)
	{
		return substr($value, 0, $length);
	}

}
