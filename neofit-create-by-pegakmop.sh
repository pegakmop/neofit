#!/bin/sh
# POSIX / BusyBox ash совместимо
# Работает на mips-3.4, mipsel-3.4, aarch64-3.10

PATH="$PATH:/opt/bin:/opt/sbin"
umask 022

# ---------- утилиты ----------
ask_mode() {
  echo "Режим:"
  echo "  [1] Установить neofit"
  echo "  [2] Удалить neofit"
  echo "  [3] Выход"
  printf "Ваш выбор (1/2/3): "
  read mode
  case "$mode" in
    1|2|3) ;;
    *) echo "Неверный выбор."; ask_mode ;;
  esac
}

ask_choice_install() {
  echo "Что установить?"
  echo "  [1] neofitsb (sing-box-go)"
  echo "  [2] neofitxray (xray)"
  echo "  [3] Оба пакета neofit"
  printf "Ваш выбор (1/2/3): "
  read choice
  case "$choice" in
    1|2|3) ;;
    *) echo "Неверный выбор."; ask_choice_install ;;
  esac
}

ask_choice_remove_base() {
  echo "Удалить базовые пакеты?"
  echo "  [1] Удалить только sing-box-go"
  echo "  [2] Удалить только xray"
  echo "  [3] Удалить оба (sing-box-go и xray)"
  echo "  [4] Не удалять базовые пакеты, только neofit"
  printf "Ваш выбор (1/2/3/4): "
  read rchoice
  case "$rchoice" in
    1|2|3|4) ;;
    *) echo "Неверный выбор."; ask_choice_remove_base ;;
  esac
}

need_mem_hint() {
  echo "Установка не удалась: возможно, недостаточно памяти. Попробуйте ещё раз либо используйте съемный носитель"
}

require_dir() {
  [ -d "$1" ] || mkdir -p "$1" || {
    echo "Не удалось создать каталог: $1"
    exit 1
  }
}

install_pkg() {
  # $1 = имя пакета
  echo "Устанавливаю пакет: $1 ..."
  opkg install "$1"
  if ! opkg list-installed | awk '{print $1}' | grep -qx "$1"; then
    echo "Пакет $1 не установлен."
    need_mem_hint
    exit 1
  fi
  echo "Пакет $1 установлен."
}

remove_pkg_if_installed() {
  # $1 = имя пакета
  if opkg list-installed | awk '{print $1}' | grep -qx "$1"; then
    echo "Удаляю пакет: $1 ..."
    opkg remove "$1" || echo "Внимание: не удалось удалить $1"
  else
    echo "Пакет $1 не установлен — пропускаю."
  fi
}

download_to() {
  # $1=url $2=dst
  url="$1"
  dst="$2"
  tmp="${dst}.tmp.$$"

  # Предпочтение curl, если нет — пробуем поставить; иначе fallback на wget
  if ! command -v curl >/dev/null 2>&1; then
    opkg install curl >/dev/null 2>&1 || true
  fi

  if command -v curl >/dev/null 2>&1; then
    curl -L -o "$tmp" "$url" || return 1
  else
    wget -O "$tmp" "$url" || return 1
  fi

  mv "$tmp" "$dst" && chmod +x "$dst"
}

stop_neofit_service() {
  if [ -x /opt/etc/init.d/S69neofit ]; then
    /opt/etc/init.d/S69neofit stop >/dev/null 2>&1 || true
  fi
}

remove_neofit_files() {
  echo "Удаляю neofit проект и файлы..."
  stop_neofit_service
  rm -f /opt/bin/neofitsb
  rm -f /opt/bin/neofitxray
  rm -f /opt/etc/init.d/S69neofit
  echo "Готово: neofit полностью удален."
}

detect_arch() {
  # Возьмём первую поддерживаемую архитектуру из вывода opkg
  ARCH=$(
    opkg print-architecture 2>/dev/null | awk '
      /^arch/ && $2 !~ /_kn$/ && $2 ~ /-[0-9]+\.[0-9]+$/ { print $2 }' |
    awk '
      /^(mips-3\.4|mipsel-3\.4|aarch64-3\.10)$/ { print; exit }'
  )
  if [ -z "$ARCH" ]; then
    echo "Unsupported or undetected architecture."
    echo "Поддерживаются: mips-3.4, mipsel-3.4, aarch64-3.10"
    exit 1
  fi
  echo "$ARCH"
}

# ---------- старт ----------
ask_mode
[ "$mode" = "3" ] && { echo "Выход."; exit 0; }

echo "Updating package list..."
opkg update || { echo "opkg update завершился с ошибкой"; exit 1; }

echo "Installing wget with HTTPS support..."
opkg install curl wget-ssl >/dev/null 2>&1 || true
opkg remove wget-nossl >/dev/null 2>&1 || true

if [ "$mode" = "2" ]; then
  # ----- УДАЛЕНИЕ -----
  remove_neofit_files

  # спросить про удаление базовых пакетов
  ask_choice_remove_base
  case "$rchoice" in
    1) remove_pkg_if_installed "sing-box-go" ;;
    2) remove_pkg_if_installed "xray" ;;
    3) remove_pkg_if_installed "sing-box-go"; remove_pkg_if_installed "xray" ;;
    4) echo "Базовые пакеты оставлены." ;;
  esac

  echo "Готово, удаление завершено."
  exit 0
fi

# ----- УСТАНОВКА -----
ask_choice_install

echo "Detecting system architecture (via opkg)..."
ARCH="$(detect_arch)"
echo "Выбрана архитектура: $ARCH"

# Карта ссылок под архитектуры
NEOFITSB_URL=""
NEOFITXRAY_URL=""
case "$ARCH" in
  mips-3.4)
    NEOFITSB_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitsb_mips-3.4/opt/bin/neofitsb_mips-3.4"
    NEOFITXRAY_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitxray_mips-3.4/opt/bin/neofitxray_mips-3.4"
    ;;
  mipsel-3.4)
    NEOFITSB_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitsb_mipsel-3.4/opt/bin/neofitsb_mipsel-3.4"
    NEOFITXRAY_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitxray_mipsel-3.4/opt/bin/neofitxray_mipsel-3.4"
    ;;
  aarch64-3.10)
    NEOFITSB_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitsb_aarch64-3.10/opt/bin/neofitsb_aarch64-3.10"
    NEOFITXRAY_URL="https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofitxray_aarch64-3.10/opt/bin/neofitxray_aarch64-3.10"
    ;;
esac

# Разбор выбора пользователя
SB_CHOSEN=0
XRAY_CHOSEN=0
case "$choice" in
  1) SB_CHOSEN=1 ;;
  2) XRAY_CHOSEN=1 ;;
  3) SB_CHOSEN=1; XRAY_CHOSEN=1 ;;
esac

# Установка базовых пакетов
if [ "$SB_CHOSEN" -eq 1 ]; then
  install_pkg "sing-box-go"
fi
if [ "$XRAY_CHOSEN" -eq 1 ]; then
  install_pkg "xray"
fi

# Проверка наличия бинарников базовых пакетов
if [ "$SB_CHOSEN" -eq 1 ] && ! command -v sing-box >/dev/null 2>&1; then
  echo "sing-box не найден после установки."
  need_mem_hint
  exit 1
fi
if [ "$XRAY_CHOSEN" -eq 1 ] && ! command -v xray >/dev/null 2>&1; then
  echo "xray не найден после установки."
  need_mem_hint
  exit 1
fi

# Подготовка каталогов
require_dir "/opt/bin"
require_dir "/opt/etc/init.d"

# На всякий случай удалим старые обёртки
rm -f /opt/bin/neofit /opt/bin/neofitxray /opt/bin/neofitsb

# Скачивание neofit-* под выбранную архитектуру
if [ "$SB_CHOSEN" -eq 1 ]; then
  echo "Скачиваю neofitsb для $ARCH ..."
  if ! download_to "$NEOFITSB_URL" "/opt/bin/neofitsb"; then
    echo "Не удалось скачать neofitsb попробуйте еще раз запустить скрипт"
    exit 1
  fi
fi
if [ "$XRAY_CHOSEN" -eq 1 ]; then
  echo "Скачиваю neofitxray для $ARCH ..."
  if ! download_to "$NEOFITXRAY_URL" "/opt/bin/neofitxray"; then
    echo "Не удалось скачать neofitxray попробуйте еще раз запустить скрипт"
    exit 1
  fi
fi

# Всегда ставим универсальный init-скрипт
echo "Ставлю init-скрипт S69neofit ..."
if ! download_to "https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/opt/etc/init.d/S69neofit"; then
  echo "Не удалось скачать скрипт автозапуска S69neofit попробуйте еще раз запустить скрипт"
  exit 1
fi

# Старт/перезапуск
/opt/etc/init.d/S69neofit || true

echo "Готово, установка завершена."
