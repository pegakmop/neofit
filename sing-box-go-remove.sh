#!/bin/sh
# === Installer NeoFit by @pegakmop ===

HRNEO_DIR="/opt/share/www/sing-box-go"
INDEX_FILE="$HRNEO_DIR/index.php"
MANIFEST_FILE="$HRNEO_DIR/manifest.json"
LIGHTTPD_CONF_DIR="/opt/etc/lighttpd/conf.d"
LIGHTTPD_CONF_FILE="$LIGHTTPD_CONF_DIR/80-sing-box-go.conf"
ip_addres=$(ip addr show br0 | grep 'inet ' | awk '{print $2}' | cut -d/ -f1)
echo ""
echo "Начинаем удаление NeoFit WebUI..."
echo ""
echo "[*] Останавливаем процессы"
/opt/etc/init.d/S80lighttpd stop
/opt/etc/init.d/S99sing-box stop
echo ""
echo "[*] Удаление установленных пакетов..."
opkg remove lighttpd lighttpd-mod-cgi lighttpd-mod-setenv lighttpd-mod-redirect lighttpd-mod-rewrite php8 php8-cgi php8-cli php8-mod-curl php8-mod-openssl sing-box-go jq --force-depends
echo ""
echo "[*] Удаление директорий..."
rm -rf "$HRNEO_DIR"
rm -rf "$LIGHTTPD_CONF_DIR"
echo ""
echo "[*] Удаление с автозагрузки..."
rm -rf /opt/etc/init.d/S99sing-box-neofit-opkgtun
rm -rf /opt/etc/init.d/S80lighttpd
rm -rf /opt/bin/neofitweb
rm -rf /opt/etc/sing-box
ndmc -c "no interface Proxy0" >/dev/null 2>&1
ndmc -c "no interface OpkgTun0" >/dev/null 2>&1
ndmc -c "system configuration save" >/dev/null 2>&1
echo ""
echo "[*] Аннигилятор веб панели удален."
rm "$0"
echo ""
echo "[*] NeoFit WebUi removed"
