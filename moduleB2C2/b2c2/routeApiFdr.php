<?php

require_once __DIR__.'/php/apiFdrB2C2.php';		

class routeApiExt {
	protected $data;
		
	function extGenereFdr(){
		
		$apiFdrB2C2 = new ApiFdrB2C2();
		$data = $this->data['extGenereFdr'];
//$data['PDFtoString']= 1;
		$res = $apiFdrB2C2->extGenereFdr($data);
		
		$this->logCall($apiFdrB2C2,$res);
		if (is_array($res)){
			echo json_encode($res);
		}	else {
			if(strpos($res,'%PDF')===0){
				//echo 1111;die;
				header("Content-type:application/pdf");
			}
			echo $res;
		}	
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
	
	function logCall($apiFdrB2C2,$res){
		$aLog = [
			$_SERVER['REMOTE_ADDR'],
			date('Ymd_H:i:s'),
			baseB2C2::VERSION,
			$apiFdrB2C2->getLogiciel(),
			@$this->data['extGenereFdr']['typeRetour'],
			@$res['status']
		];
		$sLog = implode("\t",$aLog)."\r\n";

		file_put_contents(__DIR__.'/../../log/routeApiFdr_'.date("Ymd").'.log', $sLog, FILE_APPEND);
	}
	
	
}




$routeb2c2 = new routeApiExt();
$routeb2c2->route();
?>
