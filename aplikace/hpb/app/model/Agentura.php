<?php


class Agentura extends Nette\Object
{
	/** @var Nette\Database\Context */
	private $database;


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/** @return Nette\Database\Table\Selection */
	public function findAll()
	{
		return $this->database->table('hudba_agentura')->order('nazev ASC');
	}

	/** @return Nette\Database\Table\ActiveRow */
	public function findById($id)
	{
		return $this->findAll()->get($id);
	}


  /** @return Nette\Database\Table\ActiveRow */
	public function insert($kapela)
	{
    return $this->database->table('hudba_agentura')->insert($kapela);
	}

	public function update($id, $values)
	{
		$this->database->table('hudba_agentura')->wherePrimary($id)->update($values);
	}

	public function smazat($id)
	{
    $this->database->table('hudba_agentura')->wherePrimary($id)->delete();
	}
}
