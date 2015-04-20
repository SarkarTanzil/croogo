<?php

namespace Croogo\Croogo\Controller;

use Cake\Controller\Exception\MissingComponentException;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Utility\Hash;

use Croogo\Croogo\Croogo;
use Croogo\Croogo\PropertyHookTrait;

/**
 * Croogo App Controller
 *
 * @category Croogo.Controller
 * @package  Croogo.Croogo.Controller
 * @version  1.5
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class CroogoAppController extends Controller {

	use PropertyHookTrait;

/**
 * Default Components
 *
 * @var array
 * @access public
 */
	protected $_defaultComponents = array(
		'Croogo.Croogo',
		'Security',
		'Acl.Acl',
		'Auth',
		'Session',
		'RequestHandler',
	);

/**
 * List of registered Application Components
 *
 * These components are typically hooked into the application during bootstrap.
 * @see Croogo::hookComponent
 */
	protected $_appComponents = array();

/**
 * List of registered API Components
 *
 * These components are typically hooked into the application during bootstrap.
 * @see Croogo::hookApiComponent
 */
	protected $_apiComponents = array();

/**
 * Helpers
 *
 * @var array
 * @access public
 */
	public $helpers = array(
		'Html',
		'Form',
		'Session',
		'Text',
		'Time',
		'Croogo/Croogo.Layout',
		'Croogo/Croogo.Custom',
	);

/**
 * Pagination
 */
	public $paginate = array(
		'limit' => 10,
	);

/**
 * Cache pagination results
 *
 * @var boolean
 * @access public
 */
	public $usePaginationCache = true;

/**
 * View
 *
 * @var string
 * @access public
 */
//	public $viewClass = 'Theme';

/**
 * Theme
 *
 * @var string
 * @access public
 */
	public $theme;

/**
 * Constructor
 *
 * @access public
 */
	public function __construct($request = null, $response = null, $name = null) {
		parent::__construct($request, $response, $name);
		if ($request) {
			$request->addDetector('api', array(
				'callback' => array('CroogoRouter', 'isApiRequest'),
			));
			$request->addDetector('whitelisted', array(
				'callback' => array('CroogoRouter', 'isWhitelistedRequest'),
			));
		}
		$this->eventManager()->dispatch(new Event('Controller.afterConstruct', $this));
		$this->afterConstruct();
	}

	public function initialize() {
		parent::initialize();

		$this->loadComponent('Auth');
		$this->loadComponent('Croogo/Acl.AclFilter');
	}


	/**
 * implementedEvents
 */
	public function implementedEvents() {
		return parent::implementedEvents() + array(
			'Controller.afterConstruct' => 'afterConstruct',
		);
	}

/**
 * afterConstruct
 *
 * called when Controller::__construct() is complete.
 * Override this method to perform class configuration/initialization that
 * needs to be performed earlier from Controller::beforeFilter().
 *
 * You still need to call parent::afterConstruct() method to ensure correct
 * behavior.
 */
	public function afterConstruct() {
		Croogo::applyHookProperties('Hook.controller_properties', $this);
		$this->_setupComponents();
		if (isset($this->request->params['admin'])) {
			$this->helpers[] = 'Croogo.Croogo';
			if (empty($this->helpers['Html'])) {
				$this->helpers['Html'] = array('className' => 'Croogo.CroogoHtml');
			}
			if (empty($this->helpers['Form'])) {
				$this->helpers['Form'] = array('className' => 'Croogo.CroogoForm');
			}
			if (empty($this->helpers['Paginator'])) {
				$this->helpers['Paginator'] = array('className' => 'Croogo.CroogoPaginator');
			}
		}
	}

/**
 * Setup the components array
 *
 * @param void
 * @return void
 */
	protected function _setupComponents() {
		if ($this->request && !$this->request->is('api')) {
			$this->components = Hash::merge(
				$this->_defaultComponents,
				$this->_appComponents,
				$this->components
			);
			return;
		}

		$this->components = Hash::merge(
			$this->components,
			array('Acl.Acl', 'Auth', 'Security', 'Session', 'RequestHandler', 'Acl.AclFilter'),
			$this->_apiComponents
		);
		$apiComponents = array();
		$priority = 8;
		foreach ($this->_apiComponents as $component => $setting) {
			if (is_string($setting)) {
				$component = $setting;
				$setting = array();
			}
			$className = $component;
			list(, $apiComponent) = pluginSplit($component);
			$setting = Hash::merge(compact('className', 'priority'), $setting);
			$apiComponents[$apiComponent] = $setting;
		}
		$this->_apiComponents = $apiComponents;
	}

/**
 * Allows extending action from component
 *
 * @throws MissingActionException
 */
	public function invokeAction() {
		$request = $this->request;
		try {
			return parent::invokeAction($request);
		} catch (MissingActionException $e) {
			$params = $request->params;
			$prefix = isset($params['prefix']) ? $params['prefix'] : '';
			$action = str_replace($prefix . '_', '', $params['action']);
			foreach ($this->_apiComponents as $component => $setting) {
				if (empty($this->{$component})) {
					continue;
				}
				if ($this->{$component}->isValidAction($action)) {
					$this->setRequest($request);
					return $this->{$component}->{$action}($this);
				}
			}
			throw $e;
		}
	}

/**
 * beforeFilter
 *
 * @return void
 * @throws MissingComponentException
 */
	public function beforeFilter(Event $event) {
		parent::beforeFilter($event);
		$aclFilterComponent = Configure::read('Site.acl_plugin') . 'Filter';
		if (empty($this->{$aclFilterComponent})) {
			throw new MissingComponentException(array('class' => $aclFilterComponent));
		}
		$this->{$aclFilterComponent}->auth();

		if (!$this->request->is('api')) {
			$this->Security->blackHoleCallback = 'securityError';
			$this->Security->requirePost('admin_delete');
		}

		if ($this->request->param('prefix') === 'admin') {
			$this->layout = 'Croogo/Croogo.admin';
		}

		if ($this->request->is('ajax')) {
			$this->layout = 'ajax';
		}

		if (Configure::read('Site.theme') && $this->request->param('prefix') !== 'admin') {
			$this->theme = Configure::read('Site.theme');
		} elseif (Configure::read('Site.admin_theme') && $this->request->param('prefix') === 'admin') {
			$this->theme = Configure::read('Site.admin_theme');
		}

		if (
			$this->request->param('prefix') !== 'admin' &&
			Configure::read('Site.status') == 0 &&
			$this->Auth->user('role_id') != 1
		) {
			if (!$this->request->is('whitelisted')) {
				$this->layout = 'Croogo/Croogo.maintenance';
				$this->response->statusCode(503);
				$this->set('title_for_layout', __d('croogo', 'Site down for maintenance'));
				$this->viewPath = 'Maintenance';
				$this->render('Croogo/Croogo.blank');
			}
		}

		if (isset($this->request->params['locale'])) {
			Configure::write('Config.language', $this->request->params['locale']);
		}

		if (isset($this->request->params['admin'])) {
			Croogo::dispatchEvent('Croogo.beforeSetupAdminData', $this);
		}
	}

/**
 * blackHoleCallback for SecurityComponent
 *
 * @return void
 */
	public function securityError($type) {
		switch ($type) {
			case 'auth':
			break;
			case 'csrf':
			break;
			case 'get':
			break;
			case 'post':
			break;
			case 'put':
			break;
			case 'delete':
			break;
			default:
			break;
		}
		$this->set(compact('type'));
		$this->response = $this->render('../Errors/security');
		$this->response->statusCode(400);
		$this->response->send();
		$this->_stop();
		return false;
	}

/**
 * _setupAclComponent
 */
	protected function _setupAclComponent() {
		$config = Configure::read('Access Control');
		if (isset($config['rowLevel']) && $config['rowLevel'] == true) {
			if (strpos($config['models'], $this->plugin . '.' . $this->modelClass) === false) {
				return;
			}
			$this->Components->load(Configure::read('Site.acl_plugin') . '.RowLevelAcl');
		}
	}

/**
 * Combine add and edit views
 *
 * @see Controller::render()
 */
//	public function render($view = null, $layout = null) {
//		list($plugin, ) = pluginSplit(App::location(get_parent_class($this)));
//		if ($plugin) {
//			App::build(array(
//				'View' => array(
//					Plugin::path($plugin) . 'View' . DS,
//				),
//			), App::APPEND);
//		}
//
//		if (strpos($view, '/') !== false || $this instanceof CakeErrorController) {
//			return parent::render($view, $layout);
//		}
//
//		$fallbackView = $this->__getDefaultFallbackView();
//		if (is_null($view) && in_array($this->request->action, array('admin_edit', 'admin_add', 'edit', 'add'))) {
//			$viewPaths = App::path('View', $this->plugin);
//			$themePath = $this->theme ? App::themePath($this->theme) : null;
//			$searchPaths = array_merge((array)$themePath, $viewPaths);
//			$view = $this->__findRequestedView($searchPaths);
//			if (empty($view)) {
//				$view = $fallbackView;
//			}
//		}
//
//		return parent::render($view, $layout);
//	}

/**
 * Croogo uses this callback to load Paginator helper when one is not supplied.
 * This is required so that pagination variables are correctly set with caching
 * is used.
 *
 * @return void
 * @see Controller::beforeRender()
 */
	public function beforeRender(Event $event) {
		if (!$this->usePaginationCache) {
			return;
		}
		if (!isset($this->helpers['Paginator']) && !in_array('Paginator', $this->helpers)) {
			$this->helpers[] = 'Paginator';
		}
	}

/**
 * Get Default Fallback View
 *
 * @return string
 */
	private function __getDefaultFallbackView() {
		$fallbackView = 'form';
		if (!empty($this->request->params['prefix']) && $this->request->params['prefix'] === 'admin') {
			$fallbackView = 'admin_form';
		}
		return $fallbackView;
	}

/**
 * Search for existing view override in registered view paths
 *
 * @return string
 */
	private function __findRequestedView($viewPaths) {
		if (empty($viewPaths)) {
			return false;
		}
		foreach ($viewPaths as $path) {
			$file = $this->viewPath . DS . $this->request->action . '.ctp';
			$requested = $path . $file;
			if (file_exists($requested)) {
				return $requested;
			} else {
				if (!$this->plugin) {
					continue;
				}
				$requested = $path . 'Plugin' . DS . $this->plugin . DS . $file;
				if (file_exists($requested)) {
					return $requested;
				}
			}
		}
		return false;
	}
}