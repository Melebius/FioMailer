<?php
require_once('transact-csv-rdr.class.php');
require_once('phpmailer/class.phpmailer.php');
require_once('phpmailer/class.smtp.php');
require_once('phpmailer/language/phpmailer.lang-cz.php');

/**
 * @brief Zasílač upozornění na transakce ve FIO.
 *
 * Třída provádí načtení seznamu posledních transakcí z FIO
 * a zašle mailové upozornění na transakce splňující zadané kritérium.
 *
 * Používá knihovnu PHPMailer: https://github.com/PHPMailer/PHPMailer
 *
 * @author Miroslav Matějů @<melebius@gmail.com@>
 * @copyright GNU LGPL v3.0
 */
class FioMailer
{
  // -------------------------- PUBLIC -----------------------------------
  /**
   * Vytvoření objektu se zadaným autentizačním tokenem FIO.
   * @param string $_token autentizační token FIO
   */
  public function __construct($_token)
  {
    $this->token = $_token;
  }

  /**
   * Načte z banky historii transakcí od posledního zavolání této funkce.
   * @param bool $_pickOnce nastaví, zda se může záznam při čtení z CSV vybrat víckrát
   */
  public function readHistory($_pickOnce)
  {
    // stažení dat z banky
    $csv = file(self::URL_BASE . $this->token . '/transactions.csv');
    $this->csv_reader = new TransactCsvReader($csv);
    $this->csv_reader->pickItemsOnce($_pickOnce);
    return true;
  }

  /**
   * Nastavení filtru transakcí.
   * Viz informace u třídy TransactCsvReader.
   * @param string $_key název sloupce v tabulce transakcí
   * @param string $_value žádaný začátek hodnoty v daném sloupci
   * @return bool úspěšnost nastavení filtru
   */
  public function setFilter($_key, $_value)
  {
    if(!is_null($this->csv_reader))
    {
      return $this->csv_reader->setFilter($_key, $_value);
    }
    else
    {
      return false;
    }
  }

  /**
   * Nastavení filtru směru transakcí.
   * Viz informace u třídy TransactCsvReader.
   * @param TransactDirection $_direction požadovaný směr transakcí
   * @return bool úspěšnost nastavení filtru
   */
  public function setDirectionFilter($_direction)
  {
    if(!is_null($this->csv_reader))
    {
      return $this->csv_reader->setDirectionFilter($_direction);
    }
    else
    {
      return false;
    }
  }

  /**
   * Zaslání upozornění na všechny vybrané transakce v jedné zprávě.
   * @param string $_to e-mailová adresa příjemce
   * @param string $_subj_start začátek předmětu e-mailu
   * @return bool úspěšnost odeslání
   * @todo Odesílá i prázdný seznam!
   */
  public function sendAlertDigest($_to, $_subj_start)
  {
    $subject = $_subj_start . ' – upozornění na transakce';

    $body = "V bance proběhly následující sledované transakce:\n".
            "=================================================\n\n";

    while($this->csv_reader->findNext())
    {
      $body .= $this->printTransaction().
               "\n=================================================\n\n";
    }

    $body .= "Odeslal FioMailer Akademických týdnů.\n";

    return $this->sendMail($_to, $subject, $body);
  }

  /**
   * Zaslání upozornění na jednotlivé vybrané transakce.
   * @param string $_to e-mailová adresa příjemce
   * @param string $_subj_start začátek předmětu e-mailu
   * @return bool úspěšnost odeslání
   */
  public function sendSingleAlerts($_to, $_subj_start)
  {
    $result = true;
    while($this->csv_reader->findNext())
    {
      $subject = $_subj_start . ' – ' .
                 $this->csv_reader->readTransactData('VS') . ' – ' .
                 $this->csv_reader->readTransactData('Objem');

      $body = "V bance proběhla následující sledovaná transakce:\n\n";
      $body .= $this->printTransaction().
               "\n\nOdeslal FioMailer Akademických týdnů.\n";
      $result = $this->sendMail($_to, $subject, $body) && $result;
    }

    return $result;
  }

  // ------------------------- PRIVATE -----------------------------------
  private $csv_reader = NULL; ///< čtečka transakcí
  private $token;             ///< autentizační token banky

  /// stálá část URL pro komunikaci s bankou
  const URL_BASE = 'https://fioapi.fio.cz/v1/rest/last/';

  /**
   * Vypíše údaje o právě vybrané transakci.
   * @return string řetězec obsahující na jednotlivých řádcích údaje o transakci
   */
  private function printTransaction()
  {
    return
      $this->printValue('ID pohybu').
      $this->printValue('Datum').
      $this->printValue('Objem').
      $this->printValue('Měna').
      $this->printValue('Protiúčet').
      $this->printValue('Název protiúčtu').
      $this->printValue('Kód banky').
      $this->printValue('Název banky').
      $this->printValue('KS').
      $this->printValue('VS').
      $this->printValue('SS').
      $this->printValue('Uživatelská identifikace').
      $this->printValue('Zpráva pro příjemce').
      $this->printValue('Typ').
      $this->printValue('Provedl').
      $this->printValue('Upřesnění').
      $this->printValue('Komentář').
      $this->printValue('BIC').
      $this->printValue('ID pokynu');
  }

  /**
   * Výpis jednoho údaje jedné transakce.
   * @param unknown $key
   * @return string řádek ve formátu $key: hodnota\\n
   */
  private function printValue($key)
  {
    return $key.': '.$this->csv_reader->readTransactData($key)."\n";
  }

  /**
   * Odeslání e-mailu pomocí knihovny PHPMailer.
   * @param string $_to
   * @param string $_subject
   * @param string $_body
   * @return bool úspěšnost odeslání
   */
  private function sendMail($_to, $_subject, $_body)
  {
    $mail = new PHPMailer();

    // přihlášení k serveru
    $mail->IsSMTP();  // volba metody odeslání
    $mail->SMTPAuth    = true;
    $mail->SMTPSecure  = 'ssl';
    $mail->Host        = 'smtp.gmail.com';
    $mail->Port        = 465;
    $mail->Username    = 'DOPLNIT@ADRESU.ODESILATELE';
    $mail->Password    = '---DOPLNIT HESLO---';

    // hlavička zprávy (stálá data)
    $mail->From        = $mail->Username;     // adresa = přihlašovací jméno
    $mail->FromName    = 'DOPLNIT JMENO ODESILATELE';
    $mail->SetLanguage('cz');
    $mail->CharSet     = 'utf-8';
    $mail->ContentType = 'text/plain';
    $mail->IsHTML(false);

    // obsah zprávy (proměnná data)
    $mail->AddAddress($_to);
    $mail->Subject     = $_subject;
    $mail->Body        = $_body;

    // vlastní odeslání
    $result = $mail->Send();
    if(!$result)
    {
      echo $mail->ErrorInfo;
    }
    return $result;
  }
}
?>
