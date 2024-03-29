<?php
require('../vendor/autoload.php');

define("CONFIRMATION_TOKEN_VK_BOT", getenv("CONFIRMATION_TOKEN_VK_BOT")); //подтверждение
define("TOKEN_VK_BOT", getenv("TOKEN_VK_BOT")); //Ключ доступа сообщества
define("SECRET_KEY_VK_BOT", getenv("SECRET_KEY_VK_BOT")); //Secret key
define("USER_TOKEN", getenv("USER_TOKEN")); /*Токен пользователя нужен для показа полной информации о группах и людях,
 если не хотите его указывать, вызовите getenv("TOKEN_VK_BOT")*/
define("SERVICE_KEY", getenv("SERVICE_KEY")); //Сервисный ключ


ini_set('max_execution_time', 0);

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
            $is_varn = false;
            $is_chat = $data->object->message->peer_id != $data->object->message->from_id;

            if($is_chat) { //Если сообщение в беседе добавляем + 1 к количеству сообщений пользователя и бота
                $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `mes_count`= `mes_count` + 1, `lastMes` = ". time() ." WHERE `id` = '" . $data->object->message->from_id . "' OR `id` = '" . (int)("-" . $data->group_id) . "'");
                //Очищаем преды
                $res = $mysqli->query("SELECT `lastRemovePred`, `autoremovepred` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                if($res != false){
                    $res = $res->fetch_assoc();
                    if($res["autoremovepred"] != 0)
                        if($res["lastRemovePred"] + $res["autoremovepred"] <= time()){
                            $mysqli->query("UPDATE `". $data->object->message->peer_id ."_users` SET `pred` = 0");
                            $mysqli->query("UPDATE `chats_settings` SET `lastRemovePred` = " . time() . " WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                        }
                }
            }

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
                    . "&#128196;/ — информация о боте и команды\n"
                    . "&#128100;/User info|Информация пользователя {@Айди|@домен|Пересланное сообщение} — информация пользователя в вк и чате\n"
                    . "&#128279;/Сократить ссылку {ссылка} [Включить|выключить|on|off] — сокращает ссылку через сервис вк, on - включает статистику\n"
                    . "&#128196;/Получить статистику {ссылка} {токен} — выводит статистику переходов по сокращенный ссылке (желательно использовать эту команду в личных сообщениях)\n"
                    . "&#128279;/Инвайт ссылка|Приглашение|Ссылка приглашение - выводит ссылку на приглашение в этот чат\n"
                    . "&#128075;/Пригласить {@Айди|@домен|Пересланное сообщение} [Сообщение] - отправляет приглашение пользователю в этот чат\n"
                    . "&#128195;/Список {Пользователей|забаненных|вышедших|модераторов|неактивных|онлайна} - выводит указанный список пользователей\n"
                    . "&#128221;/Chat settings|настройки беседы|чата - показывает текущий список настроек\n"
                    . "&#128127;/History punishment|/История наказаний [число] - показывает историю последних наказаний, по умолчанию 100\n"
                    . "&#128124;/Лимит модераторов - выводит лимит для модераторов\n\n"
                    . "&#128101;Модерация и Администрация:\n"
                    . "&#128110;/Предупреждение|Пред|Pred {@Айди|@домен|Пересланное сообщение} [Количество] [Причина] - Выдать предупреждение, по умолчанию 1 предупреждение\n"
                    . "&#9728;/Удалить предупреждение|пред {@Айди|@домен|Пересланное сообщение} [Количество] [Причина] - Удалить предупреждения, по умолчанию всё предупреждения\n"
                    . "&#128546;/Кик|Исключить|Kick {@Айди|@домен|Пересланное сообщение} [Причина] - Исключить пользователя из чата\n"
                    . "&#128127;/Временно забанить|temp ban {@Айди|@домен|Пересланное сообщение} {Время SS:MM:HH:DDD:MM:YY} [Причина] - Временно забанить пользователя в беседе\n"
                    . "&#128128;/Бан|Ban {@Айди|@домен|Пересланное сообщение} [Причина] - Забанить пользователя\n"
                    . "&#128124;/Разбанить|Пардон|Unban {@Айди|@домен|Пересланное сообщение} [Причина] - Разбанить пользователя\n"
                    . "&#128106;/Мега кик|мега исключение {Неактивных|вышедших|пользователей} - исключает пользователей из определённой группы\n"
                    . "&#128120;/Назначит ранг|Сет ранг {@Айди|@домен|Пересланное сообщение} {0|1|2|3|4|5|Модератор1 - Модератор4|пользователь|администратор} - Выдать ранг пользователю\n\n"
                    . "&#9881;Настройки:\n"
                    . "&#128035;/Лимит повышения рангов {Уровень: 1 - 3} {Количество предупреждений] {Количество киков] {Количество временных баннов] - Устанавливает лимит повышение рангов модераторам, если указан только уровень, лимит для него сбрасывается\n"
                    . "&#128299;/Наказание за предупреждения {От какого количества] {Тип: кик, временный бан, бан} {Время SS:MM:HH:DDD:MM:YY, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                    . "&#127809;/Очистить таблицу {пользователей|забаненных|вышедших|модераторов|наказаний|лимита|настроек|всё} - очищает указанную таблицу\n"
                    . "&#9202;/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM:YY] - сбрасывает всё предупреждения через указанное время\n"
                    . "&#9995;/Приветствие {Текст} - Устанавливает приветствие для новых пользователей, можете написать в сообщение {first_name} - чтобы указать имя, {last_name} - чтобы указать фамилию. Если текст был не указан, приветствие будет отключено\n"
                    . "&#128279;/Установить инвайт ссылку {Ссылка} - Устанавливает ссылку для подключение к беседе\n"
                    . "&#128110;/Сообщать о наказаниях {@Айди|@домен|Пересланное сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую, либо через пробел)\n"
                    . "&#128562;/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов\n"
                    . "{} - обязательный параметр, [] - необязательный параметр, {] - тип зависит от задачи\n\n"
                    . "&#128214;Информация о проекте:\n"
                    . "&#128100;Создатель: https://vk.com/codename_kos\n"
                    . "&#128064;Исходные код проекта и гайд по подключению: https://github.com/kos234/Vk-bot-moderator\n";
            }elseif (mb_strcasecmp($text[0], "/начать") == 0 || mb_strcasecmp($text[0], "/start") == 0){
                if($is_chat) {
                    if(createTabs($data->object->message->peer_id, $mysqli, $vk))
                        $request_params["message"] = "&#10004;Бот успешно добавлен! Чтобы посмотреть список моих команд и возможных настроек напишите \"/\"";
                    else
                        $request_params["message"] = "&#10060;Вы не выдали мне права администратора!";

                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif (mb_strcasecmp($text[0] . " " . $text[1], "/настройки беседы") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/настройки чата") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/chat settings") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/settings chat") == 0){
                if($is_chat) {
                    $res = $mysqli->query("SELECT * FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                    $res = $res->fetch_assoc();
                    $request_params["message"] = "&#128221;Настройки беседы: \nАвто исключение вышедших пользователей: ";
                    if ($res["autokickLeave"] == 1) $request_params["message"] .= "включено"; else $request_params["message"] .= "выключено";
                    $request_params["message"] .= "\nАвто исключение новых ботов: ";
                    if ($res["autokickBot"] == 1) $request_params["message"] .= "включено"; else $request_params["message"] .= "выключено";
                    $request_params["message"] .= "\nПриветствие: ";
                    if ($res["greeting"] == "") $request_params["message"] .= "выключено"; else $request_params["message"] .= "\"" . $res["greeting"] . "\"";
                    $request_params["message"] .= "\nСледящие за выдачей наказаний: ";
                    if ($res["tracking"] == "") $request_params["message"] .= "отсутствуют"; else $request_params["message"] .= implode(", ", getName($vk, explode(",", $res["tracking"])));
                    $request_params["message"] .= "\nСсылка для приглашения в чат: ";
                    if ($res["invite_link"] == "") $request_params["message"] .= "отсутствует"; else $request_params["message"] .= $res["invite_link"];
                    $request_params["message"] .= "\nНаказание за какое-то количество предупреждений: ";
                    if ($res["predsvarn"] == "") $request_params["message"] .= "отсутствует"; else {
                        $fields = explode(":", $res["predsvarn"]);
                        $request_params["message"] .= "за " . $fields[1];
                        if (($fields[1] >= 11 && $fields[1] <= 19) || (endNumber($fields[1]) >= 5 && endNumber($fields[1]) <= 9) || endNumber($fields[1]) == 0)
                            $request_params["message"] .= " предупреждений";
                        elseif (endNumber($fields[1]) == 1)
                            $request_params["message"] .= " предупреждение";
                        elseif (endNumber($fields[1]) >= 2 && endNumber($fields[1]) <= 4)
                            $request_params["message"] .= " предупреждения";
                        $request_params["message"] .= " вы будете ";
                        switch ($fields[0]) {
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
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/user info") == 0 || mb_strcasecmp($text[0] . " " .$text[1], "/Информация пользователя") == 0){
                $id = getId($text[2],$data->object->message->reply_message->from_id);
                error_log($id);
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
                    $request_params["message"] = "&#128100;Информация о". $type ." [id". $res_user[0]->id . "|". $res_user[0]->first_name ." " .$res_user[0]->last_name ."]: \nАйди: " . $res_user[0]->id;

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
                    }if($is_chat) {//Если сообщение в беседе добавляем
                            $res = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                            $res_mes = $res->fetch_assoc();
                            if(isset($res_mes["id"])){
                                $request_params["message"] .= "\nКоличество сообщений в беседе: " . $res_mes["mes_count"] . "\nПоследняя активность: " . date("d.m.Y G:i", $res_mes["lastMes"]) . " по UTC 0";
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

                        $request_params["message"] = "&#128100;Информация о". $type ." сообществе [club". $res_grop[0]->id . "|". $res_grop[0]->name ."]: \nАйди: " . $res_grop[0]->id;
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
                        if($is_chat) {//Если сообщение в беседе добавляем
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
                }else $request_params["message"] = "&#10060;Вы должны указать айди или переслать сообщение!";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Сократить ссылку") == 0){
                if(isset($text[2])){
                    if(mb_strcasecmp($text[3], "on") == 0 || mb_strcasecmp($text[3], "включить") == 0) $stat = 1;
                    else $stat = 0;

                    try {
                        $res_url = $vk->utils()->getShortLink(USER_TOKEN, array("url" => $text[2], "private" => $stat));
                    if($stat){
                        $request_params["peer_id"] = $data->object->message->from_id;
                        $request_params["message"] = "&#128279;Ваш токен " . $res_url["access_key"] ." для просмотра статистики ссылки: " . $res_url["short_url"] . ", но учитывайте, что она обновляется каждые 10 минут!\nДля просмотра статистики напишите: /Получить статистику " . $res_url["short_url"] . " " . $res_url["short_url"];
                        try {
                            $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                            $request_params["message"] = "&#128279;Ваша ссылка: " . $res_url["short_url"] . " токен для статистики отправлен вам в личные сообщения";
                        }catch (\VK\Exceptions\VKApiException $e){
                            $request_params["message"] = "&#10060;Для просмотра статистики ссылки, мне нужно выслать вам в личные сообщение токен просмотра статистики. Пожалуйста разрешите отправку личных сообщений!";
                        }
                    }else
                        $request_params["message"] = "&#128279;Ваша ссылка: " . $res_url["short_url"];
                    } catch (\VK\Exceptions\VKApiException $e) {
                        if($stat){
                            $request_params["message"] = "&#10060;Сервис статистики недоступен. Ошибка пользовательского токена";
                        }else {
                            $res_url = $vk->utils()->getShortLink(TOKEN_VK_BOT, array("url" => $text[2]));
                            $request_params["message"] = "&#128279;Ваша ссылка: " . $res_url["short_url"];
                        }
                    } catch (\VK\Exceptions\VKClientException $e) {
                        $request_params["message"] = "&#10060;Что-то не так с ссылкой!";
                    }
                }else $request_params["message"] = "&#10060;Вы не указали ссылку!";

            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Получить статистику") == 0){
                if(isset($text[2])) {
                    if(isset($text[3])) {
                        try {
                            $res_url = $vk->utils()->getLinkStats(USER_TOKEN, getUrlParameters($text[2], $text[3]));
                        if(isset($res_url["stats"][0]["views"])){
                            $request_params["message"] = "&#128196;Всего просмотров: " . $res_url["stats"][0]["views"] . "\n\nПросмотры по возрастным диапазонам:";
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

                        }else $request_params["message"] = "&#10060;Пока не было переходов по этой ссылке!";
                        } catch (\VK\Exceptions\Api\VKApiNotFoundException $e) {
                            $request_params["message"] = "&#10060;Что-то не так с ссылкой или токеном!1";
                        } catch (\VK\Exceptions\VKApiException $e) {
                            $request_params["message"] = "&#10060;Что-то не так с ссылкой или токеном!2";
                        } catch (\VK\Exceptions\VKClientException $e) {
                            $request_params["message"] = "&#10060;Что-то не так с ссылкой или токеном!3";
                        }
                    }else $request_params["message"] = "&#10060;Вы не указали токен для просмотра статистики!";
                }else $request_params["message"] = "&#10060;Вы не указали ссылку!";
            }elseif (mb_strcasecmp($text[0] . " " .$text[1], "/Инвайт ссылка") == 0 || mb_strcasecmp($text[0] . " " .$text[1], "/ссылка приглашения") == 0 || mb_strcasecmp($text[0], "/Приглашение") == 0){
                if($is_chat){
                    $res = $mysqli->query("SELECT `invite_link` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                    $res = $res->fetch_assoc();
                        if (mb_strcasecmp($res["invite_link"], "") != 0 || $res["invite_link"] != null)
                            $request_params["message"] = "&#128279;Ссылка для приглашения: " . $res["invite_link"];
                        else $request_params["message"] = "&#10060;Администрация беседы не указала ссылку для приглашения";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";

            }elseif(mb_strcasecmp($text[0], "/Пригласить") == 0){
                if($is_chat) {
                    $id = getId($text[1], $data->object->message->reply_message->from_id);
                    if ($id != 0) {
                        $res = $mysqli->query("SELECT `invite_link` FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                        $res = $res->fetch_assoc();
                            if (mb_strcasecmp($res["invite_link"], "") != 0 || $res["invite_link"] != null) {
                                if ($id > 0) {
                                    $request_params["message"] = "&#128075;Пользователь ";
                                } else $request_params["message"] = "&#128075;Сообщество ";
                                $res_title = $vk->messages()->getConversationsById(TOKEN_VK_BOT, array("peer_ids" => $data->object->message->peer_id));
                                if (isset($res_title["items"][0]["chat_settings"])) {
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
                                        if (!is_dir("temp")) {
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
                                        $request_params["message"] = "&#10004;Приглашение успешно отправлено!";
                                    } catch (VK\Exceptions\Api\VKApiMessagesChatUserNoAccessException $e) {
                                        $request_params["message"] = "&#10060;Пользователь не предоставил доступ к личным сообщениям";
                                    } catch (\VK\Exceptions\Api\VKApiMessagesPrivacyException $e) {
                                        $request_params["message"] = "&#10060;Пользователю ограничил отправку сообщений";
                                    } catch (\VK\Exceptions\Api\VKApiMessagesUserBlockedException $e) {
                                        $request_params["message"] = "&#10060;Пользователь заблокирован";
                                    } catch (VK\Exceptions\VKApiException $e) {
                                        $request_params["message"] = "&#10060;Пользователь не разрешил отправку личных сообщений";
                                    }
                                    $request_params["peer_id"] = $data->object->message->peer_id;
                                    $request_params["attachment"] = "";
                                } else $request_params["message"] = "&#10060;Для этой функции мне необходимы права администратора!";
                            } else $request_params["message"] = "&#10060;Администрация беседы не указала ссылку для приглашения";
                    } else $request_params["message"] = "&#10060;Вы не указали айди пользователя";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/список") == 0){
                if($is_chat) {
                    if (isset($text[1])) {
                        $empty_list = true;
                        switch (mb_strtolower($text[1])) {
                            case "пользователей":
                                $request_params["message"] = "&#128195;Список пользователей в чате:";
                                $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_users`");
                                $res_ids = array();
                                $res_fields = array();
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                    $res_fields[] = array($res_users["rang"], $res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                                }
                                if (!$empty_list) {
                                    foreach (getName($vk, $res_ids, false) as $key => $name) {
                                        $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key] . ", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: " . $res_fields[$key][1] . ", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                    }
                                }

                                break;
                            case "забаненных":
                                $request_params["message"] = "&#128195;Список забаненных пользователей в чате:";
                                $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_bans`");
                                $res_ids = array();
                                $res_fields = array();
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                    $res_fields[] = $res_users["ban"];
                                }
                                if (!$empty_list) {
                                    foreach (getName($vk, $res_ids) as $key => $name) {
                                        if ($res_fields[$key][0] == 0)
                                            $type = " навсегда";
                                        else
                                            $type = " до " . date("d.m.Y G:i", $res_fields[$key]) . "по UTC 0";
                                        $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key] . ", забанен" . $type;
                                    }
                                }
                                break;
                            case "вышедших":
                                $request_params["message"] = "&#128195;Список вышедших из чата пользователей:";
                                $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_leave`");
                                $res_ids = array();
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                }
                                if (!$empty_list) {
                                    foreach (getName($vk, $res_ids) as $key => $name) {
                                        $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key];
                                    }
                                }
                                break;
                            case "модераторов":
                                $request_params["message"] = "&#128195;Список модераторов чата:";
                                $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_moders`");
                                $res_ids = array();
                                $res_fields = array();
                                $mysqli_query = "SELECT `rang`,`pred` FROM `" . $data->object->message->peer_id . "_users` WHERE ";
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                    $mysqli_query .= "`id` = " . $res_users["id"] . " OR ";
                                    $res_fields[] = array($res_users["preds"], $res_users["kicks"], $res_users["tempbans"], $res_users["bans"]);
                                }
                                if (!$empty_list) {
                                    $res_rang = $mysqli->query(mb_substr($mysqli_query, 0, -4));
                                    foreach (getName($vk, $res_ids, false) as $key => $name) {
                                        $res_rang_id = $res_rang->fetch_assoc();
                                        $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key] . ", ранг: " . getRang($res_rang_id["rang"]) . ", количество предупреждений: " . $res_rang_id["pred"] . ", количество выданных предупреждений: " . $res_fields[$key][0] . ", количество исключенных пользователей: " . $res_fields[$key][1] . ", количество временно забаненных пользователей: " . $res_fields[$key][2] . ", количество забанных пользователей: " . $res_fields[$key][3];
                                    }
                                }
                                break;
                            case "неактивных":
                                $request_params["message"] = "&#128195;Список неактивных больше недели пользователей в чате:";
                                $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_users`");
                                $res_ids = array();
                                $res_fields = array();
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                    $res_fields[] = array($res_users["rang"], $res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                                }
                                if (!$empty_list) {
                                    foreach (getName($vk, $res_ids, false) as $key => $name) {
                                        if (time() - $res_fields[$key][3] > 604800) //604800 - одна неделя
                                            $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key] . ", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: " . $res_fields[$key][1] . ", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                    }
                                }
                                break;

                            case "онлайна":
                                $request_params["message"] = "&#128195;Список онлайн пользователей в чате:";
                                $res = $vk->messages()->getConversationMembers(TOKEN_VK_BOT, array("peer_id" => $data->object->message->peer_id, "fields" => "online"));
                                $mysqli_query = "SELECT * FROM `" . $data->object->message->peer_id . "_users` WHERE ";
                                for ($i = 0; isset($res["items"][$i]); $i++) {
                                    if ($res["profiles"][$i]["online"] == 1)
                                        $mysqli_query .= "`id` = '" . $res["profiles"][$i]["id"] . "' OR ";
                                }
                                $res = $mysqli->query(mb_substr($mysqli_query, 0, -4));
                                $res_ids = array();
                                $res_fields = array();
                                while ($res_users = $res->fetch_assoc()) {
                                    $empty_list = false;
                                    $res_ids[] = $res_users["id"];
                                    $res_fields[] = array($res_users["rang"], $res_users["pred"], $res_users["mes_count"], $res_users["lastMes"]);
                                }
                                if (!$empty_list) {
                                    foreach (getName($vk, $res_ids, false) as $key => $name) {
                                        $request_params["message"] .= "\n\n" . $name . ", айди: " . $res_ids[$key] . ", ранг: " . getRang($res_fields[$key][0]) . ", количество предупреждений: " . $res_fields[$key][1] . ", количество сообщений в беседе: " . $res_fields[$key][2] . ", последняя активность: " . date("d.m.Y G:i", $res_fields[$key][3]) . "по UTC 0";
                                    }
                                }
                                break;
                            default:
                                $request_params["message"] = "&#10060;Не верно указан список! Возможные значения: пользователей, забаненных, вышедших, модераторов, неактивных, онлайна";
                                $empty_list = false;
                                break;
                        }

                        if ($empty_list)
                            $request_params["message"] = "&#10060;Список пуст";
                    } else $request_params["message"] = "&#10060;Вы не указали список!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Лимит модераторов") == 0){
                if($is_chat) {
                    $request_params["message"] = "&#128124;";
                    for ($i = 1; $i < 4; $i++) {
                        $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_moders_limit` WHERE `rang` = " . $i);
                        $res = $res->fetch_assoc();
                        $request_params["message"] .= "Для $i уровня: ";
                        if ($res["pred"] != 0) {
                            if (($res["pred"] >= 11 && $res["pred"] <= 19) || (endNumber($res["pred"]) >= 5 && endNumber($res["pred"]) <= 9) || endNumber($res["pred"]) == 0)
                                $request_params["message"] .= " " . $res["pred"] . " предупреждений,";
                            elseif (endNumber($res["pred"]) == 1)
                                $request_params["message"] .= " " . $res["pred"] . " предупреждение,";
                            elseif (endNumber($res["pred"]) >= 2 && endNumber($res["pred"]) <= 4)
                                $request_params["message"] .= " " . $res["pred"] . " предупреждения,";
                        }
                        if ($res["kick"] != 0) {
                            if (($res["kick"] >= 11 && $res["kick"] <= 19) || (endNumber($res["kick"]) >= 5 && endNumber($res["kick"]) <= 9) || endNumber($res["kick"]) == 0)
                                $request_params["message"] .= " " . $res["kick"] . " исключений,";
                            elseif (endNumber($res["kick"]) == 1)
                                $request_params["message"] .= " " . $res["kick"] . " исключение,";
                            elseif (endNumber($res["kick"]) >= 2 && endNumber($res["kick"]) <= 4)
                                $request_params["message"] .= " " . $res["kick"] . " исключения,";
                        }
                        if ($res["tempban"] != 0) {
                            if (($res["tempban"] >= 11 && $res["tempban"] <= 19) || (endNumber($res["tempban"]) >= 5 && endNumber($res["tempban"]) <= 9) || endNumber($res["tempban"]) == 0)
                                $request_params["message"] .= " " . $res["tempban"] . " временных блокировок,";
                            elseif (endNumber($res["tempban"]) == 1)
                                $request_params["message"] .= " " . $res["tempban"] . " временная блокировка,";
                            elseif (endNumber($res["tempban"]) >= 2 && endNumber($res["tempban"]) <= 4)
                                $request_params["message"] .= " " . $res["tempban"] . " временных блокировки,";
                        }
                        $request_params["message"] = mb_substr($request_params["message"], 0, -1);
                        $request_params["message"] .= "\n\n";
                    }
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/История наказаний") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/History punishment") == 0){
                if($is_chat) {
                    if (isset($text[2]))
                        if ((int)$text[2] != 0)
                            $count = (int)$text[2];
                        else
                            $count = 100;
                    else
                        $count = 100;

                    $res_history = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_punishments`");
                    $punishments = array();
                    while ($temp = $res_history->fetch_assoc()) {
                        if ($temp != null)
                            $punishments[] = $temp;
                    }
                    if ($count > count($punishments))
                        $count = count($punishments);
                    if (($count >= 11 && $count <= 19) || (endNumber($count) >= 5 && endNumber($count) <= 9) || endNumber($count) == 0)
                        $request_params["message"] = "&#128127;Последние " . $count . " наказаний:";
                    elseif (endNumber($count) == 1)
                        $request_params["message"] = "&#128127;Последнее " . $count . " наказание:";
                    elseif (endNumber($count) >= 2 && endNumber($count) <= 4)
                        $request_params["message"] = "&#128127;Последние " . $count . " наказания:";
                    for ($i = 0; $i < $count; $i++) {
                        $res = $punishments[count($punishments) - 1 - $i];
                        $names = getName($vk, array($res["id"], $res["id_moder"]));
                        $request_params["message"] .= "\n\n" . $names[1];
                        switch ($res["type"]) {
                            case "kick":
                                $request_params["message"] .= " исключил пользователя " . $names[0];
                                break;
                            case "removeban":
                                $request_params["message"] .= " разбанил пользователя " . $names[0];
                                break;
                            case "removepred":
                                $request_params["message"] .= " удалил ";
                                if (($res["parametr"] >= 11 && $res["parametr"] <= 19) || (endNumber($res["parametr"]) >= 5 && endNumber($res["parametr"]) <= 9) || endNumber($res["parametr"]) == 0)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждений у пользователя " . $names[0];
                                elseif (endNumber($res["parametr"]) == 1)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждение у пользователя " . $names[0];
                                elseif (endNumber($res["parametr"]) >= 2 && endNumber($res["parametr"]) <= 4)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждения у пользователя " . $names[0];
                                break;
                            case "tempban":
                                $request_params["message"] .= " забанил до " . date("d.m.Y G:i", $res["parametr"]) . "по UTC 0 пользователя " . $names[0];
                                break;
                            case "ban":
                                $request_params["message"] .= " забанил пользователя " . $names[0];
                                break;
                            case "pred":
                                $request_params["message"] .= " выдал ";
                                if (($res["parametr"] >= 11 && $res["parametr"] <= 19) || (endNumber($res["parametr"]) >= 5 && endNumber($res["parametr"]) <= 9) || endNumber($res["parametr"]) == 0)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждений пользователю " . $names[0];
                                elseif (endNumber($res["parametr"]) == 1)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждение пользователю " . $names[0];
                                elseif (endNumber($res["parametr"]) >= 2 && endNumber($res["parametr"]) <= 4)
                                    $request_params["message"] .= " " . $res["parametr"] . " предупреждения пользователю " . $names[0];
                                break;
                            default:
                                $request_params["message"] .= " " . $res["type"] . " пользователя " . $names[0];
                                break;
                        }
                        if ($res["text"] != "")
                            $request_params["message"] .= ", причина: " . $res["text"];
                    }
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/Предупреждение") == 0 || mb_strcasecmp($text[0], "/пред") == 0 || mb_strcasecmp($text[0], "/kick") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 1) {
                        $id = getId($text[1], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 2;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 1;
                            if (isset($text[$num_num])) $num = (int)$text[$num_num]; else $num = 1;
                            $reason = "";
                            for ($i = 1; isset($text[$num_num + $i]); $i++) {
                                $reason .= $text[$num_num + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);

                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `pred` = `pred` + " . $num . " WHERE `id` = '" . $id . "'");
                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders` SET `preds` = `preds` + 1 WHERE `id` = '" . $data->object->message->from_id . "'");
                            $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'pred', '" . $reason . "', '" . $num . "')");
                            track($mysqli, $id, $data->object->message->from_id, $num, $reason, "pred", $data->object->message->peer_id, $vk);
                            $is_varn = true;
                            $request_params["message"] = "&#128110;Пользователю " . getName($vk, array($id))[0] . " выдано ";
                            if (($num >= 11 && $num <= 19) || (endNumber($num) >= 5 && endNumber($num) <= 9) || endNumber($num) == 0)
                                $request_params["message"] .= $num . " предупреждений";
                            elseif (endNumber($num) == 1)
                                $request_params["message"] .= $num . " предупреждение";
                            elseif (endNumber($num) >= 2 && endNumber($num) <= 4)
                                $request_params["message"] .= $num . " предупреждения";
                            if ($reason != "") $request_params["message"] .= ", по причине: " . $reason;
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 1 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Удалить предупреждение") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/Удалить пред") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 1) {
                        $id = getId($text[2], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 3;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 2;
                            $get_pred = $mysqli->query("SELECT `pred` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $id . "'");
                            $get_pred = $get_pred->fetch_assoc();

                            if (isset($text[$num_num])) if ((int)$text[$num_num] > $get_pred["pred"]) $num = $get_pred["pred"]; else $num = (int)$text[$num_num]; else $num = $get_pred["pred"];
                            $reason = "";
                            for ($i = 1; isset($text[$num_num + $i]); $i++) {
                                $reason .= $text[$num_num + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);

                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `pred` = `pred` - " . $num . " WHERE `id` = '" . $id . "'");
                            $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'removepred', '" . $reason . "', '" . $num . "')");
                            track($mysqli, $id, $data->object->message->from_id, $num, $reason, "removepred", $data->object->message->peer_id, $vk);
                            $is_varn = true;
                            $request_params["message"] = "&#9728;У пользователю " . getName($vk, array($id))[0] . " удалено ";
                            if (($num >= 11 && $num <= 19) || (endNumber($num) >= 5 && endNumber($num) <= 9) || endNumber($num) == 0)
                                $request_params["message"] .= $num . " предупреждений";
                            elseif (endNumber($num) == 1)
                                $request_params["message"] .= $num . " предупреждение";
                            elseif (endNumber($num) >= 2 && endNumber($num) <= 4)
                                $request_params["message"] .= $num . " предупреждения";
                            if ($reason != "") $request_params["message"] .= ", по причине: " . $reason;
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 1 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/исключить") == 0 || mb_strcasecmp($text[0], "/кик") == 0 || mb_strcasecmp($text[0], "/kick") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 2) {
                        $id = getId($text[1], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_reason = 2;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_reason = 1;
                            $reason = "";
                            for ($i = 0; isset($text[$num_reason + $i]); $i++) {
                                $reason .= $text[$num_reason + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);
                            try {
                                $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $id));
                                $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '$id'");
                                $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_moders` WHERE `id` = '$id'");
                                $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders` SET `kicks` = `kicks` + 1 WHERE `id` = '" . $data->object->message->from_id . "'");
                                $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'kick', '" . $reason . "', '')");
                                track($mysqli, $id, $data->object->message->from_id, "", $reason, "kick", $data->object->message->peer_id, $vk);
                                $is_varn = true;
                                $request_params["message"] = "&#128546;Пользователь " . getName($vk, array($id))[0] . " был исключен из беседы!";
                            } catch (\VK\Exceptions\Api\VKApiAccessException $e) {
                                $request_params["message"] = "&#10060;Не возможно исключить этого пользователя!";
                            }
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 2 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Временно забанить") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/temp ban") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 3) {
                        $id = getId($text[2], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 3;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 2;
                            if (isset($text[$num_num])) $num = convertTime($text[$num_num]); else $num = 1;
                            $reason = "";
                            for ($i = 1; isset($text[$num_num + $i]); $i++) {
                                $reason .= $text[$num_num + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);
                            try {
                                $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $id));
                                $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders` SET `tempbans` = `tempbans` + 1 WHERE `id` = '" . $data->object->message->from_id . "'");
                                $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'tempban', '" . $reason . "', '" . $num . "')");
                                $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_bans` (`id`, `reason`,`ban`) VALUES ('" . $id . "', '" . $reason . "', " . $num . ")");
                                track($mysqli, $id, $data->object->message->from_id, date("d.m.Y G:i", $num) . " по UTC 0", $reason, "tempban", $data->object->message->peer_id, $vk);
                                $is_varn = true;
                                $request_params["message"] = "&#128127;Пользователь " . getName($vk, array($id))[0] . " был забанен до " . date("d.m.Y G:i", $num) . " по UTC 0!";
                            } catch (\VK\Exceptions\Api\VKApiAccessException $e) {
                                $request_params["message"] = "&#10060;Не возможно забанить этого пользователя!";
                            }
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 3 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/забанить") == 0 || mb_strcasecmp($text[0], "/ban") == 0 || mb_strcasecmp($text[0], "/бан") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 4) {
                        $id = getId($text[1], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 2;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 1;
                            $reason = "";
                            for ($i = 1; isset($text[$num_num + $i]); $i++) {
                                $reason .= $text[$num_num + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);
                            try {
                                $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $id));
                                $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '$id'");
                                $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_moders` WHERE `id` = '$id'");
                                $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders` SET `bans` = `bans` + 1 WHERE `id` = '" . $data->object->message->from_id . "'");
                                $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'ban', '" . $reason . "', '')");
                                $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_bans` (`id`, `reason`,`ban`) VALUES ('" . $id . "', '" . $reason . "', '')");
                                track($mysqli, $id, $data->object->message->from_id, "", $reason, "ban", $data->object->message->peer_id, $vk);
                                $is_varn = true;
                                $request_params["message"] = "&#128128;Пользователь " . getName($vk, array($id))[0] . " был забанен навсегда!";
                            } catch (\VK\Exceptions\Api\VKApiAccessException $e) {
                                $request_params["message"] = "&#10060;Не возможно забанить этого пользователя!";
                            }
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 4 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/разбанить") == 0 || mb_strcasecmp($text[0], "/unban") == 0 || mb_strcasecmp($text[0], "/пардон") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 3) {
                        $id = getId($text[1], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 2;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 1;
                            $reason = "";
                            for ($i = 1; isset($text[$num_num + $i]); $i++) {
                                $reason .= $text[$num_num + $i] . " ";
                            }
                            $reason = mb_substr($reason, 0, -1);

                            $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_bans` WHERE `id` = '$id'");
                            $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_punishments` (`time`, `id`,`id_moder`, `type`, `text`, `parametr`) VALUES ( " . time() . ", '" . $id . "', '" . $data->object->message->from_id . "', 'removeban', '" . $reason . "', '')");
                            track($mysqli, $id, $data->object->message->from_id, "", $reason, "removeban", $data->object->message->peer_id, $vk);
                            $is_varn = true;
                            $request_params["message"] = "&#128124;Пользователь " . getName($vk, array($id))[0] . " был разбанен!";
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 3 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/мега кик") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/мега исключение") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 4) {
                        if (isset($text[2])) {
                            $ids = array();
                            $empty_list = true;
                            switch (mb_strtolower($text[2])) {
                                case "неактивных":
                                    $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_users`");
                                    $res_ids = array();
                                    while ($res_users = $res->fetch_assoc()) {
                                        if ($temp != null) {
                                            $empty_list = false;
                                            $res_ids[] = $res_users["id"];
                                            $res_fields[] = array($res_users["lastMes"]);
                                        }
                                    }
                                    if (!$empty_list) {
                                        foreach (getName($vk, $res_ids, false) as $key => $name) {
                                            if (time() - $res_fields[$key][0] > 604800) //604800 - одна неделя
                                                $ids[] = $res_ids[$key];
                                        }
                                    }
                                    break;
                                case "вышедших":
                                    $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_leave`");
                                    while ($res_users = $res->fetch_assoc()) {
                                        if ($temp != null)
                                            $ids[] = $res_users["id"];
                                    }
                                    break;
                                case "пользователей":
                                    if ($get_rang["rang"] >= 5) {
                                        $res = $mysqli->query("SELECT * FROM `" . $data->object->message->peer_id . "_users`");
                                        while ($res_users = $res->fetch_assoc()) {
                                            if ($temp != null)
                                                $ids[] = $res_users["id"];
                                        }
                                    } else $request_params["message"] = "&#10060;Для исключения пользователей вы должны быть администратором!";
                                    break;
                            }

                            if (count($ids) != 0) {
                                for ($i = 0; isset($ids[$i]); $i++) {
                                    try {
                                        $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $ids[$i]));
                                        $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_leave` WHERE `id` = '$ids[$i]'"); //на случай если он иссключит не всех
                                    } catch (\VK\Exceptions\Api\VKApiMessagesChatNotAdminException $e) {
                                    } catch (\VK\Exceptions\Api\VKApiMessagesChatUserNotInChatException $e) {
                                    } catch (\VK\Exceptions\Api\VKApiMessagesContactNotFoundException $e) {
                                    } catch (\VK\Exceptions\VKApiException $e) {
                                    } catch (\VK\Exceptions\VKClientException $e) {
                                    }
                                }
                                $request_params["message"] = "&#128106;Всех кого можно было я исключил!";
                            } else $request_params["message"] = "&#10060;Список пуст!";
                        } else $request_params["message"] = "&#10060;Вы не указали список исключения!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть модератором 4 уровня или выше!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Назначить ранг") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/Выдать ранг") == 0 || mb_strcasecmp($text[0] . " " . $text[1], "/rang set") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        $id = getId($text[2], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $num_num = 3;
                            if (isset($data->object->message->reply_message->from_id))
                                $num_num = 2;
                            if (isset($text[$num_num])) {
                                if (mb_strcasecmp($text[$num_num], "0") == 0 || mb_strcasecmp($text[$num_num], "пользователь") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 0 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " простой пользователь!";
                                } elseif (mb_strcasecmp($text[$num_num], "1") == 0 || mb_strcasecmp($text[$num_num], "модератор1") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 1 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " модератор 1 уровня!";
                                } elseif (mb_strcasecmp($text[$num_num], "2") == 0 || mb_strcasecmp($text[$num_num], "модератор2") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 2 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " модератор 2 уровня!";
                                } elseif (mb_strcasecmp($text[$num_num], "3") == 0 || mb_strcasecmp($text[$num_num], "модератор3") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 3 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " модератор 3 уровня!";
                                } elseif (mb_strcasecmp($text[$num_num], "4") == 0 || mb_strcasecmp($text[$num_num], "модератор4") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 4 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " модератор 4 уровня!";
                                } elseif (mb_strcasecmp($text[$num_num], "5") == 0 || mb_strcasecmp($text[$num_num], "администратор") == 0) {
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang`= 5 WHERE `id` = '" . $id . "'");
                                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_moders` (`id`) VALUES ('" . $id . "')");
                                    $request_params["message"] = "&#128120;Теперь " . getName($vk, array($id))[0] . " администратор!";
                                } else $request_params["message"] = "&#10060;Неправильно указан ранг! Возможные значения: 0,1,2,3,4,5,пользователь,модератор1,модератор2,модератор3,модератор4,администратор";
                            } else $request_params["message"] = "&#10060;Вы не указали ранг!";
                        } else $request_params["message"] = "&#10060;Вы не указали айди пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1] . " " . $text[2], "/Лимит повышения рангов") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[3])) {
                            if ((int)$text[3] > 0 && (int)$text[3] < 4) {
                                if (!isset($text[4]) && !isset($text[5]) && !isset($text[6]))
                                    switch ((int)$text[3]) {
                                        case 1:
                                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 5, `kick` = 0, `tempban` = 0 WHERE `rang` = '1'");
                                            $request_params["message"] = "&#128035;Лимит сброшен до 5 предупреждений!";
                                            break;
                                        case 2:
                                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 8, `kick` = 2, `tempban` = 0 WHERE `rang` = '2'");
                                            $request_params["message"] = "&#128035;Лимит сброшен до 8 предупреждений и 2 исключений из беседы!";
                                            break;
                                        case 3:
                                            $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 10, `kick` = 4, `tempban` = 2 WHERE `rang` = '3'");
                                            $request_params["message"] = "&#128035;Лимит сброшен до 10 предупреждений, 4 исключений из бесседы и 2 временных банов!";
                                            break;
                                    }
                                else {
                                    if (isset($text[4])) $pred = (int)$text[4]; else $pred = 0;
                                    if (isset($text[5])) $kick = (int)$text[5]; else $kick = 0;
                                    if (isset($text[6])) $tempban = (int)$text[6]; else $tempban = 0;
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= " . $pred . ", `kick` = " . $kick . ", `tempban` = " . $tempban . " WHERE `rang` = '" . $text[3] . "'");
                                    $request_params["message"] = "&#10004;Лимит успешно установлен!";
                                }

                            } else $request_params["message"] = "&#10060;Не верно указан уровень! Возможные значения: 1 - 3";
                        } else $request_params["message"] = "&#10060;Вы не указали уровень!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1] . " " . $text[2], "/Наказание за предупреждения") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[3])) {
                            if (isset($text[4])) {
                                if (mb_strcasecmp($text[4], "бан") == 0 || mb_strcasecmp($text[4], "ban") == 0) {
                                    $mysqli->query("UPDATE `chats_settings` SET `predsvarn`= 'ban:" . (int)$text[3] . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                    $request_params["message"] = "&#128299;Теперь после ";
                                    if (((int)$text[3] >= 11 && (int)$text[3] <= 19) || (endNumber((int)$text[3]) >= 5 && endNumber((int)$text[3]) <= 9) || endNumber((int)$text[3]) == 0)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет забанен";
                                    elseif (endNumber((int)$text[3]) == 1)
                                        $request_params["message"] .= $text[3] . " предупреждения пользователь будет забанен";
                                    elseif (endNumber((int)$text[3]) >= 2 && endNumber((int)$text[3]) <= 4)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет забанен";
                                } elseif (mb_strcasecmp($text[4] . " " . $text[5], "временный бан") == 0 || mb_strcasecmp($text[4] . " " . $text[5], "temp ban") == 0) if (isset($text[6])) {
                                    $mysqli->query("UPDATE `chats_settings` SET `predsvarn`= 'ban:" . (int)$text[3] . ":" . convertMicroTime($text[6]) . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                    $request_params["message"] = "&#128299;Теперь после ";
                                    if (((int)$text[3] >= 11 && (int)$text[3] <= 19) || (endNumber((int)$text[3]) >= 5 && endNumber((int)$text[3]) <= 9) || endNumber((int)$text[3]) == 0)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет временно забанен";
                                    elseif (endNumber((int)$text[3]) == 1)
                                        $request_params["message"] .= $text[3] . " предупреждения пользователь будет временно забанен";
                                    elseif (endNumber((int)$text[3]) >= 2 && endNumber((int)$text[3]) <= 4)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет временно забанен";

                                    $request_params["message"] .= " на " . getTime(convertMicroTime($text[6]));
                                } else $request_params["message"] = "&#10060;Вы не указали время!";
                                elseif (mb_strcasecmp($text[4], "кик") == 0 || mb_strcasecmp($text[4], "kick") == 0) {
                                    $mysqli->query("UPDATE `chats_settings` SET `predsvarn`= 'kick:" . (int)$text[3] . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                    $request_params["message"] = "&#128299;Теперь после ";
                                    if (((int)$text[3] >= 11 && (int)$text[3] <= 19) || (endNumber((int)$text[3]) >= 5 && endNumber((int)$text[3]) <= 9) || endNumber((int)$text[3]) == 0)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет исключен из беседы";
                                    elseif (endNumber((int)$text[3]) == 1)
                                        $request_params["message"] .= $text[3] . " предупреждения пользователь будет исключен из беседы";
                                    elseif (endNumber((int)$text[3]) >= 2 && endNumber((int)$text[3]) <= 4)
                                        $request_params["message"] .= $text[3] . " предупреждений пользователь будет исключен из беседы";
                                } else $request_params["message"] = "&#10060;Не верно указан тип! Возможные значения: кик, бан, временный бан, kick, ban, temp ban";
                            } else $request_params["message"] = "&#10060;Вы не указали тип!";
                        } else {
                            $mysqli->query("UPDATE `chats_settings` SET `predsvarn`= '' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10060;Вы не указали от какого количества предупреждений будет наказание, поэтому авто наказания были отключены!";
                        }
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1], "/Очистить таблицу") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[2])) {
                            switch (mb_strtolower($text[2])) {
                                case "пользователей":
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_users`");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "забаненных":
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_bans`");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "вышедших":
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_leave`");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "модераторов":
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_moders`");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "лимита":
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 5, `kick` = 0, `tempban` = 0 WHERE `rang` = '1'");
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 8, `kick` = 2, `tempban` = 0 WHERE `rang` = '2'");
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 10, `kick` = 4, `tempban` = 2 WHERE `rang` = '3'");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "настроек":
                                    $mysqli->query("DELETE FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                    $request_params["message"] = "&#10004;Таблица успешно очищена!";
                                    break;
                                case "все":
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_users`");
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_bans`");
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_leave`");
                                    $mysqli->query("DELETE FROM `" . $data->object->message->peer_id . "_moders`");
                                    $mysqli->query("DELETE FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 5, `kick` = 0, `tempban` = 0 WHERE `rang` = '1'");
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 8, `kick` = 2, `tempban` = 0 WHERE `rang` = '2'");
                                    $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_moders_limit` SET `pred`= 10, `kick` = 4, `tempban` = 2 WHERE `rang` = '3'");
                                    $request_params["message"] = "&#10004;Все таблицы успешно очищены!";
                                    break;
                                default:
                                    $request_params["message"] = "&#10060;Не верно указана таблица! Возможные значения: пользователя, забаненных, вышедших, модераторов, настроек, все";
                                    break;
                            }
                        } else $request_params["message"] = "&#10060;Вы не указали таблицу!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1] . " " . $text[2], "/Авто очистка предупреждений") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[3])) {
                            $mysqli->query("UPDATE `chats_settings` SET `autoremovepred`= " . convertMicroTime($text[3]) . " WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Теперь предупреждение будут очищаться каждые " . getTime(convertMicroTime($text[3]));
                        } else {
                            $mysqli->query("UPDATE `chats_settings` SET `autoremovepred`= 0 WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10060;Вы не указали время, поэтому авто очищение предупреждений отключено!";
                        }
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/Приветствие") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[1])) {
                            $greeting = $text[1];
                            for ($i = 2; isset($text[$i]); $i++) {
                                $greeting .= " " . $text[$i];
                            }
                            $mysqli->query("UPDATE `chats_settings` SET `greeting`= '" . $greeting . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Новое приветствие было успешно установлено!";
                        } else {
                            $mysqli->query("UPDATE `chats_settings` SET `greeting`= '' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Вы не указали текст, поэтому приветствие было очищено!";
                        }
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1] . " " . $text[2], "/Установить инвайт ссылку") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[3])) {
                            $mysqli->query("UPDATE `chats_settings` SET `invite_link`= '" . $text[3] . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Инвайт ссылка была успешно установлена!";
                        } else {
                            $mysqli->query("UPDATE `chats_settings` SET `invite_link`= '' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Вы не указали ссылку, поэтому ссылка была удалена!";
                        }
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0] . " " . $text[1] . " " . $text[2], "/Сообщать о наказаниях") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {

                        $id = getId(explode(",", $text[3])[0], $data->object->message->reply_message->from_id);
                        if ($id != 0) {
                            $ids = "";
                            $ids_user = "";
                            $ids_group = "";
                            for ($i = 3; isset($text[$i]); $i++) {
                                foreach (explode(",", $text[$i]) as $id) {
                                    $id = getId($id);
                                    if ($id != 0) {
                                        $ids .= $id . ",";
                                        if ($id > 0) $ids_user .= $id . ",";
                                        else $ids_group .= mb_substr($id, 1) . ",";
                                    }
                                }
                            }
                            $mysqli->query("UPDATE `chats_settings` SET `tracking`= '" . mb_substr($ids, 0, -1) . "' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Новые контролирующие успешно назначены!";
                            $res_mes_user = $vk->users()->get(TOKEN_VK_BOT, array("user_ids" => $ids_user, "fields" => "can_write_private_message"));
                            $res_mes_group = $vk->groups()->getById(TOKEN_VK_BOT, array("group_ids" => $ids_group, "fields" => "can_message"));
                            $names = "";
                            foreach ($res_mes_user as $res) {
                                if ($res["can_write_private_message"] == 0)
                                    $names .= "[id" . $res["id"] . "|" . $res["first_name"] . " " . $res["last_name"] . "], ";
                            }
                            if ($names != "")
                                $names = mb_substr($names, 0, -2) . " ";
                            if ($ids_group != "") {
                                foreach ($res_mes_group as $res) {
                                    if ($res["can_message"] == 0)
                                        $names .= "[club" . $res["id"] . "|" . $res["name"] . "], ";
                                }
                                if ($names != "") {
                                    $names = mb_substr($names, 0, -2) . " ";
                                }
                            }
                            if ($names != "") {
                                $request_params["message"] .= "\n" . $names . " вы были назначены контролирующими модераторов, пожалуйста разрешите мне отправку личных сообщений, для отправки отчетов!";
                            }
                        } else {
                            $mysqli->query("UPDATE `chats_settings` SET `tracking`= '' WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                            $request_params["message"] = "&#10004;Вы не указали айди, поэтому список контролирующий был очистен!";
                        }
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }elseif(mb_strcasecmp($text[0], "/Автокик") == 0 || mb_strcasecmp($text[0], "/автоисключение") == 0){
                if($is_chat) {
                    $get_rang = $mysqli->query("SELECT `rang` FROM `" . $data->object->message->peer_id . "_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    if ($get_rang["rang"] >= 5) {
                        if (isset($text[1])) {
                            switch ($text[1]) {
                                case "вышедших":
                                    if (mb_strcasecmp($text[2], "on") == 0 || mb_strcasecmp($text[2], "включить") == 0) {
                                        $mysqli->query("UPDATE `chats_settings` SET `autokickLeave`= 1 WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                        $request_params["message"] = "&#10004;Теперь вышедшие пользователи будут автоматически исключаться из беседы!";
                                    } elseif (mb_strcasecmp($text[2], "off") == 0 || mb_strcasecmp($text[2], "выключить") == 0) {
                                        $mysqli->query("UPDATE `chats_settings` SET `autokickLeave`= 0 WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                        $request_params["message"] = "&#10004;Теперь вышедшие пользователи не будут автоматически исключаться из беседы!";
                                    } else $request_params["message"] = "&#10060;Не верное действие! Возможные значения: on, off, включить, выключить";
                                    break;
                                case "ботов":
                                    if (mb_strcasecmp($text[2], "on") == 0 || mb_strcasecmp($text[2], "включить") == 0) {
                                        $mysqli->query("UPDATE `chats_settings` SET `autokickBot`= 1 WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                        $request_params["message"] = "&#10004;Теперь новые боты будут автоматически исключаться из беседы!";
                                    } elseif (mb_strcasecmp($text[2], "off") == 0 || mb_strcasecmp($text[2], "выключить") == 0) {
                                        $mysqli->query("UPDATE `chats_settings` SET `autokickBot`= 0 WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                                        $request_params["message"] = "&#10004;Теперь новые боты не будут автоматически исключаться из беседы!";
                                    } else $request_params["message"] = "&#10060;Не верное действие! Возможные значения: on, off, включить, выключить";
                                    break;

                                default:
                                    $request_params["message"] = "&#10060;Не верно указан топ пользователей! Возможные значения: вышедших, ботов";
                                    break;
                            }

                        } else $request_params["message"] = "&#10060;Вы не указали тип пользователя!";
                    } else $request_params["message"] = "&#10060;Для использования этой команды вы должны быть администратором!";
                }else $request_params["message"] = "&#10060;Эта команда не для личных сообщений!";
            }

            if(isset($data->object->message->action->type))//Инвайты
            if($data->object->message->action->type == "chat_invite_user" || $data->object->message->action->type == "chat_invite_user_by_link"){
                if($data->object->message->action->member_id == (int)("-".$data->group_id)) {
                    $request_params["message"] = "Привет&#9995; Для моей работы мне необходимы права администратора, выдайте их и напишите /начать";
                }
                $get_ban = $mysqli->query("SELECT `autokickBot` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                $get_ban = $get_ban->fetch_assoc();
                if($data->object->message->action->member_id < 0 && $get_ban["autokickBot"] == 1)
                    $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $data->object->message->action->member_id));

                $get_ban = $mysqli->query("SELECT * FROM `". $data->object->message->peer_id ."_bans` WHERE `id` = '" . $data->object->message->from_id . "'");
                if($get_ban != false){
                    $get_ban = $get_ban->fetch_assoc();
                    if ($get_ban["ban"] == 0 || $get_ban["ban"] - time() > 0)
                        $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $data->object->message->action->member_id));
                }else {
                    $mysqli->query("INSERT INTO `" . $data->object->message->peer_id . "_users` (`id`) VALUES ('" . $data->object->message->action->member_id . "')");
                    $res = $mysqli->query("SELECT `greeting` FROM `chats_settings` WHERE `chat_id` = '" . $data->object->message->peer_id . "'");
                    $res = $res->fetch_assoc();
                    if ($res["greeting"] != "") {
                        $name = getName($vk, array($data->object->message->action->member_id), true, true);
                        $request_params["message"] = str_replace("{first_name}", $name[0], $request_params["message"]);
                        if(count($name) > 1)
                        $request_params["message"] = str_replace("{last_name}", $name[1], $request_params["message"]);
                    }
                }

            }elseif($data->object->message->action->type == "chat_kick_user"){//Ливы
                if ($data->object->message->from_id == $data->object->message->action->member_id){//Если пользователь вышел сам
                    $res = $mysqli->query("SELECT `autokickLeave` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                    $res = $res->fetch_assoc();
                    if ($res["autokickLeave"] == 1)
                        $vk->messages()->removeChatUser(TOKEN_VK_BOT, array("chat_id" => $data->object->message->peer_id - 2000000000, "member_id" => $data->object->message->action->member_id));

                }elseif($data->object->message->action->member_id == (int)("-".$data->group_id)){//если кикнули бота
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_users`");
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_punishments`");
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_moders`");
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_leave`");
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_bans`");
                    $mysqli->query("DROP TABLE `" . $data->object->message->peer_id . "_moders_limit`");
                    $mysqli->query("DELETE FROM `chats_settings` WHERE `chat_id` = '" .$data->object->message->peer_id . "'");
                }

            }

            try {
                $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                if($is_varn){
                    $get_rang = $mysqli->query("SELECT `rang` FROM `". $data->object->message->peer_id ."_users` WHERE `id` = '" . $data->object->message->from_id . "'");
                    $get_rang = $get_rang->fetch_assoc();
                    $new_rang = updateRang($data->object->message->from_id, $get_rang["rang"], $mysqli, $data->object->message->peer_id);
                    if($new_rang != false) {
                        $mysqli->query("UPDATE `" . $data->object->message->peer_id . "_users` SET `rang` =  '". $new_rang ."' WHERE `id` = '" . $data->object->message->from_id . "'");
                        $request_params["message"] = "Модератор " . getName($vk, array($data->object->message->from_id))[0] . " повышен до " . getRang($new_rang, true) . "!";
                        $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                    }
                }
            } catch (\VK\Exceptions\VKApiException $e) {
            }

            echo "ok";
        break;

}
function updateRang($id, $rang, $mysqli, $peer_id){
    $res = $mysqli->query("SELECT * FROM `". $peer_id."_moders_limit`");
    $res_limit = array();
    while ($temp = $res->fetch_assoc()){
        if($temp != null)
            $res_limit[] = $temp;
    }
    $res = $mysqli->query("SELECT * FROM `". $peer_id."_moders` WHERE `id` = '". $id ."'");
    $res_moder = $res->fetch_assoc();
    switch ($rang){
        case 1:
            if($res_limit[0]["pred"] <= $res_moder["preds"] && $res_limit[0]["tempban"] <= $res_moder["tempbans"] && $res_limit[0]["kick"] <= $res_moder["kicks"])
                return 2;
            else return false;
            break;
        case 2:
            if($res_limit[1]["pred"] <= ($res_moder["preds"] + $res_limit[0]["pred"]) && $res_limit[1]["tempban"] <= ($res_moder["tempbans"] + $res_limit[0]["tempban"]) && $res_limit[1]["kick"] <= ($res_moder["kicks"] + $res_limit[0]["kick"]))
                return 3;
            else return false;
            break;
        case 3:
            if($res_limit[2]["pred"] <= ($res_moder["preds"] + $res_limit[0]["pred"] + $res_limit[1]["pred"]) && $res_limit[2]["tempban"] <= ($res_moder["tempbans"] + $res_limit[0]["tempban"] + $res_limit[1]["tempban"]) && $res_limit[2]["kick"] <= ($res_moder["kicks"] + $res_limit[0]["kick"] + $res_limit[1]["kick"]))
                return 4;
            else return false;
            break;
        default:
            return false;
        break;
    }
}

function track($mysqli, $id_warn, $id_moder, $num, $reason, $type, $peer_id, $vk){
    $request_params = array(
        'message' => "" , //сообщение
        'access_token' => TOKEN_VK_BOT, //токен для отправки от имени сообщества
        'peer_id' => "", //айди чата
        'random_id' => 0, //0 - не рассылка
        'read_state' => 1,
        'user_ids' => 0, // Нет конкретного пользователя кому адресованно сообщение
        //'reply_to' => $data->object->message->conversation_message_id, //Надеюсь что когда-то это будет работать
        'attachment' => '' //Вложение
    );
    $res = $mysqli->query("SELECT `tracking` FROM `chats_settings` WHERE `chat_id` = '". $peer_id ."'");
    $res = $res->fetch_assoc();
    $ids = explode(",", $res["tracking"]);
    $res_title = $vk->messages()->getConversationsById(TOKEN_VK_BOT, array("peer_ids" => $peer_id));
    $names = getName($vk, array($id_warn, $id_moder));
    foreach ($ids as $key => $id){
        $request_params["peer_id"] = $id;
        $request_params["message"] = "&#128110;В беседе \"" . $res_title["items"][0]["chat_settings"]["title"] . "\" модератор: " . $names[1];
        switch ($type){
            case "kick":
                $request_params["message"] .= " исключил пользователя " . $names[0];
                break;
            case "removeban":
                $request_params["message"] .= " разбанил пользователя " . $names[0];
                break;
            case "removepred":
                $request_params["message"] .= " удалил ";
                if (($num >= 11 && $num <= 19) || (endNumber($num) >= 5 && endNumber($num) <= 9) || endNumber($num) == 0)
                    $request_params["message"] .= " ". $num . " предупреждений у пользователя " . $names[0] ;
                elseif (endNumber($num) == 1)
                    $request_params["message"] .= " ". $num . " предупреждение у пользователя ". $names[0];
                elseif (endNumber($num) >= 2 && endNumber($num) <= 4)
                    $request_params["message"] .= " ". $num . " предупреждения у пользователя ". $names[0];
                break;
            case "tempban":
                $request_params["message"] .= " забанил до " . date("d.m.Y G:i", $num) . "по UTC 0 пользователя " . $names[0];
                break;
            case "ban":
                $request_params["message"] .= " забанил пользователя ". $names[0];
                break;
            case "pred":
                $request_params["message"] .= " выдал ";
                if (($num >= 11 && $num <= 19) || (endNumber($num) >= 5 && endNumber($num) <= 9) || endNumber($num) == 0)
                    $request_params["message"] .= " ". $num . " предупреждений пользователю " . $names[0] ;
                elseif (endNumber($num) == 1)
                    $request_params["message"] .= " ". $num . " предупреждение пользователю ". $names[0];
                elseif (endNumber($num) >= 2 && endNumber($num) <= 4)
                    $request_params["message"] .= " ". $num . " предупреждения пользователю ". $names[0];
                break;
            default:
                $request_params["message"] .= " " . $res["type"] . " пользователя ". $names[0];
                break;
        }

        if($reason != "")
            $request_params["message"] .= " по причине: " . $reason;
        else $request_params["message"] .= " без причины";

        try {
            $vk->messages()->send(TOKEN_VK_BOT, $request_params);
        }catch (\VK\Exceptions\VKApiException $e){
            $request_params["peer_id"] = $peer_id;
            $request_params["message"] = getName($vk, array($id))[0] . " вас указали в качестве контролирующего действия модераторов, пожалуйста разрешите мне отправку личных сообщений";
            $vk->messages()->send(TOKEN_VK_BOT, $request_params);
        }
    }
}

function mb_strcasecmp($str1, $str2, $encoding = null) { //https://www.php.net/manual/en/function.strcasecmp.php#107016 взято от сюда
    if (null === $encoding) { $encoding = mb_internal_encoding(); }
    return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}

function convertTime($times){
    $times = explode(":", $times);
    $curr_times = explode(":", date("s:i:G:j:n:Y",time()));
    return mktime((int)$curr_times[2] + (int)$times[2],(int)$curr_times[1] + (int)$times[1],(int)$curr_times[0] + (int)$times[0],(int)$curr_times[4] + (int)$times[4],(int)$curr_times[3] + (int)$times[3],(int)$curr_times[5] + (int)$times[5]);
}

function convertMicroTime($times){
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

function getRang($id, $update = false){
    switch ($id){
        case 0:
            if($update)
                return "пользователя";
            else
                return "пользователь";
            break;
        case 1:
            if($update)
                return "модератора 1 уровня";
            else
                return "модератор 1 уровня";
            break;
        case 2:
            if($update)
                return "модератора 2 уровня";
            else
                return "модератор 2 уровня";
            break;
        case 3:
            if($update)
                return "модератора 3 уровня";
            else
                return "модератор 3 уровня";
            break;
        case 4:
            if($update)
                return "модератора 4 уровня";
            else
                return "модератор 4 уровня";
            break;
        case 5:
            if($update)
                return "администратора";
            else
                return "администратор";
            break;
        default:
            if($update)
                return "Неизвестного ранга";
            else
                return "Неизвестный ранг";
            break;
    }
}

function getName($vk, $ids, $notify = true, $explode_names = false){
    $user_ids = array(); $group_ids = array(); $names = array(); $user_names = array(); $group_names = array();

    foreach ($ids as $num => $id){
        if($id>0)
            $user_ids[$num] = $id;
        else
            $group_ids[$num] = mb_substr($id, 1);
    }
    $res_user = $vk->users()->get(TOKEN_VK_BOT, array("user_ids" => implode(",", $user_ids)));
    $res_group = $vk->groups()->getById(TOKEN_VK_BOT, array("group_ids" => implode(",", $group_ids)));
    $ifor = 0;
    foreach ($user_ids as $key => $id) {
        if($key != 0) {
            if ($user_ids[$key] != $user_ids[$key - 1])
                $user_names[$key] = $res_user[$ifor];
            else
                $user_names[$key] = $user_names[$key - 1];
        }else $user_names[$key] = $res_user[$ifor];
        $ifor++;
    }
    $ifor = 0;
    foreach ($group_ids as $key => $id) {
        if($key != 0) {
            if ($group_ids[$key] != $group_ids[$key - 1])
                $group_names[$key] = $res_group[$ifor];
            else
                $group_names[$key] = $group_names[$key - 1];
        }else $group_names[$key] = $res_group[$ifor];
        $ifor++;
    }

    for($i = 0; $i < count($ids); $i++){
        if(isset($user_ids[$i])){
            if($explode_names) {
                if ($notify) {
                    $names[$i][0] = "[id" . $user_names[$i]["id"] . "|" . $user_names[$i]["first_name"] . "]";
                    $names[$i][1] = "[id" . $user_names[$i]["id"] . "|" . $user_names[$i]["last_name"] . "]";
                } else {
                    $names[$i][0] = $user_names[$i]["first_name"];
                    $names[$i][1] = $user_names[$i]["last_name"];
                }
            }else
                if ($notify)
                    $names[$i] = "[id" . $user_names[$i]["id"] . "|" . $user_names[$i]["first_name"] . " " . $user_names[$i]["last_name"] . "]";
                else
                    $names[$i] = $user_names[$i]["first_name"] . " " . $user_names[$i]["last_name"];
        }else{
            if ($notify)
                $names[$i] = "[club" . $group_names[$i]["id"] . "|" . $group_names[$i]["name"] . "]";
            else
                $names[$i] = $group_names[$i]["name"];
        }
    }

    return $names;

}

function getId($text = '', $reply_to = ''){
if($reply_to != '' || $text != ''){
    if($reply_to != ''){
        return (int)$reply_to;
    }else
        if(substr($text,0,5) == "[club")
            return (int)("-".substr(explode("|", $text)[0], 5));
        elseif(substr($text,0,3) == "[id") return (int)substr(explode("|",$text)[0], 3);
        else return 0;

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
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_users`(`id` VarChar( 255 ) NOT NULL, `rang` Int( 255 ) NOT NULL DEFAULT 0, `pred` TinyInt( 255 ) NOT NULL DEFAULT 0, `mes_count` Int( 255 ) NOT NULL DEFAULT 0, `lastMes` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_punishments`(`time` Int( 255 ) NOT NULL, `id` VarChar( 255 ) NOT NULL, `id_moder` VarChar( 255 ) NOT NULL, `type` VarChar( 255 ) NOT NULL, `text` VarChar( 255 ) NOT NULL DEFAULT '', `parametr` VarChar( 255 ) NOT NULL DEFAULT '', CONSTRAINT `unique_time` UNIQUE( `time` )) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders`( `id` VarChar( 255 ) NOT NULL, `bans` Int( 255 ) NOT NULL DEFAULT 0, `kicks` Int( 255 ) NOT NULL DEFAULT 0, `tempbans` Int( 255 ) NOT NULL DEFAULT 0, `preds` Int( 255 ) NOT NULL DEFAULT 0, CONSTRAINT `unique_id` UNIQUE( `id` )) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_leave`(`id` VarChar( 255 ) NOT NULL, CONSTRAINT `unique_id` UNIQUE( `id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_bans`(`id` VarChar( 255 ) NOT NULL, `reason` VarChar( 255 ) NOT NULL DEFAULT '', `ban` Int( 255 ) NOT NULL DEFAULT 0 ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `chats_settings`(`chat_id` VarChar( 255 ) NOT NULL, `invite_link` VarChar( 255 ) NOT NULL DEFAULT '',`autokickBot` TinyInt( 1 ) NOT NULL DEFAULT 1, `autokickLeave` TinyInt( 1 ) NOT NULL DEFAULT 0, `greeting` VarChar( 255 ) NULL DEFAULT '', `tracking` VarChar( 255 ) NULL DEFAULT '', `predsvarn` VarChar( 255 ) NOT NULL DEFAULT 'kick:10', `autoremovepred` Int( 255 ) NOT NULL DEFAULT 2678400 , `lastRemovePred` Int( 255 ) NOT NULL,CONSTRAINT `unique_chat_id` UNIQUE( `chat_id` ) ) ENGINE = InnoDB;");
    $mysqli->query("CREATE TABLE IF NOT EXISTS `". $chat_id ."_moders_limit`(`rang` VarChar( 255 ) NOT NULL, `pred` Int( 255 ) NULL, `kick` Int( 255 ) NOT NULL DEFAULT '', `tempban` Int( 255 ) NOT NULL DEFAULT '', CONSTRAINT `unique_rang` UNIQUE( `rang` )) ENGINE = InnoDB;");

    try {
        $res = $vk->messages()->getConversationMembers(TOKEN_VK_BOT, array("peer_id" => $chat_id));

        if (count($res["items"]) != 0) {
            for ($i = 0; isset($res["items"][$i]); $i++) {
                $rang = 0;
                if (isset($res["items"][$i]["is_admin"])) {
                    $rang = 5;
                    $mysqli->query("INSERT INTO `" . $chat_id . "_moders` (`id`) VALUES ('" . $res["items"][$i]["member_id"] . "')");
                }
                $mysqli->query("INSERT INTO `" . $chat_id . "_users` (`id`, `rang`) VALUES ('" . $res["items"][$i]["member_id"] . "', " . $rang . ")");
            }

            $mysqli->query("INSERT INTO IF NOT EXISTS `" . $chat_id . "_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (1, 5, 0, 0)");
            $mysqli->query("INSERT INTO IF NOT EXISTS `" . $chat_id . "_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (2, 8, 2, 0)");
            $mysqli->query("INSERT INTO IF NOT EXISTS `" . $chat_id . "_moders_limit` (`rang`, `pred`, `kick`, `tempban`) VALUES (3, 10, 4, 2)");
            $mysqli->query("INSERT INTO `chats_settings` (`chat_id`, `lastRemovePred`) VALUES (" . $chat_id . "," . time() . ")");
            return true;
        } else return false;
    }catch (VK\Exceptions\VKApiException $e){
        return false;
    }
}
?>