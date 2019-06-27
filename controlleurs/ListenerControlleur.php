<?php

    namespace Poki;

	/**
	* 
	*/
	class ListenerControlleur extends controlleur
	{
		private $cfg;
		private $usr;
		private $plg;
		
		function __construct()
		{
			$this->cfg = $this->loadController('config');
			$this->usr = $this->loadController('users');
			$this->plg = $this->loadController('plugins');
		}

		public function on($event, $datas)
		{
			$funcs = json_decode(file_get_contents(ROOT . 'appfiles/listener/plugins.poki'), true);
			foreach ($funcs as $plugin => $params) {
				if (in_array($event, $params['listener']['handle'])) {
					require_once ROOT .'pk-plugins/'. $plugin .'/listeners/'. $params['listener']['name'] .'.php';

					$class = 'Poki\\' . ucfirst(str_replace('pk-', '', $params['listener']['name']));
					$evt   = 'on' . ucfirst($event);
					$class::$evt($datas);
				}
			}
		}

		public function addListener($type, $plugin, $name) {

		}

		public function removeListener($type, $plugin, $name) {

		}

		public function muteListener($type, $plugin, $name) {

		}

		public function loadPlugins($plugid=false)
		{
			if (file_exists(ROOT . 'appfiles/listener/plugins.poki'))
			{
				$plugins = json_decode(file_get_contents(ROOT . 'appfiles/listener/plugins.poki'), true);
				return $plugid ? $plugins[$plugid] : $plugins;
			}
			else {
				return [];
			}
		}

		public function getParmas()
		{
			$params = ["get" => [], "post" => []];
			$i      = 2;

			while (Posts::get([$i]))
			{
				$val = Posts::get($i);

				if (strlen(trim($val)))
				{
					$params['get'][] = $val;
				}

				$i++;
			}

			foreach ($_POST as $k => $v)
			{
				$params['post'][$k] = $v;
			}

			$params['post'] = (object) $params['post'];

			return (object) $params;
		}

		public function app()
		{
			$this->cfg->configSurvey(false);
			$admin = $this->usr->loginSurvey(false, 'login');

			$plugid 	= Posts::get(0);
			$link_key   = Posts::get(1);
			$plugin   	= $this->loadPlugins($plugid);
			$name     	= $plugin['name'];
			$l_name   	= $plugin['label_name'];
			$m_links  	= $plugin['menulinks'];
			$door     	= ROOT . 'pk-plugins/' . $plugid . '/' . $plugin['door'] . '.php';

			if ($plugin['active'] == 0) $this->redirTo(Routes::find('home'));

			$GLOBALS['plugid']   = $plugid;
			$GLOBALS['plugname'] = $name;

			include $door;

			$class = new Main();
			$varbs = null;

			if (isset($m_links[$link_key]) && method_exists($class, $m_links[$link_key]['action']))
			{
				$varbs = $class->{ $m_links[$link_key]['action'] }( $this->getParmas() );
			}
			else if (method_exists($class, str_replace('-', '', $link_key)))
			{
				$varbs = $class->{ str_replace('-', '', $link_key) }( $this->getParmas() );
			}

			if (isset($m_links[$link_key]['view']) && file_exists($view = ROOT . 'pk-plugins/' . $plugid . '/views/' . $m_links[$link_key]['view'] . '.view.php'))
			{
				// creating scope vars array
				$scope_vars = [
					"admin"             => $admin,
					"pagetitle"         => ucfirst($l_name),
					"categories"        => $this->loadController('categories')->list(),
					"pluglist"          => $this->loadController('listener')->loadPlugins(),
					"view"              => $view,
					"app_base_url"      => Routes::find('base-route'),
					"app_files_path"    => ROOT . 'appfiles/fields_files',
					"plugin_base_url"   => Routes::find('plugins') .'/'. $plugid,
					"plugin_base_path"  => ROOT . 'pk-plugins/' . $plugid,
					"plugin_base_web"  	=> WROOT . 'pk-plugins/' . $plugid,
					// variables from action
					"vars"              => (object) $varbs,
					// styles and scripts
					"styles"			=> $plugin['styles'] ?? [],
					"scripts"			=> $plugin['scripts'] ?? []
				];

				// all vars in a same array as scope var
				$scope_vars["scope"] = $scope_vars;

				// rendering
				$this->render('app/plugview', $scope_vars);
			}
			else {
				// somthing to do
			}
		}

		public function plugin($plugid, $action, $gets, $posts)
		{
			if ($plg = $this->loadPlugins($plugid))
			{	
				if ( ! file_exists(ROOT . 'pk-plugins/' . $plugid . '/' . $plg['apidoor'] . '.php') || !$plg['active'])
				{
					return ["error" => true, "message" => "No door found !"];
				}
				else {
					include ROOT . 'pk-plugins/' . $plugid . '/' . $plg['apidoor'] . '.php';
					
					$class = new ApiEntry();

					if (method_exists($class, $action))
					{
						$class->{ $action }($gets, $posts);
						return ["error" => false];
					}
					else {
						return ["error" => true, "message" => "Action not found !"];
					}
				}
			}
			else {
				return ["error" => true, "message" => "App not found !"];
			}
		}

		public function list()
		{
			$this->loadController('plugins')->listPlugins();
		}

		public static function log($message)
		{
			file_put_contents('ROOTerror', file_get_contents('ROOTerror') . $message . "\n");
		}
	}