# neofit xray & neofit sing box от создателя проекта @pegakmop

# neofit ресурсы
канал: [https://t.me/neofitkeenetic](https://t.me/neofitkeenetic)
чат: [https://t.me/neofitkeenetic](https://t.me/neofitkeeneticchat)
бот: [https://t.me/neofitkeeneticbot](https://t.me/neofitkeeneticbot)
# установка и запуск или удаление:
Добавление репозитория:
```
opkg update && opkg install curl && curl -Ls "http://www.pegakmop.site/release/keenetic/opkg.sh" | sh
```
Добавление neofit xray:
```
opkg install neofitxray
```
Добавление neofit sing box:
```
opkg install neofitsb
```
Удаление neofit xray:
```
opkg remove neofitxray
```
Удаление neofit sing box:
```
opkg remove neofitsb
``` 
# полезная информация
init.d скрипт автозапуска веб интерфейсов с командами: **nfsb** и **nfxray** с аргументами {**start**|**stop**|**restart**|**status**} либо если вы привыкли пользоваться полными путями:
запуск в фоне neofit xray
``` 
/opt/etc/init.d/S69neofitxray restart
```
потом остановить neofit xray
```
/opt/etc/init.d/S69neofitxray stop
``` 
запуск в фоне neofit sing box
``` 
/opt/etc/init.d/S69neofitsb restart
``` 
потом остановить neofit sing box
```
/opt/etc/init.d/S69neofitsb restart
```
запустить разом neofit sing box и neofit xray
```
/opt/etc/init.d/S69neofitxray restart && /opt/etc/init.d/S69neofitsb restart
``` 
остановить все процессы neofit sing box и neofit xray
```
/opt/etc/init.d/S69neofitxray stop && /opt/etc/init.d/S69neofitsb stop
```
проверить состояние остановлен или работает neofit sing box и neofit xray
```
/opt/etc/init.d/S69neofitxray status && /opt/etc/init.d/S69neofitsb status
```
