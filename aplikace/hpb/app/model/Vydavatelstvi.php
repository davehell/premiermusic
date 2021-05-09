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
	public function findAll($kategorie = null, $limit = null, $offset = null)
	{
    $result = $this->database->table('hudba_noty');

    if($kategorie) {
      $result->where("hudba_noty_kategorie_id", $kategorie);
    }

    $result->order('nazev ASC')
           ->limit($limit, $offset);

    return $result;
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
    //format_id 14 = mp3 vèetnì melodické linky
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
}
