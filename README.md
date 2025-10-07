# neofit

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
остановить все процессы neofit
```
stop="neofit"; ps | grep "$stop" | grep -v grep | awk '{print $1}' | xargs kill -9 2>/dev/null; echo "--- если остановлен должно быть пусто ---"; ps | grep "$stop" | grep -v grep
```
проверить состояние остановлен или работает
```
ps | grep neofit | grep -v grep
```
узнать pid
```
ps | grep neofit | grep -v grep | awk '{print $1}'
```
