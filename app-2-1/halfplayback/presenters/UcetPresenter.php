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
      $this->setLayout('halfplayback');
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


  public function prihlaseniFormSucceeded($form)
  {
    $values = $form->getValues();

    try {
      $this->getUser()->login($values->login, $values->heslo);
      $this->uzivatele->casPoslPrihlaseni($this->getUser()->getId());
      $this->restoreRequest($this->backlink);
      // if($this->getUser()->isInRole('admin')) {
      //   $this->redirect('Admin:');
      // }
      // else {
      //   $this->redirect('Ucet:informace');
      // }
      $this->redirect('Ucet:informace');
    } catch (Nette\Security\AuthenticationException $e) {
      $form->addError($e->getMessage());
    }
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
}
