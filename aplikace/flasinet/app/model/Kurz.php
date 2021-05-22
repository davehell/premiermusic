<?php


class Kurz extends Nette\Object
{
  /** @var Nette\Database\Context */
  private $database;


  public function __construct(Nette\Database\Context $database)
  {
    $this->database = $database;
  }

  /** @return Nette\Database\Table\ActiveRow */
  public function eur()
  {
    return $this->database->table('kurzy')->get(1);
  }

  public function updateEur($datum, $kurz)
  {
    $this->database->table('kurzy')->wherePrimary(1)->update(array('datum' => $datum, 'kurz' => $kurz));
  }
}
