<?php

use Nette\Forms\Form;


/**
 * Sign in/out presenters.
 */
class UcetPresenter extends BasePresenter
{

  /** @persistent */
  public $backlink = '';

  /** @var Uzivatel @inject */
  public $uzivatele;

  protected function beforeRender()
  {
      parent::beforeRender();
      $this->setLayout('midi');
  }

  /**
   * Sign-in form factory.
   * @return Nette\Application\UI\Form
   */
  protected function createComponentPrihlaseniForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('login', 'Jméno:')
      ->setRequired('Prosím zadejte vaše uživatelské jméno.');

    $form->addPassword('heslo', 'Heslo:')
      ->setRequired('Prosím zadejte vaše heslo.');

    $form->addSubmit('send', 'Přihlásit');

    $form->onSuccess[] = $this->prihlaseniFormSucceeded;

    return Bs3Form::transform($form);
  }


  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentZapomenuteHesloForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('email', 'E-mail:')
      ->setRequired('Prosím zadejte váš e-mail.')
      ->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu')
      ->addRule(Form::MAX_LENGTH, 'E-mail musí mít maximálně %d znaků', 100);

    $form->addSubmit('send', 'Odeslat');

    $form->onSuccess[] = $this->zapomenuteHesloFormSucceeded;

    return Bs3Form::transform($form);
  }


  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentUzivatelForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('login', 'Uživatelské jméno')
      ->setRequired('Prosím zadejte vaše uživatelské jméno.')
      ->addRule(Form::MIN_LENGTH, 'Jméno musí mít alespoň %d znaky', 3)
      ->addRule(Form::MAX_LENGTH, 'Jméno musí mít maximálně %d znaků', 100);


    $form->addPassword('heslo', 'Heslo:')
      ->setRequired('Prosím zadejte vaše heslo.')
      ->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 4)
      ->addRule(Form::MAX_LENGTH, 'Heslo musí mít maximálně %d znaků', 100);

    $form->addPassword('hesloKontrola', 'Heslo pro kontrolu:')
      ->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu')
      ->addRule(Form::EQUAL, 'Hesla se neshodují', $form['heslo']);

    $form->addText('email', 'E-mail:')
      ->setRequired('Prosím zadejte váš e-mail.')
      ->addRule(Form::EMAIL, 'Zadejte platnou e-mailovou adresu')
      ->addRule(Form::MAX_LENGTH, 'E-mail musí mít maximálně %d znaků', 100);

    $form->addText('antiSpam', 'Ochrana proti spamu:  Kolik je dvakrát tři? (výsledek napište číslem)', 30)
      ->setRequired('Vyplňte ochranu proti spamu.')
      ->addRule(Form::INTEGER, 'Špatně vyplněná ochrana proti spamu')
      ->addRule(Form::RANGE, 'Špatně vyplněná ochrana proti spamu', array(6, 6));

    $form->addSubmit('send', 'Dokončení registrace');

    $form->onSuccess[] = $this->uzivatelFormSucceeded;
    return Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentObnovaHeslaForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addPassword('heslo', 'Heslo:')
      ->setRequired('Prosím zadejte vaše heslo.')
      ->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 4)
      ->addRule(Form::MAX_LENGTH, 'Heslo musí mít maximálně %d znaků', 100);

    $form->addPassword('hesloKontrola', 'Heslo pro kontrolu:')
      ->setRequired('Zadejte prosím heslo ještě jednou pro kontrolu')
      ->addRule(Form::EQUAL, 'Hesla se neshodují', $form['heslo']);

    $form->addHidden('email');
    $form->addHidden('heslo_token');

    $form->addSubmit('send', 'Uložit');

    $form->onSuccess[] = $this->obnovaHeslaFormSucceeded;
    return Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentDobijeniForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('castka', 'Částka (Kč):')
      ->setRequired('Prosím zadejte částku, kterou chcete nabít.')
      ->addRule(Form::INTEGER, 'Částka musí být číslo')
      ->addRule(Form::RANGE, 'Částka musí být od %d do %d Kč', array(1, 1000))
      ->setType('number');

    $form->addSubmit('send', 'Nabít kredit');

    $form->onSuccess[] = $this->dobijeniFormSucceeded;

    return Bs3Form::transform($form);
  }


  public function prihlaseniFormSucceeded($form)
  {
    $values = $form->getValues();

    try {
      $this->getUser()->login($values->login, $values->heslo);
      $this->uzivatele->casPoslPrihlaseni($this->getUser()->getId());
      $this->restoreRequest($this->backlink);
      if($this->getUser()->isInRole('admin')) {
        $this->redirect('Admin:');
      }
      else {
        $this->redirect('Ucet:informace');
      }
    } catch (Nette\Security\AuthenticationException $e) {
      $form->addError($e->getMessage());
    }
  }


  public function uzivatelFormSucceeded($form)
  {
    $values = $form->getValues();
    $uzivId = $this->getParameter('id');
    unset($values['antiSpam']);

    if($uzivId) {
      unset($values['login']);
      unset($values['hesloKontrola']);

      $this->uzivatele->update($uzivId, $values);
      $this->flashMessage('Údaje byly uloženy.', 'success');
      $this->redirect('Ucet:informace');
    }
    else {
      try {
        $this->uzivatele->registrace($values->login, $values->heslo, $values->email);
        $this->flashMessage('Registrace byla úspěšná.', 'success');
      } catch (\PDOException $e) {
        $this->flashMessage('Registrace nebyla dokončena.', 'danger');

        if($e->getCode() == 23000) { //Integrity constraint violation
          if (strpos($e->getMessage(), "1062") !== FALSE) { //Duplicate entry
            if (strpos($e->getMessage(), 'login') !== FALSE) {
              $form->addError("Zadejte jiné uživatelské jméno.");
            }
            else if (strpos($e->getMessage(), 'email') !== FALSE) {
              $form->addError("Zadejte jiný email.");
            }
          }
        }
        $this->presenter->sendTemplate();
      }

      try {
        $this->getUser()->login($values->login, $values->heslo);
        $this->uzivatele->casPoslPrihlaseni($this->getUser()->getId());
      } catch (\Exception $e) {
        $this->flashMessage('Přihlášení se nepodařilo.', 'danger');
      }
      
      $this->redirect('Ucet:informace');
    }//else
  }

  public function obnovaHeslaFormSucceeded($form)
  {
    $values = $form->getValues();

    $uziv = $this->uzivatele->obnoveniHesla($values['email'], $values['heslo_token']);
    if (!$uziv) {
      $this->flashMessage('Neplatný požadavek na obnovení hesla.', 'danger');
      $this->redirect('Ucet:prihlaseni');
    }

    unset($values['login']);
    unset($values['email']);
    unset($values['hesloKontrola']);
    $values['heslo_token'] = null;
    $values['heslo_token_platnost'] = null;

    try {
      $this->uzivatele->update($uziv->id, $values);
    } catch (\Exception $e) {
      $this->flashMessage('Změna hesla neproběhla.', 'danger');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->flashMessage('Změna hesla byla úspěšná.', 'success');
    $this->redirect('Ucet:prihlaseni');
  }

  public function dobijeniFormSucceeded($form)
  {
    $values = $form->getValues();

    try {
      $row = $this->uzivatele->pridatPozadavekNaNabiti($this->user->id, $values->castka);
    } catch (\Exception $e) {
      $this->flashMessage('Došlo k chybě. Požadavek na nabití kreditu nebyl přijat.', 'danger');
      $this->redirect('Ucet:kredit');
    }
    BasePresenter::sendMail('dobiti.latte', $this->user->getIdentity()->data['email'], $row);
    $this->flashMessage('Váš požadavek na dobití kreditu byl přijat.', 'success');
    $this->redirect('Ucet:kreditDobiti');
  }


  public function zapomenuteHesloFormSucceeded($form)
  {
    $values = $form->getValues();
    $token = md5(uniqid(rand(), true));

    try {
      $row = $this->uzivatele->zapomenuteHeslo($values['email'], $token);
    } catch (\MidiException $e) {
      $this->flashMessage($e->getMessage(), 'danger');
      $this->redirect('Ucet:zapomenuteHeslo');
    } catch (\Exception $e) {
      $this->flashMessage('Změna hesla se nepodařila.', 'danger');
      $this->redirect('Ucet:zapomenuteHeslo');
    }
    BasePresenter::sendMail('zapomenuteHeslo.latte', $values['email'], array('token' => $token, 'email' => $values['email']));
    $this->flashMessage('E-mail se změnou hesla byl odeslán.', 'success');
    $this->redirect('Ucet:prihlaseni');
  }

  public function actionOdhlaseni()
  {
    $this->getUser()->logout(TRUE);
    $this->flashMessage('Odhlášení bylo úspěšné.', 'success');
    $this->redirect('prihlaseni');
  }

  public function renderInformace()
  {
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->template->uzivatel = $this->uzivatele->findById($this->user->id);
    $this->template->sumaNakupu = $this->uzivatele->sumaNakupu($this->user->id);
  }

  public function renderZmenaUdaju($id)
  {
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni');
    }

    //uzivatel muze menit pouze sve udaje
    if($id != $this->user->id) {
      $this->redirect('Ucet:zmenaUdaju', $this->user->id);
    }

    $arr = $this->uzivatele->findById($this->user->id)->toArray();
    $this['uzivatelForm']->getComponent('login')->setAttribute("readonly", "true");
    $this['uzivatelForm']->getComponent('send')->caption = "Uložit";
    $this['uzivatelForm']->setDefaults($arr);
  }

  public function renderKredit()
  {
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->template->historie = $this->uzivatele->historieDobijeni($this->user->id);
    $this->template->cisloUctu = $this->context->parameters['hudba']['cisloUctu'];
  }

  public function renderKreditDobiti()
  {
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->template->transakce = $this->uzivatele->posledniDobiti($this->user->id);
    $this->template->cisloUctu = $this->context->parameters['hudba']['cisloUctu'];
  }

  public function renderNakupy()
  {
    if (!$this->user->isLoggedIn()) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->template->nakupy = $this->uzivatele->zakoupeneSkladby($this->user->id);
  }

  public function renderObnoveniHesla($email, $token)
  {
    $uziv = $this->uzivatele->obnoveniHesla($email, $token);
    if (!$uziv) {
      $this->flashMessage('Neplatný požadavek na obnovení hesla.', 'danger');
      $this->redirect('Ucet:prihlaseni');
    }

    $this->template->uziv = $uziv;
    $this['obnovaHeslaForm']->setDefaults($uziv);
  }
}
