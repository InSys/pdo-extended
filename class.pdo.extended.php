<?php

/** Расширение для класса PDO
 *
 * @author Insys <intsystem88@gmail.com>
 * @copyright (c) 2013, Insys
 * @link https://github.com/InSys/pdo-extended
 * @license http://opensource.org/licenses/GPL-2.0 The GNU General Public License (GPL-2.0)
 */

class PDOExtended extends PDO{
	public function __construct($dsn, $username = null, $password = null, $driver_options = array()){
		if(isset($driver_options[PDO::ATTR_PERSISTENT])){
			trigger_error(__METHOD__.': PDOExtended can not work with PDO::ATTR_PERSISTENT', E_USER_WARNING);
			unset($driver_options[PDO::ATTR_PERSISTENT]);
		}

		parent::__construct($dsn, $username, $password, $driver_options);

		$this->setAttribute(
			PDO::ATTR_STATEMENT_CLASS, array('PDOExtendedStatement', array($this))
		);

		$this->exec("SET NAMES utf8");
		$this->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
	}

	/** Получить результат SQL_CALC_FOUND_ROWS
	 *
	 * @return integer
	 */
	public function calcFoundRows(){
		$result = $this->query('SELECT FOUND_ROWS()');
		$rowCount = (int) $result->fetchColumn();

		return $rowCount;
	}

	/** Подготавливает запрос к выполнению и возвращает ассоциированный с этим запросом объект
	 *
	 * @param string $statement
	 * @param array $driver_options
	 * @return PDOExtendedStatement
	 */
	public function prepare($statement, $driver_options = array()){
		$result = false;

		try{
			$result = parent::prepare($statement, $driver_options);
		}catch(PDOException $exception){

		}

		if($result){
			//Возвращаем подготовленный запрос
			return $result;
		}else{
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
	public function query($statement, $params = array()){
		$result = null;

		$timer_start = microtime(true);

		if(!is_array($params) || count($params) == 0){
			$result = parent::query($statement);
		}else{
			$result = $this->prepare($statement)->execute($params);
		}

		$timer_finish = microtime(true);

		$this->_statisticTimeAdd($timer_finish - $timer_start);
		$this->_statisticCountIncriment();

		return $result;
	}

	/** Выполнить запрос и вернуть колличество измененых строк
	 *
	 * @param string $statement
	 * @param array $params
	 * @return integer
	 */
	public function exec($statement, $params = array()){
		$result = null;

		$timer_start = microtime(true);

		if(!is_array($params) || count($params) == 0){
			return parent::exec($statement);
		}else{
			return $this->prepare($statement)->execute($params)->rowCount();
		}

		$timer_finish = microtime(true);

		$this->_statisticTimeAdd($timer_finish - $timer_start);
		$this->_statisticCountIncriment();

		return $result;
	}

	/** Вернуть все записи по данному запросу
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @return array
	 */
	public function getAll($statement, $params = array()){
		$result = $this->query($statement, $params);

		if($result->isExecuted()){
			return $result->fetchAll();
		}else{
			return array();
		}
	}

	/** Вернуть одну запись
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @return array
	 */
	public function getRow($statement, $params = array()){
		$result = $this->query($statement, $params);

		if($result->isExecuted()){
			return $result->fetch();
		}else{
			return false;
		}
	}


	/** Вернуть записи из указанного столбца
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @param integer $column_number порядковый номер столбца
	 * @return array
	 */
	public function getColumn($statement, $params = array(), $column_number = null){
		$result = $this->query($statement, $params);

		if($result->isExecuted()){
			$columns = array();
			while($new_column = $result->fetchColumn($column_number)){
				$columns[] = $column;
			}

			return $columns;
		}else{
			return array();
		}
	}

	/** Получить одно значение
	 *
	 * @param string $statement
	 * @param array $params placeholders
	 * @param string $column_name имя или порядковый номер колонки
	 *
	 * @return null|string
	 */
	public function getOne($statement, $params = array(), $column_name = 0){
		$result = $this->query($statement, $params);

		if($result->isExecuted()){
			$data = $result->fetch(PDO::FETCH_BOTH);
			if(isset($data[$column_name])){
				return $data[$column_name];
			}else{
				return null;
			}
		}else{
			return null;
		}
	}

	/** Суммарное время выполнение запросов
	 * @var float */
	protected $statistic_time = 0;

	/** Колличество выполенных запросов
	 * @var integer */
	protected $statistic_count = 0;

	/** Приплюсовать время выполнения.
	 * Жаль в php нет friend классов. Не хочу использовать костыли, поэтому
	 * пока этот метод публичен.
	 *
	 * @ignore
	 */
	public function _statisticTimeAdd($add_time){
		$this->statistic_time += $add_time;
	}

	/** Увеличить колличество запросов на единицу.
	 * Жаль в php нет friend классов. Не хочу использовать костыли, поэтому
	 * пока этот метод публичен.
	 *
	 * @ignore
	 */
	public function _statisticCountIncriment(){
		$this->statistic_count++;
	}

	/** Суммарное время выполнения запросов
	 *
	 * @return float
	 */
	public function statisticTime(){
		return $this->statistic_time;
	}

	/** Колличество запросов к базе
	 *
	 * @return integer
	 */
	public function statisticCount(){
		return $this->statistic_count;
	}
}



class PDOExtendedStatement extends PDOStatement{

	const NO_MAX_LENGTH = -1;

	/** @var PDOExtended */
	protected $connection;

	/** @var array */
	protected $bound_params = array();

	protected function __construct(PDO $connection){
		$this->connection = $connection;
	}

	/** @var boolean */
	private $flag_error = false;

	/** Статус выполнения запроса (true - успешно, false - ошибка)
	 *
	 * @return boolean
	 */
	public function isExecuted(){
		return !$this->flag_error;
	}

	/** Назначить массив плейсхолдеров
	 *
	 * @param array $array массив со значениями плейсхолдеров
	 * @return PDOExtendedStatement
	 */
	public function bindValueList(array $array){
		if(is_array($array)){
			foreach($array as $item => $value){
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
	public function execute($input_parameters = null){
		$timer_start = microtime(true);

		if(parent::execute($input_parameters)){
			$this->flag_error = false;
		}else{
			$this->flag_error = true;
		}

		$timer_finish = microtime(true);

		$this->connection->_statisticTimeAdd($timer_finish - $timer_start);
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
	public function bindParam($parameter, &$variable, $type = PDO::PARAM_STR, $maxlen = null, $driverdata = null){
		$this->bound_params[$parameter] = array(
			'value' => &$variable,
			'type' => $type,
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
	public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR){
		$this->bound_params[$parameter] = array(
			'value' => $value,
			'type' => $data_type,
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
	public function getSQL($values = array()){
		$sql = $this->queryString;

		/**
		 * param values
		 */
		if(sizeof($values) > 0){
			foreach($values as $key => $value){
				$sql = str_replace($key, $this->connection->quote($value), $sql);
			}
		}

		/**
		 * or already bounded values
		 */
		if(sizeof($this->bound_params)){
			foreach($this->bound_params as $key => $param){
				$value = $param['value'];

				if(!is_null($param['type'])){
					$value = self::cast($value, $param['type']);
				}

				if($param['maxlen'] && $param['maxlen'] != self::NO_MAX_LENGTH){
					$value = self::truncate($value, $param['maxlen']);
				}

				if(!is_null($value)){
					$sql = str_replace($key, $this->connection->quote($value), $sql);
				}else{
					$sql = str_replace($key, 'NULL', $sql);
				}
			}
		}
		return $sql;
	}

	static protected function cast($value, $type){
		switch($type){
			case PDO::PARAM_BOOL:
				return (bool)$value;
				break;
			case PDO::PARAM_NULL:
				return null;
				break;
			case PDO::PARAM_INT:
				return (int)$value;
			case PDO::PARAM_STR:
			default:
				return $value;
		}
	}

	static protected function truncate($value, $length){
		return substr($value, 0, $length);
	}
}
