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
                'reply_to' => $data->object->message->conversation_message_id, //Надеюсь что когда-то это будет работать
                'attachment' => '' //Вложение
            );

            if (strcasecmp($text[0], "/") == 0) {
                $request_params['message'] = "&#129302;Bot moderator by kos v2.0.0\n\n"
                    . "&#9999;Команды:\n"
                    . "&#128196;/ — информация о боте\n"
                    . "/User info|Информация пользователя {@Айди|@домен|Пересланое сообщение} — информация пользователя в вк и чате\n"
                    . "/Сократить ссылку {ссылка} [Включить|выключить|on|off] — сокращает ссылку через сервис вк, on - включает статистику\n"
                    . "/Получить статистику {ссылка} {токен} — выводит статистику переходов по сокращенный ссылке (желательно использовать эту команду в личных сообщениях)\n"
                    . "/Инвайт ссылка|Приглашение|Ссылка приглашение - выводит ссылку на приглашение в этот чат\n"
                    . "/Пригласить {@Айди|@домен|Пересланое сообщение} [Сообщение] - отправляет приглашение пользователю в этот чат\n"
                    . "/Список {Пользователей|забаненных|вышедших|модераторов} - выводит указанный список пользователей\n"
                    . "/Settings|настройки - показывает список настроек\n"
                    . "/Неактив - выводит список неактивных пользователей\n"
                    . "/Онлайн - выводит список пользователей онлайн\n"
                    . "/Лимит модераторов - выводит лимит для модераторов\n\n"
                    . "Модерация и Администрация:\n"
                    . "/Предупреждение|Пред {@Айди|@домен|Пересланое сообщение} [Количество] [Причина] - Выдать предупреждение\n"
                    . "/Кик|Исключить {@Айди|@домен|Пересланое сообщение} [Причина] - Исключить пользователя из чата\n"
                    . "/Временный бан {@Айди|@домен|Пересланое сообщение} {Время SS:MM:HH:DDD:MM} [Причина] - Временно забанить пользователя в беседе\n"
                    . "/Бан {@Айди|@домен|Пересланое сообщение} [Причина] - Забанить пользователя\n"
                    . "/Разбанить|Пардон {@Айди|@домен|Пересланое сообщение} [Причина] - Разбанить пользователя\n"
                    . "/Мега кик|мега исключение {Неактивных|вышедших|пользователей} - исключает пользователей из определённой группы\n"
                    . "/Назначит ранг|Сет ранг {@Айди|@домен|Пересланое сообщение} {0|1|2|3|4|5|Модератор 1 - 4|пользователь|администратор} - Разбанить пользователя\n\n"
                    . "&#9881;Настройки:\n"
                    . "/Лимит повышение рангов {Уровень 1 - 5} {Количество предупреждений} {Количество киков} {Количество временных баннов} - устанавливает лимит повышение рангов модераторам\n"
                    . "/Наказания за предупреждения {Тип: кик, временный бан, бан} {Количество} {Время, если тип: временный бан] - Установить наказание за достижение определенного количества предупреждений\n"
                    . "/Очистить таблицу {Пользователей|забаненных|вышедших|модераторов|наказаний|лимит|настроек|всё} - очищает указанную таблицу\n"
                    . "/Авто очистка предупреждений {Время SS:MM:HH:DDD:MM} - сбрасывает всё предупреждения через указанное время\n"
                    . "/Приветствие {Текст} - Устанавливает приветствие для новых пользователей\n"
                    . "/Установить инвайт ссылку {Текст} - Устанавливает ссылку для подключение к беседе\n"
                    . "/Сообщать о наказаниях {@Айди|@домен|Пересланое сообщение} - люди, которым приходят уведомления о выдачи наказаний(если людей несколько, указывать через запятую без пробелов)\n"
                    . "/Автокик|автоисключение {Вышедших|ботов} {Включить|выключить|on|off} - Автоисключение вышедших пользователей или новых ботов\n"
                    . "{} - обязательный параметр, [] - необязательный параметр, {] - тип зависит от задачи\n\n"
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
            }elseif (strcasecmp($text[0] . " " .$text[1], "/user info") == 0 || strcasecmp($text[0] . " " .$text[1], "/Информация пользователя") == 0){
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

                    $request_params["message"] = "Информация о". $type ." [id". $res_user[0]->id . "|". $res_user[0]->first_name ." " .$res_user[0]->last_name ."]: \nАйди: " . $res_user[0]->id;

                    if(isset($res_user[0]->domain)){
                        $request_params["message"] .= "\nДомен: " . $res_user[0]->domain;
                    }if(isset($res_user[0]->status)){
                        if($res_user[0]->status != "")
                        $request_params["message"] .= "\nСтатус: " . $res_user[0]->status;
                    }if(isset($res_user[0]->last_seen)){
                        $request_params["message"] .= "\nПоследний раз был онлайн по UTC 0: " . date("G:i d", $res_user[0]->last_seen->time);
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
                        $request_params["message"] .= " ". date("Y",$res_user[0]->last_seen->time) ." c ";
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
                            $res_user = $res->fetch_assoc();
                            if(isset($res["id"])){
                                $request_params["message"] .= "\nКоличество сообщений в беседе: " . $res["mes_count"];
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
                    }if(isset($res_user[0]->university_name)){
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
                    }if(isset($res_user[0]->activities)){
                        $request_params["message"] .= "\nДеятельность пользователя: " . $res_user[0]->activities;
                    }if(isset($res_user[0]->games)){
                        $request_params["message"] .= "\nЛюбимые игры: " . $res_user[0]->games;
                    }if(isset($res_user[0]->interests)){
                        $request_params["message"] .= "\nИнтересы: " . $res_user[0]->interests;
                    }if(isset($res_user[0]->about)){
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
            }elseif (strcasecmp($text[0] . " " .$text[1], "/Сократить ссылку") == 0){
                if(isset($text[2])){
                    if(strcasecmp($text[3], "on") == 0 || strcasecmp($text[3], "включить") == 0) $stat = 1;
                    else $stat = 0;
                    try {
                        $res_url = $vk->utils()->getShortLink(USER_TOKEN, array("url" => $text[2], "private" => $stat));
                    $request_params["message"] = "Ваша ссылка: " . $res_url["short_url"];
                    if($stat){
                        $request_params["message"] .= " токен для статистики отправлен вам в личные сообщения";
                        $vk->messages()->send(TOKEN_VK_BOT, $request_params);
                        $request_params["peer_id"] = $data->object->message->from_id;
                        $request_params["message"] = "Ваш токен " . $res_url["access_key"] ." для просмотра статистики ссылки: " . $res_url["short_url"];
                    }
                    } catch (\VK\Exceptions\VKApiException $e) {
                        $request_params["message"] = "Что-то не так с ссылкой!";
                    } catch (\VK\Exceptions\VKClientException $e) {
                        $request_params["message"] = "Что-то не так с ссылкой!";
                    }
                }else $request_params["message"] = "Вы не указали ссылку!";

            }elseif (strcasecmp($text[0] . " " .$text[1], "/Получить статистику") == 0){
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
                            $request_params["message"] = "Что-то не так с ссылкой!";
                        } catch (\VK\Exceptions\VKApiException $e) {
                            $request_params["message"] = "Что-то не так с ссылкой!";
                        } catch (\VK\Exceptions\VKClientException $e) {
                            $request_params["message"] = "Что-то не так с ссылкой!";
                        }
                    }else $request_params["message"] = "Вы не указали токен для просмотра статистики!";
                }else $request_params["message"] = "Вы не указали ссылку!";
            }elseif (strcasecmp($text[0] . " " .$text[1], "/Инвайт ссылка") == 0 || strcasecmp($text[0] . " " .$text[1], "/ссылка приглашения") == 0 || strcasecmp($text[0], "/Приглашение") == 0){
                $res = $mysqli->query("SELECT `greeting` FROM `chats_settings` WHERE `chat_id` = '". $data->object->message->peer_id ."'");
                $res = $res->fetch_assoc();
                ob_start();
                var_dump($res);
                error_log(ob_get_contents());
                ob_end_clean();
                if(isset($res["greeting"])){
                    if(strcasecmp($res["greeting"], "") != 0 || $res["greeting"] != null)
                        $request_params["message"] = "Ссылка для приглашения: " . $res["greeting"];
                    else $request_params["message"] = "Администрация беседы не указала ссылку для приглашения";
                }else $request_params["message"] = "Эта команда не для личных сообщений или вашей беседы нету в базе данных!";
            }


        if(isset($data->object->message->action->type))//Инвайты
            if($data->object->message->action->type == "chat_invite_user" || $data->object->message->action->type == "chat_invite_user_by_link"){
                if($data->object->message->action->member_id == (int)("-".$data->group_id))
                    $request_params["message"] = "Для моей работы мне необходимы права администратора. Выдайте права и напишите /начать";


            }

            $vk->messages()->send(TOKEN_VK_BOT, $request_params);

            echo "ok";

            if($data->object->message->peer_id != $data->object->message->from_id) //Если сообщение в беседе добавляем + 1 к количеству сообщений
                $mysqli->query("UPDATE `". $data->object->message->peer_id ."_users` SET `mes_count`= `mes_count` + 1 WHERE `id` = '". $data->object->message->from_id ."'");
        break;

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