<?php


require_once __DIR__.'/php/CMSEffiUtils.php';

class routeB2C2 {
	protected $data;
	
	/*Form*/
	function afficheForm(){
		require_once __DIR__.'/php/formB2C2.php';
		$formB2C2 = new FormB2C2();
		$formB2C2->afficheForm(intval(@$this->data['viewProj']));
	}	
	
	function listeProjet(){
		require_once __DIR__.'/php/formB2C2.php';
		$formB2C2 = new FormB2C2();
		$formB2C2->afficheListeProjetUtilisateur();
	}
	
	function listeProjetAdmin(){
		require_once __DIR__.'/php/formB2C2.php';
		$formB2C2 = new FormB2C2();
		$formB2C2->afficheListeProjetAdmin();
	}
	

	
	
	/*API*/
	function genereTypologie(){
		require_once __DIR__.'/php/apiSolB2C2.php';
		$apiSolB2C2 = new ApiSolB2C2();
		$data = $this->data['genereTypologie'];
		$res = $apiSolB2C2->genereTypologie($data);
		echo json_encode($res);
		die;
	}
	
	function testeTypologie(){
		require_once __DIR__.'/php/apiSolB2C2.php';
		$formB2C2 = new ApiSolB2C2();
		$formB2C2->testeTypologie();
	}
	
	function genereSolutions(){
		require_once __DIR__.'/php/apiSolB2C2.php';
		$apiSolB2C2 = new ApiSolB2C2();
		$data = $this->data['genereSolutions'];
		$res = $apiSolB2C2->genereSolutions($data);
		echo json_encode($res);
		die;
	}
	
	function createPDF(){
		require_once __DIR__.'/php/cthB2C2.php';
		$apiSolB2C2 = new CthB2C2();
		$data = $this->data['createPDF'];
		$res = $apiSolB2C2->createPDF($data);
		die;
	}
	function createPDFMenages(){
		require_once __DIR__.'/php/cthB2C2.php';
		$apiSolB2C2 = new CthB2C2();
		$data = $this->data['createPDFMenages'];
		$res = $apiSolB2C2->createPDFMenages($data);
		die;
	}
	
	function genereFeuilleDeRoute(){
		require_once __DIR__.'/php/cthB2C2.php';
		$apiSolB2C2 = new CthB2C2();
		$data = $this->data['genereFeuilleDeRoute'];
		$res = $apiSolB2C2->genereFeuilleDeRoute($data );
		echo json_encode($res,JSON_PARTIAL_OUTPUT_ON_ERROR); //en cas de valeur NAN la chaine ne sort pas à blanc.
		die;
	}
	
	function deleteProj(){
		require_once __DIR__.'/php/apiSolB2C2.php';		
		$apiSolB2C2 = new ApiSolB2C2();
		$data = $this->data['deleteProj'];
		$res = $apiSolB2C2->supprimeProjet($data);
		$this->listeProjet();
		//echo json_encode($res);
		//die;
	}
	
	function copieProjet(){
		require_once __DIR__.'/php/apiSolB2C2.php';		
		$apiSolB2C2 = new ApiSolB2C2();
		$data = $this->data['copieProjet'];
		$res = $apiSolB2C2->copieProjet($data);
		$this->listeProjet();
	}
	
	function enregistreProjet(){
		require_once __DIR__.'/php/apiSolB2C2.php';		
		$apiSolB2C2 = new ApiSolB2C2();
		$data = $this->data['enregistreProjet'];
		$res = $apiSolB2C2->enregistreProjet($data);
		echo json_encode($res);
		die;
	}
	
	
	/*API EXT*/
	function extAfficheFormTest(){
		require_once __DIR__.'/php/apiFdrB2C2.php';		
		$apiFdrB2C2 = new ApiFdrB2C2();
		$data = $this->data['extAfficheFormTest'];
		$res = $apiFdrB2C2->extAfficheFormTest();
		//echo json_encode($res);
		//die;
	}
	
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
			$this->data =$_POST['data'];
		} else {
			$this->data =$_GET;
		}

		
		if (isset($this->data['genereSolutions'])){
			$this->genereSolutions();
		} else if (isset($this->data['genereTypologie'])){
			$this->genereTypologie();
		} else if (isset($this->data['genereFeuilleDeRoute'])){
			$this->genereFeuilleDeRoute();
		} else if (isset($this->data['createPDFMenages'])){
			$this->createPDFMenages();
		}else if (isset($this->data['createPDF'])){
			$this->createPDF();
		} else if (isset($this->data['enregistreProjet'])){
			$this->enregistreProjet();
		} else if (isset($this->data['copieProjet'])){
			$this->copieProjet();
		} else if (isset($this->data['deleteProj'])){
			$this->deleteProj();
		} else if (isset($this->data['listeProjet'])){
			$this->listeProjet();
		} else if (isset($this->data['listeProjetAdmin'])){
			$this->listeProjetAdmin();
		} else if (isset($this->data['testeTypologie'])){
			$this->testeTypologie();
		} else if (isset($this->data['extAfficheFormTest'])){
			$this->extAfficheFormTest();
		} else if (isset($this->data['extGenereFdr'])){
			$this->extGenereFdr();
		} else {
			$this->afficheForm();
		}
	}
	

	
	
}


$routeb2c2 = new routeB2C2();
$routeb2c2->route();
?>
