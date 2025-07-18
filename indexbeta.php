<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // 📌 Проверка наличия компонента proxy
    if (isset($input['check_proxy_component'])) {
    $success = false;

    for ($i = 0; $i < 3; $i++) {
        $output = shell_exec("ndmc -c \"components list\" 2>&1");
        if (strpos($output, "name: proxy") !== false && strpos($output, "installed:") !== false) {
            $success = true;
            break;
        }
        usleep(500000); // подождать полсекунды и попробовать снова
    }

    if ($success) {
        echo "✅ Клиент прокси установлен.";
    } else {
        echo "❌ Компонент клиент прокси не установлен.\n❗️ Установка Proxy0 отменена.\n⚠️ В веб интерфейсе роутера Keenetic заходим во вкладку > Параметры системы >> Изменить набор компонентов >>> Клиент прокси >>>> Поставить галочку и сохранить, роутер перезагрузится и установит компонент!";
    }

    exit;
}


    // 📦 Проверка и установка обновления интерфейса
    $currentVersion    = "0.0.0.10";
    $remoteVersionUrl  = "https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/neofit-version.txt";
    $context           = stream_context_create(["http" => ["timeout" => 3]]);
    $remoteContent     = @file_get_contents($remoteVersionUrl, false, $context);

    // Проверка обновления
    if (isset($input['check_update'])) {
        $response = ['current' => $currentVersion, 'update_available' => false];

        if ($remoteContent !== false) {
            $lines = explode("\n", $remoteContent);
            $versionInfo = [];
            foreach ($lines as $line) {
                $parts = explode("=", trim($line), 2);
                if (count($parts) === 2) {
                    $versionInfo[trim($parts[0])] = trim($parts[1]);
                }
            }
            if (!empty($versionInfo["Version"])) {
                $response['latest']           = $versionInfo["Version"];
                $response['show']             = $versionInfo["Show"] ?? '';
                $response['update_available'] = version_compare($versionInfo["Version"], $currentVersion, ">");
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Неверный формат файла обновления.']);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Не удалось получить информацию об обновлении.']);
            exit;
        }

        echo json_encode($response);
        exit;
    }

    // Запуск обновления интерфейса
    if (isset($input['run_update'])) {
        $out = shell_exec(
            'curl -sL "https://raw.githubusercontent.com/pegakmop/neofit/refs/heads/main/indexbeta.php" '
          . '-o /opt/share/www/sing-box-go/index.php 2>&1'
        );
        echo json_encode([
            'message' => '✔ NeoFit WebUI установил обновления. Перезагружаю веб страницу. Вы можете теперь угостить меня ☕️ кофе, заодно поддержав этим стимул фиксить баги и выпускать обновления быстрее, с помощью кнопок юмани или Тинькофф ссылки. Приятного использования.',
            'log'     => $out
        ]);
        exit;
    }
    

    // Повторная проверка IP (proxy)
    if (isset($input['check_only'])) {
        $externalIp = trim(shell_exec('curl -s myip.wtf'));
        sleep(3);
        $proxyIp    = trim(shell_exec('curl -s --interface t2s0 myip.wtf'));
        echo json_encode([
            'external_ip' => $externalIp,
            'proxy_ip'    => $proxyIp
        ]);
        exit;
    }

    // Установка конфига sing-box
    if (isset($input['config'])) {
        $configPath = '/opt/etc/sing-box/config.json';
            $configDir  = dirname($configPath);

    
    if (!is_dir($configDir)) {
        if (!mkdir($configDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Не удалось создать каталог для конфига.']);
            exit;
        }
    }

        $success    = file_put_contents($configPath, $input['config']);
        if ($success === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка при сохранении файла.']);
            exit;
        }
        sleep(5);
        $restart    = shell_exec('/opt/root/opkgtun.sh 2>&1');
        sleep(5);
        $status     = shell_exec('/opt/etc/init.d/S99sing-box status 2>&1');
        sleep(8);
        $externalIp = trim(shell_exec('ping -I opkgtun0 1.1.1.1 -c 3'));
        #$externalIp = trim(shell_exec('curl -s myip.wtf'));
        sleep(3);
        $proxyIp    = trim(shell_exec('curl -s --interface t2s0 myip.wtf'));

        echo json_encode([
            'restart'     => $restart,
            'status'      => $status,
            'external_ip' => $externalIp,
            'proxy_ip'    => $proxyIp,
            'message'     => 'Конфиг успешно сохранён: /opt/etc/sing-box/config.json.'
        ]);
        exit;
    }

    // Установка интерфейса Proxy0
    if (isset($input['proxy_commands']) && is_array($input['proxy_commands'])) {
        $log = [];
        foreach ($input['proxy_commands'] as $cmd) {
            $out     = shell_exec($cmd . ' 2>&1');
            $log[]   = "» $cmd\n$out";
        }
        echo implode("\n", $log);
        exit;
    }
        // Отключение IPv6
    if (isset($input['disable_ipv6'])) {
        $script = <<<SH
#!/bin/sh
curl -kfsS http://localhost:79/rci/show/interface/ | jq -r '
  to_entries[] |
  select(.value.defaultgw == true or .value.via != null) |
  if .value.via then "\\(.value.id) \\(.value.via)" else "\\(.value.id)" end
' | while read -r iface via; do
  echo "⛔️ Отключаем IPv6 на \$iface..."
  ndmc -c "no interface \$iface ipv6 address"
  if [ -n "\$via" ]; then
    echo "⛔️ Отключаем IPv6 на \$via..."
    ndmc -c "no interface \$via ipv6 address"
  fi
done
echo "💾 Сохраняем конфигурацию..."
ndmc -c "system configuration save"
echo "✅ Готово. IPv6 отключён на нужных интерфейсах."
SH;

        $tmp = '/tmp/disable_ipv6.sh';
        file_put_contents($tmp, $script);
        chmod($tmp, 0755);
        $out = shell_exec("$tmp 2>&1");

        echo json_encode([
            'message' => '🛠 IPv6 отключён',
            'log'     => $out
        ]);
        exit;
    }

    // Неизвестный запрос
    http_response_code(400);
    echo "Ошибка: неизвестный формат запроса.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>NeoFit WebUI Sing-box</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --bg: #f8f9fa;
    --text: #212529;
    --card-bg: #ffffff;
    --input-bg: #ffffff;
    --border: #ced4da;
    --highlight: #0d6efd;
  }

  [data-theme="dark"] {
    --bg: #121212;
    --text: #e0e0e0;
    --card-bg: #1e1e1e;
    --input-bg: #2a2a2a;
    --border: #444;
    --highlight: #0d6efd;
  }

  body {
    background-color: var(--bg) !important;
    color: var(--text);
  }

  .card {
    background-color: var(--card-bg);
    color: var(--text);
  }

  .form-control,
  .form-select,
  .form-check-input {
    background-color: var(--input-bg);
    color: var(--text);
    border-color: var(--border);
  }

  .form-control::placeholder {
    color: #aaa;
  }

  pre,
  textarea {
    background-color: var(--input-bg);
    color: var(--text);
  }

  .btn-primary {
    background-color: var(--highlight);
    border-color: var(--highlight);
  }

  .btn-outline-secondary,
  .btn-outline-danger,
  .btn-outline-primary {
    color: var(--highlight);
    border-color: var(--highlight);
  }
    
[data-theme="dark"] .modal-content {
  background-color: var(--card-bg);
  color: var(--text);
}

[data-theme="dark"] .modal-header {
  border-bottom-color: var(--border);
}

[data-theme="dark"] .modal-body {
  border-top-color: var(--border);
}

[data-theme="dark"] .btn-close {
  filter: invert(1);
}

[data-theme="dark"] #installOutput {
  background-color: var(--input-bg);
  color: var(--text);
  border-color: var(--border);
}
</style>
</head>
<body>
  <div class="container mt-5">
    <div class="card shadow">
      <div class="card-body">
      <div class="text-end mb-3">
      </div> 
        <h3 class="card-title mb-4" <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()">
   👉 🌓 👈 NeoFit для Sing-box
  </button></h3>

<div class="mb-3">
  <label for="router" class="form-label">
    IP роутера (local ip & public ip):
  </label>
  <input
    type="text"
    id="router"
    class="form-control"
    value=""
  >
</div>

<script>
window.addEventListener("DOMContentLoaded", () => {
  const routerInput = document.getElementById("router");

  if (routerInput && location.hostname.match(/^(\d{1,3}\.){3}\d{1,3}$/)) {
    // Только IP, без порта
    routerInput.value = location.hostname;
  } else {
    // Полный host с портом, если есть
    routerInput.value = "192.168.1.1";
  }
});
</script>

        <div class="mb-3">
          <label for="links" class="form-label">
            Прокси ссылки ключи для config.json:
          </label>
          <textarea
            id="links"
            class="form-control"
            rows="8"
            placeholder="ss://, vless:// Каждый добавленный ключ должен быть с новой строки, пробелы недопустимы, только перенос с новой строки! Пример ниже:
ss://тутпервыйключ
vless://тутследующийключ
          "></textarea>
        </div>

        <div class="form-check mb-3">
          <input
            class="form-check-input"
            type="checkbox"
            id="includeClashApi"
            checked
          >
          <label class="form-check-label" for="includeClashApi">
            <button id="goTo9090Btn" class="btn btn-sm btn-outline-primary d-none" onclick="goTo9090()"> Веб интерфейс Sing-Box </button>

          </label>
        </div>
        <button  
            id="updateBtn"
            class="btn btn-outline-danger d-none"
            onclick="runUpdate()"
          >⬇️ Обновить веб интерфейс</button>
          <div id="warnings" class="text-danger mb-3"></div>
          <button><a href="https://yoomoney.ru/to/410012481566554">на ☕️ Юмани</a></button>
        <button><a href="https://www.tinkoff.ru/rm/seroshtanov.aleksey9/HgzXr74936">на ☕️Тинькофф</a></button> </br></br>

        <div class="d-flex gap-2 mb-3">
          <button
            class="btn btn-primary"
            onclick="generateConfig()"
          >Установить на Кинетик</button>
          <button
            id="pasteBtn"
            class="btn btn-outline-secondary btn-sm"
            onclick="pasteClipboard()"
          >📋 Вставить url</button>
          <button hidden
  id="ipv6Btn"
  class="btn btn-danger d-none"
  onclick="disableIPv6()">Отключить: Протокол IPv6</button>
        </div>
        <div class="d-flex gap-2 mb-3">
          <button hidden 
          id="installAllBtn" 
          class="btn btn-success d-none"
          onclick="installAll()">🚀 Установить Proxy0 + config.json</button>
        </div>

        <div id="resultWrapper" class="d-none">
          <h5>Результат:</h5>
          <pre
            id="result"
            class="bg-dark text-white p-3 rounded"
            style="white-space: pre-wrap;"
          ></pre>
          <div class="mt-2">
            <a
              id="downloadBtn"
              class="btn btn-success d-none"
              download="config.json"
            >⬇ Скачать на устройство</a></br></br>
            <button
              id="copyBtn"
              class="btn btn-secondary d-none"
              onclick="copyConfig()"
            >Скопировать в буфер</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Модальное окно для установки -->
<div
  class="modal fade"
  id="installModal"
  tabindex="-1"
  aria-hidden="true"
>
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Установка на Кинетик</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">✖️</button>
      </div>
      <div class="modal-body">
        <pre
          id="installOutput"
          class="p-2 border rounded"
          style="white-space: pre-wrap;"
        >Ожидание...</pre>
        <div class="text-end mt-3">
          <button hidden 
            id="recheckBtn"
            class="btn btn-outline-primary d-none"
            onclick="recheckProxy()"
          >🔄 Перепроверка работы прокси</button>
        </div>
      </div>
    </div>
  </div>
</div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  ></script>
  <script>
    function getPostUrl() {
      return `${location.origin}/index.php`;
      alert(getPostUrl());
    }

    function pasteClipboard() {
      navigator.clipboard.readText()
        .then(text => {
          document.getElementById("links").value = text;
          document.getElementById("links").focus();
        })
        .catch(err => {
          alert("Не удалось получить текст из буфера обмена: " + err);
        });
    }

    function parseSS(line) {
      try {
        const url = new URL(line);
        const [authPart, serverPart] = url.href.replace("ss://", "").split('@');
        let decoded;
        try {
          decoded = atob(authPart);
        } catch {
          decoded = decodeURIComponent(authPart);
        }
        const [method, password] = decoded.split(':');
        const [server, port = "8388"] = serverPart.split(':');

        const tag = decodeURIComponent(url.hash.slice(1)) ||
                   (url.search.includes("outline=1") ? "Outline" : "ShadowSocks");

        return {
          type: "shadowsocks",
          tag,
          server,
          server_port: parseInt(port),
          method,
          password
        };
      } catch (e) {
        console.log("Ошибка парсинга SS:", e);
        return null;
      }
    }

    function parseVLESS(line) {
      try {
        const url = new URL(line);
        const [uuid, serverPort] = url.href.replace("vless://", "").split('@');
        const [server, port] = serverPort.split(':');
        const params = new URLSearchParams(url.search);
        const tag = decodeURIComponent(url.hash.slice(1)) || "VLESS";
        const security = params.get("security") || "none";
        const flow     = params.get("flow") || params.get("mode");
        const sni      = params.get("sni");
        const host     = params.get("host");
        const transportType = params.get("type") || "ws";

        const config = {
          type: "vless",
          tag,
          server,
          server_port: parseInt(port),
          uuid,
          packet_encoding: "xudp"
        };

        if (security !== "none") {
          config.tls = {
            enabled: true,
            server_name: sni || server,
            insecure: false,
            utls: {
              enabled: true,
              fingerprint: params.get("fp") || "chrome"
            }
          };
          if (security === "reality") {
            config.tls.reality = {
              enabled: true,
              public_key: params.get("pbk") || "",
              short_id: params.get("sid") || ""
            };
          }
        }

        if (security !== "reality") {
          config.transport = { type: transportType };
          if (transportType === "ws") {
            config.transport.path = params.get("path") || "/";
            if (host) config.transport.headers = { Host: host };
          } else if (transportType === "grpc" && params.get("path")) {
            config.transport.serviceName = params.get("path");
          }
        }

        if (flow && transportType !== "grpc") {
          config.flow = flow;
        }

        return config;
      } catch (e) {
        console.log("Ошибка парсинга VLESS:", e);
        return null;
      }
    }

    function parseVMess(line) {
      try {
        const raw = line.replace("vmess://", "");
        const obj = JSON.parse(atob(raw));
        return {
          type: "vmess",
          tag: obj.ps || "VMess",
          server: obj.add,
          server_port: parseInt(obj.port),
          uuid: obj.id,
          security: obj.security || "auto",
          tls: obj.tls === "tls",
          transport: {
            type: obj.net || "tcp",
            path: obj.path || "/"
          }
        };
      } catch (e) {
        console.log("Ошибка парсинга VMess:", e);
        return null;
      }
    }

    function parseTrojan(line) {
      try {
        const url = new URL(line);
        const [password, hostPort] = url.href.replace("trojan://", "").split('@');
        const [server, port] = hostPort.split(':');
        return {
          type: "trojan",
          tag: decodeURIComponent(url.hash.slice(1)) || "Trojan",
          server,
          server_port: parseInt(port),
          password,
          tls: {
            enabled: true,
            server_name: url.searchParams.get("sni") || server,
            insecure: false
          }
        };
      } catch (e) {
        console.log("Ошибка парсинга Trojan:", e);
        return null;
      }
    }

    function parseTUIC(line) {
      try {
        const url = new URL(line);
        const tag = decodeURIComponent(url.hash.slice(1)) || "TUIC";
        const [uuid, password] = url.username.includes(':')
          ? url.username.split(':')
          : [url.username, url.password];
        return {
          type: "tuic",
          tag,
          server: url.hostname,
          server_port: parseInt(url.port),
          uuid,
          password,
          alpn: ["h3"],
          tls: {
            enabled: true,
            server_name: url.hostname,
            insecure: false
          }
        };
      } catch (e) {
        console.log("Ошибка парсинга TUIC:", e);
        return null;
      }
    }

    function generateConfig() {
      const routerIp       = document.getElementById("router").value.trim();
      const proxyLinks     = document.getElementById("links").value.trim().split('\n').filter(l => l.trim());
      const includeClash   = document.getElementById("includeClashApi").checked;
      const resultDiv      = document.getElementById("result");
      const warningsDiv    = document.getElementById("warnings");
      const downloadLink   = document.getElementById("downloadBtn");
      const copyBtn        = document.getElementById("copyBtn");
      const resultWrapper  = document.getElementById("resultWrapper");

      warningsDiv.innerHTML = '';
      resultWrapper.classList.add("d-none");
      downloadLink.classList.add("d-none");
      copyBtn.classList.add("d-none");

      if (!routerIp) {
        warningsDiv.innerHTML = "Ошибка: IP роутера обязателен";
        return;
      }
      if (proxyLinks.length === 0) {
        warningsDiv.innerHTML = "Ошибка: нужен хотя бы один ключ";
        return;
      }

      const outbounds = [];
      const tags      = [];
      const warns     = [];

      proxyLinks.forEach(line => {
        let cfg = null;
        if (line.startsWith("ss://"))      cfg = parseSS(line);
        else if (line.startsWith("vless://")) cfg = parseVLESS(line);
        else if (line.startsWith("vmess://")) cfg = parseVMess(line);
        else if (line.startsWith("trojan://")) cfg = parseTrojan(line);
        else if (line.startsWith("tuic://"))   cfg = parseTUIC(line);

        if (cfg) {
          outbounds.push(cfg);
          if (["vless","vmess","trojan","tuic"].includes(cfg.type)) {
            tags.push(cfg.tag);
          }
        } else {
          warns.push(`Не удалось распарсить: ${line}`);
        }
      });

      if (tags.length) {
        outbounds.unshift({
          type: "selector",
          tag: "select",
          outbounds: tags,
          default: tags[0],
          interrupt_exist_connections: false
        });
      }
      outbounds.push(
        { type: "direct", tag: "direct" },
        { type: "block",  tag: "block"  }
      );

      const config = {
        experimental: { cache_file: { enabled: true } },
        log: { level: "debug", timestamp: true },
        inbounds: [
          {
            type: "tun",
            interface_name: "opkgtun0",
            domain_strategy: "ipv4_only",
            address: "172.16.250.1/30",
            auto_route: false,
            strict_route: false,
            sniff: true,
            sniff_override_destination: false
          },
          {
            type: "mixed",
            tag: "mixed-in",
            listen: "0.0.0.0",
            listen_port: 1080,
            sniff: true,
            sniff_override_destination: false
          }
        ],
        outbounds,
        route: {
          auto_detect_interface: false,
          final: tags.length ? "select" : "direct",
          rules: [
            { protocol: "dns", outbound: "dns-out" },
            { network: "udp", port: 443, outbound: "block" }
          ]
        }
      };

      if (includeClash) {
        config.experimental.clash_api = {
          external_controller: `${routerIp}:9090`,
          external_ui: "ui",
          access_control_allow_private_network: true
        };
      }

      const jsonStr = JSON.stringify(config, null, 2);
      resultDiv.textContent = jsonStr;
      resultWrapper.classList.remove("d-none");

      const blob = new Blob([jsonStr], { type: "application/json" });
      downloadLink.href = URL.createObjectURL(blob);
      downloadLink.classList.remove("d-none");
      copyBtn.classList.remove("d-none");

      if (warns.length) {
        warningsDiv.innerHTML = warns.map(w => `– ${w}`).join("<br>");
      }
    }
    
    
    

    function copyConfig() {
      const text = document.getElementById("result").textContent;
      navigator.clipboard.writeText(text)
        .then(() => alert("Конфиг скопирован в буфер!"))
        .catch(e => alert("Ошибка копирования: " + e));
    }




    
    
    
    
    function disableIPv6() {
  const modal = new bootstrap.Modal(document.getElementById('installModal'));
  const out   = document.getElementById("installOutput");
  out.textContent = "⏳ Отключение IPv6...";
  modal.show();

  fetch(getPostUrl(), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ disable_ipv6: true })
  })
  .then(res => res.json())
  .then(d => {
    out.textContent += "\n" + d.message + "\n\n" + d.log;
  })
  .catch(e => {
    out.textContent += "\n❌ Ошибка: " + e;
  });
}





    function installAll() {
  const cfg      = document.getElementById("result").textContent.trim();
  const routerIp = document.getElementById("router").value.trim();
  const modal    = new bootstrap.Modal(document.getElementById('installModal'));
  const out      = document.getElementById("installOutput");

  if (!cfg || !routerIp) {
    alert("❗️ Нужно минимум один ключ чтобы сгенерировать config.json и указать IP роутера, если он отличается от указанного выше!");
    return;
  }

  out.textContent = "🔍 Проверка установленного компонента клиент прокси...\n";
  modal.show();

  // 1. Проверка компонента proxy
  fetch(getPostUrl(), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ check_proxy_component: true })
  })
  .then(res => res.text())
  .then(txt => {
    out.textContent += txt;
    if (!txt.includes("✅")) {
      out.textContent += "\n⛔️ Операция установки отменена. Установите компонент клиент прокси и повторите попытку.";
      return;
    }

    out.textContent += "\n⏳ Сохранение config.json...\n";

    // 2. Установка конфига
    fetch(getPostUrl(), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ config: cfg })
    })
    .then(res => res.json())
    .then(data => {
      out.textContent += "✅ Конфиг сохранен: " + data.message + "\n⏳ Настройка интерфейсов...\n";

      // 3. Установка Proxy0 и OpkgTun0
      const cmds = [
        // --- Proxy0 ---
        'ndmc -c "no interface Proxy0" >/dev/null 2>&1',
        'ndmc -c "system configuration save" >/dev/null 2>&1',
        'ndmc -c "interface Proxy0" >/dev/null 2>&1',
        `ndmc -c "interface Proxy0 description NeoFit-Proxy0-${routerIp}:1080" >/dev/null 2>&1`,
        'ndmc -c "interface Proxy0 proxy protocol socks5" >/dev/null 2>&1',
        'ndmc -c "interface Proxy0 proxy socks5-udp" >/dev/null 2>&1',
        `ndmc -c "interface Proxy0 proxy upstream ${routerIp} 1080" >/dev/null 2>&1`,
        'ndmc -c "interface Proxy0 up" >/dev/null 2>&1',
        'ndmc -c "interface Proxy0 ip global 1" >/dev/null 2>&1',
        'ndmc -c "system configuration save" >/dev/null 2>&1',
        'ndmc -c "no interface Proxy0 ipv6 address" >/dev/null 2>&1',

        // --- OpkgTun0 ---
        '/opt/etc/init.d/S99sing-box stop',
        'ndmc -c "no interface OpkgTun0"',
        'ndmc -c "system configuration save"',
        'ndmc -c "interface OpkgTun0"',
        'ndmc -c "interface OpkgTun0 ip address 172.16.250.1/30"',
        'ndmc -c "interface OpkgTun0 ip global 1"',
        'ndmc -c "interface OpkgTun0 up"',
        'ndmc -c "system configuration save"',       // save before waiting
        'sleep 9',                                   // ждём 6 секунд
        '/opt/sbin/ip route del default via 172.16.250.1 dev opkgtun0 metric 1500',
        '/opt/sbin/ip route add default via 172.16.250.1 dev opkgtun0 metric 1500',
        'ndmc -c "system configuration save"',       // сохраняем после маршрутов
        '/opt/sbin/ip link delete opkgtun0',
        '/opt/etc/init.d/S99sing-box restart',
        '/opt/etc/init.d/S99sing-box status'
      ];

      fetch(getPostUrl(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ proxy_commands: cmds })
      })
      .then(res => res.text())
      .then(cmdOut => {
        out.textContent += cmdOut + "\n⏳ Проверка IP...\n";

        // 4. Повторная проверка IP
        fetch(getPostUrl(), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ check_only: true })
        })
        .then(res => res.json())
        .then(ipData => {
          out.textContent +=
            "🌐 Провайдерский IP: " + ipData.external_ip +
            "\n🛡️ Proxy0 IP: "       + ipData.proxy_ip +
            ((ipData.proxy_ip && ipData.proxy_ip !== ipData.external_ip)
              ? "\n🎯 Прокси работает!"
              : "\n❌ Прокси не работает, нажмите кнопку ниже для повторения проверки."
            ) +
            "\n🎉 Установка завершена!";
          document.getElementById("recheckBtn").classList.remove("d-none");
        })
        .catch(e => out.textContent += "\n❌ Ошибка при проверке IP:\n" + e);

      })
      .catch(e => out.textContent += "\n❌ Ошибка настройки интерфейсов:\n" + e);

    })
    .catch(e => out.textContent += "\n❌ Ошибка при установке config.json:\n" + e);

  })
  .catch(e => out.textContent += "\n❌ Ошибка проверки компонента proxy:\n" + e);
}







    function runUpdate() {
      fetch(getPostUrl(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ run_update: true })
      })
      .then(res => res.json())
      .then(d => { alert(d.message); location.reload(); })
      .catch(e => alert("❌ Ошибка обновления: " + e));
    }

    function checkUpdate(manual = true) {
      const btn = document.getElementById("updateBtn");
      fetch(getPostUrl(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ check_update: true })
      })
      .then(res => res.json())
      .then(d => {
        if (d.update_available) {
          btn.classList.remove("d-none");
          btn.textContent = `⬇️ Доступно обновление: v${d.latest}`;
          btn.title = d.show || "";
          if (manual && confirm(`Доступна новая версия ${d.latest}\n${d.show}\nОбновить?`)) {
            runUpdate();
          }
        } else {
          btn.classList.add("d-none");
          if (manual) alert("✅ Вы уже на последней версии.");
        }
      })
      .catch(e => {
        if (manual) alert("❌ Ошибка проверки: " + e);
        else console.warn("Ошибка авто-проверки:", e);
      });
    }






    function runUpdate() {
      fetch(getPostUrl(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ run_update: true })
      })
      .then(res => res.json())
      .then(d => { alert(d.message); location.reload(); })
      .catch(e => alert("❌ Ошибка обновления: " + e));
    }

    function checkUpdate(manual = true) {
      const btn = document.getElementById("updateBtn");
      fetch(getPostUrl(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ check_update: true })
      })
      .then(res => res.json())
      .then(d => {
        if (d.update_available) {
          btn.classList.remove("d-none");
          btn.textContent = `⬇️ Доступно обновление: v${d.latest}`;
          btn.title = d.show || "";
          if (manual && confirm(`Доступна новая версия ${d.latest}\n${d.show}\nОбновить?`)) {
            runUpdate();
          }
        } else {
          btn.classList.add("d-none");
          if (manual) alert("✅ Вы уже на последней версии.");
        }
      })
      .catch(e => {
        if (manual) alert("❌ Ошибка проверки: " + e);
        else console.warn("Ошибка авто-проверки:", e);
      });
    }




    // Чтобы после генерации сразу появились кнопки установки
    
    
    
    const origGen = generateConfig;
    generateConfig = function() {
      origGen();
      //document.getElementById("installBtn").classList.remove("d-none");
      //document.getElementById("proxyBtn").classList.remove("d-none");
      document.getElementById("installAllBtn").classList.remove("d-none");
        document.getElementById("ipv6Btn").classList.remove("d-none");
        // ⚙️ Автоматическая установка после генерации
  setTimeout(() => installAll(), 1111); // небольшая задержка, чтобы всё отрисовалось
    };

    window.addEventListener("DOMContentLoaded", () => {
      const routerField = document.getElementById("router");
      const pasteBtn    = document.getElementById("pasteBtn");
      if (location.protocol !== "https:") pasteBtn?.classList.add("d-none");
      // Авто-проверка обновлений без модалов
      setTimeout(() => checkUpdate(false), 1000);
    });
    
    function toggleTheme() {
  const theme = document.documentElement.getAttribute("data-theme");
  const newTheme = theme === "dark" ? "light" : "dark";
  document.documentElement.setAttribute("data-theme", newTheme);
  localStorage.setItem("theme", newTheme);
}

window.addEventListener("DOMContentLoaded", () => {
  // Восстановление темы
  const savedTheme = localStorage.getItem("theme") || "light";
  document.documentElement.setAttribute("data-theme", savedTheme);

  // Показывать/скрывать кнопку панели 9090
  const checkbox = document.getElementById("includeClashApi");
  const btn9090  = document.getElementById("goTo9090Btn");

  function toggle9090Btn() {
    if (checkbox.checked) {
      btn9090.classList.remove("d-none");
    } else {
      btn9090.classList.add("d-none");
    }
  }

  if (checkbox && btn9090) {
    checkbox.addEventListener("change", toggle9090Btn);
    toggle9090Btn(); // начальная проверка
  }
});
  </script>
<script>
function goTo9090() {
  const loc = window.location;
  const newUrl = `${loc.protocol}//${loc.hostname}:9090${loc.pathname}${loc.search}${loc.hash}`;
  window.open(newUrl, '_blank');
}
</script
</body>
</html>
