#!/bin/sh
# Установка NeoFit Xray WebUI
#  curl -o /opt/root/neofit.sh https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/xray/xray-install.sh && chmod +x /opt/root/neofit.sh && /opt/root/neofit.sh
# === АНИМАЦИЯ ===
animation() {
    local pid=$1 message=$2 spin='|/-\\' i=0
    echo -n "[ ] $message..."
    while kill -0 $pid 2>/dev/null; do
        i=$(( (i+1) %4 ))
        printf "\r[%s] %s..." "${spin:$i:1}" "$message"
        usleep 100000
    done
    wait $pid
    if [ $? -eq 0 ]; then
        printf "\r[✔] %s\n" "$message"
    else
        printf "\r[✖] %s\n" "$message"
    fi
}

run_with_animation() {
    local msg="$1"
    shift
    ("$@") >/dev/null 2>&1 &
    animation $! "$msg"
}

echo "Отказ от ответственности:"
echo ""
echo "Автор проекта NeoFit WebUI снимает с себя любую ответственность за любые неправомерные действия пользователя."
echo ""
echo "При использовании данного софта в неправомерных целях пользователь сам несет ответственность за свои действия."
echo ""
echo "Если пользователь не согласен с заявлением выше, пользователь обязан прервать установку софта нажав ^C либо закрыв терминал!"
sleep 6
echo ""
echo "Начинаем установку NeoFit Xray WebUI..."
echo ""
run_with_animation "Добавление DNS 9.9.9.9 и 8.8.4.4"
ndmc -c "dns-proxy tls upstream 9.9.9.9 sni dns.quad9.net" >/dev/null 2>&1
ndmc -c "dns-proxy tls upstream 8.8.4.4 sni dns.google" >/dev/null 2>&1
ndmc -c "system configuration save" >/dev/null 2>&1
echo ""
echo "Начинается установка NeoFit Xray WebUI..."

run_with_animation "Установка Lighttpd + PHP8" \
    opkg install lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite php8 php8-cgi xray

run_with_animation "Создание директорий" \
    mkdir -p /opt/share/www/xray /opt/etc/lighttpd/conf.d

run_with_animation "Создание manifest.json" sh -c 'cat > /opt/share/www/xray/manifest.json <<EOF
{
  "name": "NeoFit",
  "short_name": "NeoFit",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#1b2434",
  "theme_color": "#fff",
  "orientation": "any",
  "prefer_related_applications": false,
  "icons": [
    { "src": "180x180.png", "sizes": "180x180", "type": "image/png" }
  ]
}
EOF'

run_with_animation "Создание index.php" sh -c '
curl -sL https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/xray/index.php -o /opt/share/www/xray/index.php
'
run_with_animation "Загрузка иконок" sh -c '
curl -sL https://raw.githubusercontent.com/pegakmop/hrneo/refs/heads/main/opt/share/www/hrneo/180x180.png -o /opt/share/www/hrneo/180x180.png
curl -sL https://raw.githubusercontent.com/pegakmop/hrneo/refs/heads/main/opt/share/www/hrneo/apple-touch-icon.png -o /opt/share/www/hrneo/apple-touch-icon.png
'

run_with_animation "Настройка Lighttpd" sh -c 'cat > /opt/etc/lighttpd/conf.d/80-xray.conf <<EOF
server.port := 8896
server.username := ""
server.groupname := ""

\$HTTP["host"] =~ "^(.+):8896$" {
    url.redirect = ( "^/xray/" => "http://%1:96" )
    url.redirect-code = 301
}

\$SERVER["socket"] == ":96" {
    server.document-root = "/opt/share/www/"
    server.modules += ( "mod_cgi" )
    cgi.assign = ( ".php" => "/opt/bin/php8-cgi" )
    setenv.set-environment = ( "PATH" => "/opt/bin:/usr/bin:/bin" )
    index-file.names = ( "index.php" )
    url.rewrite-once = ( "^/(.*)" => "/xray/$1" )
}
EOF'

run_with_animation "Перезапуск Lighttpd" /opt/etc/init.d/S80lighttpd restart

ip_address=$(ip addr show br0 2>/dev/null | awk '/inet / {print $2}' | cut -d/ -f1 | head -n1)
echo ""
echo "✅ NeoFit WebUI установлен. Откройте в браузере: http://$ip_address:96"
rm "$0"
echo ""
echo "Угостить кофем разработчика скинув донат:"
echo ""
echo "https://yoomoney.ru/to/410012481566554  кошелек юмани"
echo "2200700768833807 карта тинькоф"
echo ""
sleep 5
echo "Канал в телеграм: https://t.me/neofitkeenetic"
echo ""
