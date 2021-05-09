<?php

use Nette\Forms\Form,
    Nette\Image,
    Nette\Mail\Message,
    Nette\Mail\SendmailMailer;

/**
 * Homepage presenter.
 */
class AgenturaPresenter extends BasePresenter
{

	/** @var Agentura @inject*/
	public $agentura;

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentPoptavkaForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addText('nazev', 'Název:')
      ->setRequired('Prosím zadejte název kapely.')
      ->addRule(Form::MAX_LENGTH, 'Název kapely musí mít maximálně %d znaků', 100);

    $form->addTextArea('popis', 'Popis:')
      ->setRequired('Prosím zadejte popis kapely.')
      ->addRule(Form::MAX_LENGTH, 'Kontaktní údaje musí mít maximálně %d znaků', 1000);

    $form->addTextArea('kontakt', 'Kontaktní údaje:')
      ->setRequired('Prosím zadejte kontaktní údaje inzerátu.')
      ->addRule(Form::MAX_LENGTH, 'Kontaktní údaje musí mít maximálně %d znaků', 200);


    $form->addText('www', 'Webové stránky:')
      ->addRule(Form::MAX_LENGTH, 'Webové stránky musí mít maximálně %d znaků', 200);

    $form->addRadioList('zastupovat', 'Požadujete zastupování:', array('1'=>'ano','0'=>'ne'));

    $form->addText('antiSpam', 'Ochrana proti spamu:  Kolik je dvakrát tři? (výsledek napište číslem)', 30)
      ->setRequired('Vyplňte ochranu proti spamu.')
      ->addRule(Form::INTEGER, 'Špatně vyplněná ochrana proti spamu')
      ->addRule(Form::RANGE, 'Špatně vyplněná ochrana proti spamu', array(6, 6));

    $form->addSubmit('send', 'Odeslat');

    $form->onSuccess[] = $this->poptavkaFormSucceeded;

    return \Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentKapelaForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addText('nazev', 'Název:')
      ->setRequired('Prosím zadejte název kapely.')
      ->addRule(Form::MAX_LENGTH, 'Název kapely musí mít maximálně %d znaků', 100);

    $form->addTextArea('popis', 'Popis:')
      ->setRequired('Prosím zadejte popis kapely.')
      ->addRule(Form::MAX_LENGTH, 'Kontaktní údaje musí mít maximálně %d znaků', 1000);


    $form->addText('www', 'Webové stránky:')
      ->addRule(Form::MAX_LENGTH, 'Webové stránky musí mít maximálně %d znaků', 200);

    $form->addUpload('foto', 'Foto:')
      ->addCondition(Form::FILLED)
      ->addRule(Form::IMAGE, 'Foto musí být JPEG, PNG nebo GIF.');

    $form->addSubmit('send', 'Odeslat');

    $form->onSuccess[] = $this->kapelaFormSucceeded;

    return \Bs3Form::transform($form);
  }



  public function poptavkaFormSucceeded($form)
  {
    $values = $form->getValues();

    $text = "Název:\n" . $values['nazev'] . "\n";
    $text .= "Popis:\n" . $values['popis'] . "\n";
    $text .= "Kontakt:\n" . $values['kontakt'] . "\n";
    $text .= "Web:\n" . $values['www'] . "\n";
    $text .= "Zastupovat:\n";
    $text .= $values['popis'] == "1" ? "ano" : "ne" . "\n";

    $params = $this->context->parameters['hudba'];

    $mail = new Message;
    $mail->setFrom('Lubomír Piskoř <' . $params['adminMail'] . '>')
        ->addTo($params['adminMail'])
        ->setSubject('Hudební agentura - poptávka zveřejnění kapely')
        ->setBody($text);
    $mailer = new SendmailMailer;
    $mailer->send($mail);


    $this->flashMessage('Objednávka byla odeslána.' , 'success');
    $this->redirect('Agentura:default');
  }

  public function kapelaFormSucceeded($form)
  {
    $values = $form->getValues();
    $kapelaId = $this->getParameter('id');
    $uploads = $this->context->getService('httpRequest')->getFiles();
    unset($values['foto']);

    if($kapelaId) { //editace
      try {
        $this->agentura->update($kapelaId, $values);
      } catch (\Exception $e) {
        $this->flashMessage('kapelu se nepodařilo uložit.', 'danger');
      }
    }
    else { //nova kapela
      try {
        $kapela = $this->agentura->insert($values);
        $kapelaId = $kapela->id;
      } catch (\Exception $e) {
        $this->flashMessage('Kapelu se nepodařilo uložit.', 'danger');
      }
    }

    //presun uploadovanych souboru z tmp adresare do ciloveho umisteni
    $destDir = $this->context->parameters['wwwDir'] . '/img/data/agentura';
    $nazev = '';
    foreach ($uploads as $soubor) {
      if($soubor && $soubor->isOk) {
        $ext = pathinfo($soubor->getName(), PATHINFO_EXTENSION );
        $nazev = 'kapela-' . $kapelaId . '.' . $ext;
        $soubor->move($destDir . '/' . $nazev);
        $image = Image::fromFile($destDir . '/' . $nazev);
        $image->resize(1024, 1024);
        $image->save($destDir . '/' . $nazev);
        $image->resize(150, 150);
        $image->save($destDir . '/thumb-' . $nazev);
      }
    }

    if($nazev) {
      try {
        $this->agentura->update($kapelaId, array('foto'=>$nazev));
      } catch (\Exception $e) {
        $this->flashMessage('Nepodařilo se uložit foto.', 'danger');
      }
    }

    $this->flashMessage('Kapela byla uložena.' , 'success');
    $this->redirect('Agentura:default');
  }

	public function renderDefault()
	{
    $kapely = $this->agentura->findAll();
    $this->template->kapely = $kapely;
	}

	public function renderDetail($id)
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $kapela = $this->agentura->findById($id);
    if (!$kapela) {
      $this->error('Požadovaná kapela neexistuje.');
    }
    $this->template->kapela = $kapela;

    $this['kapelaForm']->setDefaults($kapela);
	}

	public function actionSmazat($id)
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }

    $kapela = $this->agentura->findById($id);
    if (!$kapela) {
      $this->error('Požadovaná kapela neexistuje.');
    }

    if($kapela->foto) {
      $destDir = $this->context->parameters['wwwDir'] . '/img/data/agentura';
      if(file_exists($destDir . '/' . $kapela->foto)) unlink($destDir . '/' . $kapela->foto);
      if(file_exists($destDir . '/thumb-' . $kapela->foto)) unlink($destDir . '/thumb-' . $kapela->foto);
    }

    $this->agentura->smazat($id);
    $this->flashMessage('Kapela byla smazána.', 'success');
    $this->redirect('Agentura:default');
	}

	public function actionPridat()
	{
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }
	}
}
