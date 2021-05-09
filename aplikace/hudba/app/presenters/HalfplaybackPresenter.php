<?php

use Nette\Application\Responses\FileResponse,
    Nette\Forms\Form,
    Nette\Mail\Message,
    Nette\Mail\SendmailMailer,
    Nette\Utils\Strings;

class HalfplaybackPresenter extends BasePresenter
{

	/** @var Vydavatelstvi @inject*/
	public $vydavatelstvi;
	/** @var Halfplayback @inject*/
	public $halfplayback;

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentSkladbaForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addSelect('hudba_hpback_kategorie_id', 'Kategorie:', $this->halfplayback->seznamKategorii())
      ->setRequired('Prosím vyberte kategorii.')
      ->setPrompt('Zvolte kategorii');

    $form->addText('nazev', 'Název:')
      ->setRequired('Prosím zadejte název skladby.')
      ->addRule(Form::MAX_LENGTH, 'Název skladby musí mít maximálně %d znaků', 100);

    $form->addTextArea('popis', 'Popis:')
      ->addRule(Form::MAX_LENGTH, 'Popis musí mít maximálně %d znaků', 1000);

    $form->addText('cena', 'Cena:')
      ->setRequired('Prosím zadejte cenu skladby.')
      ->addRule(Form::INTEGER, 'Částka musí být číslo')
      ->addRule(Form::RANGE, 'Částka musí být od %d do %d Kč', array(1, 1000))
      ->setType('number');

    $form->addUpload('soubor', 'Demo:');

    $form->addSubmit('send', 'Uložit');

    $form->onSuccess[] = $this->skladbaFormSucceeded;

    return \Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentNakupForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addHidden('id');

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

    foreach ($this->halfplayback->seznamKategorii() as $id=>$kategorie) {
      $form->addCheckbox('kat' . $id, $kategorie);
    }

    $form->addSubmit('send', 'Zobrazit vybrané');

    $form->onSuccess[] = $this->hledaniFormSucceeded;

    return Bs3Form::transform($form);
  }

  public function skladbaFormSucceeded($form)
  {
    $values = $form->getValues();
    $skladbaId = $this->getParameter('id');
    $uploads = $this->context->getService('httpRequest')->getFiles();
    unset($values['soubor']);

    if($skladbaId) { //editace
      try {
        $this->halfplayback->update($skladbaId, $values);
      } catch (\Exception $e) {
        $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
      }
    }
    else { //nova skladba
      try {
        $skladba = $this->halfplayback->insert($values);
        $skladbaId = $skladba->id;
      } catch (\Exception $e) {
        $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
      }
    }

    $this->flashMessage('Skladba byla uložena.' , 'success');

    //presun uploadovanych souboru z tmp adresare do ciloveho umisteni
    $destDir = $this->context->parameters['appDir'] . '/../data/halfplayback';
    $nazev = '';
    foreach ($uploads as $soubor) {
      if($soubor && $soubor->isOk) {
        $ext = pathinfo($soubor->getName(), PATHINFO_EXTENSION );
        $nazev = Strings::webalize($values['nazev']) . '.' . $ext;
        $soubor->move($destDir . '/skladba-' . $skladbaId);
      }
    }

    if($nazev) {
      try {
        $this->halfplayback->update($skladbaId, array('soubor'=>$nazev));
      } catch (\Exception $e) {
        $this->flashMessage('Nepodařilo se uložit demo.', 'danger');
      }
    }

    $this->redirect('Halfplayback:default');
  }


  public function nakupFormSucceeded($form)
  {
    $values = $form->getValues();

    $skladba = $this->halfplayback->findById($values['id']);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }

    $text = "Název: " . $skladba->nazev . "\n";
    $text .= "Cena: " . $skladba->cena . " Kč\n\n";
    $text .= "Adresa:\n" . $values['adresa'] . "\n";
    $text .= "Telefon: " . $values['tel'] . "\n";
    $text .= "Email: " . $values['email'] . "\n";
    $text .= "Poznámka:\n" . $values['pozn'] . "\n";


    $params = $this->context->parameters['hudba'];

    $mail = new Message;
    $mail->setFrom('Lubomír Piskoř <' . $params['adminMail'] . '>')
        ->addTo($params['adminMail'])
        ->addTo($params['hpbackMail'])
        ->setSubject('Halfplayback - objednávka skladby')
        ->setBody($text);
    $mailer = new SendmailMailer;
    $mailer->send($mail);


    $this->flashMessage('Objednávka byla odeslána.' , 'success');
    $this->redirect('Halfplayback:detail', $skladba->id);
  }

  public function hledaniFormSucceeded($form)
  {
    $values = $form->getValues();
    $this->redirect('Halfplayback:default', array("filtr" => $values));
  }

	public function renderDefault()
	{
    $seznamKategorii = $this->halfplayback->seznamKategorii();

    $filtr = $this->getParameter('filtr');
    $kategorie = array();
    if($filtr) {
      $this['hledaniForm']->setDefaults($filtr);
      foreach ($seznamKategorii as $id => $nazev) {
        if($filtr['kat' . $id]) $kategorie[] = $id;
      }
    }

    $pocetSkladeb = $this->halfplayback->findAll($kategorie)->count();
    $vp = new \VisualPaginator($this, 'vp');
    $paginator = $vp->getPaginator();
    $paginator->itemsPerPage = 50;
    $paginator->itemCount = $pocetSkladeb;

    $skladby = $this->halfplayback->findAll($kategorie, $paginator->getLength(), $paginator->getOffset());
    $this->template->skladby = $skladby;
    $this->template->seznamKategorii = $seznamKategorii;
	}

	public function renderDetail($id)
	{
    $skladba = $this->halfplayback->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }
    $this->template->skladba = $skladba;
    $this['nakupForm']->setDefaults(array('id'=>$id));

    if($this->user->isInRole('spravce')) {
      $this['skladbaForm']->setDefaults($skladba);
    }
	}

	public function actionSmazat($id)
	{
    if (!$this->user->isInRole('spravce')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $skladba = $this->halfplayback->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }

    if($skladba->soubor) {
      $destDir = $this->context->parameters['appDir'] . '/../data/halfplayback';
      $nazev = '/skladba-' . $skladba->id;
      if(file_exists($destDir . '/' . $nazev)) unlink($destDir . '/' . $nazev);
    }

    $this->halfplayback->smazat($id);
    $this->flashMessage('Skladba byla smazána.' , 'success');
    $this->redirect('Halfplayback:default');
	}

	public function actionPridat()
	{
    if (!$this->user->isInRole('spravce')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }
	}

	public function actionDownload($id)
	{
    $skladba = $this->halfplayback->findById($id);
    if (!$skladba) {
      $this->error('Požadovaná skladba neexistuje.');
    }

    $this->sendResponse(new FileResponse($this->context->parameters['appDir'] . '/../data/halfplayback' . '/skladba-' . $skladba->id, $skladba->soubor));
	}
}
