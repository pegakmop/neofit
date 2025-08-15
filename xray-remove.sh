#!/bin/sh
# Удаление NeoFit Xray WebUI
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


echo "Начинаем удаление NeoFit WebUI..."
echo ""
run_with_animation "Остановка сервисов"
/opt/etc/init.d/S80lighttpd stop
/opt/etc/init.d/S24xray stop
echo ""
echo "Начинается удаление NeoFit WebUI..."

run_with_animation "Удаление Lighttpd + PHP8" \
    opkg remove lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite php8 php8-cgi xray

run_with_animation "Удаление директорий" \
    rm -rf /opt/share/www/xray 
    rm -rf /opt/etc/lighttpd
    rm -rf /opt/etc/xray
    ndmc -c "no interface Proxy0" >/dev/null 2>&1
    ndmc -c "system configuration save" >/dev/null 2>&1
echo ""

echo ""
echo "✅ NeoFit WebUI удален"
rm "$0"
