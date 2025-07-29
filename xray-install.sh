#!/bin/sh
# Установка NeoFit WebUI

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

echo "Начинается установка NeoFit WebUI..."

run_with_animation "Установка Lighttpd + PHP8" \
    opkg install lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite \
    php8 php8-cgi php8-cli php8-mod-curl php8-mod-openssl php8-mod-session jq

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

run_with_animation "Создание index.php" sh -c 'cat > /opt/share/www/xray/index.php <<EOF
<?php
\$currentVersion = "0.0.0.0";
\$remoteVersionUrl = "https://raw.githubusercontent.com/pegakmop/hrneo/main/version.txt";
\$updateNotice = "";
\$message = "";
\$context = stream_context_create(["http" => ["timeout" => 3]]);
\$remoteContent = @file_get_contents(\$remoteVersionUrl, false, \$context);
if (\$_SERVER["REQUEST_METHOD"] === "POST" && isset(\$_POST["run_update"])) {
    shell_exec("curl -L -s \\"https://raw.githubusercontent.com/pegakmop/hrneo/refs/heads/main/hrneo-web.sh\\" > /tmp/hrneo-web.sh && sh /tmp/hrneo-web.sh");
    \$message = "✔ Обновление запущено. Перезагрузите страницу через пару секунд.";
}
if (\$remoteContent !== false) {
    \$lines = explode("\\n", \$remoteContent);
    foreach (\$lines as \$line) {
        \$parts = explode("=", trim(\$line), 2);
        if (count(\$parts) == 2) \$versionInfo[trim(\$parts[0])] = trim(\$parts[1]);
    }
    if (!empty(\$versionInfo["Version"]) && version_compare(\$versionInfo["Version"], \$currentVersion, ">")) {
        \$updateNotice = "<div class=\\"update-box\\"><h2>Доступно обновление: v" . htmlspecialchars(\$versionInfo["Version"]) . "</h2><p>" . nl2br(htmlspecialchars(\$versionInfo["Show"])) . "</p><form method=\\"post\\"><button type=\\"submit\\" name=\\"run_update\\">⬇️ Обновить сейчас</button></form></div>";
    } else {
        \$updateNotice = "<p class=\\"up-to-date\\">✅ Установлена последняя версия: v" . \$currentVersion . "</p>";
    }
} else {
    \$updateNotice = "<p class=\\"error\\">⚠️ Не удалось получить информацию об обновлении.</p>";
}
?>
<!DOCTYPE html><html lang=\\"ru\\"><head><meta charset=\\"UTF-8\\"><meta name=\\"viewport\\" content=\\"width=device-width, initial-scale=1.0\\"><title>HRNeo Обновление</title><style>body{background:#1e1e2f;color:#e0e0e0;font-family:\\"Segoe UI\\",sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem}.update-box{background:#292c42;padding:2rem;border-radius:10px;max-width:500px;width:100%;box-shadow:0 0 15px rgba(0,0,0,0.5);text-align:center}.update-box h2{color:#68b0ab;margin-bottom:1rem}.update-box p{margin-bottom:1.5rem;line-height:1.5}button{background:#68b0ab;color:#1e1e2f;border:none;padding:0.7rem 1.5rem;font-weight:bold;font-size:1rem;cursor:pointer;border-radius:5px}button:hover{background:#55958f}.up-to-date{text-align:center;font-size:1.1rem;color:#8aff8a}.error{text-align:center;font-size:1.1rem;color:#ff6c6c}.message{text-align:center;font-weight:bold;color:#ffd966;margin-bottom:1rem}</style></head><body><div class=\\"update-box\\"><?php if (\$message): ?><div class=\\"message\\"><?= htmlspecialchars(\$message) ?></div><?php endif; ?><?= \$updateNotice ?></div></body></html>
EOF'

run_with_animation "Загрузка иконок" sh -c '
curl -sL https://raw.githubusercontent.com/pegakmop/hrneo/refs/heads/main/opt/share/www/hrneo/180x180.png -o /opt/share/www/hrneo/180x180.png
curl -sL https://raw.githubusercontent.com/pegakmop/hrneo/refs/heads/main/opt/share/www/hrneo/apple-touch-icon.png -o /opt/share/www/hrneo/apple-touch-icon.png
'

run_with_animation "Настройка Lighttpd" sh -c 'cat > /opt/etc/lighttpd/conf.d/80-hrneo.conf <<EOF
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
echo "✅ NeoFit WebUI установлен. Откройте в браузере: http://$ip_address:88"
