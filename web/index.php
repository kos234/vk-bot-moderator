<?php
require('../vendor/autoload.php');

define("CONFIRMATION_TOKEN_VK_BOT", getenv("CONFIRMATION_TOKEN_VK_BOT")); //подтверждение
define("TOKEN_VK_BOT", getenv("TOKEN_VK_BOT")); //Ключ доступа сообщества
define("SECRET_KEY_VK_BOT", getenv("SECRET_KEY_VK_BOT")); //Secret key
define("USER_TOKEN", getenv("USER_TOKEN")); /*Токен пользователя нужен для показа полной информации о группах и людях,
 при желании его можно не указывать, а на 319 строке передать не токен пользователя а токен группы*/
define("SERVICE_KEY", getenv("SERVICE_KEY")); //Сервисный ключ


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
                //'reply_to' => $data->object->message->conversation_message_id, //Надеюсь что когда-то это будет работать
                'attachment' => '' //Вложение
            );

            if (mb_strcasecmp($text[0], "/") == 0) {
                $request_params['message'] = "&#129302;Bot moderator by kos v2.0.0\n\n"
                    . "&#9999;Команды:\n"
                    . "&#128196;/ — информация о боте\n"
                    . "/User info|Информация пользователя {@Айди|@домен|Пересланое сообщение} — информация пользователя в вк и чате\n"
                    . "/Сократить ссылку {ссылка} [Включить|выключить|on|off] — сокращает ссылку через сервис вк, on - включает статистику\n"
                    . "/Получить статистику {ссылка} {токен} — выводит статистику переходов по сокращенный ссылке (желательно использовать эту команду в личных сообщениях)\n"
                    . "/Инвайт ссылка|Приглашение|Ссылка приглашение - выводит ссылку на приглашение в этот чат\n"
                    . "/Пригласить {@Айди|@домен|Пересланое сообщение} [Сообщение] - отправляет приглашение пользователю в этот чат\n"
                    . "/Список {Пользователей|забаненных|вышедших|модераторов|неактивных|онлайна} - выводит указанный список пользователей\n"
                    . "/Settings|настройки [chat|беседы|чата] - показывает либо возможный, либо текущий список настроек\n"
                    . "/Лимит модераторов - выводит лимит для модераторов\n\n"
                    . "Модерация и Администрация:\n"
                    . "/Предупреждение|Пред {@Айди|@домен|Пересланое сообщение} [Количество] [Причина] - Выдать предупреждение, по умолчанию 1 предупреждение\n"
                    . "/Удалить предупреждение|пред {@Айди|@домен|Пересланое сообщение} [Количество] - Удалить предупреждения, по умолчанию всё предупреждения\n"
                    . "/Кик|Исключить {@Айди|@домен|Пересланое сообщение} [Причина] - Исключить пользователя из чата\n"
                    . "/Временный бан {@Айди|@домен|Пересланое сообщение} {Время SS:MM:HH:DDD:MM} [Причина] - Временно забанить пользователя в беседе\n"
                    . "/Бан {@Айди|@домен|Пересланое сообщение} [Причина] - Забанить пользователя\n"
                    . "/Разбанить|Пардон {@Айди|@домен|Пересланое сообщение} [Причина] - Разбанить пользователя\n"
                    . "/Мега кик|мега исключение {Неактивных|вышедших|пользователей} - исключает пользователей из определённой группы\n"
                    . "/Назначит ранг|Сет ранг {@Айди|@домен|Пересланое сообщение} {0|1|2|3|4|5|Модератор 1 - 4|пользователь|администратор} - Разбанить пользователя\n\n"
                    . "&#9881;Настройки:\n"
                    . "/Выдать ранг {0|1|2|3|4|5|пользователь|модератор1|модератор2|модератор3|модератор4|администратор} {@Айди|@домен|Пересланое сообщение} - Выдать предупреждение\n"
                    . "/Лимит повышение рангов {Уровень 1 - 5} {Количество предупреждений} {Количество киков} {Количество временных баннов} - устанавливает лимит повышение рангов модераторам\n"
                    . "/Наказания за предупреждения {Тип: кик, временный бан, бан} {Количество} {Время, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                    . "/Очистить таблицу {Пользователей|забаненных|вышедших|модераторов|наказаний|лимит|настроек|всё} - очищает указанную таблицу\n"
                    . "/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM} - сбрасывает всё предупреждения через указанное время\n"
                    . "/Приветствие {Текст {first_name} {last_name}} - Устанавливает приветствие для новых пользователей, {first_name} - чтобы указать имя, {last_name} - чтобы указать фамилию\n"
                    . "/Установить инвайт ссылку {Текст} - Устанавливает ссылку для подключение к беседе\n"
                    . "/Сообщать о наказаниях {@Айди|@домен|Пересланое сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую без пробелов)\n"
                    . "/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов\n"
                    . "{} - обязательный параметр, [] - необязательный параметр, {] - тип зависит от задачи\n\n"
                    . "&#128214;Информация о проекте:\n"
                    . "&#128100;Создатель: https://vk.com/i_love_python\n"
                    . "&#128064;Исходные код проекта и гайд по подключению: https://github.com/kos234/Vk-bot-moderator\n";
            }elseif (mb_strcasecmp($text[0], "/начать") == 0 ||mb_strcasecmp($text[0], "/start") == 0){
                $res = $mysqli->query("SELECT `rang` FROM `". $data->object->message->peer_id."_users` WHERE `id` = '". $data->object->message->from_id ."'");
                $resAdmin = $res->fetch_assoc();
                if($resAdmin["rang"] == "5") {
                    try {
                        createTabs($data->object->message->peer_id, $mysqli, $vk);
                        $request_params["message"] = "Я готов к работе! Вы всегда можете настроить меня, чтобы узнать как напишите /settings";
                    } catch (\VK\Exceptions\VKApiException $e) {
                        $request_params["message"] = "Вы не предоставили права!";
                    }
                }
            }elseif (mb_strcasecmp($text[0], "/настройки") == 0 ||mb_strcasecmp($text[0], "/settings") == 0){
                if(isset($text[1]) && (mb_strcasecmp($text[1], "беседы") == 0 || mb_strcasecmp($text[1], "чата") == 0 || mb_strcasecmp($text[1], "chat") == 0)){
                    $res = $mysqli->query("SELECT * FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                    $res = $res->fetch_assoc();
                    $request_params["message"] = "Настройки беседы: \nАвто исключение вышедших пользователей: ";
                    if ($res["autokickLeave"] == 1) $request_params["message"] .= "включено"; else $request_params["message"] .= "выключено";
                    $request_params["message"] .= "\nАвто исключение новых ботов: ";
                    if ($res["autokickBot"] == 1) $request_params["message"] .= "включено"; else $request_params["message"] .= "выключено";
                    $request_params["message"] .= "\nПриветствие: ";
                    if ($res["greeting"] == "") $request_params["message"] .= "выключено"; else $request_params["message"] .= "\"" . $res["greeting"] . "\"";
                    $request_params["message"] .= "\nСледящие за выдачей наказаний: ";
                    if ($res["tracking"] == "") $request_params["message"] .= "отсутствуют"; else $request_params["message"] .= implode(", ",getName($vk, explode(",", $res["tracking"])));
                    $request_params["message"] .= "\nСсылка для приглашения в чат: ";
                    if ($res["invite_link"] == "") $request_params["message"] .= "отсутствуют"; else $request_params["message"] .= $res["invite_link"];
                    $request_params["message"] .= "\nНаказание за какое-то количество предупреждений: ";
                    if ($res["predsvarn"] == "") $request_params["message"] .= "отсутствуют"; else{
                        $fields = explode(":",$res["predsvarn"]);
                        $request_params["message"] .= "за " . $fields[1];
                        if(($fields[1] >= 11 && $fields[1] <= 19) || (endNumber($fields[1]) >= 5 && endNumber($fields[1]) <= 9) || endNumber($fields[1]) == 0)
                            $request_params["message"] .= " предупреждений";
                        elseif (endNumber($fields[1]) == 1)
                            $request_params["message"] .= " предупреждение";
                        elseif (endNumber($fields[1]) >= 2 && endNumber($fields[1]) <= 4)
                            $request_params["message"] .= " предупреждения";
                        $request_params["message"] .= " вы будете ";
                        switch ($fields[0]){
                            case "kick":
                                $request_params["message"] .= "исключены из беседы";
                                break;
                            case "tempban":
                                $request_params["message"] .= "забанены на " . getTime($fields[2]);
                                break;
                            case "ban":
                                $request_params["message"] .= "забанены";
                                break;
                        }
                    }
                    $request_params["message"] .= "\nОчистка предупреждений происходит каждые: " . getTime($res["autoremovepred"]);
                    $request_params["message"] .= "\nПослеждняя очистка была " . date("d.m.Y G:i", $res["lastRemovePred"]) . "по UTC 0";
                }else
                $request_params["message"] = "&#9881;Настройки:\n"
                . "/Лимит повышение рангов {Уровень 1 - 5} {Количество предупреждений} {Количество киков} {Количество временных баннов} - устанавливает лимит повышение рангов модераторам\n"
                . "/Наказания за предупреждения {Тип: кик, временный бан, бан} {Количество} {Время, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                . "/Очистить таблицу {Пользователей|забаненных|вышедших|модераторов|наказаний|всё} - очищает указанную таблицу\n"
                . "/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM} - сбрасывает всё предупреждения через указанное время\n"
                . "/Приветствие {Текст} - Установить приветствие для новых пользователей\n"
                . "/Сообщать о наказаниях {@Айди|@домен|Пересланое сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую без пробелов)\n"
                . "/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/user info") == 0 || mb_strcasecmp($text[0] . " " .$text[1], "/Информация пользователя") == 0){
                $id = getId($text[2],$data->object->message->reply_message->from_id);

                if($id != 0){
                    $type = "";
                    if($id > 0){
                $res_user = json_decode(json_encode($vk->users()->get(USER_TOKEN, array("user_ids" => $id,
                    "fields" => "id,first_name,last_name,deactivated,is_closed,verified,domain,bdate,can_post,can_see_all_posts,can_send_friend_request,"
                . "can_write_private_message,city,connections,country,status,contacts,counters,about,activities,education,career,last_seen,interests,home_town,games,has_photo", "name_case" => "abl"))));

                    if(isset($res_user[0]->deactivated)){
                        if($res_user[0]->deactivated == "deleted")
                            $type = " удаленном ";
                        elseif ($res_user[0]->deactivated == "banned")
                            $type = " забаненном ";
                    }
                        ob_start();
                        var_dump($res_user);
                        error_log(ob_get_contents());
                        ob_end_clean();
                    $request_params["message"] = "Информация о". $type ." [id". $res_user[0]->id . "|". $res_user[0]->first_name ." " .$res_user[0]->last_name ."]: \nАйди: " . $res_user[0]->id;

                    if(isset($res_user[0]->domain)){
                        $request_params["message"] .= "\nДомен: " . $res_user[0]->domain;
                    }if(isset($res_user[0]->status)){
                        if($res_user[0]->status != "")
                        $request_params["message"] .= "\nСтатус: " . $res_user[0]->status;
                    }if(isset($res_user[0]->last_seen)){
                        $request_params["message"] .= "\nПоследний раз был онлайн: " . date("G:i d", $res_user[0]->last_seen->time);
                        switch (date("m",$res_user[0]->last_seen->time)){
                            case 1:
                                $request_params["message"] .= " января";
                                break;
                            case 2:
                                $request_params["message"] .= " февраля";
                                break;
                            case 3:
                                $request_params["message"] .= " марта";
                                break;
                            case 4:
                                $request_params["message"] .= " апреля";
                                break;
                            case 5:
                                $request_params["message"] .= " мая";
                                break;
                            case 6:
                                $request_params["message"] .= " июня";
                                break;
                            case 7:
                                $request_params["message"] .= " июля";
                                break;
                            case 8:
                                $request_params["message"] .= " августа";
                                break;
                            case 9:
                                $request_params["message"] .= " сентября";
                                break;
                            case 10:
                                $request_params["message"] .= " октября";
                                break;
                            case 11:
                                $request_params["message"] .= " ноября";
                                break;
                            case 12:
                                $request_params["message"] .= " декабря";
                                break;
                        }
                        $request_params["message"] .= " ". date("Y",$res_user[0]->last_seen->time) ." по UTC 0 c ";
                        switch ($res_user[0]->last_seen->platform){
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
                    } if(isset($res_user[0]->is_closed)){
                        if($res_user[0]->is_closed == 1) $request_params["message"] .= "\nТип профиля: закрытый";
                        else $request_params["message"] .= "\nТип профиля: Открытый";
                    } if(isset($res_user[0]->verified)){
                        if($res_user[0]->verified == 1) $request_params["message"] .= "\nПодтвержденный профиль";
                        else $request_params["message"] .= "\nНеподтвержденный профиль";
                    } if(isset($res_user[0]->bdate)){
                        $request_params["message"] .= "\nДата рождения: " . $res_user[0]->bdate;
                    }if(isset($res_user[0]->city->title)){
                        $request_params["message"] .= "\nГород: " . $res_user[0]->city->title;
                    }if(isset($res_user[0]->country->title)){
                        $request_params["message"] .= "\nСтрана: " . $res_user[0]->country->title;
                    }if(isset($res_user[0]->home_town)) {
                        $request_params["message"] .= "\nРодной город: " . $res_user[0]->home_town;
                    }if(isset($res_user[0]->mobile_phone)){
                        if($res_user[0]->mobile_phone != "")
                        $request_params["message"] .= "\nМобильный телефон: " . $res_user[0]->mobile_phone;
                    }if(isset($res_user[0]->home_phone)){
                        if($res_user[0]->home_phone != "")
                        $request_params["message"] .= "\nДомашний телефон: " . $res_user[0]->home_phone;
                    }if($data->object->message->peer_id != $data->object->message->from_id) {//Если сообщение в беседе добавляем
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                            $res_mes = $res->fetch_assoc();
                            if(isset($res_mes["id"])){
                                $request_params["message"] .= "\nКоличество сообщений в беседе: " . $res_mes["mes_count"] . "\nПоследняя активность: " . date("d.m.Y G:i", $res_mes["lastMes"]) . "по UTC 0";
                            }
                    }
                        if(isset($res_user[0]->can_post)){
                            if ($res_user[0]->can_post == 1) $request_params["message"] .= "\n\nУ пользователя открыта стена";
                            else $request_params["message"] .= "\n\nУ пользователя закрыта закрыта стена";
                        if(isset($res_user[0]->can_see_all_posts)){
                            if ($res_user[0]->can_see_all_posts == 1) $request_params["message"] .= ", запрещен просмотр чужих записей";
                            else $request_params["message"] .= ", разрешен просмотр чужих записей";
                        }if(isset($res_user[0]->can_see_audio)){
                            if ($res_user[0]->can_see_audio == 1) $request_params["message"] .= ", у пользователя открыты аудиозаписи";
                            else $request_params["message"] .= ", у пользователя закрыты аудиозаписи";
                        }if(isset($res_user[0]->can_send_friend_request)){
                            if ($res_user[0]->can_send_friend_request == 1) $request_params["message"] .= ", включены уведомления о заявках в друзья";
                            else $request_params["message"] .= ", выключены уведомления о заявках в друзья";
                        }if(isset($res_user[0]->can_write_private_message)){
                            if ($res_user[0]->can_write_private_message == 1) $request_params["message"] .= ", открыты сообщения";
                            else $request_params["message"] .= ", закрыты сообщения";
                        }if (isset($res_user[0]->has_photo)){
                            if ($res_user[0]->has_photo == 1) $request_params["message"] .= ", установлена своя аватарка";
                            else $request_params["message"] .= ", не установлена своя аватарка";
                        }
                    }if(isset($res_user[0]->skype)){
                        $request_params["message"] .= "\nSkype: " . $res_user[0]->skype;
                    }if(isset($res_user[0]->facebook)){
                        $request_params["message"] .= "\nFacebook: " . $res_user[0]->facebook;
                    }if(isset($res_user[0]->twitter)){
                        $request_params["message"] .= "\nTwitter: " . $res_user[0]->twitter;
                    }if(isset($res_user[0]->livejournal)){
                        $request_params["message"] .= "\nLiveJournal: " . $res_user[0]->livejournal;
                    }if(isset($res_user[0]->instagram)){
                        $request_params["message"] .= "\nInstagram: " . $res_user[0]->instagram;
                    }if(isset($res_user[0]->counters)){
                        $request_params["message"] .= "\n\nКоличество объектов: ";
                        if(isset($res_user[0]->counters->albums))
                            $request_params["message"] .= " альбомов: " . $res_user[0]->counters->albums;
                        if(isset($res_user[0]->counters->videos))
                            $request_params["message"] .= ", видеозаписей: " . $res_user[0]->counters->videos;
                        if(isset($res_user[0]->counters->audios))
                            $request_params["message"] .= ", аудиозаписей: " . $res_user[0]->counters->audios;
                        if(isset($res_user[0]->counters->photos))
                            $request_params["message"] .= ", фотографий: " . $res_user[0]->counters->photos;
                        if(isset($res_user[0]->counters->notes))
                            $request_params["message"] .= ", заметок: " . $res_user[0]->counters->notes;
                        if(isset($res_user[0]->counters->friends))
                            $request_params["message"] .= ", друзей: " . $res_user[0]->counters->friends;
                        if(isset($res_user[0]->counters->groups))
                            $request_params["message"] .= ", сообществ: " . $res_user[0]->counters->groups;
                        if(isset($res_user[0]->counters->online_friends))
                            $request_params["message"] .= ", друзей онлайн: " . $res_user[0]->counters->online_friends;
                        if(isset($res_user[0]->counters->user_videos))
                            $request_params["message"] .= ", видеозаписей с пользователем: " . $res_user[0]->counters->user_videos;
                        if(isset($res_user[0]->counters->followers))
                            $request_params["message"] .= ", подписчиков: " . $res_user[0]->counters->followers;
                        if(isset($res_user[0]->counters->pages))
                            $request_params["message"] .= ", интересных страниц: " . $res_user[0]->counters->pages ;
                    }if(isset($res_user[0]->career))
                        if($res_user[0]->career == array()){
                        $request_params["message"] .= "\n\nКарьера пользователя: ";
                        for ($i = 0; isset($res_user[0]->career[$i]); $i++){
                           $res_g = json_decode(json_encode($vk->groups()->getById(TOKEN_VK_BOT, array("group_id" => $res_user[0]->career[$i]->group_id))));
                           $request_params["message"] .= "[" . $res_g[0]->screen_name . "|" .$res_g[0]->name . "]";

                            if(isset($res_user[0]->career[$i]->from) && isset($res_user[0]->career[$i]->until))
                                $request_params["message"] .= " " . $res_user[0]->career[$i]->from . " - " . $res_user[0]->career[$i]->until;
                            elseif (isset($res_user[0]->career[$i]->from) && !isset($res_user[0]->career[$i]->until))
                                $request_params["message"] .= " с " . $res_user[0]->career[$i]->from;
                            elseif (!isset($res_user[0]->career[$i]->from) && isset($res_user[0]->career[$i]->until))
                                $request_params["message"] .= " до " . $res_user[0]->career[$i]->until;
                            if ($res_user[0]->career[$i]->country_id) {
                                $res_count = json_decode(json_encode($vk->database()->getCountriesById(SERVICE_KEY, array("country_ids" => $res_user[0]->career[$i]->country_id))));
                                $request_params["message"] .= ", страна: " . $res_count[0]->title;
                            }
                            if ($res_user[0]->career[$i]->city_id) {
                                $res_city = json_decode(json_encode($vk->database()->getCitiesById(SERVICE_KEY, array("city_ids" => $res_user[0]->career[$i]->city_id))));
                                $request_params["message"] .= ", город: " . $res_city[0]->title;
                            }if ($res_user[0]->career[$i]->position){
                                $request_params["message"] .= ", должность: " . $res_user[0]->career[$i]->position;
                            }

                            if(isset($res_user[0]->career[$i + 1]))
                                $request_params["message"] .= "; ";
                        }
                    }if($res_user[0]->university_name != ""){
                        $request_params["message"] .= "\n\nВысшее образование: " . $res_user[0]->university_name;
                        if(isset($res_user[0]->faculty_name))
                            if(!$res_user[0]->faculty_name == "")
                            $request_params["message"] .= ", " . $res_user[0]->faculty_name;
                        if(isset($res_user[0]->education_form))
                            $request_params["message"] .= ", форма обучения: " . $res_user[0]->education_form;
                        if(isset($res_user[0]->education_status))
                            $request_params["message"] .= ", статус: " . $res_user[0]->education_status;
                        if(isset($res_user[0]->graduation))
                            $request_params["message"] .= ", выпуск: " . $res_user[0]->graduation;
                    }if($res_user[0]->activities != ""){
                        $request_params["message"] .= "\nДеятельность пользователя: " . $res_user[0]->activities;
                    }if($res_user[0]->games != ""){
                        $request_params["message"] .= "\nЛюбимые игры: " . $res_user[0]->games;
                    }if($res_user[0]->interests != ""){
                        $request_params["message"] .= "\nИнтересы: " . $res_user[0]->interests;
                    }if($res_user[0]->about != ""){
                        $request_params["message"] .= "\nО пользователе: " . $res_user[0]->about;
                    }
                        error_log("fsfdd");
                }else{
                        $id = (int)substr($id, 1);
                        $res_grop = json_decode(json_encode($vk->groups()->getById(USER_TOKEN, array("group_id" => $id,
                            "fields" => "id,name,screen_name,is_closed,status,deactivated,type,activity,addresses,age_limits,can_create_topic,can_message,can_post,can_see_all_posts,can_upload_doc,can_upload_video,city,contacts,counters,country,cover,description,fixed_post,has_photo"))));

                        if(isset($res_grop[0]->deactivated)){
                            if($res_grop[0]->deactivated == "deleted")
                                $type = " удаленном ";
                            elseif ($res_grop[0]->deactivated == "banned")
                                $type = " заблокированном ";
                        }

                        $request_params["message"] = "Информация о". $type ." сообществе [club". $res_grop[0]->id . "|". $res_grop[0]->name ."]: \nАйди: " . $res_grop[0]->id;
                        if(isset($res_grop[0]->screen_name)){
                            $request_params["message"] .= "\nДомен: " . $res_grop[0]->screen_name;
                        }if(isset($res_grop[0]->status)){
                            $request_params["message"] .= "\nСтатус: " . $res_grop[0]->status;
                        }if(isset($res_grop[0]->is_closed) && isset($res_grop[0]->type)){
                            $request_params["message"] .= "\nТип: ";

                            if($res_grop[0]->type == "event"){
                                if($res_grop[0]->is_closed == 0)
                                    $request_params["message"] .= " открытое мероприятие";
                                elseif($res_grop[0]->is_closed == 1)
                                    $request_params["message"] .= " закрытое мероприятие";
                                elseif($res_grop[0]->is_closed == 2)
                                    $request_params["message"] .= " частное мероприятие";
                            }else{
                                if($res_grop[0]->is_closed == 0)
                                    $request_params["message"] .= " открытая";
                                elseif($res_grop[0]->is_closed == 1)
                                    $request_params["message"] .= " закрытая";
                                elseif($res_grop[0]->is_closed == 2)
                                    $request_params["message"] .= " частная";

                                if ($res_grop[0]->type == "group ")
                                    $request_params["message"] .= " группа";
                                elseif ($res_grop[0]->type == "page")
                                    $request_params["message"] .= " публичная страница";
                            }
                        }if(isset($res_grop[0]->age_limits)){
                            if($res_grop[0]->age_limits == 1)
                                $request_params["message"] .= "\nБез возрастного ограничения";
                            elseif($res_grop[0]->age_limits == 2)
                                $request_params["message"] .= "\nВозрастное ограничение 16+";
                            elseif($res_grop[0]->age_limits == 3)
                                $request_params["message"] .= "\nВозрастное ограничение 18+";
                        }if(isset($res_grop[0]->city->title)){
                            $request_params["message"] .= "\nГород: " . $res_grop[0]->city->title;
                        }if(isset($res_grop[0]->country->title)){
                            $request_params["message"] .= "\nСтрана: " . $res_grop[0]->country->title;
                        }if(isset($res_grop[0]->activity)) {
                            $request_params["message"] .= "\nТематика: " . $res_grop[0]->activity;
                        }if(isset($res_grop[0]->addresses->main_address_id)){
                            if($res_grop[0]->addresses->is_enabled)
                                $request_params["message"] .= "\nАдрес: " . $res_grop[0]->addresses->main_address_id . " (Я не знаю как это расшифровать)";
                        }
                        if($data->object->message->peer_id != $data->object->message->from_id) {//Если сообщение в беседе добавляем
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                            $res_mes = $res->fetch_assoc();
                            if(isset($res_mes["id"])){
                                $request_params["message"] .= "\nКоличество сообщений в беседе: " . $res_mes["mes_count"] . "\nПоследняя активность: " . date("d.m.Y G:i", $res_mes["lastMes"]) . "по UTC 0";
                            }
                        }
                        if(isset($res_grop[0]->description)) {
                            if($res_grop[0]->description != "")
                            $request_params["message"] .= "\n\nОписание: " . $res_grop[0]->description;
                        }

                        $request_params["message"] .= "\n\nУ сообщества ";

                        if(isset($res_grop[0]->can_create_topic)) {
                            if ($res_grop[0]->can_create_topic == 1) $request_params["message"] .= "включено добавление обсуждений пользователями ,";
                            else $request_params["message"] .= "выключено добавление обсуждений пользователями, ";
                        }
                            if (isset($res_grop[0]->can_message)){
                                if($res_grop[0]->can_message == 1) $request_params["message"] .= "включены сообщения, ";
                                else $request_params["message"] .= "выключены сообщения, ";
                            }
                            if (isset($res_grop[0]->can_post)){
                            if($res_grop[0]->can_post == 1) $request_params["message"] .= "открыта стена, ";
                            else $request_params["message"] .= "закрыта стена, ";
                            }
                            if (isset($res_grop[0]->can_see_all_posts)){
                                if($res_grop[0]->can_see_all_posts == 1) $request_params["message"] .= "открыты чужие записи, ";
                                else $request_params["message"] .= "скрыты чужие записи, ";
                            }
                            if (isset($res_grop[0]->can_upload_doc)){
                                if($res_grop[0]->can_upload_doc == 1) $request_params["message"] .= "включена загрузка документов пользователями, ";
                                else $request_params["message"] .= "выключена загрузка документов пользователями, ";
                            }
                            if (isset($res_grop[0]->can_upload_video)){
                                if($res_grop[0]->can_upload_video == 1) $request_params["message"] .= "включена загрузка видеозаписей пользователями, ";
                                else $request_params["message"] .= "выключена загрузка видеозаписей пользователями, ";
                            }
                            if (isset($res_grop[0]->cover->enabled)){
                                if($res_grop[0]->cover->enabled == 1) $request_params["message"] .= "установлена обложка, ";
                                else $request_params["message"] .= ", не установлена обложка";
                            }
                            if (isset($res_grop[0]->has_photo)){
                                if($res_grop[0]->has_photo == 1) $request_params["message"] .= "установлена аватарка, ";
                                else $request_params["message"] .= "не установлена аватарка, ";
                            }
                        $request_params["message"] = substr($request_params["message"],0, -2);

                        if(isset($res_grop[0]->contacts))
                            if($res_grop[0]->contacts !=array()){
                            $request_params["message"] .= "\n\nКонтакты:\n";
                            for ($i = 0; isset($res_grop[0]->contacts[$i]); $i++){
                                $res_user = json_decode(json_encode($vk->users()->get(TOKEN_VK_BOT, array("user_ids" => $res_grop[0]->contacts[$i]->user_id))));

                                $request_params["message"] .= "[id". $res_user[0]->id . "|". $res_user[0]->first_name ." " .$res_user[0]->last_name ."]";
                                if(isset($res_grop[0]->contacts[$i]->desc) || isset($res_grop[0]->contacts[$i]->phone) || isset($res_grop[0]->contacts[$i]->email)){
                                    $request_params["message"] .= " - ";
                                    if(isset($res_grop[0]->contacts[$i]->desc))
                                        $request_params["message"] .= "должность: " . $res_grop[0]->contacts[$i]->desc;
                                    if (isset($res_grop[0]->contacts[$i]->phone))
                                        if (isset($res_grop[0]->contacts[$i]->desc)) $request_params["message"] .= ", телефон: " . $res_grop[0]->contacts[$i]->phone;
                                        else $request_params["message"] .= "телефон: " . $res_grop[0]->contacts[$i]->phone;
                                    if (isset($res_grop[0]->contacts[$i]->email))
                                        if (isset($res_grop[0]->contacts[$i]->desc) || isset($res_grop[0]->contacts[$i]->phone)) $request_params["message"] .= ", Email: " . $res_grop[0]->contacts[$i]->email;
                                        else $request_params["message"] .= "Email: " . $res_grop[0]->contacts[$i]->email;
                                }

                                if (isset($res_grop[0]->contacts[$i + 1]))
                                    $request_params["message"] .= "\n";
                            }
                        }if(isset($res_grop[0]->counters)){
                            $request_params["message"] .= "\n\nОбъекты: ";
                            if(isset($res_grop[0]->counters->photos))
                                $request_params["message"] .= "фотографий: " . $res_grop[0]->counters->photos . ", ";
                            if(isset($res_grop[0]->counters->albums))
                                $request_params["message"] .= "альбомов: " . $res_grop[0]->counters->albums . ", ";
                            if(isset($res_grop[0]->counters->audios))
                                $request_params["message"] .= "аудиозаписей: " . $res_grop[0]->counters->audios . ", ";
                            if(isset($res_grop[0]->counters->videos))
                                $request_params["message"] .= "видеозаписей: " . $res_grop[0]->counters->videos . ", ";
                            if(isset($res_grop[0]->counters->topics))
                                $request_params["message"] .= "обсуждений: " . $res_grop[0]->counters->topics . ", ";
                            if(isset($res_grop[0]->counters->docs))
                                $request_params["message"] .= "документов: " . $res_grop[0]->counters->docs . ", ";

                            $request_params["message"] = substr($request_params["message"],0, -2);

                        }if(isset($res_grop[0]->fixed_post)){
                            $request_params["message"] .= "\n\nЗакреплённый пост:";
                            $request_params["attachment"] = "wall-" . $res_grop[0]->id . "_" . $res_grop[0]->fixed_post;
                        }
                    }
                }else $request_params["message"] = "Вы должны указать айди или переслать сообщение!";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Сократить ссылку") == 0){
                if(isset($text[2])){
                    if(mb_strcasecmp($text[3], "on") == 0 || mb_strcasecmp($text[3], "включить") == 0) $stat = 1;
                    else $stat = 0;
                    try {
                        $res_url = $vk->utils()->getShortLink(USER_TOKEN, array("url" => $text[2], "private" => $stat));
                    if($stat){
                        $request_params["peer_id"] = $data->object->message->from_id;
                        $request_params["message"] = "Ваш токен " . $res_url["access_key"] ." для просмотра статистики ссылки: " . $res_url["short_url"] . ", но учитывайте, что она обновляется каждые 10 минут!\nДля просмотра статистики напишите: /Получить статистику " . $res_url["short_url"] . " " . $res_url["short_url"];
                        try {
                            $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                            $request_params["message"] = "Ваша ссылка: " . $res_url["short_url"] . " токен для статистики отправлен вам в личные сообщения";
                        }catch (\VK\Exceptions\VKApiException $e){
                            $request_params["message"] = "Для просмотра статистики ссылки, мне нужно выслать вам в личные сообщение токен просмотра статистики. Пожалуйста разрешите отправку личных сообщений!";
                        }
                    }else
                        $request_params["message"] = "Ваша ссылка: " . $res_url["short_url"];
                    } catch (\VK\Exceptions\VKApiException $e) {
                        $request_params["message"] = "Что-то не так с ссылкой!";
                    } catch (\VK\Exceptions\VKClientException $e) {
                        $request_params["message"] = "Что-то не так с ссылкой!";
                    }
                }else $request_params["message"] = "Вы не указали ссылку!";

            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Получить статистику") == 0){
                if(isset($text[2])) {
                    if(isset($text[3])) {
                        try {
                            $res_url = $vk->utils()->getLinkStats(USER_TOKEN, getUrlParameters($text[2], $text[3]));
                        if(isset($res_url["stats"][0]["views"])){
                            $request_params["message"] = "Всего просмотров: " . $res_url["stats"][0]["views"] . "\n\nПросмотры по возрастным диапазонам:";
                            for ($i = 0; isset($res_url["stats"][0]["sex_age"][$i]); $i++){
                                $request_params["message"] .= "\n" . $res_url["stats"][0]["sex_age"][$i]["age_range"] . ", женщин: " . $res_url["stats"][0]["sex_age"][$i]["female"] . ", мужчин: " . $res_url["stats"][0]["sex_age"][$i]["male"];
                            }

                            $request_params["message"] .= "\n\nПросмотры по странам:";

                            for ($i = 0; isset($res_url["stats"][0]["countries"][$i]); $i++){
                                $res_count = $vk->database()->getCountriesById(SERVICE_KEY, array("country_ids" => $res_url["stats"][0]["countries"][$i]["country_id"]));
                                $request_params["message"] .= "\n" . $res_count[0]["title"] . ": " . $res_url["stats"][0]["countries"][$i]["views"];
                            }
                            $request_params["message"] .= "\n\nПросмотры по городам:";

                            for ($i = 0; isset($res_url["stats"][0]["cities"][$i]); $i++){
                                $res_count = $vk->database()->getCitiesById(SERVICE_KEY, array("city_ids" => $res_url["stats"][0]["cities"][$i]["city_id"]));
                                $request_params["message"] .= "\n" . $res_count[0]["title"] . ": " . $res_url["stats"][0]["cities"][$i]["views"];
                            }

                        }else $request_params["message"] = "Пока не было переходов по этой ссылке!";
                        } catch (\VK\Exceptions\Api\VKApiNotFoundException $e) {
                            $request_params["message"] = "Что-то не так с ссылкой или токеном!1";
                        } catch (\VK\Exceptions\VKApiException $e) {
                            $request_params["message"] = "Что-то не так с ссылкой или токеном!2";
                        } catch (\VK\Exceptions\VKClientException $e) {
                            $request_params["message"] = "Что-то не так с ссылкой или токеном!3";
                        }
                    }else $request_params["message"] = "Вы не указали токен для просмотра статистики!";
                }else $request_params["message"] = "Вы не указали ссылку!";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Инвайт ссылка") == 0 || mb_strcasecmp($text[0] . " " .$text[1], "/ссылка приглашения") == 0 || mb_strcasecmp($text[0], "/Приглашение") == 0){
                $res = $mysqli->query("SELECT `invite_link` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                $res = $res->fetch_assoc();
                if(isset($res["invite_link"])){
                    if(mb_strcasecmp($res["invite_link"], "") != 0 || $res["invite_link"] != null)
                        $request_params["message"] = "Ссылка для приглашения: " . $res["invite_link"];
                    else $request_params["message"] = "Администрация беседы не указала ссылку для приглашения";
                }else $request_params["message"] = "Эта команда не для личных сообщений или вашей беседы нету в базе данных!";

            }elseif(mb_strcasecmp($text[0], "/Пригласить") == 0){
                $id = getId($text[1],$data->object->message->reply_message->from_id);
                if($id != 0) {
                    $res = $mysqli->query("SELECT `invite_link` FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                    $res = $res->fetch_assoc();
                    if (isset($res["invite_link"])) {
                        if (mb_strcasecmp($res["invite_link"], "") != 0 || $res["invite_link"] != null) {
                            if ($id > 0) {
                                $request_params["message"] = "Пользователь ";
                            } else $request_params["message"] = "Сообщество ";
                            $res_title = $vk->messages()->getConversationsById(TOKEN_VK_BOT, array("peer_ids" => $data->object->message->peer_id));
                            if(isset($res_title["items"][0]["chat_settings"])) {
                                try {
                                    $request_params["message"] .= getName($vk, $data->object->message->from_id)[0] . " приглашает вас вступить в беседу: \"" . $res_title["items"][0]["chat_settings"]["title"] . "\", ссылка для вступления - " . $res["invite_link"];
                                    if (isset($data->object->message->reply_message->from_id))
                                        $mes = 1;
                                    else $mes = 2;
                                    if (isset($text[$mes])) {
                                        $request_params["message"] .= "\nКомментарий:";
                                        for ($i = $mes; isset($text[$i]); $i++) {
                                            $request_params["message"] .= " " . $text[$i];
                                        }
                                    }
                                    $upload_server = $vk->photos()->getMessagesUploadServer(TOKEN_VK_BOT, array("peer_id" => $id));
                                    if(!is_dir("temp")) {
                                        mkdir("temp", 0777, true);
                                    }
                                    file_put_contents('temp/photo.jpg', file_get_contents($res_title["items"][0]["chat_settings"]["photo"]["photo_200"]));
                                    $photo = $vk->getRequest()->upload($upload_server["upload_url"], 'photo', realpath('temp/photo.jpg'));
                                    $res_photo = $vk->photos()->saveMessagesPhoto(TOKEN_VK_BOT, array(
                                        'server' => $photo['server'],
                                        'photo' => $photo['photo'],
                                        'hash' => $photo['hash']
                                    ));
                                    unlink(realpath('temp/photo.jpg'));
                                    $request_params["attachment"] = "photo" . $res_photo[0]["owner_id"] . "_" . $res_photo[0]["id"] . "_" . $res_photo[0]["access_key"];
                                    $request_params["message"] .= "\nАватарка:";
                                    $request_params["peer_id"] = $id;
                                    $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                                    $request_params["message"] = "Приглашение успешно отправлено!";
                                } catch (VK\Exceptions\Api\VKApiMessagesChatUserNoAccessException $e) {
                                    $request_params["message"] = "Пользователь не предоставил доступ к личным сообщениям";
                                } catch (\VK\Exceptions\Api\VKApiMessagesPrivacyException $e) {
                                    $request_params["message"] = "Пользователю ограничил отправку сообщений";
                                } catch (\VK\Exceptions\Api\VKApiMessagesUserBlockedException $e) {
                                    $request_params["message"] = "Пользователь заблокирован";
                                } catch (VK\Exceptions\VKApiException $e) {
                                    $request_params["message"] = "Пользователь не разрешил отправку личных сообщений";
                                }
                                $request_params["peer_id"] = $data->object->message->peer_id;
                                $request_params["attachment"] = "";
                            }else $request_params["message"] = "Для этой функции мне необходимы права администратора!";
                        }else $request_params["message"] = "Администрация беседы не указала ссылку для приглашения";
                    } else $request_params["message"] = "Эта команда не для личных сообщений или вашей беседы нету в базе данных!";
                } else $request_params["message"] = "Вы не указали айди пользователя";
            }elseif(mb_strcasecmp($text[0], "/список") == 0){
                error_log("tack");
                if (isset($text[1])){
                    $empty_list = true;
                    switch (mb_strtolower($text[1])){
                        case "пользователей":
                            $request_params["message"] = "Список пользователей в чате:";
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_users`");
                            $res_ids = array();
                            $res_fields = array();
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                                $res_fields[] = array($res_users["rang"],$res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                            }
                            if(!$empty_list){
                                foreach (getName($vk, $res_ids, false) as $key => $name){
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key] .", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: ". $res_fields[$key][1] .", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                }
                            }
                            
                            break;
                        case "забаненных":
                            $request_params["message"] = "Список забаненных пользователей в чате:";
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_bans`");
                            $res_ids = array();
                            $res_fields = array();
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                                $res_fields[] = $res_users["ban"];
                            }
                            if(!$empty_list){
                                foreach (getName($vk, $res_ids) as $key => $name){
                                    if($res_fields[$key][0] == 0)
                                        $type = " навсегда";
                                    else
                                        $type = " до " . date("d.m.Y G:i", $res_fields[$key]) . "по UTC 0";
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key] .", забанен" . $type;
                                }
                            }
                            break;
                        case "вышедших":
                            $request_params["message"] = "Список вышедших из чата пользователей:";
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_leave`");
                            $res_ids = array();
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                            }
                            if(!$empty_list){
                                foreach (getName($vk, $res_ids) as $key => $name){
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key];
                                }
                            }
                            break;
                        case "модераторов":
                            $request_params["message"] = "Список модераторов чата:";
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_moders`");
                            $res_ids = array();
                            $res_fields = array();
                            $mysqli_query = "SELECT `rang`,`pred` FROM `". $data->object->message->peer_id ."_users` WHERE ";
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                                $mysqli_query .= "`id` = " . $res_users["id"] . " OR ";
                                $res_fields[] = array($res_users["preds"],$res_users["kicks"], $res_users["tempbans"], $res_users["bans"]);
                            }
                            if(!$empty_list){
                                $res_rang = $mysqli->query(mb_substr($mysqli_query,0,-4));
                                foreach (getName($vk, $res_ids, false) as $key => $name){
                                    $res_rang_id = $res_rang->fetch_assoc();
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key] .", ранг: " . getRang($res_rang_id["rang"]) . ", количество предупреждений: " . $res_rang_id["pred"] . ", количество выданных предупреждений: " . $res_fields[$key][0] . ", количество исключенных пользователей: " . $res_fields[$key][1] . ", количество временно забаненных пользователей: " . $res_fields[$key][2] . ", количество забанных пользователей: " . $res_fields[$key][3];
                                }
                            }
                            break;
                        case "неактивных":
                            $request_params["message"] = "Список неактивных больше недели пользователей в чате:";
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_users`");
                            $res_ids = array();
                            $res_fields = array();
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                                $res_fields[] = array($res_users["rang"],$res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                            }
                            if(!$empty_list){
                                foreach (getName($vk, $res_ids, false) as $key => $name){
                                    if(time() - $res_fields[$key][3] > 604800) //604800 - одна неделя
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key] .", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: ". $res_fields[$key][1] .", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                }
                            }
                            break;

                        case "онлайна":
                            $request_params["message"] = "Список онлайн пользователей в чате:";
                            $res = $vk->messages()->getConversationMembers(TOKEN_VK_BOT, array("peer_id" => $data->object->message->peer_id, "fields" => "online"));
                            $mysqli_query = "SELECT * FROM `". $data->object->message->peer_id ."_users` WHERE ";
                            for ($i = 0; isset($res["items"][$i]); $i++){
                                if($res["profiles"][$i]["online"] == 1)
                                    $mysqli_query .= "`id` = '". $res["profiles"][$i]["id"] ."' OR ";
                            }
                            $res = $mysqli->query(mb_substr($mysqli_query,0,-4));
                            $res_ids = array();
                            $res_fields = array();
                            while($res_users = $res->fetch_assoc()){
                                $empty_list = false;
                                $res_ids[] = $res_users["id"];
                                $res_fields[] = array($res_users["rang"],$res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                            }
                            if(!$empty_list){
                                foreach (getName($vk, $res_ids, false) as $key => $name){
                                    $request_params["message"] .= "\n" . $name . ", айди: ". $res_ids[$key] .", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: ". $res_fields[$key][1] .", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                }
                            }
                            break;
                        default:
                            $request_params["message"] = "Не верно указан список! Возможные значения: пользователей, забаненных, вышедших, модераторов, неактивных, онлайна";
                            $empty_list = false;
                            break;
                    }

                    if($empty_list)
                        $request_params["message"] = "Список пуст";
                }else $request_params["message"] = "Вы не указали список!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Лимит модераторов") == 0){
                for ($i = 1; $i < 4; $i++) {
                    $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_moders_limit` WHERE `rang` = " . $i);
                    $res = $res->fetch_assoc();
                    $request_params["message"] .= "Для $i уровня: ";
                    if ($res["pred"] != 0) {
                        if (($res["pred"] >= 11 && $res["pred"] <= 19) || (endNumber($res["pred"]) >= 5 && endNumber($res["pred"]) <= 9) || endNumber($res["pred"]) == 0)
                            $request_params["message"] .= " ". $res["pred"] . " предупреждений,";
                        elseif (endNumber($res["pred"]) == 1)
                            $request_params["message"] .= " ". $res["pred"] . " предупреждение,";
                        elseif (endNumber($res["pred"]) >= 2 && endNumber($res["pred"]) <= 4)
                            $request_params["message"] .= " ". $res["pred"] . " предупреждения,";
                    }
                    if ($res["kick"] != 0) {
                        if (($res["kick"] >= 11 && $res["kick"] <= 19) || (endNumber($res["kick"]) >= 5 && endNumber($res["kick"]) <= 9) || endNumber($res["kick"]) == 0)
                            $request_params["message"] .= " ". $res["kick"] . " исключений,";
                        elseif (endNumber($res["kick"]) == 1)
                            $request_params["message"] .= " ". $res["kick"] . " исключение,";
                        elseif (endNumber($res["kick"]) >= 2 && endNumber($res["kick"]) <= 4)
                            $request_params["message"] .= " ". $res["kick"] . " исключения,";
                    }if ($res["tempban"] != 0) {
                        if (($res["tempban"] >= 11 && $res["tempban"] <= 19) || (endNumber($res["tempban"]) >= 5 && endNumber($res["tempban"]) <= 9) || endNumber($res["tempban"]) == 0)
                            $request_params["message"] .= " ". $res["tempban"] . " временных блокировок,";
                        elseif (endNumber($res["tempban"]) == 1)
                            $request_params["message"] .= " ". $res["tempban"] . " временная блокировка,";
                        elseif (endNumber($res["tempban"]) >= 2 && endNumber($res["tempban"]) <= 4)
                            $request_params["message"] .= " ". $res["tempban"] . " временных блокировки,";
                    }
                    $request_params["message"] = mb_substr($request_params["message"], 0 ,-1);
                    $request_params["message"] .= "\n";
                }
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/История наказаний") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/History punishment") == 0){
                if(isset($text[2]))
                    if((int)$text[2] != 0)
                        $count = (int)$text[2];
                    else
                        $count = 100;
                else
                    $count = 100;

                $res_history = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_punishments`");
                $punishments = array();
                while ($temp = $res_history->fetch_assoc()){
                    if($temp != null)
                    $punishments[] = $temp;
                }
                if($count > count($punishments))
                    $count = count($punishments);
                if (($count >= 11 && $count <= 19) || (endNumber($count) >= 5 && endNumber($count) <= 9) || endNumber($count) == 0)
                    $request_params["message"] = "Последние ". $count ." наказаний:";
                elseif (endNumber($count) == 1)
                    $request_params["message"] = "Последнее ". $count ." наказание:";
                elseif (endNumber($count) >= 2 && endNumber($count) <= 4)
                    $request_params["message"] = "Последние ". $count ." наказания:";
                ob_start();
                var_dump($punishments);
                error_log(ob_get_contents());
                ob_end_clean();
                for ($i = 0; $i <= $count; $i++){
                    $res = $punishments[count($punishments) - 1 - $i];
                    $names = getName($vk, array($res["id"], $res["id_moder"]));
                    ob_start();
                    var_dump($names);
                    error_log(ob_get_contents());
                    ob_end_clean();
                    $request_params["message"] .= "\n". $names[1];
                    switch ($res["type"]){
                        case "kick":
                            $request_params["message"] .= " исключил пользователя " . $names[0];
                            break;
                        case "tempban":
                            $request_params["message"] .= " забанил до " . date("d.m.Y G:i", $res["parametr"]) . "по UTC 0 пользователя " . $names[0];
                            break;
                        case "ban":
                            $request_params["message"] .= " забанил пользователя ". $names[0];
                            break;
                        case "pred":
                            $request_params["message"] .= " выдал ";
                            if (($res["parametr"] >= 11 && $res["parametr"] <= 19) || (endNumber($res["parametr"]) >= 5 && endNumber($res["parametr"]) <= 9) || endNumber($res["parametr"]) == 0)
                                $request_params["message"] .= " ". $res["parametr"] . " предупреждений пользователю " . $names[0] ;
                            elseif (endNumber($res["parametr"]) == 1)
                                $request_params["message"] .= " ". $res["parametr"] . " предупреждение пользователю ". $names[0];
                            elseif (endNumber($res["parametr"]) >= 2 && endNumber($res["parametr"]) <= 4)
                                $request_params["message"] .= " ". $res["parametr"] . " предупреждения пользователю ". $names[0];
                            break;
                        default:
                            $request_params["message"] .= " " . $res["type"] . " пользователя ". $names[0];
                            break;
                    }
                    if ($res["text"] != "")
                        $request_params["message"] .= ", причина: " . $res["text"];
                }

            }elseif(mb_strcasecmp($text[0], "/Предупреждение") == 0 || mb_strcasecmp($text[0], "/пред") == 0){
                $get_rang = $mysqli->query("SELECT `rang` FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                $get_rang = $get_rang->fetch_assoc();
                if($get_rang["rang"] >= 1){
                    $id = getId($text[1],$data->object->message->reply_message->from_id);
                    if($id != 0){
                        $num_num = 2;
                        if (isset($data->object->message->reply_message->from_id))
                            $num_num = 1;
                        if (isset($text[$num_num])) $num = (int)$text[$num_num]; else $num = 1;
                        $reason = "";
                        for ($i = 1; isset($text[$num_num + $i]); $i++){
                            $reason .= $text[$num_num + $i] . " ";
                        }
                        $reason = mb_substr($reason, 0 , -1);

                        $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `pred` = `pred` + ". $num ." WHERE `id` = '" . $id . "'");
                        $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders` SET `preds` = `preds` + 1 WHERE `id` = '" . $data->object->message->from_id . "'");
                        $mysqli->query("INSERT INTO `". $data->object->message->peer_id ."_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() .", '". $id ."', '". $data->object->message->from_id ."', 'pred', '". $reason ."', '". $num ."')");
                        track($mysqli, $id, $data->object->message->from_id, $num, $reason);
                        $request_params["message"] = "Пользователю " . getName($vk, array($id))[0] . " выдано ";
                        if (($num >= 11 && $num <= 19) || (endNumber($num) >= 5 && endNumber($num) <= 9) || endNumber($num) == 0)
                            $request_params["message"] .= $num . " предупреждений";
                        elseif (endNumber($num) == 1)
                            $request_params["message"] .= $num . " предупреждение";
                        elseif (endNumber($num) >= 2 && endNumber($num) <= 4)
                            $request_params["message"] .= $num . " предупреждения";
                        if($reason != "") $request_params["message"] .= ", по причине: " . $reason;
                    }else $request_params["message"] = "Вы не указали айди пользователя!";
                }else $request_params["message"] = "Для использования этой команды вы должны быть модератором 1 уровня или выше!";
            }elseif(mb_strcasecmp($text[0], "/Онлайн") == 0 || mb_strcasecmp($text[0], "/Online") == 0){
                $get_rang = $mysqli->query("SELECT `rang` FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                $get_rang = $get_rang->fetch_assoc();
                if($get_rang["rang"] >= 1){

                }else $request_params["message"] = "Для использования этой команды вы должны быть модератором 1 уровня или выше!";
            }



        if(isset($data->object->message->action->type))//Инвайты
            if($data->object->message->action->type == "chat_invite_user" || $data->object->message->action->type == "chat_invite_user_by_link"){
                if($data->object->message->action->member_id == (int)("-".$data->group_id))
                    $request_params["message"] = "Для моей работы мне необходимы права администратора. Выдайте права и напишите /начать";

                $mysqli->query("INSERT INTO `". $data->object->message->peer_id ."_users` (`id`) VALUES ('". $data->object->message->action->member_id ."')");
                $res = $mysqli->query("SELECT `greeting` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                $res = $res->fetch_assoc();
                if($res["greeting"] != ""){
                    $greeting = "";
                    $greeting_temp = "";
                    $greeting_temps = explode("{first_name}", $res["greeting"]);
                    for ($i = 0; isset($greeting_temps[$i]); $i++){
                        if (!isset($greeting_temps[$i + 1]))
                            $greeting_temp .=  $greeting_temps[$i];
                        else
                            $greeting_temp .=  $greeting_temps[$i] . explode(" ", getName($vk, array($data->object->message->action->member_id))[0])[0] . "]";
                    }
                    $greeting_temps = explode("{last_name}", $greeting_temp);
                    for ($i = 0; isset($greeting_temps[$i]); $i++){
                        if (!isset($greeting_temps[$i + 1]))
                            $greeting .=  $greeting_temps[$i];
                        else {
                            $temp = explode(" ", getName($vk, array($data->object->message->action->member_id))[0]);
                            if(isset($temp[1])) {
                                $var = explode("|", $temp[0]);

                                $greeting .= $greeting_temps[$i] . $var[0] . "|" .$temp[1];
                            }
                            else $greeting .= $greeting_temps[$i] . $temp[0] . "]";
                        }
                    }
                    $request_params["message"] = $greeting;
                }


            }

            try {
                $vk->messages()->send(TOKEN_VK_BOT, $request_params);
            } catch (\VK\Exceptions\VKApiException $e) {
            }

            echo "ok";

            if($data->object->message->peer_id != $data->object->message->from_id) { //Если сообщение в беседе добавляем + 1 к количеству сообщений пользователя и бота
                $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `mes_count`= `mes_count` + 1, `lastMes` = ". time() ." WHERE `id` = '" . $data->object->message->from_id . "' OR `id` = '" . (int)("-" . $data->group_id) . "'");
            }
        break;

}
function track($mysqli, $id_warn, $id_moder, $num, $reason){

}

function mb_strcasecmp($str1, $str2, $encoding = null) { //https://www.php.net/manual/en/function.strcasecmp.php#107016 взято от сюда
    if (null === $encoding) { $encoding = mb_internal_encoding(); }
    return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}

function convertTime($times){
    $time_return = 0;
    $times = explode(":", $times);
    if(isset($times[0]))
        $time_return += (int)$times[0];
    if(isset($times[1]))
        $time_return += (int)$times[1] * 60;
    if(isset($times[2]))
        $time_return += (int)$times[2] * 60 * 60;
    if(isset($times[3]))
        $time_return += (int)$times[3] * 24 * 60 * 60;
    if(isset($times[4]))
        $time_return += (int)$times[4] * 31 * 24 * 60 * 60;

    return $time_return;
}

function getTime($time){
    $time_string = "";
    if ($time != 0) {
        $time = array(intdiv($time,60), $time%60);
        if((int)$time[1] != 0) {
            if (((int)$time[1] >= 11 && (int)$time[1] <= 19) || (endNumber((int)$time[1]) >= 5 && endNumber((int)$time[1]) <= 9) || endNumber((int)$time[1]) == 0)
                $time_string .= $time[1] . " секунд ";
            elseif (endNumber((int)$time[1]) == 1)
                $time_string .= $time[1] . " секунду ";
            elseif (endNumber((int)$time[1]) >= 2 && endNumber((int)$time[1]) <= 4)
                $time_string .= $time[1] . " секунды ";
        }

        if ($time[0] != 0){
            $time = array(intdiv($time[0],60), $time[0]%60);
            if((int)$time[1] != 0) {
                if (((int)$time[1] >= 11 && (int)$time[1] <= 19) || (endNumber((int)$time[1]) >= 5 && endNumber((int)$time[1]) <= 9) || endNumber((int)$time[1]) == 0)
                    $time_string .= $time[1] . " минут ";
                elseif (endNumber((int)$time[1]) == 1)
                    $time_string .= $time[1] . " минуту ";
                elseif (endNumber((int)$time[1]) >= 2 && endNumber((int)$time[1]) <= 4)
                    $time_string .= $time[1] . " минуты ";
            }

            if ($time[0] != 0){
                $time = array(intdiv($time[0],24), $time[0]%24);
                if((int)$time[1] != 0) {
                    if (((int)$time[1] >= 11 && (int)$time[1] <= 19) || (endNumber((int)$time[1]) >= 5 && endNumber((int)$time[1]) <= 9) || endNumber((int)$time[1]) == 0)
                        $time_string .= $time[1] . " часов ";
                    elseif (endNumber((int)$time[1]) == 1)
                        $time_string .= $time[1] . " час ";
                    elseif (endNumber((int)$time[1]) >= 2 && endNumber((int)$time[1]) <= 4)
                        $time_string .= $time[1] . " часа ";
                }

                if ($time[0] != 0){
                    $time = array(intdiv($time[0],31), $time[0]%31);
                    if((int)$time[1] != 0) {
                        if (((int)$time[1] >= 11 && (int)$time[1] <= 19) || (endNumber((int)$time[1]) >= 5 && endNumber((int)$time[1]) <= 9) || endNumber((int)$time[1]) == 0)
                            $time_string .= $time[1] . " дней ";
                        elseif (endNumber((int)$time[1]) == 1)
                            $time_string .= $time[1] . " день ";
                        elseif (endNumber((int)$time[1]) >= 2 && endNumber((int)$time[1]) <= 4)
                            $time_string .= $time[1] . " дня ";
                    }

                    if ($time[0] != 0){
                        $time = array(intdiv($time[0],12), $time[0]%12);
                        if((int)$time[1] != 0) {
                            if (((int)$time[1] >= 11 && (int)$time[1] <= 19) || (endNumber((int)$time[1]) >= 5 && endNumber((int)$time[1]) <= 9) || endNumber((int)$time[1]) == 0)
                                $time_string .= $time[1] . " месяцев ";
                            elseif (endNumber((int)$time[1]) == 1)
                                $time_string .= $time[1] . " месяц ";
                            elseif (endNumber((int)$time[1]) >= 2 && endNumber((int)$time[1]) <= 4)
                                $time_string .= $time[1] . " месяца ";
                        }

                        if ($time[0] != 0){
                            if((int)$time[1] != 0) {
                                if (((int)$time[0] >= 11 && (int)$time[0] <= 19) || (endNumber((int)$time[0]) >= 5 && endNumber((int)$time[0]) <= 9) || endNumber((int)$time[0]) == 0)
                                    $time_string .= $time[0] . " лет ";
                                elseif (endNumber((int)$time[0]) == 1)
                                    $time_string .= $time[0] . " год ";
                                elseif (endNumber((int)$time[0]) >= 2 && endNumber((int)$time[0]) <= 4)
                                    $time_string .= $time[0] . " года ";
                            }
                        }
                    }
                }
            }
        }
    }else $time_string = "0 секунд";
    return $time_string;
}

function endNumber($number){
    return round(($number/10 - intdiv($number, 10)) * 10);
}

function getRang($id){
    switch ($id){
        case 0:
            return "пользователь";
            break;
        case 1:
            return "модератор 1 уровня";
            break;
        case 2:
            return "модератор 2 уровня";
            break;
        case 3:
            return "модератор 3 уровня";
            break;
        case 4:
            return "модератор 4 уровня";
            break;
        case 5:
            return "администратор";
            break;
        default:
            return "Неизвестный ранг";
            break;
    }
}

function getName($vk, $ids, $notify = true){
    $user_ids = array(); $group_ids = array(); $names = array();
    foreach ($ids as $num => $id){
        if($id>0)
            $user_ids[$num] = $id;
        else
            $group_ids[$num] = $id;
    }
    $res_user = $vk->users()->get(TOKEN_VK_BOT, array("user_ids" => implode(",", $user_ids)));
    $res_group = $vk->groups()->getById(TOKEN_VK_BOT, array("group_ids" => implode(",", $group_ids)));
    $ifor = 0;
    foreach ($user_ids as $key => $id) {
        $user_ids[$key] = $res_user[$ifor];
        $ifor++;
    }
    $ifor = 0;
    foreach ($group_ids as $key => $id) {
        $group_ids[$key] = $res_group[$ifor];
        $ifor++;
    }

    for($i = 0; $i < count($ids); $i++){
        if(isset($user_ids[$i])){
            if ($notify)
                $names[] = "[id" . $user_ids[$i]["id"] . "|" . $user_ids[$i]["first_name"] . " " . $user_ids[$i]["last_name"] . "]";
            else
                $names[] = $user_ids[$i]["first_name"] . " " . $user_ids[$i]["last_name"];
        }else{
            if ($notify)
                $names[] = "[club" . $group_ids[$i]["id"] . "|" . $group_ids[$i]["name"] . "]";
            else
                $names[] = $group_ids[$i]["name"];
        }
    }

    return $names;

}

function getId($text = '', $reply_to = ''){
if(isset($text) || isset($reply_to)){
    if(isset($reply_to)){
        return (int)$reply_to;
    }else
        if(substr($text,0,5) == "[club")
            return (int)("-".substr(explode("|", $text)[0], 5));
        else return (int)substr(explode("|",$text)[0], 3);

}else return 0;
}
function getUrlParameters($url, $token){
    $key = ""; $domain = "";
    for ($i = strlen($url) - 1; $i >= 0; $i--){
        if($url[$i] == "/"){
            if($i == strlen($url) - 1)
                $url = substr($url, 0, $i);
            elseif($key == "" ){
                $key = substr($url, $i + 1);
                $url = substr($url, 0, $i);
            }
            elseif ($domain == "") {
                $domain = str_replace(".", "_", substr($url, $i + 1));
            }
        }

    }

    return array("access_key" => $token, "key" => $key, "source" => $domain, "interval" => "forever", "extended" => 1);
}

function createTabs($chat_id, $mysqli, $vk){
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_users`(`id` VarChar( 255 ) NOT NULL, `rang` TinyInt( 255 ) NOT NULL DEFAULT 0, `pred` TinyInt( 255 ) NOT NULL DEFAULT 0, `mes_count` Int( 255 ) NOT NULL DEFAULT 0, `lastMes` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_punishments`(`time` Int( 255 ) NOT NULL, `id` VarChar( 255 ) NOT NULL, `id_moder` VarChar( 255 ) NOT NULL, `type` VarChar( 255 ) NOT NULL, `text` VarChar( 255 ) NOT NULL DEFAULT '', `parametr` VarChar( 255 ) NOT NULL DEFAULT '', CONSTRAINT `unique_time` UNIQUE( `time` )) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders`( `id` VarChar( 255 ) NOT NULL, `bans` Int( 255 ) NOT NULL DEFAULT 0, `kicks` Int( 255 ) NOT NULL DEFAULT 0, `tempbans` Int( 255 ) NOT NULL DEFAULT 0, `preds` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` )) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_leave`(`id` VarChar( 255 ) NOT NULL, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_bans`(`id` VarChar( 255 ) NOT NULL, `reason` VarChar( 255 ) NOT NULL DEFAULT '', `ban` Int( 255 ) NOT NULL DEFAULT 0 ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `chats_settings`(`chat_id` VarChar( 255 ) NOT NULL, `invite_link` VarChar( 255 ) NOT NULL DEFAULT '',`autokickBot` TinyInt( 1 ) NOT NULL DEFAULT 1, `autokickLeave` TinyInt( 1 ) NOT NULL DEFAULT 0, `greeting` VarChar( 255 ) NULL DEFAULT '', `tracking` VarChar( 255 ) NULL DEFAULT '', `predsvarn` VarChar( 255 ) NOT NULL DEFAULT 'kick:10', `autoremovepred` Int( 255 ) NOT NULL, `lastRemovePred` Int( 255 ) NOT NULL,CONSTRAINT `unique_chat_id` UNIQUE( `chat_id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders_limit`(`rang` VarChar( 255 ) NOT NULL, `pred` Int( 255 ) NULL, `kick` Int( 255 ) NULL, `tempban` Int( 255 ) NULL, CONSTRAINT `unique_rang` UNIQUE( `rang` )) ENGINE = InnoDB;");

    $res = $vk->messages()->getConversationMembers(TOKEN_VK_BOT, array("peer_id" => $chat_id));
    for ($i = 0; isset($res["items"][$i]); $i++){
        $rang = 0;
        if($res["items"][$i]["is_admin"]) $rang = 5;
        $mysqli->query("INSERT INTO `". $chat_id ."_users` (`id`, `rang`) VALUES ('". $res->items[$i]->member_id ."', ". $rang .")");
    }

    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (1, 5, 0, 0)");
    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (2, 6, 2, 0)");
    $mysqli->query("INSERT INTO `". $chat_id ."_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (3, 10, 4, 2)");
    $mysqli->query("INSERT INTO `chats_settings` (`chat_id`, `autoremovepred`, `lastRemovePred`) VALUES (". $chat_id .",". 2678400 . "," . time() .")");
}
?>