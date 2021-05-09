<?php
use Nette\Application\Responses\FileResponse,
    Nette\Utils\Validators,
    Nette\Forms\Form,
    Nette\Application\Responses\TextResponse;

/**
 * Admin presenter.
 */
class AdminPresenter extends BasePresenter
{

  /** @persistent */
  public $login;
  /** @persistent */
  public $email;
  /** @persistent */
  public $raditZ = 'login';
  /** @persistent */
  public $raditS = 'nazev';
  /** @persistent */
  public $asc = '1';

	/** @var Skladba @inject*/
	public $skladby;
	/** @var Uzivatel @inject*/
	public $uzivatele;

  public function startup()
  {
    parent::startup();
    if (!$this->user->isInRole('admin')) {
      $this->flashMessage('Pro vstup na požadovanou stránku se musíte přihlásit.');
      $this->redirect('Ucet:prihlaseni', array('backlink' => $this->storeRequest()));
    }
  }

  protected function beforeRender()
  {
      parent::beforeRender();
      $this->setLayout('midi');
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentKreditForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('castka', 'Částka (Kč):')
      ->setRequired('Prosím zadejte částku, kterou chcete odebrat.')
      ->addRule(Form::INTEGER, 'Částka musí být číslo')
      ->addRule(Form::RANGE, 'Částka musí být od %d do %d Kč', array(1, 1000))
      ->setType('number');

    $form->addHidden('uzivId');

    $form->addSubmit('send', 'Odebrat kredit');

    $form->onSuccess[] = $this->kreditFormSucceeded;

    return Bs3Form::transform($form);
  }

  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentObdobiForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('zacatek', 'Datum od:')
      ->setRequired('Prosím zadejte počáteční datum.');

    $form->addText('konec', 'Datum do:')
      ->setRequired('Prosím zadejte koncové datum.');

    $form->addSubmit('send', 'zobrazit');

    $form->onSuccess[] = $this->obdobiFormSucceeded;

    return Bs3Form::transform($form);
  }


  /**
   * @return Nette\Application\UI\Form
   */
  protected function createComponentHledaniForm()
  {
    $form = new Nette\Application\UI\Form;

    $form->addText('login', 'Login:')
      ->addRule(Form::MAX_LENGTH, 'Login může mít maximálně %d znaků', 100);

    $form->addText('email', 'Email:')
      ->addRule(Form::MAX_LENGTH, 'Email může mít maximálně %d znaků', 100);

    $form->addSubmit('send', 'Hledat');

    $form->onSuccess[] = $this->hledaniFormSucceeded;

    return Bs3Form::transform($form);
  }


  public function kreditFormSucceeded($form)
  {
    $values = $form->getValues();

    $uziv = $this->uzivatele->findById($values->uzivId);
    if (!$uziv) {
      $this->error('Požadovaný uživatel neexistuje.');
    }

    try {
      $row = $this->uzivatele->odebratKredit($uziv, $values->castka);
      $this->flashMessage('Kredit ve výši ' . $values->castka . ' Kč byl odebrán.', 'success');
      $this->redirect('Admin:zakaznikDetail', $uziv->id);

    } catch (\Exception $e) {
      $this->flashMessage($e->getMessage(), 'danger');
    }
  }

  public function obdobiFormSucceeded($form)
  {
    $values = $form->getValues();
    $this->redirect('Admin:stahovani', $values['zacatek'], $values['konec']);
  }


  public function hledaniFormSucceeded($form)
  {
    $values = $form->getValues();
    $params = array('login' => $values['login'], 'email' => $values['email']);
    if(!$params['login']) $params['login'] = null;
    if(!$params['email']) $params['email'] = null;
    $this->redirect('Admin:zakaznici', $params);
  }


	public function renderDefault()
	{
		$this->template->pocetZakazniku = $this->uzivatele->vsichniZakaznici()->count();
		$this->template->nabitoKreditu = $this->uzivatele->nabitoKreditu();
		$this->template->pocetSkladeb = $this->skladby->pocetSkladeb();
		$this->template->prumCena = $this->skladby->prumernaCena();
		$this->template->stazenoSkladeb = $this->skladby->stazenoSkladeb();
		$this->template->nakoupenoZa = $this->skladby->celkemNakoupenoZa();
		$this->template->cekajiciNaDobiti = $this->uzivatele->cekajiciNaDobiti();
	}

	public function actionPripsatKredit($id)
	{
    $trans = $this->uzivatele->cekajiciNaDobiti()->get($id);
    if (!$trans) {
      $this->error('Požadovaná transakce neexistuje.');
    }
    $this->uzivatele->pripsatKredit($trans);
    BasePresenter::sendMail('navyseni.latte', $trans->uzivatel->email, $trans);
    $this->flashMessage('Kredit ve výši ' . $trans->castka . ' Kč byl připsán.', 'success');
    $this->redirect('default');
	}

	public function actionZrusitDobiti($id)
	{
    $trans = $this->uzivatele->cekajiciNaDobiti()->get($id);
    if (!$trans) {
      $this->error('Požadovaná transakce neexistuje.');
    }

    $this->uzivatele->zrusitPozadavekNaNabiti($id);
    BasePresenter::sendMail('storno.latte', $trans->uzivatel->email, $trans);
    $this->flashMessage('Požadavek na nabití částky ' . $trans->castka . ' Kč byl odebrán.', 'success');
    $this->redirect('default');
	}

	public function actionOdebratKredit($uzivId, $castka = 0)
	{
    $uziv = $this->uzivatele->findById($uzivId);
    if (!$uziv) {
      $this->error('Požadovaný uživatel neexistuje.');
    }

    if(!Validators::is($castka, 'int:0..1000')) {
      $this->flashMessage('Chybně zadaná částka: ' . $castka, 'warning');
      $this->redirect('default');
    }

    if($uziv->kredit - $castka < 0) {
      $this->flashMessage('Zadána příliš vysoká částka - kredit by byl záporný', 'warning');
      $this->redirect('default');
    }
    $this->uzivatele->odebratKredit($uziv, $castka);
    $this->flashMessage('Kredit ve výši ' . $castka . ' Kč byl odebrán.', 'success');
    $this->redirect('default');
	}

	public function renderZakaznici()
	{
    $filtry['login'] = $this->getParameter('login');
    $filtry['email'] = $this->getParameter('email');
    $this['hledaniForm']->setDefaults($filtry);
    $razeni['sloupec'] = $this->getParameter('raditZ');
    $razeni['smer'] = $this->getParameter('asc') ? 'ASC' : 'DESC';

    $pocetZakazniku = $this->uzivatele->pocetZakazniku($filtry, $razeni);
    $vp = new VisualPaginator($this, 'vp');
    $paginator = $vp->getPaginator();
    $paginator->itemsPerPage = 50;
    $paginator->itemCount = $pocetZakazniku;

    $this->template->zakaznici = $this->uzivatele->findAllDetails($filtry, $razeni, $paginator->getLength(), $paginator->getOffset());
    $this->template->razeniSloupec = $razeni['sloupec'];
    $this->template->razeniAsc = $this->getParameter('asc');
	}

	public function renderZakaznikDetail($id)
	{
    $uzivatel = $this->uzivatele->findById($id);
    if (!$uzivatel) {
      $this->error('Požadovaný uživatel neexistuje.');
    }
    $this->template->uzivatel = $uzivatel;
    $this->template->sumaNakupu = $this->uzivatele->sumaNakupu($id);
    $this->template->historie = $this->uzivatele->historieDobijeni($id);
    $this->template->nakupy = $this->uzivatele->zakoupeneSkladby($id);
    $this['kreditForm']->setDefaults(array('uzivId' => $id));
	}

	public function renderStahovani($zacatek = null, $konec = null)
	{
    if(!$zacatek) $zacatek = '01.01.2008';
    if(!$konec) $konec = date('d.m.Y');
    $this['obdobiForm']->setDefaults(array('zacatek'=>$zacatek, 'konec'=>$konec));
    $razeni['sloupec'] = $this->getParameter('raditS');
    $razeni['smer'] = $this->getParameter('asc') ? 'ASC' : 'DESC';

    $pocetSkladeb = $this->skladby->prehledStahovani($this->dmyToYmd($zacatek), $this->dmyToYmd($konec))->count();
    $vp = new VisualPaginator($this, 'vp');
    $paginator = $vp->getPaginator();
    $paginator->itemsPerPage = 50;
    $paginator->itemCount = $pocetSkladeb;

    $this->template->nakupy = $this->skladby->prehledStahovani($this->dmyToYmd($zacatek), $this->dmyToYmd($konec), $razeni, $paginator->getLength(), $paginator->getOffset());
    $this->template->zacatek = $zacatek;
    $this->template->konec = $konec;
    $this->template->razeniSloupec = $razeni['sloupec'];
    $this->template->razeniAsc = $this->getParameter('asc');
	}

	private function dmyToYmd($text)
	{
    $arr = explode('.', $text);
    if(count($arr) != 3) return '';
    return $arr[2] . '-' . $arr[1] . '-' . $arr[0];
	}

	public function actionStahovaniDownload($zacatek, $konec)
	{
    $nakupy = $this->skladby->prehledStahovani($this->dmyToYmd($zacatek), $this->dmyToYmd($konec), array('sloupec' => 'nazev', 'smer' => 'ASC'));

    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', 'Název')
                ->setCellValue('B1', 'Interpret')
                ->setCellValue('C1', 'Cena')
                ->setCellValue('D1', 'Počet stažení');

    $radek = 2;
    foreach ($nakupy as $nakup) {
      $objPHPExcel->setActiveSheetIndex(0)
                  ->setCellValue('A' . $radek, $nakup->skladba->nazev)
                  ->setCellValue('B' . $radek, $nakup->skladba->autor)
                  ->setCellValue('C' . $radek, $nakup->skladba->cena)
                  ->setCellValue('D' . $radek, $nakup->pocet);
      $radek++;
    }

    $soubor = $this->context->parameters['appDir'] . '/../data/stahovani.xls';
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save($soubor);
    $this->sendResponse(new FileResponse($soubor));
	}
}
