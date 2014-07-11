<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Test třídy FioMailer</title>
</head>
<body>
	<h1>Test třídy FioMailer</h1>
<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

define('ENDL', "<br/>\n");

require('../fio-mailer.class.php');

echo "<h2>Start</h2>\n<pre>\n";

function logInfo($_condition, $_message)
{
  echo $_message.': ';
  echo $_condition ? 'OK' : 'CHYBA';
  echo "\n";
}

$to = '---DOPLŇ SVOJI MAILOVOU ADRESU---';

$mailer = new FioMailer('---DOPLŇ TOKEN---');
logInfo($mailer->readHistory(true), 'Čtení historie');

echo "<h2>Odesílání</h2>\n";

logInfo($mailer->sendSingleAlerts($to, 'Vše'), 'Všechny transakce');

echo "\n<hr/>\n";

// ID = 5088*
logInfo($mailer->setFilter('ID pohybu', '5088'), 'Nastavení filtru ID');
logInfo($mailer->sendSingleAlerts($to, 'Podle ID'), 'Odeslání podle ID');

echo "\n<hr/>\n";

// Objem = 1000
logInfo($mailer->setFilter('Objem', '1000,0'), 'Nastavení filtru objemu');
logInfo($mailer->sendSingleAlerts($to, 'Tisícovka'), 'Odeslání podle objemu');

echo "\n</pre>\n<h2>Konec</h2>\n";
?>
</body>
</html>
