# neofit xray & neofit sing box от создателя проекта @pegakmop

# neofit оффициальные ресурсы:

Наш канал: [https://t.me/neofitkeenetic](https://t.me/neofitkeenetic)

Наш чат: [https://t.me/neofitkeenetic](https://t.me/neofitkeeneticchat)

Наш бот: [https://t.me/neofitkeeneticbot](https://t.me/neofitkeeneticbot)

Поддержать развитие рублем для автора: [https://yoomoney.ru/to/410012481566554](https://yoomoney.ru/to/410012481566554)

# установка и запуск или удаление:
Добавление нашего репозитория:
```
opkg update && opkg install curl && curl -Ls "http://www.pegakmop.site/release/keenetic/opkg.sh" | sh
```
Установка neofit xray & sing-box-go:
```
opkg install neofit
```
Установка neofit xray:
```
opkg install neofitxray
```
Установка neofit sing box:
```
opkg install neofitsb
```
Удаление neofit xray & sing-box-go:
```
opkg remove neofit
```
Удаление neofit xray:
```
opkg remove neofitxray
```
Удаление neofit sing box:
```
opkg remove neofitsb
```
# Как получать обновления?
```
opkg update && opkg upgrade
```
# полезная информация о командах пакетов:
init.d скрипт автозапуска веб интерфейсов с командами: **nfsb** и **nfxray** и с аргументами {**start**|**stop**|**restart**|**status**} либо если вы привыкли пользоваться полными путями:
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
