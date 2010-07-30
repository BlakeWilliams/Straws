<?php
	require 'straws.php';
	class App extends Straws {
		function get_index() {
			$locals['test'] = $this->session->name ? $this->session->name : "Hello world";
			$this->render('home',$locals);
		}
		function set_session() {
			$this->session->name = $this->params['name'];
			$this->render('home',array('test'=>"GO HOME /"));
		}
	}
	$app = new App(array('defaultLayout' => 'layout','useSessions' => true)); // Options must be in an array
	$app->get('/','get_index');
	$app->get('/name/:name','set_session');
	$app->run();
?>