<?php
/***********************************************
*
*	Grille d'experimentation pour des travaux  
*   BBC Compatible
*
************************************************/

//require_once __DIR__.'/php/CMSEffiUtils.php';

class routeApiExt {
	protected $data;
		
	function extGenereFdr(){
		require_once __DIR__.'/php/apiFdrB2C2.php';		
		$apiFdrB2C2 = new ApiFdrB2C2();
		$data = $this->data['extGenereFdr'];
		$res = $apiFdrB2C2->extGenereFdr($data);
		echo json_encode($res);
		die;
	}	
	
	/*Route*/
	function route(){		
		if (isset($_POST['data'])){
			$this->data = $_POST['data'];
		} else {
			$this->data = $_GET;
		}
		
		if (isset($this->data['extGenereFdr'])){
			$this->extGenereFdr();
		} 
	}
	

	
	
}




$routeb2c2 = new routeApiExt();
$routeb2c2->route();
?>
