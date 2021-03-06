<?php

require_once ('mysql_helper.php');
require_once ('configure.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * получаем коннект к базе
 * @return mysqli
 */
function connectToDb()
{
    static $db;
    if ($db === null) {
        $config = getConfig();
        $db = mysqli_connect($config['db_host'], $config['db_user'], $config['db_password'], $config['db_database']);
        mysqli_set_charset($db, 'utf8');
        if (!$db) {
            print('Ошибка: Невозможно подключиться к MySQL  ' .mysqli_connect_error());
            die();
        }
    }
    return $db;
}

// используем данные для доступа к БД
function getConfig()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/connection.php';
    }
    return ($config);
}

/**
 * Подготовка выражения
 * @param array $parameterList
 * @param null $db
 * @return array|bool|null
 */
function processingSqlQuery(array $parameterList, $db = null)
{
    if ($db === null) {
        $db = connectToDb();
    }

    $params = addLimit($parameterList);

    $stmt = db_get_prepare_stmt($db, $params['sql'], $params['data']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $result = true;
    if ($res != false) {
        if (mysqli_num_rows($res) > 1) {
            $result = mysqli_fetch_all($res, MYSQLI_ASSOC);
        } else {
            $result = mysqli_fetch_array($res, MYSQLI_ASSOC);
        }
    }
    return $result;
}

/**
 * Установка лимитов для результатов запрос, если лимит использован
 * @param array $parameterList
 * @return array $parameterList
 */
function addLimit(array $parameterList)
{
    if (!empty($parameterList['limit'])) {
        if ((int)$parameterList['limit']) {
            $parameterList['sql'] .= ' LIMIT ?';
            $parameterList['data'][] = (int)$parameterList['limit'];
        }
    }

    return $parameterList;
}

/** Установка оффсета для результатов запроса, если оффсет использован
 * @param array $parameterList
 * @return array $parameterList
 */
function addOffset(array $parameterList)
{
    if (!empty($parameterList['offset'])) {
        if ((int)$parameterList['offset']) {
            $parameterList['sql'] .= ' OFFSET ?';
            $parameterList['data'][] = (int)$parameterList['offset'];
        }
    }

    return $parameterList;
}

/**
 * Подсчет времени до завершения лота
 * @param string $finishTime Время завершения лота
 * @param bool $secShow Включает отображение секунд
 *
 * @return string Время о конца торгов лота
 */
function lotFinishTime($finishTime, bool $secShow = false)
{
    $timeLeft = '';
    $finishTime = strtotime($finishTime); //timestamp

    $time = $finishTime - time();

    if ($time < 1) {
        $timeLeft = '00:00';
    } else {
        $format = '%02d:%02d'; // тут в формате ожидается по 2 символа
        !$secShow ?: $format .= ':%02d';

        $timeLeft = sprintf($format, ($time / 3600), ($time / 60) % 60, $time % 60);
    }
    return $timeLeft;
}

/**
 * Получение списка категорий
 * @param int|null $limit необязательное поле лимита для запроса
 * @param null $db Ресурс соединения с ДБ
 * @return array|bool|null
 */
function getCatList(int $limit = null, $db = null) {
    $sql = 'SELECT `cat_name`, `id` FROM categories;';
    $parameterList = [
        'sql' => $sql,
        'data' => [],
        'limit' => $limit
    ];
    return processingSqlQuery($parameterList, $db);
}

/**
 * Получение списка последних лотов
 * @param int|null $limit количество получаемых лотов
 * @param null $db подключение к ДБ
 *
 * @return mixed данные лота
 */
function getLotsList(int $limit = null, $db = null)
{
    $sql = 'SELECT l.lot_name, l.start_price, l.img_url, l.id, MAX(b.bid_price) AS cur_price, cat.cat_name, COUNT(b.lot_id) AS bids_qty, l.finish_date
              FROM lots l
              LEFT JOIN bids b ON b.lot_id=l.id
              LEFT JOIN categories cat ON cat.id=l.category_id
              WHERE winner_id IS NULL
              GROUP BY l.id
              ORDER BY l.create_date DESC';
    $parameterList = [
        'sql' => $sql,
        'data' => [],
        'limit' => $limit
    ];
    return processingSqlQuery($parameterList, $db);
}

/**
 * Получение данных об одном лоте по его id
 * @param int $lot_id Цена товара точная
 * @param null $db подключение к ДБ
 *
 * @return mixed данные лота
 */
function getLot(int $lot_id, $db = null)
{
    $sql = 'SELECT l.lot_name, l.start_price, c.cat_name, l.id, l.img_url, l.lot_description, MAX(b.bid_price) AS cur_price, l.bid_step, l.finish_date, l.author_id
              FROM lots l
                LEFT JOIN bids b ON b.lot_id = l.id
                LEFT JOIN categories c ON c.id=l.category_id
              WHERE l.category_id=c.id AND l.id = ?
              GROUP BY l.id';

    $parametersList = [
        'sql' => $sql,
        'data' => [$lot_id],
        'limit' => 1
    ];
    return processingSqlQuery($parametersList, $db);
}

/**
 * Возвращает текущую цену лота
 * @param $lot_info
 * @return mixed
 */
function lotPrice($lot_info)
{
    if (is_null($lot_info['cur_price'])) {
        $lot_info['cur_price'] = $lot_info['start_price'];

    } else {
        $lot_info['cur_price'];
    }

    return $lot_info;
}

/**
 * Создает нового юзера
 * @param array $user_data Данные юзера
 * @param array $user_avatar Загруженное изображение
 *
 * @return array|int|string Id добавленного лота или массив ошибок
 */
function saveUser(array $user_data, array $user_avatar)
{
    $result = [
        'result' => true,
        'errors' => [],
    ];

    $errors = array_merge(checkFieldsSaveUser($user_data), checkUplImage($user_avatar, 'photo'));

    if (empty($errors)) {
        $config = getConfig();
        if ($imageName = saveImage($user_avatar, $config['avatarDirUpl'])) {
            $sql = 'INSERT INTO users
                      (us_name, us_email, us_password, create_date, us_image, us_contacts)
                    VALUES
                      (?, ?, ?, NOW(), ?, ?)';
            $parametersList = [
                'sql' => $sql,
                'data' => [
                    $user_data['name'],
                    $user_data['email'],
                    password_hash(trim($user_data['password']), PASSWORD_DEFAULT),
                    $imageName,
                    $user_data['message']
                ],
            ];
            processingSqlQuery($parametersList);

            $result = [
                'result' => true,
                'errors' => [],
            ];
        }
    } else {
        $result = [
            'result' => false,
            'errors' => $errors,
        ];
    }
    return $result;
}

/**
 * Проверяет обязательые поля в добавлении нового пользователя
 * @param array $user_data
 * @return array $errors
 */
function checkFieldsSaveUser(array $user_data)
{
    $errors = formRequiredFields($user_data,
        [
            'email', 'password', 'password2', 'name', 'message'
        ]); // названия полей в шаблоне

    if (!filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите адрес электронной почты';
    } elseif (getUserInfoByEmail($user_data['email'])) {
        $errors['email'] = 'Пользователь с указанным email уже существует';
    }

    if (empty($errors['password']) && $user_data['password'] !== $user_data['password2']) {
        $errors['password'] = 'Введенные пароли не совпадают';
    }

    return $errors;
}

/**
 * Получает юзера по мейлу
 * @param string $email
 * @param int $limit
 * @return array|bool|null
 */
function getUserInfoByEmail(string $email, $limit = 1)
{
    if (empty($email)) {
        return false;
    }
    $sql = 'SELECT * FROM users WHERE us_email = ?'; #TODO выпили тут звездочку нафиг!
    $parameterList = [
        'sql' => $sql,
        'data' => [
            $email
        ],
        'limit' => $limit
    ];

    $result = processingSqlQuery($parameterList);
    return $result;
}

/**
 * Логинит пользователя на сайт
 * Не показываем юзеру, что из пары пароль-логин было неправильным.
 * @param array $user_data данные, полученные от юзера
 * @return array
 * todo впилить проверку на количество попыток авторизации (кука?)
 */
function login(array $user_data)
{
    $result = [
        'result' => true,
        'user'   => [],
        'errors' => []
    ];

    $errors = checkFieldsLogin($user_data);
    $foundUser = getUserInfoByEmail($user_data['email']); // данные юзера.

    if (empty($errors) && $foundUser) {
        if (password_verify($user_data['password'], $foundUser['us_password'])) {
            $foundUser['us_password'] = passwordNeedsReHash($foundUser, $user_data['password']);
            $result = [
                'result' => true,
                'user'   => $foundUser,
                'errors' => [],
            ];
        } else {
            $errors['password'] = 'Неправильный логин или пароль';
            $result = [
                'result' => false,
                'user'   => [],
                'errors' => $errors
            ];
        }
    } else {
        $errors['email'] = 'Неправильный логин или пароль';
        $result = [
            'result' => false,
            'user'   => [],
            'errors' => $errors
        ];
    }

    return $result;
}

/**
 * Проверяет обязательые поля в авторизации пользователя
 * @param array $user_data
 * @return array $errors
 */
function checkFieldsLogin(array $user_data)
{
    $errors = formRequiredFields($user_data,
        [
            'email', 'password'
        ]);

    if (empty($errors['email']) && (!filter_var($user_data['email'], FILTER_VALIDATE_EMAIL))) {
        $errors['email'] = 'Введите адрес электронной почты';
    }

    return $errors;
}

/**
 * Проверяет, нужно ли пересчитать хеш пароля пользователя, и вызывает его обновление
 * @param array $foundUser
 * @param string $password
 * @return bool|mixed|string
 */
function passwordNeedsReHash(array $foundUser, string $password)
{
    if (password_needs_rehash($foundUser['us_password'], PASSWORD_DEFAULT)) {
        return passwordUpdating($foundUser['id'], $password);
    }

    return $foundUser['us_password'];
}

/**
 * Обновляет у пользователя хеш пароля
 * @param int $userId
 * @param string $password
 * @param null $db
 * @return bool|string
 */
function passwordUpdating(int $userId, string $password, $db = null)
{
    $reHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = 'UPDATE users SET us_password = ? WHERE id = ?';

    $parameterList = [
        'sql' => $sql,
        'data' => [
            $reHash,
            $userId
        ],
        'limit' => 1
    ];

    processingSqlQuery($parameterList, $db);

    return $reHash;
}

/**
 * Получает данные сессии
 * @return array данные сессии
 */
function getSession()
{
    static $session = null;


    if ($session === null && !is_null($_SESSION)) {
        $session = $_SESSION;
    }

    return $session;
}

/**
 * Получает данные сессии пользователя
 *
 * @return array|bool данные пользователя из сессии
 */
function getUserSessionData()
{
    return getSession()['user'] ?? false;
}

/**
 * Проверяет, авторизован ли пользователь
 *
 * @return bool Результат
 */
function isAuthorized()
{
    if (!empty(getUserSessionData())) {
//    if (!empty($_SESSION)) {
        return true;
    }

    return false;
}

/**
 * Округляет до целого цену лота
 * @param int $price Цена товара точная
 *
 * @return string $price_formatted округленная цена
 */
function price_round($price)
{
    htmlspecialchars($price);
    if ($price < 1000)
    {
        $price_round = $price;
    } else
    {
        $price_round = number_format(ceil($price), '0', '0', '&thinsp;');
    }
    return $price_formatted = $price_round . ' ₽';
};

/**
 * Сохраняет данные лота
 * @param array $lot_data Данные лота
 * @param array $lot_image Загруженное изображение
 * @param null $db Подключение к БД
 *
 * @return array|int|string Id добавленного лота или массив ошибок
 */
function saveLot(array $lot_data, array $lot_image, $db = null)
{
    $result = [
        'result' => true,
        'errors' => [],
    ];

    $errors = array_merge(checkFieldsSaveLot($lot_data), checkUplImage($lot_image, 'photo'));

    if (empty($errors)) {
        $config = getConfig();
        if ($imageName = saveImage($lot_image, $config['imgDirUpl'])) {
            $sql = 'INSERT INTO lots
                      (lot_name, create_date, category_id, start_price, bid_step, img_url, lot_description, author_id, finish_date)
                    VALUES 
                      (?, NOW(), ?, ?, ?, ?, ?, ?, ?)';
        $parametersList = [
            'sql' => $sql,
            'data' => [
                $lot_data['name'],
                $lot_data['category'],
                $lot_data['start_price'],
                $lot_data['step'],
                $imageName,
                $lot_data['description'],
                getUserSessionData()['id'],
                $lot_data['finish_date']
            ],
        ];
        processingSqlQuery($parametersList, $db);

            $result = [
                'result' => mysqli_insert_id(connectToDb()),
                'errors' => [],
            ];
        }
    } else {
        $result = [
            'result' => true,
            'errors' => $errors,
        ];
    }
    return $result;
};

/**
 * Проверяет поля формы на соответствие заданному ограничению
 * @param array $lot_data Данные лота
 *
 * @return array массив ошибок
 */
function checkFieldsSaveLot(array $lot_data) // #TODO удостовериться, что поля в форме совпадают по названию
{
    $errors = formRequiredFields($lot_data,
        [
            'name', 'category', 'description', 'start_price', 'step', 'finish_date'
        ]); // названия полей в шаблоне

    if (!getLot($lot_data['category'])) {
        $errors['category'] = 'Выберите категорию';
    }

    if (!filter_var($lot_data['start_price'], FILTER_VALIDATE_INT) && empty($errors['start_price'])) {
        $errors['start_price'] = 'Введите цену продажи';
    } else {
        if ($lot_data['start_price'] < 0) {$errors['start_price'] = 'Цена продажи должна быть больше нуля';}
    }

    if (!filter_var($lot_data['step'], FILTER_VALIDATE_INT) && empty($errors['step'])) {
        $errors['step'] = 'Введите шаг ставки';
    } else {
        if ($lot_data['step'] < 1) {$errors['step'] = 'Шаг ставки должен быть больше единицы';}
    }

    if (is_numeric(strtotime($lot_data['finish_date']))) {
        if (strtotime($lot_data['finish_date']) < time()) {
            $errors['finish_date'] = 'Дата окончания торгов не может быть в прошлом';
        }
    } else {
        $errors['finish_date'] = 'Выберите дату';
    }

    return $errors;
};

/**
 * Формирует список проверяемых полей и проверяет их заполненность
 * @param array $form Данные формы
 * @param array $fields Список обязательных полей
 *
 * @return array массив ошибок
 */
function formRequiredFields(array $form, array $fields)
{
    $errors = [];

    foreach ($fields as $field) {
        if (empty($form[$field])) {
            $errors[$field] = 'Поле не заполнено';
        }
    }
    return $errors;
}

/**
 * Проверка загружаемого изображения
 * @param array $image Изображение
 * @param string $key Название поля для возврата ошибки
 *
 * @return array $error массив ошибок
 */
function checkUplImage(array $image, string $key)
{
    $error = [];

    if (empty($image['size']) or ($_FILES["photo"]["error"] != UPLOAD_ERR_OK)) {  //тут потенциально может быть хрень
        $error[$key] = 'Выберите изображение';
    } elseif ($image['size'] > 5e+6) {
        $error[$key] = 'Изображение не должно быть более 5Мб'; // #TODO проверить размер файла
    } else {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $image['tmp_name']); // вытаскиваем тип файла

        $fileFormat = ['image/jpeg', 'image/jpg', 'image/png'];

        if (!in_array($fileType, $fileFormat)) {
            $error[$key] = 'Выберите фотографию формата JPEG, JPG или PNG';
        }
    }

    return $error;
};

function dirExistence(string $dir)
{
    if (!file_exists($dir)) {
        mkdir($dir);
    }
}

/**
 * Сохраняет изображение на сервер
 * @param array $image изображение для сохранения
 * @param string $dir папка для сохранения
 *
 * @return bool|string Результат загрузки
 */
function saveImage(array $image, string $dir)
{
    $uploadDir = __DIR__ ; // в корне проекта
    $name = basename($image["name"]); // здесь только имя.расширение файла
    $uploadFile = "$uploadDir\\$dir\\$name";

    dirExistence("$uploadDir\\$dir");

    if (move_uploaded_file($image['tmp_name'], "$uploadFile")) {
        return "$dir/$name";
    } else {
        return false;
    }
}

/**
 * Проверка новой ставки при добавлении
 * @param $newBet
 * @param $minBet
 * @return array
 */
function checkNewBet($newBet, $minBet)
{
    $errors = formRequiredFields($newBet, ['cost']);

    if (empty($errors)) {
        if (filter_var($newBet['cost'], FILTER_VALIDATE_INT)) {

            if ($newBet['cost'] < $minBet) {
                $errors['cost'] = 'Ставка не может быть ниже минимальной';
            }
        } else {
            echo "fignia";
            $errors['cost'] = 'Неверно указана цена';
        }
    }
    return $errors;
}

/**
 * Добавляет новую ставку в базу
 * @param int $lotId идентификатор лота
 * @param array $newBet новая ставка пользователя
 * @param int $minPrice минимальная текущая ставка
 * @param null $db БД
 *
 * @return array
 */
function betAdd(int $lotId, array $newBet, int $minPrice, $db = null)
{
    $result = [
        'result' => true,
        'errors' => [],
    ];

    $errors = checkNewBet($newBet, $minPrice);

    if (empty($errors)) {
        $sql = 'INSERT INTO bids
                      (bid_date, bid_price, user_id, lot_id)
                    VALUES 
                      (NOW(), ?, ?, ?)';
        $parametersList = [
            'sql' => $sql,
            'data' => [
                $newBet['cost'],
                getUserSessionData()['id'],
                $lotId,

            ],
        ];
        processingSqlQuery($parametersList, $db);

        $result = [
            'result' => true,
            'errors' => []
            ];
    } else {
        $result = [
            'result' => false,
            'errors' => $errors
        ];
    }
    return $result;
}

/**
 * Возвращает минимальную ставку на данный момент
 * @param $lot_info array Информация о лоте из БД
 * @param $type bool Позволяет выбрать, форматировать ли (false) с рублем ответ
 *
 * @return string Минимальная ставка
 */
function minBet($lot_info, $type = false)
{
    if ($type === false) {
    return price_round($lot_info['cur_price'] + $lot_info['bid_step']);
    } else {
        return htmlspecialchars($lot_info['cur_price'] + $lot_info['bid_step']);
    }
}

/**
 * Получает список текущих ставок на лот
 * @param int $lotId
 * @param null $db
 * @return array|bool|null
 */
function getLotBets(int $lotId, $db = null)
{
    $sql = 'SELECT b.bid_date, b.bid_price, b.lot_id, u.us_name
            FROM bids b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE lot_id = ? 
            ORDER BY bid_date DESC';
    $parametersList = [
        'sql' => $sql,
        'data' => [
            $lotId
        ],
    ];
    $betList = processingSqlQuery($parametersList, $db);

    return $betList;
}

/**
 * Возвращает количество ставок на лот
 * @param int $lotId
 * @param null $db
 * @return array|bool|null
 */
function getLotBetsCount(int $lotId, $db = null)
{
    $sql = 'SELECT COUNT(lot_id) as betsCount
            FROM bids
            WHERE lot_id = ?';
    $parametersList = [
        'sql' => $sql,
        'data' => [
            $lotId
        ],
    ];
    $betListCount = processingSqlQuery($parametersList, $db);

    return $betListCount;
}

/**
 * Форматирует вывод времени для вывода на списке ставок
 * @param $date
 * @return false|string
 */
function formatTime($date)
{
    $timestamp = strtotime($date);
    $time = date( 'd.m.y в G:i', $timestamp);

    return $time;
}

/**
 * Подсказывает, показывать ли форму добавления ставки на лог
 * @param $lot_info
 * @return bool
 */
function showBetForm($lot_info)
{
    if (isAuthorized() && strtotime($lot_info['finish_date']) > time()) {
        if (($lot_info['author_id'] !== getUserSessionData()['id']) &&
            (lastLotBidder($lot_info['id'])['user_id'] !== getUserSessionData()['id']) ||
            (intval(getLotBetsCount($lot_info['id'])) === 0)) {
            return true;
        }
    }
    return false;
}

/**
 * Получаем ID пользователя, сделавшего последнюю ставку на лот
 * @param $lotId
 * @param int $limit
 * @return array|bool|null
 */
function lastLotBidder($lotId, $limit = 1)
{
    $sql = 'SELECT user_id
            FROM bids
            WHERE lot_id = ?
            ORDER BY bid_date DESC';
    $parametersList = [
        'sql' => $sql,
        'data' => [
            $lotId
        ],
        'limit' => $limit
    ];
    $lastBidder = processingSqlQuery($parametersList);
    return $lastBidder;
}

function biddingIsOver(array $lot_info)
{
    if (strtotime($lot_info['finish_date']) < time()) {
        return true;
    } else {
        return false;
    }
}

/**
 * Рендерит указанный шаблон
 * @param $templ string название шаблона,
 * @param $data array данные для шаблона
 * @return string готовый для вставки шаблон
 * @throws $e;
 */
function renderTemplate(string $templ, $data)
{
    $filePath = __DIR__ . '/templates/' . $templ . '.php';
    if (!file_exists($filePath)) {
        return 'Template '. $templ. '.php doesn\'t exist at ' . $filePath;
    }

    extract($data);
    ob_start();
    try {
        include ($filePath);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return ob_get_clean();
}
