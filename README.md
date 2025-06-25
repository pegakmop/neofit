# NeoFit WebUI
Данный проект создан для облегчения соблюдения личной конфиденцальности пользователя по законам Конституции его страны, при использовании entware с помощью sing-box-go пакета предоставляя веб интерфейс и настройку на роутере за пользователя одной командой. Проект представлен для в познавательных целях, автор проекта не несет ответственности за нарушение пользователем законов его страны и за неправомерное использование данного софта пользователем.

Установить можно командой:
```
curl -o /opt/root/neofit.sh https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/sing-box-go-install.sh && chmod +x /opt/root/neofit.sh && /opt/root/neofit.sh
```
Удалить можно командой:
```
/opt/etc/init.d/S80lighttpd stop && /opt/etc/init.d/S99sing-box stop && rm -rf /opt/share/www/sing-box-go && rm -rf /opt/etc/sing-box/config.json && opkg remove lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite php8 php8-cgi php8-cli php8-mod-curl php8-mod-openssl php8-mod-session sing-box-go jq
```
