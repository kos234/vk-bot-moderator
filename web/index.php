<?php
require('../vendor/autoload.php');

define("CONFIRMATION_TOKEN_VK_BOT", getenv("CONFIRMATION_TOKEN_VK_BOT")); //подтверждение
define("TOKEN_VK_BOT", getenv("TOKEN_VK_BOT")); //Ключ доступа сообщества
define("SECRET_KEY_VK_BOT", getenv("SECRET_KEY_VK_BOT")); //Secret key
//define("VERSION_API_VK", 5.103); //Версия апи
define("GROUP_ID", getenv("GROUP_ID")); //Айди группы С МИНУСОМ "-"


ini_set('max_execution_time', 900);

if (!isset($_REQUEST)) //проверяем получили ли мы запрос
    return;

$url=parse_url(getenv("CLEARDB_DATABASE_URL")); //Подключаемся к бд

$server = $url["host"];
$username = $url["user"];
$password = $url["pass"];
$db = substr($url["path"],1); //база данных
//error_log($server.' <- сервер '.$username.' <- имя пользователя '.$password.' <- пароль '.$db.' <- база данных'); //Если нужно узнать данные бд
$mysqli = new mysqli($server, $username, $password,$db); //Подключаемся

if ($mysqli->connect_error) {//проверка подключились ли мы
    die('Ошибка подключения (' . $mysqli->connect_errno . ') '. $mysqli->connect_error); //если нет выводим ошибку и выходим из кода
}
$mysqli->query("SET NAMES 'utf8'");

$data = json_decode(file_get_contents('php://input'));
//Проверяем secretKey
if(strcmp($data->secret, SECRET_KEY_VK_BOT) !== 0 && strcmp($data->type, 'confirmation') !== 0)
    return;//Если не наш, выдаем ошибку серверу vk

//Проверка события запроса
switch ($data->type) {

    case 'confirmation':
        //Отправляем код
        echo CONFIRMATION_TOKEN_VK_BOT;
        break;

        case 'message_new':
            $vk = new VK\Client\VKApiClient();
            $text = explode(' ', $data->object->message->text);

            $request_params = array(
                'message' => "" , //сообщение
                'access_token' => TOKEN_VK_BOT, //токен для отправки от имени сообщества
                'peer_id' => $data->object->message->peer_id, //айди чата
                'random_id' => 0, //0 - не рассылка
                'read_state' => 1,
                'user_ids' => 0, // Нет конкретного пользователя кому адресованно сообщение
                'reply_to' => $data->object->message->conversation_message_id, //Надеюсь что когда-то это будет работать
                'attachment' => '' //Вложение
            );

            if (strcasecmp($text[0], "/") == 0) {
                $request_params['message'] = "&#129302;Bot moderator by kos v2.0.0\n\n"
                    . "&#9999;Команды:\n"
                    . "&#128196;/ — информация о боте\n"
                    . "/Info user|Инфо пользователя {@Айди|@домен|Пересланое сообщение} — информация пользователя в вк и чате\n"
                    . "/Сократить ссылку {ссылка} — сокращает ссылку через сервис вк\n"
                    . "/Инвайт ссылка|Приглашение|Ссылка приглашение - выводит ссылку на приглашение в этот чат\n"
                    . "/Пригласить {@Айди|@домен|Пересланое сообщение} [Сообщение] - отправляет приглашение пользователю в этот чат\n"
                    . "/Список {Пользователей|забаненных|вышедших} - выводит указанный список пользователей\n"
                    . "/Неактив - выводит список неактивных пользователей\n"
                    . "/Онлайн - выводит список пользователей онлайн\n\n"
                    . "Модерация:\n"
                    . "/Предупреждение|Пред {@Айди|@домен|Пересланое сообщение} [Количество] [Причина] - Выдать предупреждение\n"
                    . "/Кик|Исключить {@Айди|@домен|Пересланое сообщение} [Причина] - Исключить пользователя из чата\n"
                    . "/Временный бан {@Айди|@домен|Пересланое сообщение} {Время SS:MM:HH:DDD:MM} [Причина] - Временно забанить пользователя в беседе\n"
                    . "/Бан {@Айди|@домен|Пересланое сообщение} [Причина] - Забанить пользователя\n"
                    . "/Разбанить|Пардон {@Айди|@домен|Пересланое сообщение} [Причина] - Разбанить пользователя\n"
                    . "/Мега кик|мега исключение {Неактивных|вышедших|пользователей} - исключает пользователей из определённой группы\n"
                    . "{} - обязательный параметр, [] - необязательный параметр, {] - тип зависит от задачи\n\n"
                    . "&#9881;Настройки:\n"
                    . "/Лимит повышение рангов {Уровень 1 - 5} {Количество предупреждений} {Количество киков} {Количество временных баннов} - устанавливает лимит повышение рангов модераторам\n"
                    . "/Наказания за предупреждения {Тип: кик, временный бан, бан} {Количество} {Время, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                    . "/Очистить таблицу {Пользователей|забаненных|вышедших|модераторов|наказаний|всё} - очищает указанную таблицу\n"
                    . "/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM} - сбрасывает всё предупреждения через указанное время\n"
                    . "/Приветствие {Текст} - Установить приветствие для новых пользователей\n"
                    . "/Сообщать о наказаниях {@Айди|@домен|Пересланое сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую без пробелов)\n"
                    . "/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов\n\n"
                    . "&#128214;Информация о проекте:\n"
                    . "&#128100;Создатель: https://vk.com/i_love_python\n"
                    . "&#128064;Исходные код проекта и гайд по подключению: https://github.com/kos234/Vk-bot-moderator\n";
            }elseif (strcasecmp($text[0], "/начать") == 0 ||strcasecmp($text[0], "/start") == 0){
                $res = $mysqli->query("SELECT `rang` FROM `". $data->object->message->peer_id."_users` WHERE `id` = '". $data->object->message->from_id ."'");
                $resAdmin = $res->fetch_assoc();
                    try {
                        createTabs($data->object->message->peer_id, $mysqli, $vk);
                        $request_params["message"] = "Я готов к работе! Вы всегда можете настроить меня, чтобы узнать как напишите /settings";
                    } catch (\VK\Exceptions\VKApiException $e) {
                        $request_params["message"] = "Вы не предоставили права!";
                    }
            }elseif (strcasecmp($text[0], "/настройки") == 0 ||strcasecmp($text[0], "/settings") == 0){
                $request_params["message"] = "&#9881;Настройки:\n"
                . "/Лимит повышение рангов {Уровень 1 - 5} {Количество предупреждений} {Количество киков} {Количество временных баннов} - устанавливает лимит повышение рангов модераторам\n"
                . "/Наказания за предупреждения {Тип: кик, временный бан, бан} {Количество} {Время, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                . "/Очистить таблицу {Пользователей|забаненных|вышедших|модераторов|наказаний|всё} - очищает указанную таблицу\n"
                . "/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM} - сбрасывает всё предупреждения через указанное время\n"
                . "/Приветствие {Текст} - Установить приветствие для новых пользователей\n"
                . "/Сообщать о наказаниях {@Айди|@домен|Пересланое сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую без пробелов)\n"
                . "/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов";
            }elseif (strcasecmp($text[0] . " " .$text[1], "/Info user") == 0 || strcasecmp($text[0] . " " .$text[1], "/Инфо пользователя") == 0){
                if($id = substr(explode("|", $text[3])[0], 1) || $id = $data->object->message->reply_message->from_id){

                    $request_params["message"] = $id;
                }else $request_params["message"] = "Вы должны указать айди или переслать сообщение!";
            }


        if(isset($data->object->message->action->type))//Инвайты
            if($data->object->message->action->type == "chat_invite_user" || $data->object->message->action->type == "chat_invite_user_by_link"){
                if($data->object->message->action->member_id == GROUP_ID)
                    $request_params["message"] = "Для моей работы мне необходимы права администратора. Выдайте права и напишите /начать";


            }


            $vk->messages()->send(TOKEN_VK_BOT, $request_params);

            echo "ok";
        break;

}

function createTabs($chat_id, $mysqli, $vk){
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_users`(`id` VarChar( 255 ) NOT NULL, `rang` TinyInt( 255 ) NOT NULL DEFAULT 0, `mes_count` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_punishments`(`id` VarChar( 255 ) NOT NULL, `type` VarChar( 255 ) NOT NULL, `text` VarChar( 255 ) NOT NULL, `parametr` Int( 255 ) NOT NULL ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders`(`id` VarChar( 255 ) NOT NULL, `bans` Int( 255 ) NOT NULL DEFAULT 0, `kicks` Int( 255 ) NOT NULL DEFAULT 0, `tempbans` Int( 255 ) NOT NULL DEFAULT 0, `preds` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_leave`(`id` VarChar( 255 ) NOT NULL, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `chats_settings`(`chat_id` VarChar( 255 ) NOT NULL,`autokickBot` TinyInt( 1 ) NOT NULL DEFAULT 1, `autokickLeave` TinyInt( 1 ) NOT NULL DEFAULT 0, `greeting` VarChar( 255 ) NULL, `tracking` VarChar( 255 ) NULL, `predsvarn` VarChar( 255 ) NOT NULL DEFAULT 'kick:10', `autoremovepred` Int( 255 ) NOT NULL,CONSTRAINT `unique_chat_id` UNIQUE( `chat_id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders_limit`(`rang` VarChar( 255 ) NOT NULL, `pred` Int( 255 ) NULL, `kick` Int( 255 ) NULL, `tempban` Int( 255 ) NULL, CONSTRAINT `unique_rang` UNIQUE( `rang` )) ENGINE = InnoDB;");

    $res = json_decode(json_encode($vk->messages()->getConversationMembers(TOKEN_VK_BOT, array("peer_id" => $chat_id))));
    for ($i = 0; isset($res->items[$i]); $i++){
        $rang = 0;
        if($res->items[$i]->is_admin) $rang = 5;
        $mysqli->query("INSERT INTO `". $chat_id ."_users` (`id`, `rang`) VALUES ('". $res->items[$i]->member_id ."', ". $rang .")");
    }

    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`) VALUES (1, 5)");
    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`, `kick`) VALUES (2, 6, 2)");
    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (3, 10, 4, 2)");
    $mysqli->query("INSERT INTO `chats_settings` (`chat_id`, `autoremovepred`) VALUES (". $chat_id .",". (time() + 2419200) .")");
}
?>