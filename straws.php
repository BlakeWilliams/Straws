<?php
// (c) 2010 Blake Williams, released under the New BSD License	
	class View {
		private $fileName;
		private $variables;
		private $viewDir;

		public function __construct($fileName,$viewDir,$variables) {
			$this->variables = $variables;
			$this->fileName = $fileName;
			$this->viewDir = $viewDir;
		}

		public function render($layout = true) {
			$this->includeFile($layout);
		}
		public function  renderLayout ($layout = "layout") {
			$this->includeFile($layout);
		}
		public function yield() {
			$this->includeFile();
		}
		public function viewExists() {
			if(file_exists(Straws::getRoot() .'/' . $this->viewDir . $this->fileName . '.php')) {
				return true;
			}
			return false;
		}
		private function includeFile($layout = false) {
			if(!$this->viewExists()) {
				Straws::showError();
			}
			extract($this->variables);
			extract($locals);
			if($layout) {
				include(Straws::getRoot() .'/' . $this->viewDir . $layout . '.php');
			} else {
				include(Straws::getRoot() .'/' . $this->viewDir . $this->fileName . '.php');	
			}
		}
	}
	
	class Url {
		public $url;
		private $method;
		public $match = false;
		public $params = array();
	
		public function __construct($requestMethod,$url) {
			$requestUri = $_SERVER['REQUEST_URI'];
			$this->url = $url;
			$this->method = $requestMethod;
			
			if(strtoupper($requestMethod) == $_SERVER['REQUEST_METHOD']) {
				$paramKeys = $this->segment($this->url);
				$paramValues = $this->segment($requestUri);
				if(count($paramKeys) == count($paramValues)) {
					$paramList = array_combine($paramKeys, $paramValues);
					foreach($paramList as $key => $value) {
						if($key != $value && !preg_match('/^:/',$key)) {
							$this->match = false;
							return;
						}
						if($key != $value && preg_match('/^:/',$key)) {
							$key = preg_replace('/^:/','',$key);
							$this->params[$key] = $value;
						}
						
					}
					$this->match = true;
				}
			}
		}
		public function segment($url) {
			$segments = split('/',$url);
			array_shift($segments);
			return $segments;
		}
	}
	class Request {
		public function __construct() {}	
		public function __get($key) {
			return isset($_POST[$key]) ? $_POST[$key] : null;
		}
	}
	
	class Session {
		public function __construct() {
			session_name('straws_session');
			session_start();
		}
		public function __get($key) {
            return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
        }

        public function __set($key, $value) {
            $_SESSION[$key] = $value;
            return $value;
        }
	}
	
	class Straws {
		private $mappings = array();
		protected $options;
		protected $params = array();
		protected $session;
		protected $request ;//= new Request();
		
		public function __construct($options = array()) {
			$this->options = array('layout' => false, 'viewDir' => 'views/', 'showErrors' => false,
									'errorLayout' => '', '404Layout' => '','useSessions' => false);
			foreach($options as $key => $value) {
				$this->options[$key] = $value;
			}
			error_reporting(E_ALL);
			ini_set('display_errors', '1');
			if($this->options['showErrors'] == false) {
				set_error_handler(array($this, 'showError'), 2);
			}
			if($this->options['useSessions']) {
				$this->session = new Session();
			}
			$this->request = new Request();
		}
	
		public function get($url,$methodName) {
			$this->event('get',$url,$methodName);
		}
		public function post($url,$methodName) {
			$this->event('post',$url,$methodName);
		}
		public function put($url,$methodName) {
			$this->event('post',$url,$methodName);
		}
		public function delete($url,$methodName) {
			$this->event('post',$url,$methodName);
		}
		private function event($requestMethod, $url, $methodName) {
			if (method_exists($this,$methodName)) {
				array_push($this->mappings,array($requestMethod,$url,$methodName));
			}
		}
		private function execute($methodName) {
			return call_user_func_array(array($this,$methodName),false);
		}
		public function getRoot() {
			return dirname(__FILE__);
		}
		public function redirect($path) {
			header("Location: $path");
		}
		protected function render($fileName,$locals = array(),$layout = true) {
			if($layout === true) {
				$layout = 'layout';
			}
			$variables['locals'] = $locals;
			$variables['params'] = $this->params;
			$variables['request'] = $this->request;
        	$variables['session'] = $this->session;

			$view = new View($fileName,$this->options['viewDir'],$variables);
			return $view->render($layout);
		}
		protected function sendFile($fileName, $contentType, $filePath) {
            header("Content-type: $contentType");
            header("Content-Disposition: attachment; filename=$fileName");
			return readfile($filePath);
        }
		protected function sendDownload($fileName, $filePath) {
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Description: File Transfer");
            header("Content-Disposition: attachment; filename=$fileName".";");
            header("Content-Transfer-Encoding: binary");
            return readfile($filePath);
        }
		public function run() {
			foreach($this->mappings as $mapping) {
				$url = new Url($mapping[0],$mapping[1]);
				if($url->match) {
					$this->params = $url->params;
					return $this->execute($mapping[2]);
				}
			}
			return $this->show404();
		}
		public function showError() {
			header('HTTP/1.0 500 Server Error');
			$this->render('500',array(),$this->options['errorLayout']);	
			die();
		}
		public function show404() {
			header('HTTP/1.0 404 Not Found');
			$this->render('404',array(),$this->options['404Layout']);
			die();
		}
	}
?>