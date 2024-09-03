# GigaChat PHP
Класс для общения с GigaChat сбера в PHP.

1. Закодируйте начения Client Id и Client Secret в Base 64, взятые из https://developers.sber.ru/studio/workspaces/
2. Полученый ключ добавьте в CLIENT_AUTH скрипта

Использование:
```
require __DIR__ . '/gigachat.php';
use neiro\Gigachat;

if($giga = gigachat::getInstance()){
  echo $giga::answer('Привет');
}
```

Для запроса картинок используется функция ```get_image()```, в ней следует изменить директорию сохранения изображений.
В функции ```answer()``` можно изменить формат вывода изображений в ответе

# Yandex-GPT-PHP
Класс для общения с Yandex GPT в PHP

1. Получите OAuth токен Яндекса и вставьте его в CLIENT_AUTH скрипта ([https://cloud.yandex.ru/ru/docs/iam/operations/iam-token/create](https://cloud.yandex.ru/ru/docs/iam/operations/iam-token/create#api_1))
2. Получите идентификатор каталога, на который у вашего аккаунта есть роль ai.languageModels.user или выше b вставьте его в X_FOLDER_ID скрипта (https://cloud.yandex.ru/ru/docs/resource-manager/operations/folder/get-id#console_1)
   
Использование:
```
require __DIR__ . '/yachat.php';
use neiro\YaChat;

if($ya = yachat::getInstance()){
   echo $ya::answer('Привет');
}
```

# Kandinsky3-PHP
Класс для генерации изображений с помощью Kandinsky 3 в PHP

Получите ключи по инструкции https://fusionbrain.ai/docs/doc/api-dokumentaciya/ и вставьте их в скрипт 
   
Использование:
```
require __DIR__ . '/kandinsky.php';
use neiro\imageGen;

if($kd = imageGen::getInstance()){
   print_r($kd::promt('Зеленый кот'));
}
```
Для выбора соотношения сторон нужно добавить к промту `[х:у]`.

Доступные соотношения: `'16:9', '9:16', '3:2', '2:3'`.
