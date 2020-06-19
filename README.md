#Bot moderator for vk / Бот модератор для вк
### Created using the libraries' / Создано с использованием библиотек: [php-getting-started](https://github.com/heroku/php-getting-started), [vk-php-sdk](https://github.com/VKCOM/vk-php-sdk)

## Connection / Подключение

**Install [Git](https://git-scm.com/downloads), [Heroku ToolBelt](https://devcenter.heroku.com/articles/heroku-cli), and [Composer](https://getcomposer.org/)**

### For Heroku / Для Heroku
Сopy repository to server folder / Скопируйте репозиторий в папку сервера

`git clone https://github.com/kos234/Vk-bot-moderator.git`

`cd vk-bot-moderator`

`heroku login`

`heroku create <Your project name>`

`git push heroku master`

`heroku addons:create cleardb:ignite`


Now you need to create configs / Теперь вам нужно создать конфиги

`heroku config:set CONFIRMATION_TOKEN_VK_BOT=` String that the server should return (Group)/ Строка, которую должен вернуть сервер (Сообщество)

`heroku config:set TOKEN_VK_BOT=` Access key (Group)/ Ключ доступа (Сообщество)

`heroku config:set SECRET_KEY_VK_BOT=` Secret key (Group)/ Секретный ключ (Сообщество)

`heroku config:set USER_TOKEN=` Access token (User)/ Токен (Пользователь) - **Required for methods of displaying information about a user, unless you want to specify your token, you can specify group token (TOKEN_VK_BOT) / Необходим для методов вывода информации о пользователе, если не хотите указывать свой токен, можете указать токен группы (TOKEN_VK_BOT)**

`heroku config:set SERVICE_KEY=` Service access key (App)/ Сервисный ключ доступа (Приложение)

###For another hosting service / Для другого хостинга
 
Create a Composer project in server folder / В папке сервера создайте проект Composer

`composer init`

Installing VK SDK / Устанавливаем VK SDK

`composer require vkcom/vk-php-sdk`

Copying the file `index.php` go to the server folder and change `require('../vendor/autoload.php');` on `require('vendor/autoload.php');`. In `index.php` instead of `getenv`, specify the values directly. Values are described in the settings for **Heroku**

Копируем файл `index.php` в папку сервера и меняем `require('../vendor/autoload.php');` на `require('vendor/autoload.php');`. В `index.php` вместо `getenv` укажите значения напрямую, описание значений есть в установки для **Heroku**

###Without hosting / Без хостинга

Invite the bot to a VK conversation / Пригласите бота в беседу вк https://vk.com/app6441755_-195541692?ref=group_menu