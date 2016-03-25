<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Test třídy TransactCsvReader</title>
</head>
<body>
	<h1>Test třídy TransactCsvReader</h1>
<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

define('ENDL', "<br/>\n");

require('../transact-csv-rdr.class.php');

echo "<h2>Start</h2>\n";

$transacts = new TransactCsvReader(file("specimen.csv"));
//$transacts->setFilter('ID pohybu', '1252');

function vypis($pole)
{
  global $transacts;
  echo $pole.': '.$transacts->readTransactData($pole).ENDL;
}

function vypisTransakce()
{
  global $transacts;
  while ($transacts->findNext())
  {
    echo "<h4>Transakce</h4>\n";
    echo vypis('ID pohybu');
    echo vypis('Datum');
    echo vypis('Objem');
    echo vypis('Měna');
    echo vypis('Protiúčet');
    echo vypis('Název protiúčtu');
    echo vypis('Kód banky');
    echo vypis('Název banky');
    echo vypis('KS');
    echo vypis('VS');
    echo vypis('SS');
    echo vypis('Uživatelská identifikace');
    echo vypis('Zpráva pro příjemce');
    echo vypis('Typ');
    echo vypis('Provedl');
    echo vypis('Upřesnění');
    echo vypis('Komentář');
    echo vypis('BIC');
    echo vypis('ID pokynu');
    echo "<hr/>\n";
  }
}

$transacts->pickItemsOnce(true);

echo "<h3>ID = 1252*</h3>\n";
$transacts->setFilter('ID pohybu', '1252');
vypisTransakce();

echo "<h3>Objem = 0,0*</h3>\n";
$transacts->setFilter('Objem', '0,0');
vypisTransakce();

$transacts->setFilter('Objem', '');

echo "<h3>Příchozí</h3>\n";
$transacts->setDirectionFilter(TransactDirection::Incoming);
vypisTransakce();

echo "<h3>Odchozí</h3>\n";
$transacts->setDirectionFilter(TransactDirection::Outgoing);
vypisTransakce();

echo "<h3>Všechny</h3>\n";
$transacts->pickItemsOnce(false);
$transacts->setFilter('Objem', '');
$transacts->setDirectionFilter(TransactDirection::All);
vypisTransakce();

echo "<h2>Konec</h2>\n";
?>
</body>
</html>
