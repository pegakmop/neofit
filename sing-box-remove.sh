#!/bin/sh
# Удаление NeoFit sing-box-go WebUI
#  curl -o /opt/root/neofit.sh https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/sing-box/sing-box-remove.sh && chmod +x /opt/root/neofit.sh && /opt/root/neofit.sh
# === АНИМАЦИЯ ===
animation() {
    local pid=$1 message=$2 spin='|/-\' i=0
    echo -n "[ ] $message..."
    while kill -0 "$pid" 2>/dev/null; do
        i=$(( (i + 1) % 4 ))
        printf "\r[%s] %s..." "${spin:$i:1}" "$message"
        # если нет usleep, раскомментируй следующую строку и закомментируй usleep
        # sleep 0.1
        usleep 100000 2>/dev/null || sleep 0.1
    done
    wait "$pid"
    if [ $? -eq 0 ]; then
        printf "\r[✔] %s\n" "$message"
    else
        printf "\r[✖] %s\n" "$message"
    fi
}

run_with_animation() {
    local msg="$1"; shift
    ( "$@" ) >/dev/null 2>&1 &
    animation $! "$msg"
}

echo "Начинаем удаление NeoFit WebUI..."
echo ""

# Правильный вызов: передаём обе команды внутрь
run_with_animation "Остановка сервисов" \
    sh -c '/opt/etc/init.d/S80lighttpd stop; /opt/etc/init.d/S99sing-box stop'

echo ""
echo "Начинается удаление NeoFit WebUI..."
echo "[*] Удаление установленных пакетов..."

REQUIRED_PACKAGES="lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite php8 php8-cgi php8-cli php8-mod-curl php8-mod-openssl sing-box-go jq"

for pkg in $REQUIRED_PACKAGES; do
    if opkg list-installed | awk '{print $1}' | grep -qx "$pkg"; then
        echo "[+] Удаление $pkg..."
        if ! opkg remove "$pkg" --force-depends >/dev/null 2>&1; then
            echo "[X] Ошибка при удалении пакета: $pkg"
            exit 1
        fi
    else
        echo "[-] $pkg не установлен — пропуск"
    fi
done
echo ""

# Групповые команды под одной анимацией
run_with_animation "Удаление директорий и настроек" \
    sh -c 'rm -rf /opt/share/www/sing-box; \
           rm -rf /opt/etc/lighttpd; \
           rm -rf /opt/etc/sing-box; \
           ndmc -c "no interface Proxy0" >/dev/null 2>&1; \
           ndmc -c "system configuration save" >/dev/null 2>&1'

echo ""
sleep 2
echo "✅ NeoFit WebUI удален"
rm -- "$0"
