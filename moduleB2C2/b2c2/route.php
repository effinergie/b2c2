<?php
/***********************************************
*
*	Grille d'experimentation pour des travaux  
*   BBC Compatible
*
************************************************/

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
		} else if (isset($this->data['createPDF'])){
			$this->createPDF();
		} else if (isset($this->data['enregistreProjet'])){
			$this->enregistreProjet();
		} else if (isset($this->data['copieProjet'])){
			$this->copieProjet();
		} else if (isset($this->data['deleteProj'])){
			$this->deleteProj();
		} else if (isset($this->data['listeProjet'])){
			$this->listeProjet();
		} else if (isset($this->data['testeTypologie'])){
			$this->testeTypologie();
		} else {
			$this->afficheForm();
		}
	}
}
/*
require_once __DIR__.'/php/apiSolB2C2.php';
$b = new apiSolB2C2();
//$c = $b->chercheValeurTableau('typologieBatiment',['typoBat'=>'haussmanAv1948']);
//print_r($c);
//$c = $b->getColTableauValeur('departement','numDep',34,'zoneClim');
//print_r($c);
//$c = $b->getLigneTableauValeur2('departement','numDep',34555,'zoneClim');
//print_r($c);

$partSurf = $b->chercheValeurTableauCol('partSurfMurInt',['surf'=>'40'],'partSurf');
print_r($partSurf);
die;*/



$routeb2c2 = new routeB2C2();
$routeb2c2->route();
?>
