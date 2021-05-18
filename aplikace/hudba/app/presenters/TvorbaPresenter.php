<?php

use Nette\Forms\Form;

class TvorbaPresenter extends BasePresenter
{

	/** @var Tvorba @inject*/
	public $tvorba;

    public function renderDefault()
    {
        $this->template->seznam = $this->tvorba->findAll();

        $count = $this->tvorba->findAll()->count();
        $vp = new \VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = 50;
        $paginator->itemCount = $count;

        $items = $this->tvorba->findAll($paginator->getLength(), $paginator->getOffset());
        $this->template->items = $items;
    }

    public function renderDetail($id)
    {
        $skladba = $this->tvorba->findById($id);
        if (!$skladba) {
          $this->error('Požadovaná skladba neexistuje.');
        }
        $this->template->skladba = $skladba;

        if($this->user->isInRole('admin')) {
          $this['skladbaForm']->setDefaults($skladba);
        }
    }

    /**
    * @return Nette\Application\UI\Form
    */
    protected function createComponentSkladbaForm()
    {
        $form = new \Nette\Application\UI\Form;

        $form->addText('nazev', 'Název:')
          ->setRequired('Prosím zadejte název skladby.')
          ->addRule(Form::MAX_LENGTH, 'Název skladby musí mít maximálně %d znaků', 100);

        $form->addText('url', 'Odkaz:')
          ->setRequired('Prosím zadejte odkaz na skladbu.')
          ->addRule(Form::MAX_LENGTH, 'Odkaz na skladbu musí mít maximálně %d znaků', 500);

        $form->addText('interpret', 'Interpret:');

        $form->addText('hudba', 'Autor hudby:');

        $form->addText('text', 'Autor textu:');

        $form->addTextArea('popis', 'Popis:')
          ->addRule(Form::MAX_LENGTH, 'Popis musí mít maximálně %d znaků', 1000);

        $form->addSubmit('send', 'Uložit');

        $form->onSuccess[] = $this->skladbaFormSucceeded;

        return \Bs3Form::transform($form);
    }

    public function skladbaFormSucceeded($form)
    {
        $values = $form->getValues();
        $skladbaId = $this->getParameter('id');


        if($skladbaId) { //editace
          try {
            $this->tvorba->update($skladbaId, $values);
          } catch (\Exception $e) {
            $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
          }
        }
        else { //nova skladba
          try {
            $skladba = $this->tvorba->insert($values);
            $skladbaId = $skladba->id;
          } catch (\Exception $e) {
            $this->flashMessage('Skladbu se nepodařilo uložit.', 'danger');
          }
        }

        $this->flashMessage('Skladba byla uložena.' , 'success');
        $this->redirect('Tvorba:default');
    }

    public function actionSmazat($id)
    {
        if (!$this->user->isInRole('admin')) {
          $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
          $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
        }

        $skladba = $this->tvorba->findById($id);
        if (!$skladba) {
          $this->error('Požadovaná sklaba neexistuje.');
        }

        $this->tvorba->smazat($id);
        $this->flashMessage('Skladba byly smazána.' , 'success');
        $this->redirect('Tvorba:default');
    }
}
