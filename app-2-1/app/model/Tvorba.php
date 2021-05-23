<?php

class Tvorba extends Nette\Object
{
    /** @var Nette\Database\Context */
    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    /** @return Nette\Database\Table\Selection */
    public function findAll($limit = null, $offset = null)
    {
        $result = $this->database->table('hudba_tvorba');

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
        $this->database->table('hudba_tvorba')->wherePrimary($notyId)->update($values);
    }

  /** @return Nette\Database\Table\ActiveRow */
    public function insert($cd)
    {
    return $this->database->table('hudba_tvorba')->insert($cd);
    }

    public function smazat($notyId)
    {
    $this->database->table('hudba_tvorba')->wherePrimary($notyId)->delete();
    }
}
