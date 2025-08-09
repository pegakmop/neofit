<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $path  = '/opt/etc/xray/config.json';

    if (!$input) {
        http_response_code(400);
        echo json_encode(["error" => "Пустой запрос"]);
        exit;
    }

    // 1) Сохраняем Xray-конфиг
    if (file_put_contents($path, $input) === false) {
        http_response_code(500);
        echo json_encode(["error" => "Не удалось записать конфиг"]);
        exit;
    }

    // 2) Выполняем ndmc-команды для каждого inbound
    $cfg  = json_decode($input, true);
    $host = '127.0.0.1';  // <-- IP или hostname роутера
    if (isset($cfg['inbounds']) && is_array($cfg['inbounds'])) {
        foreach ($cfg['inbounds'] as $inb) {
            if (!isset($inb['tag'], $inb['port'])) continue;
            $tag   = $inb['tag'];            // e.g. "socks-in-socks0"
            $port  = $inb['port'];           // e.g. 1080
            preg_match('/(\d+)$/', $tag, $m);
            $n     = $m[1] ?? '0';
            $ifName = "Proxy{$n}";

            $cmds = [
                "ndmc -c \"no interface {$ifName}\"",
                "ndmc -c \"interface {$ifName}\"",
                "ndmc -c \"interface {$ifName} description pegakmop-xray-{$tag}-{$ifName}-{$host}:{$port}\"",
                "ndmc -c \"interface {$ifName} proxy protocol socks5\"",
                "ndmc -c \"interface {$ifName} proxy socks5-udp\"",
                "ndmc -c \"interface {$ifName} proxy upstream {$host} {$port}\"",
                "ndmc -c \"interface {$ifName} up\"",
                "ndmc -c \"interface {$ifName} ip global 1\""
            ];
            foreach ($cmds as $c) {
                exec($c . ' 2>&1', $out, $code);
            }
        }
        // сохранить системную конфигурацию роутера
        exec('ndmc -c "system configuration save" 2>&1');
    }

    // 3) Перезапускаем Xray
    exec('/opt/etc/init.d/S24xray restart 2>&1', $out2, $code2);

    if ($code2 === 0) {
        echo json_encode(["status" => "ok", "message" => "✅ Конфиг сохранен на роутере, интерфейс(ы) добавлен(ы) и Xray пакет был перезапущен"]);
    } else {
        echo json_encode([
            "status"  => "warning",
            "message" => "⚠️ Ошибка! Конфиг Не сохранен или интерфейсы НЕ созданы или Xray НЕ перезапущен",
            "output"  => $out2
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Neofit Xray</title>
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      --bg-color: #f9f9f9; --text-color: #333; --border-color: #ccc;
      --card-bg: #fff; --button-bg: #007BFF; --button-text: #fff;
      font-family: Arial, sans-serif; margin: 0; padding: 20px;
      background: var(--bg-color); color: var(--text-color);
      transition: background-color .3s, color .3s;
    }
    body.dark-theme {
      --bg-color: #1e1e1e; --text-color: #c9d1d9;
      --border-color: #555; --card-bg: #282c34;
      --button-bg: #444; --button-text: #fff;
    }
    h1 { text-align: center; margin-bottom: 30px; }
    .controls {
      display: flex; justify-content: center; gap: 10px;
      flex-wrap: wrap; margin-bottom: 20px;
    }
    button {
      background: var(--button-bg); color: var(--button-text);
      border: none; border-radius: 20px; padding: 10px 20px;
      font-size: 16px; cursor: pointer; transition: opacity .3s;
    }
    button:hover { opacity: .9; }
    .interface-container {
      border: 1px solid var(--border-color);
      background: var(--card-bg); padding: 10px;
      margin-bottom: 10px; border-radius: 4px;
    }
    .interface-header {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 10px;
    }
    .link-field {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 8px;
    }
    input[type="text"] {
      width: 100%; padding: 8px; border: 1px solid var(--border-color);
      border-radius: 4px; background: var(--card-bg);
      color: var(--text-color); transition: border-color .3s;
    }
    .config-display {
      border: 1px solid var(--border-color);
      background: var(--card-bg); padding: 10px;
      margin-top: 10px; border-radius: 4px;
      max-height: 60vh; overflow-y: auto; position: relative;
    }
    .trash-btn, .add-link-btn {
      width: 32px; height: 32px; padding: 0; border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; background: var(--button-bg);
      color: var(--button-text); transition: opacity .3s;
    }
    .trash-btn:hover, .add-link-btn:hover { opacity: .9; }
    #warnings { color: #ff6b6b; margin-top: 10px; }
    #theme-toggle {
      position: fixed; top: 20px; right: 20px;
      width: 40px; height: 40px; border: none; border-radius: 50%;
      background: var(--button-bg); color: var(--button-text);
      font-size: 18px; cursor: pointer; z-index: 1000;
    }
    @media (min-width: 600px) {
      .container { max-width: 600px; margin: 0 auto; }
    }
    .config-display pre { margin: 0; padding: 0; background: transparent; border: none; }
    .config-display code {
      display: block; padding: 10px; font-size: 14px; line-height: 1.5;
    }
    .copy-btn {
      position: absolute; top: 8px; right: 8px; z-index: 10;
      background: var(--button-bg); color: var(--button-text);
      border: none; border-radius: 4px; padding: 4px 8px;
      font-size: 14px; cursor: pointer; transition: opacity .3s;
    }
    .copy-btn:hover { opacity: .9; }
    .tooltip {
      position: fixed; top: 20px; left: 50%;
      transform: translateX(-50%);
      background: #4CAF50; color: white; padding: 8px 16px;
      border-radius: 4px; opacity: 0; transition: opacity .3s;
      z-index: 9999;
    }
    .tooltip.show { opacity: 1; }
  </style>
</head>
<body class="dark-theme">
  <div class="container">
    <h1>NeoFit Xray</h1>
    <div class="controls">
      <button><a href="https://yoomoney.ru/to/410012481566554">на ☕️ Юмани</a></button>
      <button><a href="https://www.tinkoff.ru/rm/seroshtanov.aleksey9/HgzXr74936">на ☕️Тинькофф</a></button>
      <button onclick="addInterface()">🆕Создать</button>
      <button hidden onclick="showUploadDialog()">🆒Просмотреть</button>
      <button onclick="generateConfig()">🆗Сгенерировать</button>
      <button onclick="saveConfig()">🆙Сохранить</button>
    </div>
    <div id="warnings"></div>
    <div id="interfacesContainer"></div>
    <div id="configDisplay" class="config-display" style="display: none;">
      <button hidden class="copy-btn" onclick="copyConfigToClipboard()">📋 Скопировать в буфер обмена</button>
      <pre><code id="output" class="language-json"></code></pre>
    </div>
  </div>
  <button id="theme-toggle" onclick="toggleTheme()">🌓</button>
  <div id="copyTooltip" class="tooltip">Скопировано в буфер обмена!</div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
  <script>
    let config = {}, interfaceCount = 0, isConfigModified = false, baseSocksPort = 1080;

    function addInterface() {
      interfaceCount++;
      isConfigModified = true;
      const container = document.createElement('div');
      container.className = 'interface-container';
      container.id = `interface-${interfaceCount}`;

      const header = document.createElement('div');
      header.className = 'interface-header';

      const delBtn = document.createElement('button');
      delBtn.className = 'trash-btn';
      delBtn.innerHTML = '🗑️';
      delBtn.title = 'Удалить интерфейс';
      delBtn.onclick = () => { container.remove(); isConfigModified = true; };

      const nameInput = document.createElement('input');
      nameInput.type = 'text';
      nameInput.placeholder = 'Название интерфейса (например, socks0)';
      nameInput.value = `socks${interfaceCount - 1}`;
      nameInput.maxLength = 20;

      header.appendChild(delBtn);
      header.appendChild(nameInput);

      const linksContainer = document.createElement('div');
      linksContainer.className = 'links-container';

      const addLinkBtn = document.createElement('button');
      addLinkBtn.className = 'add-link-btn';
      addLinkBtn.innerHTML = '+';
      addLinkBtn.title = 'Добавить ссылку';
      addLinkBtn.onclick = () => { addLinkField(linksContainer); isConfigModified = true; };

      container.appendChild(header);
      container.appendChild(linksContainer);
      //container.appendChild(addLinkBtn);
      document.getElementById('interfacesContainer').appendChild(container);

      addLinkField(linksContainer);
    }

    function addLinkField(container) {
      const linkField = document.createElement('div');
      linkField.className = 'link-field';

      const input = document.createElement('input');
      input.type = 'text';
      input.placeholder = 'vless://... vmess://... trojan://... ss://... socks://...';

      const deleteBtn = document.createElement('button');
      deleteBtn.className = 'trash-btn';
      deleteBtn.innerHTML = '🗑️';
      deleteBtn.title = 'Удалить ссылку';
      deleteBtn.onclick = () => { linkField.remove(); isConfigModified = true; };

      linkField.appendChild(input);
      linkField.appendChild(deleteBtn);
      container.appendChild(linkField);
    }

    function parseVlessLinkForXray(link) {
      const match = link.match(/vless:\/\/([^@]+)@([^:]+):(\d+)\/?(?:\?([^#]*))?(?:#(.*))?/);
      if (!match) return null;
      const uuid = match[1], server = match[2], server_port = parseInt(match[3],10);
      const params = new URLSearchParams(match[4]||''), tag = decodeURIComponent(match[5]||'').trim() || `vless-${server}-${server_port}`;
      const outbound = {
        protocol: "vless",
        settings: { vnext:[{ address:server, port:server_port, users:[{ id:uuid, encryption:params.get("encryption")||"none", flow:params.get("flow")||"" }]}] },
        streamSettings:{ network:params.get("type")||"tcp", security:params.get("security")||"none" },
        tag
      };
      if(outbound.streamSettings.security==="tls"){
        outbound.streamSettings.tlsSettings={ serverName:params.get("sni")||server, alpn:["h2","http/1.1"] };
      } else if(outbound.streamSettings.security==="reality"){
        outbound.streamSettings.realitySettings={ publicKey:params.get("pbk")||"", fingerprint:params.get("fp")||"chrome", serverName:params.get("sni")||server, shortId:params.get("sid")||"", spiderX:params.get("path")||"/" };
      }
      if((params.get("type")||"tcp")==="ws"){
        outbound.streamSettings.wsSettings={ path:params.get("path")||"/", headers:{ Host:params.get("host")||server } };
      }
      return outbound;
    }

    function parseVmessLinkForXray(link) {
      try {
        const b64 = link.replace('vmess://',''), json = JSON.parse(atob(b64.replace(/-/g,'+').replace(/_/g,'/')));
        return {
          protocol:"vmess",
          settings:{ vnext:[{ address:json.add, port:parseInt(json.port,10), users:[{ id:json.id, alterId:json.aid?parseInt(json.aid,10):0, security:json.scy||"auto" }]}] },
          streamSettings:{ network:json.net||"tcp", security:json.tls==="tls"?"tls":"none", tlsSettings:json.tls==="tls"?{ serverName:json.sni||json.add }:undefined },
          tag:json.ps||`vmess-${json.add}-${json.port}`
        };
      } catch(e) { return null; }
    }

    function parseTrojanLinkForXray(link) {
      const match = link.match(/trojan:\/\/([^@]+)@([^:]+):(\d+)(?:\?([^#]*))?(?:#(.*))?/);
      if (!match) return null;
      const password = match[1], server = match[2], server_port = parseInt(match[3],10);
      const params = new URLSearchParams(match[4]||''), tag = decodeURIComponent(match[5]||'').trim()||`trojan-${server}-${server_port}`;
      return {
        protocol:"trojan",
        settings:{ servers:[{ address:server, port:server_port, password }] },
        streamSettings:{ network:"tcp", security:params.get("sni")?"tls":"none", tlsSettings:params.get("sni")?{ serverName:params.get("sni") }:undefined },
        tag
      };
    }

    function parseShadowsocksLinkForXray(link) {
      try {
        let url = link.replace('ss://','');
        let tag = url.includes('#')?decodeURIComponent(url.split('#')[1]):'';
        url = url.split('#')[0];
        const [userinfo, hostinfo] = url.includes('@')?url.split('@'):[atob(url),""];
        const [method, password] = userinfo.includes(':')?userinfo.split(':'):[userinfo,""];
        const [server, port] = hostinfo.split(':');
        return {
          protocol:"shadowsocks",
          settings:{ servers:[{ address:server, port:parseInt(port,10), method, password }] },
          tag: tag||`ss-${server}-${port}`
        };
      } catch(e) { return null; }
    }

    function parseSocksLinkForXray(link) {
      const re = /socks:\/\/(?:([^:]+):([^@]+)@)?([^:]+):(\d+)(?:#(.*))?/;
      const m = link.match(re); if (!m) return null;
      const username = m[1]||"", password = m[2]||"", server = m[3], server_port = parseInt(m[4],10);
      const tag = decodeURIComponent(m[5]||'').trim()||`socks-${server}-${server_port}`;
      return {
        protocol:"socks",
        settings:{ servers:[{ address:server, port:server_port, users:username?[{ user:username, pass:password }]:[] }] },
        tag
      };
    }

    function parseLink(link) {
      if (link.startsWith('vless://')) return parseVlessLinkForXray(link);
      if (link.startsWith('vmess://')) return parseVmessLinkForXray(link);
      if (link.startsWith('trojan://')) return parseTrojanLinkForXray(link);
      if (link.startsWith('ss://')) return parseShadowsocksLinkForXray(link);
      if (link.startsWith('socks://')) return parseSocksLinkForXray(link);
      return null;
    }

    function getNextFreePort(inbounds, startPort) {
      const used = new Set(inbounds.map(ib=>ib.port));
      let p = startPort;
      while (used.has(p)) p++;
      return p;
    }

    function generateConfig() {
      if (!isConfigModified && config.inbounds && config.inbounds.length) {
        const out = document.getElementById('output');
        out.textContent = JSON.stringify(config, null, 2);
        hljs.highlightElement(out);
        document.getElementById('configDisplay').style.display = 'block';
        resizeOutputContainer();
        return;
      }
      let newConfig = {
        log: { loglevel: "none" },
        inbounds: [],
        outbounds: []
      };
      let warnings = [];
      let socksPort = baseSocksPort;
      const interfaces = document.querySelectorAll('.interface-container');
      const usedTags = new Set();
      const routingRules = [];

      interfaces.forEach((ic, idx) => {
        const name = ic.querySelector('.interface-header input[type="text"]').value.trim() || `socks${idx}`;
        const links = ic.querySelectorAll('.links-container input[type="text"]');
        if (links.length === 0) {
          warnings.push(`⚠️ Интерфейс "${name}" без ссылок.`);
          alert(`⚠️ Интерфейс "${name}" без ссылок.`);
          return;
        }
        const port = getNextFreePort(newConfig.inbounds, socksPort);
        const inboundTag = `${name}`;
        newConfig.inbounds.push({
          protocol: "socks",
          port,
          tag: inboundTag,
          settings: { auth: "noauth", udp: true }
        });
        socksPort = port + 1;

        const outTags = [];
        links.forEach(inp => {
          const l = inp.value.trim();
          if (!l) return;
          const o = parseLink(l);
          if (o) {
            if (!usedTags.has(o.tag)) {
              newConfig.outbounds.push(o);
              usedTags.add(o.tag);
            }
            outTags.push(o.tag);
          } else {
            warnings.push(`⚠️ Неверная ссылка: ${l}`);
            alert(`⚠️ Неверная ссылка: ${l}`);
          }
        });
        if (outTags.length) {
          routingRules.push({
            type: "field",
            inboundTag: [inboundTag],
            outboundTag: outTags[0]
          });
        }
      });

      newConfig.outbounds.push({ protocol: "freedom", tag: "direct" });
      newConfig.outbounds.push({ protocol: "blackhole", tag: "blocked" });
      if (interfaces.length > 1) {
        newConfig.routing = { rules: routingRules };
      }

      config = newConfig;
      document.getElementById('warnings').innerHTML = warnings.join('<br>');
      const out = document.getElementById('output');
      out.textContent = JSON.stringify(config, null, 2);
      hljs.highlightElement(out);
      document.getElementById('configDisplay').style.display = 'block';
      resizeOutputContainer();
    }

    function saveConfig() {
      if (!config || !config.inbounds) {
        document.getElementById('warnings').innerHTML = "❌Нет конфигурации для сохранения";
        return;
      }
      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(config, null, 2)
      })
      .then(r => r.json())
      .then(d => alert(d.message || "Готово"))
      .catch(e => { console.error(e); alert("Ошибка при отправке конфига"); });
    }

    function showUploadDialog() {
      const inp = document.createElement('input');
      inp.type = 'file'; inp.accept = '.json';
      inp.onchange = e => {
        const f = e.target.files[0];
        if (!f) return;
        const r = new FileReader();
        r.onload = ev => loadedConfig(ev.target.result);
        r.readAsText(f);
      };
      inp.click();
    }

    function loadedConfig(txt) {
      try {
        const ld = JSON.parse(txt);
        document.getElementById('interfacesContainer').innerHTML = '';
        interfaceCount = 0; isConfigModified = false; baseSocksPort = 1080;
        if (ld.inbounds && ld.outbounds) {
          const socksIn = ld.inbounds.filter(x => x.protocol === 'socks');
          if (socksIn.length) baseSocksPort = Math.max(...socksIn.map(x => x.port)) + 1;
          const names = new Set();
          socksIn.forEach(ib => {
            const nm = ib.tag.replace('socks-in-', '');
            if (names.has(nm)) return;
            names.add(nm);
            interfaceCount++;
            const cid = `interface-${interfaceCount}`;
            const cont = document.createElement('div'); cont.className = 'interface-container'; cont.id = cid;
            const hdr = document.createElement('div'); hdr.className = 'interface-header';
            const db = document.createElement('button'); db.className = 'trash-btn'; db.innerHTML = '🗑️';
            db.onclick = () => { cont.remove(); isConfigModified = true; };
            const ni = document.createElement('input'); ni.type = 'text'; ni.value = nm; ni.maxLength = 20;
            hdr.appendChild(db); hdr.appendChild(ni);
            const lc = document.createElement('div'); lc.className = 'links-container'; 
            const ab = document.createElement('button'); ab.className='add-link-btn'; ab.innerHTML='+'; 
            ab.onclick = () => { addLinkField(lc); isConfigModified=true; };
            cont.appendChild(hdr); cont.appendChild(lc); cont.appendChild(ab);
            document.getElementById('interfacesContainer').appendChild(cont);
            const rule = ld.routing ? ld.routing.rules.find(r => r.inboundTag.includes(ib.tag)) : null;
            if (rule) {
              const ot = rule.outboundTag;
              const o = ld.outbounds.find(x => x.tag === ot);
              if (o) {
                const lf = document.createElement('div'); lf.className = 'link-field';
                const inp2 = document.createElement('input'); inp2.type = 'text'; inp2.value = ot;
                const db2 = document.createElement('button'); db2.className = 'trash-btn'; db2.innerHTML='🗑️';
                db2.onclick = () => { lf.remove(); isConfigModified = true; };
                lf.appendChild(inp2); lf.appendChild(db2); lc.appendChild(lf);
              }
            }
          });
        }
        config = ld;
        generateConfig();
      } catch (e) {
        document.getElementById('warnings').innerHTML = `Ошибка загрузки: ${e.message}`;
      }
    }

    function toggleTheme() {
      document.body.classList.toggle('dark-theme');
    }

    async function copyConfigToClipboard() {
      try {
        const out = document.getElementById('output');
        await navigator.clipboard.writeText(out.textContent);
        const tip = document.getElementById('copyTooltip');
        tip.classList.add('show');
        setTimeout(() => tip.classList.remove('show'), 2000);
      } catch (e) {
        console.error(e);
        alert('Не удалось скопировать');
      }
    }

    function resizeOutputContainer() {
      const c = document.getElementById('configDisplay');
      if (!c || c.style.display === 'none') return;
      requestAnimationFrame(() => {
        c.style.height = 'auto';
        c.style.height = Math.min(c.scrollHeight, 600) + 'px';
      });
    }
  </script>
</body>
</html>
