<?php


class Bazar extends Nette\Object
{
	/** @var Nette\Database\Context */
	private $database;


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/** @return Nette\Database\Table\Selection */
	public function findAll($typ = null, $kategorie = null, $limit = null, $offset = null)
	{
		$result = $this->database->table('hudba_bazar');

    if($typ) {
      $result->where("typ", $typ);
    }
    if($kategorie) {
      $result->where("hudba_bazar_kategorie_id", $kategorie);
    }
            
    $result->order('datum DESC')
           ->limit($limit, $offset);

    return $result;
	}

	/** @return Nette\Database\Table\ActiveRow */
	public function findById($id)
	{
		return $this->findAll()->get($id);
	}

	public function update($inzeratId, $values)
	{
		$this->database->table('hudba_bazar')->wherePrimary($inzeratId)->update($values);
	}

  /** @return Nette\Database\Table\ActiveRow */
	public function insert($inzerat)
	{
    $inzerat['datum'] = new Nette\Database\SqlLiteral('NOW()');
    return $this->database->table('hudba_bazar')->insert($inzerat);
	}

	public function smazatFoto($inzeratId, $fotoId, $soubory)
	{
    $values = array();
    if($fotoId == 1) $values['foto1'] = null;
    else if($fotoId == 1) $values['foto2'] = null;
    else $values['foto2'] = null;
    $this->database->table('hudba_bazar')->wherePrimary($inzeratId)->update($values);

    foreach ($soubory as $soubor) {
      if(file_exists($soubor)) unlink($soubor);
    }
	}

	public function smazat($inzeratId)
	{
    $this->database->table('hudba_bazar')->wherePrimary($inzeratId)->delete();
	}

  /** @return array */
	public function seznamKategorii()
	{
    return $this->database->table('hudba_bazar_kategorie')->fetchPairs('id', 'nazev');
	}
}
