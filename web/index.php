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
                if(isset($text[2]) || isset($data->object->message->reply_message->from_id)){
                    $id = "";
                    if(isset($data->object->message->reply_message->from_id))
                        $id = $data->object->message->reply_message->from_id;
                     else
                        $id = substr(explode("|",$text[2])[0], 3);
                $res = json_decode(json_encode($vk->users()->get(TOKEN_VK_BOT, array("user_ids" => $id,
                    "fields" => "id,first_name,last_name,deactivated,is_closed,verified,domain,bdate,can_post,can_see_all_posts,can_send_friend_request,"
                . "can_write_private_message,city,connections,country,contacts,counters,about,activities,education,career,last_seen,interests,home_town,games,has_photo", "name_case" => "abl"))));

                ob_start();
                var_dump($res);
                error_log(ob_get_contents());
                ob_end_clean();


                $type = "";
                    if(isset($res->deactivated)){
                        if($res->deactivated == "deleted")
                            $type = " удаленном ";
                        elseif ($res->deactivated == "banned")
                            $type = " забаненном ";
                    }

                    $request_params["message"] = "Информация о". $type ." [id". $res->id . "|". $res->first_name ." " .$res->last_name ."]: \nАйди: " . $res->id;

                    if(isset($res->last_seen)){
                        $request_params["message"] .= "\nПоследний раз был онлайн: " . strtotime("G:i d/m/y", $res->last_seen->time) . " c ";
                        switch ($res->last_seen->platform){
                            case 1:
                                $request_params["message"] .= "мобильной версии сайта";
                                break;
                            case 2:
                                $request_params["message"] .= "iPhone";
                                break;
                            case 3:
                                $request_params["message"] .= "iPad";
                                break;
                            case 4:
                                $request_params["message"] .= "Android";
                                break;
                            case 5:
                                $request_params["message"] .= "Windows Phone";
                                break;
                            case 6:
                                $request_params["message"] .= "Windows 10";
                                break;
                            case 7:
                                $request_params["message"] .= "сайта";
                                break;
                        }
                    }if(isset($res->domain)){
                        $request_params["message"] .= "\nДомен: " . $res->domain;
                    } if(isset($res->is_closed)){
                        if($res->is_closed == 1) $request_params["message"] .= "\nТип профиля: закрытый";
                        else $request_params["message"] .= "\nТип профиля: Открытый";
                    } if(isset($res->verified)){
                        if($res->verified == 1) $request_params["message"] .= "\nПодтвержденный профиль";
                        else $request_params["message"] .= "\nНеподтвержденный профиль";
                    } if(isset($res->bdate)){
                        $request_params["message"] .= "\nДата рождения: " . $res->bdate;
                    }}if(isset($res->city->title)){
                        $request_params["message"] .= "\nГород: " . $res->city->title;
                    }if(isset($res->city->country)){
                        $request_params["message"] .= "\nСтрана: " . $res->country->title;
                    }if(isset($res->home_town)){
                    $request_params["message"] .= "\nРодной город: " . $res->home_town;
                    if(isset($res->can_post)){
                            if ($res->can_post == 1) $request_params["message"] .= "\nУ пользователя открыта стена";
                            else $request_params["message"] .= "\nУ пользователя закрыта закрыта стена";
                        if(isset($res->can_see_all_posts)){
                            if ($res->can_see_all_posts == 1) $request_params["message"] .= ", запрещен просмотр чужих записей";
                            else $request_params["message"] .= ", разрешен просмотр чужих записей";
                        }if(isset($res->can_see_audio)){
                            if ($res->can_see_audio == 1) $request_params["message"] .= ", у пользователя открыты аудиозаписи";
                            else $request_params["message"] .= ", у пользователя закрыты аудиозаписи";
                        }if(isset($res->can_send_friend_request)){
                            if ($res->can_send_friend_request == 1) $request_params["message"] .= ", включены уведомления о заявках в друзья";
                            else $request_params["message"] .= ", выключены уведомления о заявках в друзья";
                        }if(isset($res->can_write_private_message)){
                            if ($res->can_write_private_message == 1) $request_params["message"] .= ", открыты сообщения";
                            else $request_params["message"] .= ", закрыты сообщения";
                        }if(isset($res->can_write_private_message)){
                            if ($res->can_write_private_message == 1) $request_params["message"] .= ", открыты сообщения";
                            else $request_params["message"] .= ", закрыты сообщения";
                        }if (isset($res->has_photo)){
                            if ($res->has_photo == 1) $request_params["message"] .= ", установлена своя аватарка";
                            else $request_params["message"] .= ", не установлена своя аватарка";
                        }
                    }if(isset($res->skype)){
                        $request_params["message"] .= "\nSkype: " . $res->skype;
                    }if(isset($res->facebook)){
                        $request_params["message"] .= "\nFacebook: " . $res->facebook;
                    }if(isset($res->twitter)){
                        $request_params["message"] .= "\nTwitter: " . $res->twitter;
                    }if(isset($res->livejournal)){
                        $request_params["message"] .= "\nLiveJournal: " . $res->livejournal;
                    }if(isset($res->instagram)){
                        $request_params["message"] .= "\nInstagram: " . $res->instagram;
                    }if(isset($res->mobile_phone)){
                        $request_params["message"] .= "\nМобильный телефон: " . $res->mobile_phone;
                    }if(isset($res->home_phone)){
                        $request_params["message"] .= "\nДомашний телефон: " . $res->home_phone;
                    }if(isset($res->counters)){
                        $request_params["message"] .= "\nКоличество объектов: альбомов: " . $res->counters->albums
                        . ", видеозаписей: " . $res->counters->videos
                        . ", аудиозаписей: " . $res->counters->audios
                        . ", фотографий: " . $res->counters->photos
                        . ", заметок: " . $res->counters->notes
                        . ", друзей: " . $res->counters->friends
                        . ", сообществ: " . $res->counters->groups
                        . ", друзей онлайн: " . $res->counters->online_friends
                        . ", видеозаписей с пользователем: " . $res->counters->user_videos
                        . ", подписчиков: " . $res->counters->followers
                        . ", интересных страниц: " . $res->counters->pages ;
                    }if(isset($res->career)){
                        $request_params["message"] .= "\nКарьера пользователя: ";
                        for ($i = 0; isset($res->career[$i]); $i++){
                           $res_g = json_decode(json_encode($vk->groups()->getById(TOKEN_VK_BOT, array("group_id" => $res->career[$i]->group_id))));
                            $request_params["message"] .= "[" . $res_g->screen_name . "|" .$res_g->name . "]";

                            if(isset($res->career[$i]->from) && isset($res->career[$i]->until))
                                $request_params["message"] .= " " . $res->career[$i]->from . " - " . $res->career[$i]->until;
                            elseif (isset($res->career[$i]->from) && !isset($res->career[$i]->until))
                                $request_params["message"] .= " с" . $res->career[$i]->from;
                            elseif (!isset($res->career[$i]->from) && isset($res->career[$i]->until))
                                $request_params["message"] .= " до" . $res->career[$i]->until;
                            if ($res->career[$i]->country_id) {
                                $res_count = json_decode(json_encode($vk->database()->getCountriesById(TOKEN_VK_BOT, array("country_ids" => $res->career[$i]->country_id))));
                                $request_params["message"] .= ", страна: " . $res_count[0]->title;
                            }
                            if ($res->career[$i]->city_id) {
                                $res_city = json_decode(json_encode($vk->database()->getCitiesById(TOKEN_VK_BOT, array("city_ids" => $res->career[$i]->city_id))));
                                $request_params["message"] .= ", город: " . $res_city[0]->title;
                            }if ($res->career[$i]->position){
                                $request_params["message"] .= ", должность: " . $res->career[$i]->position;
                            }

                            if(isset($res->career[$i + 1]))
                                $request_params["message"] .= "; ";
                        }
                    }if(isset($res->university_name)){
                        $request_params["message"] .= "\nВысшее образование: " . $res->university_name;
                        if(isset($res->faculty_name))
                            $request_params["message"] .= ", " . $res->faculty_name;
                        if(isset($res->education_form))
                            $request_params["message"] .= ", форма обучения: " . $res->education_form;
                        if(isset($res->education_status))
                            $request_params["message"] .= ", статус: " . $res->education_status;
                        if(isset($res->graduation))
                            $request_params["message"] .= ", выпуск: " . $res->graduation;
                    }if(isset($res->activities)){
                        $request_params["message"] .= "\nДеятельность пользователя: " . $res->activities;
                    }if(isset($res->games)){
                        $request_params["message"] .= "\nЛюбимые игры: " . $res->games;
                    }if(isset($res->interests)){
                        $request_params["message"] .= "\nИнтересы: " . $res->interests;
                    }if(isset($res->about)){
                        $request_params["message"] .= "\nО пользователе: " . $res->about;
                    }

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