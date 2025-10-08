# neofit
init.d скрипт автозапуска веб интерфейсов с командой **neofit** и аргументами {**start**|**stop**|**restart**|**status**}
```
curl -o /opt/etc/init.d/S69neofit https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/opt/etc/init.d/S69neofit
ls -la /opt/etc/init.d/S69neofit
chmod +x /opt/etc/init.d/S69neofit
ls -la /opt/etc/init.d/S69neofit
/opt/etc/init.d/S69neofit
```
запуск в фоне neofitxray
``` 
/opt/bin/neofitxray >/dev/null 2>&1 &
```
потом остановить neofitxray
```
ps | grep neofitxray | grep -v grep | awk '{print $1}' | xargs kill -9 && ps | grep neofitxray
``` 
запуск в фоне neofitsb
``` 
/opt/bin/neofitsb >/dev/null 2>&1 &
``` 
потом остановить neofitsb
```
ps | grep neofitsb | grep -v grep | awk '{print $1}' | xargs kill -9 && ps | grep neofitsb
```
запустить разом оба неофита
```
(/opt/bin/neofitxray >/dev/null 2>&1 &) ; (/opt/bin/neofitsb >/dev/null 2>&1 &)
``` 
остановить все процессы neofit
```
stop="neofit"; ps | grep "$stop" | grep -v grep | awk '{print $1}' | xargs kill -9 2>/dev/null; echo "---если остановлен должно быть пусто---"; ps | grep "$stop" | grep -v grep
```
проверить состояние остановлен или работает
```
ps | grep neofit | grep -v grep
```
узнать pid
```
ps | grep neofit | grep -v grep | awk '{print $1}'
```
