# Kandinsky3-PHP
Класс для общения с Yandex GPT в PHP

Получите ключи по инструкции https://fusionbrain.ai/docs/doc/api-dokumentaciya/ и вставьте их в скрипт 
   
Использование:
```
require __DIR__ . '/yachat.php';
use yachat\YaChat;

$ya = yachat::getInstance();
echo $ya::answer('Привет');
```

