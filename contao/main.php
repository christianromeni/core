<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Initialize the system
 */
define('TL_MODE', 'BE');
require_once '../system/initialize.php';


/**
 * Class Main
 *
 * Main back end controller.
 * @copyright  Leo Feyer 2005-2013
 * @author     Leo Feyer <https://contao.org>
 * @package    Core
 */
class Main extends Backend
{

	/**
	 * Current Ajax object
	 * @var object
	 */
	protected $objAjax;


	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		// Redirect to the install tool
		if (!Config::getInstance()->isComplete())
		{
			$this->redirect('install.php');
		}

		$this->import('BackendUser', 'User');
		parent::__construct();

		$this->User->authenticate();

		// Password change required
		if ($this->User->pwChange)
		{
			$objSession = $this->Database->prepare("SELECT su FROM tl_session WHERE sessionID=? AND pid=?")
										 ->execute(session_id(), $this->User->id);

			if (!$objSession->su)
			{
				$this->redirect('contao/password.php');
			}
		}

		// Front end redirect
		if (Input::get('do') == 'feRedirect')
		{
			$this->redirectToFrontendPage(Input::get('page'), Input::get('article'));
		}

		// Convenience functions
		if ($this->User->isAdmin)
		{
			// Safe mode off
			if (Input::get('smo'))
			{
				$this->Config->update("\$GLOBALS['TL_CONFIG']['coreOnlyMode']", false);
				$this->redirect($this->getReferer());
			}

			// Build internal cache
			if (Input::get('bic'))
			{
				$this->import('Automator');
				$this->Automator->generateInternalCache();
				$this->redirect($this->getReferer());
			}
		}

		System::loadLanguageFile('default');
		System::loadLanguageFile('modules');
	}


	/**
	 * Run the controller and parse the login template
	 */
	public function run()
	{
		$this->Template = new BackendTemplate('be_main');
		$this->Template->main = '';

		// Ajax request
		if ($_POST && Environment::get('isAjaxRequest'))
		{
			$this->objAjax = new Ajax(Input::post('action'));
			$this->objAjax->executePreActions();
		}

		// Error
		if (Input::get('act') == 'error')
		{
			$this->Template->error = $GLOBALS['TL_LANG']['ERR']['general'];
			$this->Template->title = $GLOBALS['TL_LANG']['ERR']['general'];
		}
		// Welcome screen
		elseif (!Input::get('do') && !Input::get('act'))
		{
			$this->Template->main .= $this->welcomeScreen();
			$this->Template->title = $GLOBALS['TL_LANG']['MSC']['home'];
		}
		// Open a module
		elseif (Input::get('do'))
		{
			$this->Template->main .= $this->getBackendModule(Input::get('do'));
			$this->Template->title = $this->Template->headline;
		}

		$this->output();
	}


	/**
	 * Add the welcome screen
	 * @return string
	 */
	protected function welcomeScreen()
	{
		System::loadLanguageFile('explain');

		$objTemplate = new BackendTemplate('be_welcome');
		$objTemplate->messages = Message::generate(false, true);

		// HOOK: add custom messages
		if (isset($GLOBALS['TL_HOOKS']['getSystemMessages']) && is_array($GLOBALS['TL_HOOKS']['getSystemMessages']))
		{
			$arrMessages = array();

			foreach ($GLOBALS['TL_HOOKS']['getSystemMessages'] as $callback)
			{
				$this->import($callback[0]);
				$strBuffer = $this->$callback[0]->$callback[1]();

				if ($strBuffer != '')
				{
					$arrMessages[] = $strBuffer;
				}
			}

			if (!empty($arrMessages))
			{
				$objTemplate->messages .= "\n" . implode("\n", $arrMessages);
			}
		}

		// Add the versions overview
		Versions::addToTemplate($objTemplate);

		$objTemplate->welcome = sprintf($GLOBALS['TL_LANG']['MSC']['welcomeTo'], $GLOBALS['TL_CONFIG']['websiteTitle']);
		$objTemplate->showDifferences = specialchars(str_replace("'", "\\'", $GLOBALS['TL_LANG']['MSC']['showDifferences']));
		$objTemplate->systemMessages = $GLOBALS['TL_LANG']['MSC']['systemMessages'];
		$objTemplate->shortcuts = $GLOBALS['TL_LANG']['MSC']['shortcuts'][0];
		$objTemplate->shortcutsLink = $GLOBALS['TL_LANG']['MSC']['shortcuts'][1];
		$objTemplate->editElement = specialchars($GLOBALS['TL_LANG']['MSC']['editElement']);

		return $objTemplate->parse();
	}


	/**
	 * Output the template file
	 */
	protected function output()
	{
		// Default headline
		if ($this->Template->headline == '')
		{
			$this->Template->headline = $GLOBALS['TL_CONFIG']['websiteTitle'];
		}

		// Default title
		if ($this->Template->title == '')
		{
			$this->Template->title = $this->Template->headline;
		}

		$this->Template->theme = Backend::getTheme();
		$this->Template->base = Environment::get('base');
		$this->Template->language = $GLOBALS['TL_LANGUAGE'];
		$this->Template->title = specialchars($this->Template->title);
		$this->Template->charset = $GLOBALS['TL_CONFIG']['characterSet'];
		$this->Template->account = $GLOBALS['TL_LANG']['MOD']['login'][1];
		$this->Template->preview = $GLOBALS['TL_LANG']['MSC']['fePreview'];
		$this->Template->previewTitle = specialchars($GLOBALS['TL_LANG']['MSC']['fePreviewTitle']);
		$this->Template->pageOffset = Input::cookie('BE_PAGE_OFFSET');
		$this->Template->logout = $GLOBALS['TL_LANG']['MSC']['logoutBT'];
		$this->Template->logoutTitle = specialchars($GLOBALS['TL_LANG']['MSC']['logoutBTTitle']);
		$this->Template->backendModules = $GLOBALS['TL_LANG']['MSC']['backendModules'];
		$this->Template->username = $GLOBALS['TL_LANG']['MSC']['user'] . ' ' . $GLOBALS['TL_USERNAME'];
		$this->Template->skipNavigation = specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']);
		$this->Template->request = ampersand(Environment::get('request'));
		$this->Template->top = $GLOBALS['TL_LANG']['MSC']['backToTop'];
		$this->Template->modules = $this->User->navigation();
		$this->Template->home = $GLOBALS['TL_LANG']['MSC']['home'];
		$this->Template->homeTitle = $GLOBALS['TL_LANG']['MSC']['homeTitle'];
		$this->Template->backToTop = specialchars($GLOBALS['TL_LANG']['MSC']['backToTopTitle']);
		$this->Template->expandNode = $GLOBALS['TL_LANG']['MSC']['expandNode'];
		$this->Template->collapseNode = $GLOBALS['TL_LANG']['MSC']['collapseNode'];
		$this->Template->loadingData = $GLOBALS['TL_LANG']['MSC']['loadingData'];
		$this->Template->loadFonts = $GLOBALS['TL_CONFIG']['loadGoogleFonts'];
		$this->Template->isAdmin = $this->User->isAdmin;
		$this->Template->isCoreOnlyMode = $GLOBALS['TL_CONFIG']['coreOnlyMode'];
		$this->Template->coreOnlyMode = $GLOBALS['TL_LANG']['MSC']['coreOnlyMode'];
		$this->Template->coreOnlyOff = specialchars($GLOBALS['TL_LANG']['MSC']['coreOnlyOff']);
		$this->Template->coreOnlyHref = $this->addToUrl('smo=1');
		$this->Template->needsCacheBuild = (!$GLOBALS['TL_CONFIG']['bypassCache'] && !is_dir(TL_ROOT . '/system/cache/dca'));
		$this->Template->buildCacheHref = $this->addToUrl('bic=1');
		$this->Template->buildCacheLink = $GLOBALS['TL_LANG']['MSC']['buildCacheLink'];
		$this->Template->buildCacheText = $GLOBALS['TL_LANG']['MSC']['buildCacheText'];
		$this->Template->isPopup = Input::get('popup');

		// Front end preview links
		if (defined('CURRENT_ID') && CURRENT_ID != '')
		{
			// Pages
			if (Input::get('do') == 'page')
			{
				$this->Template->frontendFile = '?page=' . CURRENT_ID;
			}

			// Articles
			elseif (Input::get('do') == 'article')
			{
				if (($objArticle = ArticleModel::findByPk(CURRENT_ID)) !== null)
				{
					$this->Template->frontendFile = '?page=' . $objArticle->pid;
				}
			}
		}

		$this->Template->output();
	}
}


/**
 * Instantiate the controller
 */
$objMain = new Main();
$objMain->run();
