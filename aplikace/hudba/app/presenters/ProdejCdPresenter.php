<?php

use Nette\Forms\Form,
    Nette\Mail\Message,
    Nette\Mail\SendmailMailer;

class ProdejCdPresenter extends BasePresenter
{

	/** @var Cd @inject*/
	public $cd;


  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentNakupForm()
  {
    $form = new \Nette\Application\UI\Form;

    $form->addHidden('id');

    $platba = array(
        'dobirka' => 'Dobírkou',
        'prevod' => 'Převodem na účet',
    );
    $form->addRadioList('platba', 'Platba:', $platba)
         ->setRequired('Prosím vyberte způsob platby.');

    $form->addText('pocet', 'Počet kusů:')
      ->setRequired('Prosím zadejte počet kusů.')
      ->addRule(Form::INTEGER, 'Počet kusů musí být číslo')
      ->addRule(Form::RANGE, 'Počet kusů musí být od %d do %d', array(1, 10))
      ->setType('number');

    $form->addTextArea('adresa', 'Dodací adresa:')
      ->setRequired('Prosím zadejte dodací adresu.')
      ->addRule(Form::MAX_LENGTH, 'Název musí mít maximálně %d znaků', 300);

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

  public function nakupFormSucceeded($form)
  {
    $values = $form->getValues();

    $cd = $this->cd->findById($values['id']);
    if (!$cd) {
      $this->error('Požadované cd neexistuje.');
    }

    $text = "Název: " . $cd->nazev . "\n";
    $text .= "Cena za kus: " . $cd->cena . " Kč\n\n";
    $text .= "Platba: " . ($values['platba'] == "dobirka" ? "dobírkou" : "převodem") . "\n";
    $text .= "Počet kusů: " . $values['pocet'] . "\n";
    $text .= "Adresa:\n" . $values['adresa'] . "\n";
    $text .= "Telefon: " . $values['tel'] . "\n";
    $text .= "Email: " . $values['email'] . "\n";
    $text .= "Poznámka:\n" . $values['pozn'] . "\n";

    $params = $this->context->parameters['hudba'];

    $mail = new Message;
    $mail->setFrom('Lubomír Piskoř <' . $params['adminMail'] . '>')
        ->addTo($params['adminMail'])
        ->setSubject('Objednávka CD')
        ->setBody($text);
    $mailer = new SendmailMailer;
    $mailer->send($mail);

    $this->flashMessage('Objednávka byla odeslána.' , 'success');
    $this->redirect('ProdejCd:detail', $cd->id);
  }

	public function renderDefault()
	{
    $this->template->seznamCd = $this->cd->findAll();
	}

  public function renderDetail($id)
  {
    $cd = $this->cd->findById($id);
    if (!$cd) {
      $this->error('Požadované cd neexistuje.');
    }
    $this->template->cd = $cd;
    $this['nakupForm']->setDefaults(array('id'=>$id));
  }
}
