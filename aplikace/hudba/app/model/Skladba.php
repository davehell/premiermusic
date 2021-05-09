<?php


class Skladba extends Nette\Object
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
		$skladby = $this->database->table('skladba');
    if($filtry['nazev']) {
      $skladby = $skladby->where('nazev LIKE ?', '%' . $filtry['nazev'] . '%');
    }
    if($filtry['autor']) {
      $skladby = $skladby->where('autor LIKE ?', '%' . $filtry['autor'] . '%');
    }
    if($filtry['zanr']) {
      $skladby = $skladby->where('zanr_id', $filtry['zanr']);
    }
    if($filtry['verze']) {
      $skladby = $skladby->where('verze', $filtry['verze']);
    }

    if($razeni) {
      $skladby = $skladby->order($razeni['sloupec'] . ' ' . $razeni['smer']);
    }

    $skladby = $skladby->limit($limit, $offset);
    return $skladby;
	}

	/** @return Nette\Database\Table\ActiveRow */
	public function findById($id)
	{
		return $this->findAll()->get($id);
	}


	/** @return Nette\Database\Table\Selection */
	public function formatySkladby($id)
	{
		return $this->database->table('soubor')->where('skladba_id', $id);
	}

  /** @return array */
	public function seznamZanru()
	{
    return $this->database->table('zanr')->fetchPairs('id', 'nazev');
	}

  /** @return array */
	public function seznamFormatu($demo = null)
	{
    return $this->database->table('format')->where('demo', $demo == 'demo' ? 1 : 0)->fetchPairs('id', 'nazev');
	}


	/** @return Nette\Database\Table\Selection */
	public function prehledStahovani($od, $do, $razeni = null, $limit = null, $offset = null)
	{
    if(!$od || !$do) return null;
    $do .= ' 23:59:59';

    $skladby = $this->database->table('nakup')->select('skladba_id, skladba.nazev AS nazev, skladba.autor AS autor, nakup.cena AS cena, count(*) AS pocet')->where('datum >= ?', $od)->where('datum <= ?', $do)->group('skladba_id');

    if($razeni) {
      $skladby = $skladby->order($razeni['sloupec'] . ' ' . $razeni['smer']);
    }

    $skladby = $skladby->limit($limit, $offset);

    return $skladby;
	}

	/** @return Nette\Database\Table\Selection */
	public function oblibene()
	{
    return $this->database->table('skladba')->order('pocet_stazeni DESC')->limit(10);
	}

	/** @return Nette\Database\Table\Selection */
	public function novinky()
	{
    return $this->findAll()->order('datum_pridani DESC')->limit(10);
	}

	public function pocetSkladeb()
	{
    return $this->findAll()->count();
	}

	public function prumernaCena()
	{
		return $this->findAll()->aggregation("AVG(cena)");
	}

	public function update($skladbaId, $values)
	{
		$this->database->table('skladba')->wherePrimary($skladbaId)->update($values);
	}

  /** @return Nette\Database\Table\ActiveRow */
	public function insert($skladba)
	{
    $skladba['datum_pridani'] = new Nette\Database\SqlLiteral('NOW()');
    return $this->database->table('skladba')->insert($skladba);
	}

  /** @return Nette\Database\Table\ActiveRow */
	public function delete($skladbaId)
	{
    return $this->database->table('skladba')->wherePrimary($skladbaId)->delete();
	}

  /** @return Nette\Database\Table\ActiveRow */
	public function ulozitSoubory($soubory, $adresar)
	{
    foreach ($soubory as $soubor) {
      $this->database->table('soubor')->where('skladba_id', $soubor['skladba_id'])->where('format_id', $soubor['format_id'])->delete();
    }
    return $this->database->table('soubor')->insert($soubory);
	}

	public function nazevSouboru($id)
	{
    return $this->database->table('soubor')->get($id);
	}

  /** @return null */
	public function exportNazvuSkladeb($soubor)
	{
    $skladby = $this->database->table('skladba')->select('id,nazev')->order('nazev');
    $eol = "\r\n";
    $handle = fopen('safe://' . $soubor, 'w');
    fwrite($handle, '[' . $eol);
    foreach ($skladby as $skladba) {
      fwrite($handle, '{"id":' . $skladba->id . ',"value":"' . $skladba->nazev . '"},' . $eol);
    }
    fwrite($handle, '{}' . $eol);
    fwrite($handle, ']' . $eol);
    fclose($handle);
	}

  /** @return null */
	public function exportAutoru($soubor)
	{
    $skladby = $this->database->table('skladba')->select('DISTINCT autor')->order('autor');
    $eol = "\r\n";
    $handle = fopen('safe://' . $soubor, 'w');
    fwrite($handle, '[' . $eol);
    foreach ($skladby as $skladba) {
      fwrite($handle, '"' . $skladba->autor . '",' . $eol);
    }
    fwrite($handle, '""' . $eol);
    fwrite($handle, ']' . $eol);
    fclose($handle);
	}

	public function stazenoSkladeb()
	{
		return $this->database->table('nakup')->where("uzivatel.role", "zakaznik")->count();
	}

	public function celkemNakoupenoZa()
	{
		return $this->database->table('nakup')->where("uzivatel.role", "zakaznik")->sum("cena");
	}
}
