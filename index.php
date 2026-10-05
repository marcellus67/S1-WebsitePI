<?php
// Workshop: IP-configuratie van de webserver tonen
date_default_timezone_set('Europe/Amsterdam');

function run($cmd) { return trim(shell_exec($cmd) ?? ''); }
function h($s)     { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$fout = '';
$resultaat = false;
$octetten = ['', '', '', ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Controle aan de serverkant: elk octet moet een getal 0-255 zijn
    for ($i = 0; $i < 4; $i++) {
        $w = trim($_POST["o$i"] ?? '');
        $octetten[$i] = $w;
        if ($w === '' || !ctype_digit($w) || strlen($w) > 3 || (int)$w > 255) {
            $fout = 'Octet ' . ($i + 1) . ' is ongeldig. Gebruik getallen van 0 t/m 255.';
            break;
        }
    }
    if ($fout === '') {
        $clientIp = implode('.', array_map('intval', $octetten));
        $echteIp  = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
        $serverIp = $_SERVER['SERVER_ADDR'] ?? 'onbekend';
        $hostname = run('hostname');
        $adressen = run('ip -4 -br address show scope global');
        $gateway  = run("ip -4 route show default | awk '{print \$3; exit}'");
        $dns      = run("resolvectl dns 2>/dev/null || grep '^nameserver' /etc/resolv.conf");
        $tijd     = date('d-m-Y H:i:s');
        $resultaat = true;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Workshop webserver</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 1rem;background:#f4f6f8;color:#222}
  .kaart{background:#fff;border-radius:8px;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.1);margin-bottom:1rem}
  .ip{display:flex;align-items:center;gap:.3rem;font-size:1.4rem}
  .ip input{width:3.5rem;font-size:1.4rem;text-align:center;padding:.3rem}
  .ip input:invalid:not(:placeholder-shown){border-color:#b00020}
  button{margin-top:1rem;padding:.6rem 1.4rem;font-size:1rem;cursor:pointer}
  table{border-collapse:collapse;width:100%}
  td{padding:.4rem;border-bottom:1px solid #ddd;vertical-align:top}
  td:first-child{font-weight:bold;width:35%}
  pre{margin:0;white-space:pre-wrap}
  .fout{color:#b00020}
  .noteer{background:#e8f5e9;border-left:5px solid #2e7d32}
  .waarschuwing{background:#fff8e1;border-left:5px solid #f9a825}
</style>
</head>
<body>

<div class="kaart">
  <h1>Workshop: webserver</h1>
  <p>Vul het IP-adres van <strong>jouw eigen client</strong> in.
     Typ een punt (.) om naar het volgende veld te gaan.</p>
  <?php if ($fout): ?><p class="fout"><?= h($fout) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <div class="ip">
      <?php for ($i = 0; $i < 4; $i++): ?>
        <input class="octet" name="o<?= $i ?>" value="<?= h($octetten[$i]) ?>"
               maxlength="3" inputmode="numeric" required placeholder="0"
               pattern="25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d" aria-label="Octet <?= $i + 1 ?>">
        <?php if ($i < 3): ?><span>.</span><?php endif; ?>
      <?php endfor; ?>
    </div>
    <button type="submit">Verstuur</button>
  </form>
</div>

<?php if ($resultaat): ?>
<div class="kaart">
  <h2>IP-configuratie van de webserver</h2>
  <table>
    <tr><td>Hostname</td><td><?= h($hostname) ?></td></tr>
    <tr><td>Bereikt via serveradres</td><td><?= h($serverIp) ?></td></tr>
    <tr><td>Interfaces (IPv4)</td><td><pre><?= h($adressen) ?></pre></td></tr>
    <tr><td>Default gateway</td><td><?= h($gateway ?: 'geen') ?></td></tr>
    <tr><td>DNS-server(s)</td><td><pre><?= h($dns ?: 'onbekend') ?></pre></td></tr>
  </table>
</div>

<?php if ($clientIp !== $echteIp): ?>
<div class="kaart waarschuwing">
  Let op: je vulde <b><?= h($clientIp) ?></b> in, maar de webserver ziet het verzoek
  binnenkomen vanaf <b><?= h($echteIp) ?></b>. Controleer je IP-adres
  (<code>ipconfig</code> of <code>ip a</code>), of zit er NAT tussen client en server?
</div>
<?php endif; ?>

<div class="kaart noteer">
  <h2>Noteer dit als resultaat van de workshop</h2>
  <p>Op <?= h($tijd) ?> heeft client <b><?= h($clientIp) ?></b> via HTTP verbinding
     gemaakt met webserver <b><?= h($hostname) ?></b> op IP-adres <b><?= h($serverIp) ?></b>
     (default gateway: <?= h($gateway ?: 'geen') ?>).</p>
</div>
<?php endif; ?>

<script>
// Gedrag van de vier octetvelden
const velden = [...document.querySelectorAll('.octet')];
velden.forEach((veld, i) => {
  veld.addEventListener('keydown', e => {
    // Punt (of komma) springt naar het volgende veld
    if ((e.key === '.' || e.key === ',') ) {
      e.preventDefault();
      if (veld.value !== '' && velden[i + 1]) { velden[i + 1].focus(); velden[i + 1].select(); }
    }
    // Backspace in een leeg veld springt terug
    if (e.key === 'Backspace' && veld.value === '' && velden[i - 1]) {
      e.preventDefault(); velden[i - 1].focus();
    }
  });
  veld.addEventListener('input', () => {
    veld.value = veld.value.replace(/\D/g, '');          // alleen cijfers
    if (veld.value.length === 3 && velden[i + 1]) velden[i + 1].focus();
  });
  veld.addEventListener('paste', e => {
    // Een volledig IP-adres plakken verdeelt het automatisch over de vier velden
    const delen = e.clipboardData.getData('text').trim().split('.');
    if (delen.length === 4) {
      e.preventDefault();
      delen.forEach((d, j) => velden[j].value = d.replace(/\D/g, '').slice(0, 3));
      velden[3].focus();
    }
  });
});
</script>
</body>
</html>
