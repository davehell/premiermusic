<?php

use Nette\Application\Responses\FileResponse,
    Nette\Forms\Form,
    Nette\Image,
    Nette\Mail\Message,
    Nette\Mail\SendmailMailer;

/**
 * Homepage presenter.
 */
class VydavatelstviPresenter extends BasePresenter
{

	/** @var Vydavatelstvi @inject*/
	public $vydavatelstvi;

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentCdForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addSelect('hudba_noty_kategorie_id', 'Kategorie:', $this->vydavatelstvi->seznamKategorii())
      ->setRequired('Prosím vyberte kategorii.')
      ->setPrompt('Zvolte kategorii');

    $form->addText('nazev', 'Název:')
      ->setRequired('Prosím zadejte název alba.')
      ->addRule(Form::MAX_LENGTH, 'Název alba musí mít maximálně %d znaků', 100);

    $form->addTextArea('popis', 'Popis:')
      ->addRule(Form::MAX_LENGTH, 'Popis musí mít maximálně %d znaků', 1000);

    $form->addText('cena', 'Cena:')
      ->setRequired('Prosím zadejte cenu skladby.')
      ->addRule(Form::INTEGER, 'Částka musí být číslo')
      ->addRule(Form::RANGE, 'Částka musí být od %d do %d Kč', array(1, 1000))
      ->setType('number');

    $form->addSelect('soubor_id', 'Demo:', $this->vydavatelstvi->demoSkladby())
      ->setPrompt('Zvolte demo mp3');

    $form->addUpload('foto', 'Foto s ukázkou:')
      ->addCondition(Form::FILLED)
      ->addRule(Form::IMAGE, 'Foto musí být JPEG, PNG nebo GIF.');

    $form->addSubmit('send', 'Uložit');

    $form->onSuccess[] = $this->cdFormSucceeded;

    return \Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentNakupForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addHidden('id');

    $form->addText('pocet', 'Počet kusů:')
      ->setRequired('Prosím zadejte počet kusů.')
      ->addRule(Form::INTEGER, 'Počet kusů musí být číslo')
      ->addRule(Form::RANGE, 'Počet kusů musí být od %d do %d', array(1, 10))
      ->setType('number');

    $form->addTextArea('adresa', 'Dodací adresa:')
      ->setRequired('Prosím zadejte dodací adresu.')
      ->addRule(Form::MAX_LENGTH, 'Dodací adresa musí mít maximálně %d znaků', 300);

    $form->addText('email', 'E-mail:')
      ->addRule(Form::MAX_LENGTH, 'E-mail musí mít maximálně %d znaků', 100)
      ->addCondition(Form::FILLED)
      ->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu');

    $form->addText('tel', 'Telefon:')
      ->addRule(Form::MAX_LENGTH, 'Telefonní číslo musí mít maximálně %d znaků', 20);

    $form['email']->addConditionOn($form['tel'], ~Form::FILLED)
      ->setRequired('Prosím zadejte kontaktní telefon nebo e-mail.');
    $form['tel']->addConditionOn($form['email'], ~Form::FILLED)
      ->setRequired('Prosím zadejte kontaktní telefon nebo e-mail.');

    $form->addText('pozn', 'Poznámka:')
      ->addRule(Form::MAX_LENGTH, 'Poznámka musí mít maximálně %d znaků', 300);

    $form->addText('antiSpam', 'Ochrana proti spamu:  Kolik je dvakrát tři? (výsledek napište číslem)', 30)
      ->setRequired('Vyplňte ochranu proti spamu.')
      ->addRule(Form::INTEGER, 'Špatně vyplněná ochrana proti spamu')
      ->addRule(Form::RANGE, 'Špatně vyplněná ochrana proti spamu', array(6, 6));

    $form->addSubmit('send', 'Odeslat objednávku');

    $form->onSuccess[] = $this->nakupFormSucceeded;

    return \Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentHledaniForm()
  {
    $form = new Nette\Application\UI\Form;

    foreach ($this->vydavatelstvi->seznamKategorii() as $id=>$kategorie) {
      $form->addCheckbox('kat' . $id, $kategorie);
    }

    $form->addSubmit('send', 'Zobrazit vybrané');

    $form->onSuccess[] = $this->hledaniFormSucceeded;

    return Bs3Form::transform($form);
  }

  public function cdFormSucceeded($form)
  {
    $values = $form->getValues();
    $notyId = $this->getParameter('id');
    $uploads = $this->context->getService('httpRequest')->getFiles();
    unset($values['foto']);

    if($notyId) { //editace
      try {
        $this->vydavatelstvi->update($notyId, $values);
      } catch (\Exception $e) {
        $this->flashMessage('Noty se nepodařilo uložit.', 'danger');
      }
    }
    else { //nova skladba
      try {
        $cd = $this->vydavatelstvi->insert($values);
        $notyId = $cd->id;
      } catch (\Exception $e) {
        $this->flashMessage('Noty se nepodařilo uložit.', 'danger');
      }
    }

    //presun uploadovanych souboru z tmp adresare do ciloveho umisteni
    $destDir = $this->context->parameters['wwwDir'] . '/img/data/vydavatelstvi';
    $nazev = '';
    foreach ($uploads as $soubor) {
      if($soubor && $soubor->isOk) {
        $ext = pathinfo($soubor->getName(), PATHINFO_EXTENSION );
        $nazev = 'noty-' . $notyId . '.' . $ext;
        $soubor->move($destDir . '/' . $nazev);
        $image = Image::fromFile($destDir . '/' . $nazev);
        $image->resize(1024, 1024);
        $image->save($destDir . '/' . $nazev);
      }
    }

    if($nazev) {
      try {
        $this->vydavatelstvi->update($notyId, array('foto'=>$nazev));
      } catch (\Exception $e) {
        $this->flashMessage('Nepodařilo se uložit foto.', 'danger');
      }
    }

    $this->flashMessage('Noty byly uloženy.' , 'success');
    $this->redirect('Vydavatelstvi:default');
  }


  public function nakupFormSucceeded($form)
  {
    $values = $form->getValues();

    $cd = $this->vydavatelstvi->findById($values['id']);
    if (!$cd) {
      $this->error('Požadované noty neexistují.');
    }

    $text = "Název: " . $cd->nazev . "\n";
    $text .= "Cena za kus: " . $cd->cena . " Kč\n\n";
    $text .= "Počet kusů: " . $values['pocet'] . "\n";
    $text .= "Adresa:\n" . $values['adresa'] . "\n";
    $text .= "Telefon: " . $values['tel'] . "\n";
    $text .= "Email: " . $values['email'] . "\n";
    $text .= "Poznámka:\n" . $values['pozn'] . "\n";


    $params = $this->context->parameters['hudba'];

    $mail = new Message;
    $mail->setFrom('Lubomír Piskoř <' . $params['adminMail'] . '>')
        ->addTo($params['adminMail'])
        ->setSubject('Hudební vydavatelství - objednávka not')
        ->setBody($text);
    $mailer = new SendmailMailer;
    $mailer->send($mail);


    $this->flashMessage('Objednávka byla odeslána.' , 'success');
    $this->redirect('Vydavatelstvi:detail', $cd->id);
  }

  public function hledaniFormSucceeded($form)
  {
    $values = $form->getValues();
    $this->redirect('Vydavatelstvi:default', array("filtr" => $values));
  }

	public function renderDefault()
	{
    $kategorieVse = $this->vydavatelstvi->kategorie();

    $filtr = $this->getParameter('filtr');
    $kategorie = array();
    if($filtr) {
      $this['hledaniForm']->setDefaults($filtr);
      foreach ($kategorieVse as $kat) {
        if($filtr['kat' . $kat->id]) $kategorie[] = $kat->id;
      }
    }

    $pocetNot = $this->vydavatelstvi->findAll($kategorie)->count();
    $vp = new \VisualPaginator($this, 'vp');
    $paginator = $vp->getPaginator();
    $paginator->itemsPerPage = 50;
    $paginator->itemCount = $pocetNot;

    $noty = $this->vydavatelstvi->findAll($kategorie, $paginator->getLength(), $paginator->getOffset());
    $this->template->noty = $noty;
    $this->template->kategorie = $kategorieVse;
	}


	public function renderDetail($id)
	{
    $noty = $this->vydavatelstvi->findById($id);
    if (!$noty) {
      $this->error('Požadované noty neexistují.');
    }
    $this->template->noty = $noty;
    $this['nakupForm']->setDefaults(array('id'=>$id));

    if($this->user->isInRole('admin')) {
      $this['cdForm']->setDefaults($noty);
    }
	}

	public function actionSmazat($id)
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $cd = $this->vydavatelstvi->findById($id);
    if (!$cd) {
      $this->error('Požadované noty neexistují.');
    }

    if($cd->foto) {
      $destDir = $this->context->parameters['wwwDir'] . '/img/data/vydavatelstvi';
      if(file_exists($destDir . '/' . $cd->foto)) unlink($destDir . '/' . $cd->foto);
    }

    $this->vydavatelstvi->smazat($id);
    $this->flashMessage('Noty byly smazány.' , 'success');
    $this->redirect('Vydavatelstvi:default');
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
    $soubor = $this->vydavatelstvi->nazevSouboru($id);
    if (!$soubor) {
      $this->error('Požadovaný soubor neexistuje.');
    }

    if(!$soubor->format->demo) {
      $this->error('Požadovaný soubor neexistuje.');
    }

    $this->sendResponse(new FileResponse($this->context->parameters['appDir'] . '/../data/midi' . '/skladba-' . $soubor->skladba_id . '-' . $soubor->format_id, $soubor->nazev));
	}
}
