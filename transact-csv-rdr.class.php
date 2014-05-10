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
    // uložení posledního řádku hlavičky do názvů sloupců
    // V hlavičce tabulky by se neměly vyskytovat uvozovky,
    // takže si vystačíme s explode().
    $this->cols = explode(';', $_csv[self::CSV_HEADER_LENGTH]);

    // načtení transakcí do pole
    $this->transacts = array();
    for($i = self::CSV_HEADER_LENGTH; $i < count($_csv); $i++)
    {
      $this->transacts[] = self::csv_explode($_csv[$i]);
    }

    // inicializace vlastností, které by měly později nastavit metody
    $this->index    = self::INITIAL_INDEX;
    $this->filt_col = 0;
    $this->filt_val = "";
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
   * @note Nelze použít víc filtrů současně!
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
      if(self::startsWith($this->transacts[$i][$this->filt_col], $this->filt_val))
      {
        $this->index = $i;
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
    return $this->transacts[$this->index][array_search($_key, $this->cols)];
  }

  // ------------------------- PRIVATE -----------------------------------
  private $transacts; ///< pole transakcí
  private $cols;      ///< názvy sloupců tabulky transakcí
  private $index;     ///< index aktuální transakce (pole $transacts)

  private $filt_col;  ///< index sloupce použitého jako filtr
  private $filt_val;  ///< hodnota, podle níž se sloupec filtruje

  const CSV_HEADER_LENGTH = 13;   ///< počet řádků před tabulkou transakcí
  const INITIAL_INDEX     =  0;   ///< hodnota indexu na začátku průchodu polem

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
?>
