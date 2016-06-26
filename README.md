Расширение класса PDO
============

Данное расширение добавляет следующий функционал:
*	Цепочки вызовов
*	Аналоги функций из pear::db
*	Показать сформированный запрос
*	Функции для подсчета статистики запросов
*	Дополнительные функции
*	Дополнительные опции
*	Другие изменения


Цепочки вызовов
-----------
Старый синтаксис:

	$request = $pdo->prepare('SELECT data FROM table WHERE param = :placeholder ');
	$request->bindValue('placeholder', 'value');
	$request->execute();
	$data = $request->fetchAll();
	
Новый синтаксис:

	$data = $pdo
		->prepare('SELECT data FROM table WHERE param = :placeholder ')
		->bindValue('placeholder', 'value')
		->execute()
		->fetchAll();
		
Вобщем короче, удобнее, нагляднее.

Цепочки поддерживают следующие функции:

	PDO->prepare
	PDO->query
	PDOStatement->bindParam
	PDOStatement->bindValue
	PDOStatement->bindValueList
	PDOStatement->execute

Аналоги функций из pear::db
-----------
Представлены следующие функции
	
	public function getAll($statement, $params = array());
	public function getRow($statement, $params = array());
	public function getColumn($statement, $params = array(), $column_number = null);
	public function getOne($statement, $params = array(), $column_name = 0);
	
Конечно представлены не все функции из pear:DB, но это именно те которых мне не хватало в моих проектах.
	
Использовать очень просто:
	
	$data = $pdo->getAll('SELECT data FROM table WHERE param = :placeholder ', array('placeholder' => 'value'));
	
Запросы получаются намного короче и лаконичнее чем использование даже предложенных мною цепочек.


Показать сформированный запрос
-----------
При использовании плейсхолдеров отсутствует возможность получить полный запрос (т.е. c заменой плейсхолдеров). Вобщем то это не проблема этого расширения. Это особенность реализации PDO, ведь сама замена плейсхолдеров проводится уже в БД.

	$sql = $pdo
		->prepare('SELECT data FROM table WHERE param = :placeholder ')
		->bindValue('placeholder', 'value')
		->buildSQL();
		
Вернет сгенерированный запрос. Пока работает *только с именованными плейсхолдерами*.


Функции для подсчета статистики запросов
-----------
Это уже функционал который не добавляет только ленивый.

Используется для подсчета колличества выполненных запросов и времени затраченного на выполнение запросов.

	public function statisticTime();
	public function statisticCount();
	
По названию понятно что какая возвращает.


Дополнительные функции
------------

Тут оформлены дополнительные функции, которые я использую в своих проектах.

Возвращает результат SELECT FOUND_ROWS(), оформлено в одну функцию для более удобного использования (работает только для MySQL):

	PDO->calcFoundRows()
	
Алиас для lastInsertId:

	PDO->calcLastInsertId()
	
Присвоить значения нескольким плейсхолдерам за один вызов функции:
	
	PDOStatement->bindValueList(array $array)
	
Дополнительные опции
------------
##### PDOExtended::ATTR_USE_UTF

Включено по умолчанию. Только MySQL. При инициализации подключения установить все настройки кодировок в UTF-8. При подключении выполняются следующие запросы:

	$this->exec("SET NAMES utf8 COLLATE utf8_general_ci");
	$this->exec("SET CHARACTER SET utf8");



##### PDOExtended::ATTR_STRICT_MODE

Включено по умолчанию. Только MySQL. При инициализации подключения включить строгий режим. При подключении выполняется следующий запрос:

	$this->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");



##### PDOExtended::ATTR_TIME_ZONE

Только MySQL. При инициализации подключения установить временную зону сервера. При подключении выполняется следующий запрос:

	$this->exec("SET time_zone=?");

Например:

	$pdo->setAttribute(PDOExtended::ATTR_TIME_ZONE, '+0:00');
	$pdo->setAttribute(PDOExtended::ATTR_TIME_ZONE, null);  //Отключить изменение временной зоны сервера



Другие изменения
------------
Включены `PDOExtended::ATTR_STRICT_MODE` и `PDOExtended::ATTR_USE_UTF`. Будем двигать правильные настройки mysql и правильную кодировку в массы =)

По умолчанию включена генерация исключений при возникновении ошибок `PDOExtended::ERRMODE_EXCEPTION`.

Также изменены две стандартные функции, а точнее добавлены дополнительные параметры к ним:
	
	PDO->exec($statement, $params = array());
	PDO->query($statement, $params = array());
	
Функции делают все тоже самое, согласно их документации, но добавлен еще один параметр **$params** массив который может содержать набор плейсхолдеров.

Пример:

	$data = $pdo->
		query(
			'SELECT data FROM table WHERE param = :placeholder ',	
			array(
				'placeholder' => 'value'
			)
		)->
		fetchAll();




Заключение
-----------
Как то так. Понятно что уже существующие проекты никто не будет переводить на этот класс. Но новые - почему бы и нет?

Так например при разработке меня всегда раздражала излишняя многословность PDO из коробки. Здесь она исправлена.



История изменений
-----------
 - **2.0** вынесены настройки ATTR_USE_UTF, ATTR_STRICT_MODE, ATTR_TIME_ZONE. Немного общих изменений. Багфиксы. Включение генерации исключений при возникновении ошибок по умолчанию.
 - **1.1** исправлено несколько ошибок подсчета статистики, изменено имя метода `getSql` на `buildSQL` (во избежание ассоциаций с методами `getAll`, `getOne` и проч.), изменено форматирование и именование переменных, приведено к общему стайлгайду.
 - **1.0** паблик версия
