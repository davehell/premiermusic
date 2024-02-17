<?php

class Vydavatelstvi extends Nette\Object
{
  /** @var Nette\Database\Context */
  private $database;


  public function __construct(Nette\Database\Context $database)
  {
    $this->database = $database;
  }


  /** @return Nette\Database\Table\Selection */
  public function findAll($filtry = null, $razeni = null, $limit = null, $offset = null)
  {
    $result = $this->database->table('hudba_noty');

    if($filtry['nazev']) {
      $result = $result->where('nazev LIKE ?', '%' . $filtry['nazev'] . '%');
    }
    if($filtry['popis']) {
      $result = $result->where('popis LIKE ?', '%' . $filtry['popis'] . '%');
    }
    if($filtry['kategorie']) {
      $result = $result->where('hudba_noty_kategorie_id', $filtry['kategorie']);
    }

    if($razeni) {
      $result = $result->order($razeni['sloupec'] . ' ' . $razeni['smer']);
    }

    $result->limit($limit, $offset);

    return $result;
  }

  /** @return null */
  public function exportNazvuNot($soubor)
  {
    $noty = $this->findAll()->select('DISTINCT nazev');
    $eol = "\r\n";
    $handle = fopen('safe://' . $soubor, 'w');
    fwrite($handle, '[' . $eol);
    foreach ($noty as $nota) {
      fwrite($handle, '"' . trim($nota->nazev) . '",' . $eol);
    }
    fwrite($handle, '""' . $eol);
    fwrite($handle, ']' . $eol);
    fclose($handle);
  }

  /** @return null */
  public function exportPopisuNot($soubor)
  {
    $noty = $this->database->table('hudba_noty')->select('DISTINCT popis');
    $eol = "\r\n";
    $handle = fopen('safe://' . $soubor, 'w');
    fwrite($handle, '[' . $eol);
    foreach ($noty as $nota) {
      fwrite($handle, '"' . trim($nota->popis) . '",' . $eol);
    }
    fwrite($handle, '""' . $eol);
    fwrite($handle, ']' . $eol);
    fclose($handle);
  }

  /** @return Nette\Database\Table\ActiveRow */
  public function findById($id)
  {
    return $this->findAll()->get($id);
  }

  public function update($notyId, $values)
  {
    $this->database->table('hudba_noty')->wherePrimary($notyId)->update($values);
  }

  /** @return Nette\Database\Table\ActiveRow */
  public function insert($cd)
  {
    return $this->database->table('hudba_noty')->insert($cd);
  }

  public function smazat($notyId)
  {
    $this->database->table('hudba_noty')->wherePrimary($notyId)->delete();
  }

  public function demoSkladby()
  {
    //format_id 14 = mp3 v?tn?melodick?linky
    return $this->database->table('soubor')->select('skladba.nazev AS nazev, soubor.id')->where('format_id', 14)->order('nazev ASC')->fetchPairs('id', 'nazev');
  }

  public function nazevSouboru($id)
  {
    return $this->database->table('soubor')->get($id);
  }

  /** @return array */
  public function seznamKategorii()
  {
    return $this->database->table('hudba_noty_kategorie')->fetchPairs('id', 'nazev');
  }

  /** @return array */
  public function kategorie()
  {
    return $this->database->table('hudba_noty_kategorie');
  }

  /** @return Nette\Database\Table\Selection */
  public function novinky()
  {
    return $this->findAll()->order('id DESC')->limit(10);
  }
}
