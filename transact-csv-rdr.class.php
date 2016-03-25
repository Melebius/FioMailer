<?php
/**
 * @brief Čtečka seznamu transakcí ve formátu CSV.
 *
 * Třída poskytuje data ze seznamu transakcí ve formátu CSV
 * definovaném ve FIO API (http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf).
 *
 * Vyžaduje PHP 5.3.0 nebo novější.
 *
 * @author Miroslav Matějů @<melebius@gmail.com@>
 * @copyright GNU LGPL v3.0
 */
class TransactCsvReader
{
  // -------------------------- PUBLIC -----------------------------------
  /**
   * Vytvoření objektu podle zadaného obsahu CSV.
   * @param array $_csv obsah souboru CSV ve formátu FIO API,
   *                    zapsaný jako pole (funkce file())
   */
  public function __construct(array $_csv)
  {
    // zjištění délky hlavičky
    // Dokumentace API sice počítá s prázdným řádkem
    // před tabulkou transakcí, ale ve skutečnosti chybí.
    // Hlavičku tabulky tedy radši najdeme sami.
    for($i = 0; $i < count($_csv); $i++)
    {
      if(self::startsWith($_csv[$i], 'ID pohybu'))
      {
        $tab_header_idx = $i;
        break;
      }
    }

    // uložení posledního řádku hlavičky do názvů sloupců
    // V hlavičce tabulky by se neměly vyskytovat uvozovky,
    // takže si vystačíme s explode().
    $this->cols = explode(';', $_csv[$tab_header_idx]);

    // načtení transakcí do pole
    // vytvoření pole $picked s hodnotami false
    $this->transacts = array();
    $this->picked    = array();
    for($i = $tab_header_idx+1; $i < count($_csv); $i++)
    {
      $this->transacts[] = self::csv_explode($_csv[$i]);
      $this->picked[]    = false;
    }

    // inicializace vlastností, které by měly později nastavit metody
    $this->index    = self::INITIAL_INDEX;
    $this->filt_col = 0;
    $this->filt_val = "";
    $this->pick_once = false;
    $this->direction_filter = TransactDirection::All;
  }

  /**
   * Nastavení filtru transakcí.
   *
   * Při následném volání metody findNext() budou zahrnuty jen transakce,
   * jejichž klíč začíná na $value.
   *
   * Volání metody způsobí vynulování ukazatele na aktuální prvek seznamu.
   * Pro nalezení první transakce splňující požadavky filtru
   * je nutné zavolat findNext().
   *
   * @note Pokud je zadán prázdný řetězec jako $value,
   * budou zahrnuty všechny transakce, které mají příslušné pole NEPRÁZDNÉ!
   *
   * @note Nelze použít víc filtrů na hodnotu současně,
   * ale je možné současně použít filtr směru.
   *
   * @return bool Vrátí false, pokud se nepodařilo filtr nastavit.
   *              Například pokud klíč neexistuje.
   * @param string $_key klíč – název sloupce tabulky
   * @param string $_value řetězec, kterým má začínat hledaná hodnota
   */
  public function setFilter($_key, $_value)
  {
    $this->filt_col = array_search($_key, $this->cols);
    $this->filt_val = $_value;
    $this->index    = self::INITIAL_INDEX;

    // zkontrolujeme, zda byl klíč nalezen (=== pro rozlišení 0/FALSE)
    return ($this->filt_col !== false);
  }

  /**
   * Nastavení filtru směru transakcí.
   *
   * Při následném volání metody findNext() budou zahrnuty jen transakce
   * zadaného směru.
   *
   * @note Lze kombinovat s metodou setFilter(). Po nastavení obou typů filtru
   * budou zahrnuty transakce, které splní podmínky obou filtrů současně.
   *
   * @param TransactDirection $_direction volba směru transakce
   */
  public function setDirectionFilter($_direction)
  {
    $this->direction_filter = $_direction;
  }

  /**
   * Nastavení, zda vyzvednout položku jen jednou.
   * Po nastavení true bude metoda findNext() přeskakovat transakce,
   * které již byly vybrány dříve (za dobu existence objektu).
   * @param bool $_pickOnce
   */
  public function pickItemsOnce($_pickOnce)
  {
    $this->pick_once = $_pickOnce;
  }

  /**
   * Přechod na další transakci v seznamu.
   *
   * Přesune ukazatel, používaný metodou readTransactData(),
   * na následující transakci odpovídající filtru.
   *
   * @return bool úspěšnost nalezení následující transakce
   */
  public function findNext()
  {
    for($i = $this->index+1; $i < count($this->transacts); $i++)
    {
      if(!($this->pick_once && $this->picked[$i]) &&
         self::startsWith($this->transacts[$i][$this->filt_col], $this->filt_val) &&
         ($this->direction_filter == TransactDirection::All ||
          $this->getDirection($i) == $this->direction_filter))
      {
        $this->index = $i;
        $this->picked[$i] = true;
        return true;
      }
    }
    return false;
  }

  /**
   * Čtení údaje o transakci.
   *
   * Načte zvolený údaj aktuální transakce.
   *
   * @return string údaj aktuální transakce
   * @param string $_key sloupec tabulky, který se má načíst
   */
  public function readTransactData($_key)
  {
    return $this->readAnyTransactData($this->index, $_key);
  }

  // ------------------------- PRIVATE -----------------------------------
  private $transacts; ///< pole transakcí
  private $picked;    ///< pole příznaků, zda již byly transakce vybrány
  private $cols;      ///< názvy sloupců tabulky transakcí
  private $index;     ///< index aktuální transakce (pole $transacts)

  private $filt_col;  ///< index sloupce použitého jako filtr
  private $filt_val;  ///< hodnota, podle níž se sloupec filtruje
  private $pick_once; ///< předávat stejnou transakci jen jednou?
  private $direction_filter;  ///< filtr směru transakce

  const INITIAL_INDEX = -1; ///< index před nalezením 1. odpovídající transakce
                            ///< (první platný index zmenšený o 1)

  /**
   * Čtení údaje o zvolené transakci.
   *
   * Načte zvolený údaj transakce určené indexem.
   *
   * @param int $_index  index zvolené transakce
   * @param string $_key sloupec tabulky, který se má načíst
   */
  private function readAnyTransactData($_index, $_key)
  {
    return $this->transacts[$_index][array_search($_key, $this->cols)];
  }

  /**
   * Načtení směru zvolené transakce.
   *
   * @return TransactDirection směr transakce
   * @param int $_index index záznamu v poli transakcí
   */
  private function getDirection($_index)
  {
    if($this->readAnyTransactData($_index, "Objem") > 0)
    {
      return TransactDirection::Incoming;
    }
    else
    {
      return TransactDirection::Outgoing;
    }
  }

  /**
   * Začíná řetězec $haystack řetězcem $needle?
   *
   * Pomocná funkce pro práci s textem.
   *
   * @link http://stackoverflow.com/a/834355/711006
   *
   * @param string $haystack kde hledáme
   * @param string $needle co hledáme
   * @return bool
   */
  private static function startsWith($haystack, $needle)
  {
    return (substr($haystack, 0, strlen($needle)) === $needle);
  }

  /**
   * Končí řetězec $haystack řetězcem $needle?
   *
   * Pomocná funkce pro práci s textem.
   *
   * @link http://stackoverflow.com/a/834355/711006
   *
   * @param string $haystack kde hledáme
   * @param string $needle co hledáme
   * @return bool
   */
  private static function endsWith($haystack, $needle)
  {
    return (substr($haystack, 0, -strlen($needle)) === $needle);
  }

  /**
   * Rozdělení řádku CSV na pole.
   *
   * Oproti vestavěné funkci explode() zohledňuje speciální chování
   * středníků a uvozovek, viz http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf.
   *
   * @return array pole hodnot v zadaném řádku
   * @param string $row řádek souboru CSV
   */
  private static function csv_explode($row)
  {
    return str_getcsv($row, ';', '"', '"');
  }
}

/**
 * Možné volby směru transakce pro TransactCsvReader::setDirectionFilter().
 */
abstract class TransactDirection
{
  const All = 1;
  const Incoming = 2;
  const Outgoing = 3;
}
?>
