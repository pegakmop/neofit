#!/opt/bin/sh
#curl -sL https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/020-sing-box.sh -o /opt/etc/ndm/netfilter.d/020-sing-box.sh
#chmod +x /opt/etc/ndm/netfilter.d/020-sing-box.sh
#/opt/etc/ndm/netfilter.d/020-sing-box.sh
# Ждём 5 секунд, чтобы интерфейс tun+ успел появиться
sleep 5

# Функция для проверки существования правила
rule_exists() {
    iptables-save | grep -q -- "$1"
}

# Добавляем правило INPUT, если его ещё нет
RULE="-A INPUT -i tun+ -j ACCEPT"
if ! rule_exists "$RULE"; then
    /opt/sbin/iptables -A INPUT -i tun+ -j ACCEPT
    logger "020-sing-box.sh: Added rule: $RULE"
else
    logger "020-sing-box.sh: Rule already exists: $RULE"
fi

# Добавляем правило FORWARD (входящий трафик), если его ещё нет
RULE="-A FORWARD -i tun+ -j ACCEPT"
if ! rule_exists "$RULE"; then
    /opt/sbin/iptables -A FORWARD -i tun+ -j ACCEPT
    logger "020-sing-box.sh: Added rule: $RULE"
else
    logger "020-sing-box.sh: Rule already exists: $RULE"
fi

# Добавляем правило FORWARD (исходящий трафик), если его ещё нет
RULE="-A FORWARD -o tun+ -j ACCEPT"
if ! rule_exists "$RULE"; then
    /opt/sbin/iptables -A FORWARD -o tun+ -j ACCEPT
    logger "020-sing-box.sh: Added rule: $RULE"
else
    logger "020-sing-box.sh: Rule already exists: $RULE"
fi
