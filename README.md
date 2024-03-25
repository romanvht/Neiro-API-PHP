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
