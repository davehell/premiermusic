<?php

use Nette\Application\Responses\FileResponse,
    Nette\Forms\Form,
    Nette\Utils\Strings;

/**
 * Skladba presenter.
 */
class SkladbaPresenter extends BasePresenter
{

  /** @persistent */
  public $nazev;
  /** @persistent */
  public $autor;
  /** @persistent */
  public $zanr;
  /** @persistent */
  public $verze;
  /** @persistent */
  public $radit = 'nazev';
  /** @persistent */
  public $asc = '1';
  /** @persistent */
  public $mode;

	/** @var Skladba @inject*/
	public $skladby;
  /** @var Uzivatel @inject*/
  public $uzivatele;
  /** @var Kurz @inject*/
  public $kurzy;

  protected function beforeRender()
  {
      parent::beforeRender();
      $this->setLayout('midi');
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentSkladbaForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addGroup('Skladba');
    $form->addText('nazev', 'Název:')
      ->setRequired('Prosím zadejte název skladby.')
      ->addRule(Form::MAX_LENGTH, 'Název musí mít maximálně %d znaků', 100);

    $form->addText('autor', 'Interpret:')
      ->setRequired('Prosím zadejte interpreta skladby.')
      ->addRule(Form::MAX_LENGTH, 'Interpret musí mít maximálně %d znaků', 100);

    $form->addText('cena', 'Cena:')
      ->setRequired('Prosím zadejte cenu skladby.')
      ->addRule(Form::INTEGER, 'Částka musí být číslo')
      ->addRule(Form::RANGE, 'Částka musí být od %d do %d Kč', array(1, 100))
      ->setType('number');

    $form->addText('poznamka', 'Poznámka:')
      ->addRule(Form::MAX_LENGTH, 'Poznámka musí mít maximálně %d znaků', 100);

    $form->addSelect('zanr_id', 'Žánr:', $this->skladby->seznamZanru())
      ->setRequired('Prosím zadejte žánr.')
      ->setPrompt('Zvolte žánr');

    $form->addSelect('verze', 'Verze:', array('MIDI' => 'MIDI', 'Karaoke' => 'Karaoke'))
      ->setRequired('Prosím zadejte verzi.')
      ->setPrompt('Zvolte verzi');

    $form->addGroup('Ukázky');
    foreach($this->skladby->seznamFormatu('demo') as $formatId => $formatNazev) {
      $form->addUpload('format' . $formatId, $formatNazev);
    }

    $form->addGroup('Plné verze');
    foreach($this->skladby->seznamFormatu('plna') as $formatId => $formatNazev) {
      $form->addUpload('format' . $formatId, $formatNazev);
    }

    $form->addGroup('Uložení')->setOption('container', 'fieldset id=ulozeni');
    $form->addSubmit('send', 'Uložit');

    $form->onSuccess[] = $this->skladbaFormSucceeded;
    $form->addProtection('Vypršel časový limit, odešlete formulář znovu.');

    return Bs3Form::transform($form);
  }


  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentHledaniForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('nazev', 'Název:')
      ->addRule(Form::MAX_LENGTH, 'Název skladby může mít maximálně %d znaků', 100);

    $form->addText('autor', 'Interpret:')
      ->addRule(Form::MAX_LENGTH, 'Interpret může mít maximálně %d znaků', 100);

    $form->addSelect('zanr', 'Žánr:', $this->skladby->seznamZanru())
      ->setPrompt('všechny');

    $form->addSelect('verze', 'Verze:', array('MIDI' => 'MIDI', 'Karaoke' => 'Karaoke'))
      ->setPrompt('všechny');

    $form->addSubmit('send', 'Hledat');

    $form->onSuccess[] = $this->hledaniFormSucceeded;

    return Bs3Form::transform($form);
  }


  public function skladbaFormSucceeded($form)
  {
    $values = $form->getValues();
    $skladbaId = $this->getParameter('id');
    $uploads = $this->context->getService('httpRequest')->getFiles();
    $idFormatu = array(); //název uploadovaného tmp souboru => id formatu, kte kteremu soubor patri

    //odstraneni upload polozek z pole s informacemi pro ulozeni
    //vytvoreni pole $idFormatuy
    foreach($values as $key => $val) {
      if($val instanceof Nette\Http\FileUpload && Strings::startsWith($key, 'format')) {
        $tmpFile = pathinfo($val->getTemporaryFile(), PATHINFO_BASENAME );
        $idFormatu[$tmpFile] = substr($key, 6); //odstraneni retezce "format" - zustane pouze id
        unset($values[$key]);
      }
    }


    if($skladbaId) { //editace
      try {
        $this->skladby->update($skladbaId, $values);
      } catch (\Exception $e) {
        $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
      }
    }
    else { //nova skladba
      try {
        $skladba = $this->skladby->insert($values);
        $skladbaId = $skladba->id;
      } catch (\Exception $e) {
        $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
      }
    }

    $this->flashMessage('Skladba byla uložena.', 'success');

    //presun uploadovanych souboru z tmp adresare do ciloveho umisteni
    $destDir = $this->context->parameters['appDir'] . '/../data/midi';
    $soubory = array();
    foreach ($uploads as $soubor) {
      if($soubor && $soubor->isOk) {
        $ext = pathinfo($soubor->getName(), PATHINFO_EXTENSION );
        $formatId = $idFormatu[pathinfo($soubor->getTemporaryFile(), PATHINFO_BASENAME)];
        $filename = Strings::webalize($values['nazev']) . '.' . $ext;
        $soubor->move($destDir . '/skladba-' . $skladbaId . '-' . $formatId);
        $soubory[] = array('skladba_id' => $skladbaId, 'format_id' => $formatId, 'nazev' => $filename);
      }
    }

    if(count($soubory)) {
      try {
        $this->skladby->ulozitSoubory($soubory, $destDir);
      } catch (\Exception $e) {
        $this->flashMessage('Soubory se nepodařilo nahrát.', 'danger');
      }
    }

    $this->skladby->exportNazvuSkladeb($this->context->parameters['wwwDir'] . '/skladby.json');
    $this->skladby->exportAutoru($this->context->parameters['wwwDir'] . '/autori.json');

    $this->redirect('Skladba:detail', $skladbaId);
  }


  public function hledaniFormSucceeded($form)
  {
    $values = $form->getValues();
    $params = array('nazev' => $values['nazev'], 'autor' => $values['autor'], 'zanr' => $values['zanr'], 'verze' => $values['verze']);
    if(!$params['nazev']) $params['nazev'] = null;
    if(!$params['autor']) $params['autor'] = null;
    $this->redirect('Skladba:katalog', $params);
  }

	public function renderDefault()
	{
    $this->template->oblibene = $this->skladby->oblibene();
    $this->template->novinky = $this->skladby->novinky();
    $this->template->pocet = $this->skladby->pocetSkladeb();
    $this->template->formaty = $this->skladby->seznamFormatu('plneVerze');
	}

	public function renderKatalog($mode = null)
  {
    $filtry['nazev'] = $this->getParameter('nazev');
    $filtry['autor'] = $this->getParameter('autor');
    $filtry['zanr']  = $this->getParameter('zanr');
    $filtry['verze'] = $this->getParameter('verze');
    $this['hledaniForm']->setDefaults($filtry);
    $razeni['sloupec'] = $this->getParameter('radit');
    $razeni['smer'] = $this->getParameter('asc') ? 'ASC' : 'DESC';


    $pocetSkladeb = $this->skladby->findAll($filtry)->count();
    $vp = new VisualPaginator($this, 'vp');
    $paginator = $vp->getPaginator();
    $paginator->itemsPerPage = 50;
    $paginator->itemCount = $pocetSkladeb;

    $this->template->skladby = $this->skladby->findAll($filtry, $razeni, $paginator->getLength(), $paginator->getOffset());
    $this->template->adminMode = $this->user->isInRole('admin') && $mode;
    $this->template->razeniSloupec = $razeni['sloupec'];
    $this->template->razeniAsc = $this->getParameter('asc');
    $this->template->formaty = $this->skladby->seznamFormatu('plneVerze');
	}

  public function renderDetail($id)
  {
    $skladba = $this->skladby->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }
    $this->template->skladba = $skladba;
    $this->template->soubory = $this->skladby->formatySkladby($id);
    $this->template->maZakoupeno = $this->uzivatele->maZakoupeno($this->user->id, $id);

    $kurzEur = $this->kurzy->eur();
    $this->template->cenaEur = $skladba->cena / floatval($kurzEur->kurz);

    if($this->user->isInRole('admin')) {
      $this['skladbaForm']->setDefaults($skladba->toArray());
    }
  }

	public function actionNakup($id)
	{
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro nákup skladby se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $skladba = $this->skladby->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }
    $this->template->skladba = $skladba;

    //zakaznik uz nakoupeno ma - nebude tedy kupovat znovu
    if($this->uzivatele->maZakoupeno($this->user->id, $id)){
      $this->redirect('Skladba:detail', $id);
    }

    try {
      $this->uzivatele->koupitSkladbu($this->user->id, $skladba);
    } catch (\Exception $e) {
      $this->flashMessage($e->getMessage(), 'danger');
      $this->redirect('Skladba:detail', $id);
    }

    BasePresenter::sendMail('nakup.latte', $this->user->getIdentity()->data['email'], $skladba);
	}


	public function actionPridat()
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }
	}

	public function actionDownload($id)
	{
    $soubor = $this->skladby->nazevSouboru($id);
    if (!$soubor) {
      $this->error('Požadovaný soubor neexistuje.');
    }

    if(!$soubor->format->demo) {
      if (!$this->user->isLoggedIn()) {
        $this->flashMessage('Pro stažení skladby se musíte přihlásit.');
        $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
      }

      if(!$this->uzivatele->maZakoupeno($this->user->id, $soubor->skladba_id)){
        $this->flashMessage('Tuto skladbu nemáte zakoupenou.', 'danger');
        $this->redirect('Skladba:detail', $soubor->skladba_id);
      }
    }

    $this->sendResponse(new FileResponse($this->context->parameters['appDir'] . '/../data/midi' . '/skladba-' . $soubor->skladba_id . '-' . $soubor->format_id, $soubor->nazev));
	}


	public function actionSmazat($id)
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $skladba = $this->skladby->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }
    $soubory = $this->skladby->formatySkladby($id);

    try {
      $this->skladby->delete($id);
    } catch (\Exception $e) {
      $msg = 'Tuto skladbu není možné smazat. ';
      if($e->getCode() == 23000) $msg .= ' Pravděpodobně si ji už někdo koupil.'; //a foreign key constraint fails
      $this->flashMessage($msg, 'danger');
      $this->redirect('Skladba:detail', $id);
    }

    foreach($soubory as $soubor) {
      $nazev = $this->context->parameters['appDir'] . '/../data/midi' . '/skladba-' . $soubor->skladba_id . '-' . $soubor->format_id;
      if(file_exists($nazev)) unlink($nazev);
    }

    $this->flashMessage('Sklaba byla smazána.', 'success');
    $this->redirect('Skladba:katalog');
	}

  public function actionKurzyMen()
  {
    $url = 'https://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt';
    $i = 0;
    $datum = '';
    $kurz = '';

    /* příklad odpovědi:
    15.03.2016 #52
    EMU|euro|1|EUR|27,045
    */
    if ($stream = fopen($url, 'r')) {
      while (($line = fgets($stream)) !== false) {
        if(++$i == 1) {
          $tmp = explode(' ', $line);
          $datum = trim($tmp[0]);
        }
        if(strpos($line, 'EUR') !== false) {
          $tmp = explode('|', $line);
          $kurz = str_replace(',', '.', trim($tmp[4]));
        }
      }
      fclose($stream);
    }
    $this->kurzy->updateEur($datum, $kurz);
    $this->terminate();
  }
}
