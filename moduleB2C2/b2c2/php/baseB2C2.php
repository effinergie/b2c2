<?php

class baseB2C2{
	
	const VERSION = '0.75'; 
	protected $listeValeur;
	protected $listeTableauValeur=[];	
	
	/***********************************
	* Initialisation
	************************************/
	
	public function getInitVal(){
		$cur_dir = explode('\\', __dir__);
		$cur_dir = $cur_dir[count($cur_dir)-2];		
		 
		return [
			'URI_BASE'=>CMSEffiUtils::getCMSUriBase(),
			'URI_PROJ_BASE'=>CMSEffiUtils::getCMSUriProjBase(),
			'VERSION'=>$this::VERSION,
			'lstVal'=>$this->getListesValeurs(),
			'imgTypoBat'=>$this->getListeImgTypoBat(),
			'typeChauffageDecentralise' => $this->getTypeChauuffageDecentralise()
		];
	}
	
	
	
	/***********************************
	* Resources
	************************************/
	protected function initListeTableauValeur(){
		$this->getListesValeurs();
	}
	
	protected function chargeListeTableauValeur($nomFichier){	
		$res = array();
		$listeChamps = array();
		if (($handle = fopen(__DIR__.'/../res/'.$nomFichier.'.csv','r')) !== FALSE) {
			while (($data = fgetcsv($handle)) !== FALSE) {	
				if($data[0][0]=='-'){
					if (isset($nomTableau)){
						$this->listeTableauValeur[$nomTableau] = $res;
						if ($listeChamps[1]=='lib'){
							$this->listeValeur[$nomTableau] = $this->getListeValeurDepuisTableau($nomTableau,$listeChamps[0],$listeChamps[1]);
						}						
					}					
					$res = array();
					$listeChamps = array();
					$nomTableau = substr($data[0], 1);
				} else {
					//premiere ligne c'est les entête
					if (!count($listeChamps)){
						foreach ($data as $val){
							if (!$val) break;
							$listeChamps[] = $val;
						}			
					} else {
						$ligne = array();
						foreach ($listeChamps as $i=>$nomChamp){
							$val = $data[$i];
							$ligne[$nomChamp] = $val;
						}						
						array_push($res,$ligne);
					}
				}
			}
			fclose($handle);
		}
		if (isset($nomTableau)){
			$this->listeTableauValeur[$nomTableau] = $res;
			if ($listeChamps[1]=='lib'){
				$this->listeValeur[$nomTableau] = $this->getListeValeurDepuisTableau($nomTableau,$listeChamps[0],$listeChamps[1]);
				if (count($listeChamps) == 2){
					//on ne garde pas les tableaux qui n'ont qu'un libellé.
					unset($this->listeTableauValeur[$nomTableau]);
				}
			}
		}
	}
	
	protected function getTableauValeur($nomTableau){	
		if(!isset($this->listeTableauValeur[$nomTableau])){
			$res = array();
			if (($handle = fopen(__DIR__.'/../res/'.$nomTableau.'.csv','r')) !== FALSE) {
				while (($data = fgetcsv($handle)) !== FALSE) {				
					//premiere ligne du fichier est les entête
					if (!isset($listeChamps)){
						$listeChamps = $data;					
					} else {
						$ligne = array();
						foreach ($data as $i=>$val){
							$nomChamp = $listeChamps[$i];
							$ligne[$nomChamp] = $val;
						}						
						array_push($res,$ligne);
					}
				}
				fclose($handle);
			}
			$this->listeTableauValeur[$nomTableau] = $res;
		}
		return $this->listeTableauValeur[$nomTableau];
	}
	
	/*public function chercheValeurTableau($nomTableau,$aValEntree,$bArrayRes=false,$bGetFirstFind=true){
		$aSolutions = array();
		$aLignesTableauValeur = $this->getTableauValeur($nomTableau);
		//echo '<pre>';print_r($logigrammeMur);die;
		//print_r($logigrammeMur);die;
		foreach ($aLignesTableauValeur as $ligneTableau){
			$res = $this->testeLigneTableauValeur($ligneTableau,$aValEntree,$bArrayRes);
			
			if ($res !== false){		
				if ($bGetFirstFind){
					return $res;
				}
				$idLgn = reset($res)[0]; //récupere la valeur de la première colonne comme ID
				$aSolutions[$idLgn]=$res;
			}
		}
		
		if ($bGetFirstFind){
			return false;
		}
	
		return $aSolutions;
	}*/
	
	/*protected function testeLigneTableauValeur($ligneTableau,$aValEntree,$bArrayRes){
		//dans la ligne du logigramme, le nom des champs résultats commence par -	
		$res = array();
		foreach($ligneTableau as $nomChamp=>$val){
			$nomChamp = trim($nomChamp);
			//cas des alias de champs pour tester plusieurs valeurs sur une colonne. (ET au lieu de OU)
			$nomChamp = explode('#',$nomChamp)[0];
			$aVal = Explode(',',$val);
			$aVal = array_map('trim', $aVal);
			//if (strpos($nomChamp,'-')!==0){			
			if ($nomChamp[0]=='-'){	
				//champs résultats
				$nomChampRes = substr($nomChamp, 1);		
				//champs resultat dans un tableau ou pas.
				if ($bArrayRes){
					$res[$nomChampRes]=$aVal;	
				} else {
					$res[$nomChampRes]=trim($ligneTableau[$nomChamp]);						
				}										
			} else {
	
				$aValTest = $aValEntree[$nomChamp];
				if (!is_array($aValTest)){
					$aValTest = [$aValTest];
				}
				
				//test si la valeur correspond (* veut dire toute valeurs possible)
				if (!in_array('*',$aVal) ){
					//if (!in_array($valTest,$aVal)){ 
					if (count(array_intersect($aValTest,$aVal))==0){ 
						return false;
					}
				}				
			}
		}
		if (count($res)){
			return $res;
		} else {
			return false;
		}
	}	*/

	public function chercheValeurTableau($nomTableau,$aValEntree,$bArrayRes=false,$bGetFirstFind=true){
		$aSolutions = array();
		$aLignesTableauValeur = $this->getTableauValeur($nomTableau);
		//echo '<pre>';print_r($logigrammeMur);die;
		//print_r($logigrammeMur);die;
		foreach ($aLignesTableauValeur as $ligneTableau){
			$res = $this->testeLigneTableauValeur($ligneTableau,$aValEntree,$bArrayRes);

			if ($res !== false){
				//$ligneRes = $this->chercheValeurTableau_colResAuto($ligneTableau,$bArrayRes);
				if ($bGetFirstFind){
					return $res ;
				}
				$idLgn = reset($res)[0]; //récupere la valeur de la première colonne comme ID
				$aSolutions[$idLgn]=$res;
			}
		}
		
		if ($bGetFirstFind){
			return false;
		}
		
		return $aSolutions;
	}	
	
	/*protected function chercheValeurTableau_colResAuto($ligneTableau,$bArrayRes){	
		//dans la ligne du logigramme, le nom des champs résultats commence par -
		//Si l'entete des collones résultats commencent pat - alors on ne retourne que ces colonnes. sinon on retourne toutes les colonnes.
		$res = array();
		$resAuto = array();
		foreach($ligneTableau as $nomChamp=>$val){
			if ($bArrayRes){
				$aVal = explode(',',$val);
				$aVal = array_map('trim', $aVal);
				$valCol=$aVal;	
			} else {
				$valCol=trim($ligneTableau[$nomChamp]);						
			}				
			$res[$nomChamp] = $valCol;
			if ($nomChamp[0]=='-'){	
				$nomChampResAuto = substr($nomChamp, 1);	
				$resAuto[$nomChampResAuto] = $valCol;				
			}
		}
		if (count($resAuto)){
			return $resAuto;
		} else {
			return $res;
		}		
	}*/
	
	protected function testeLigneTableauValeur($ligneTableau,$aValEntree,$bArrayRes){
		//dans la ligne du logigramme, le nom des champs résultats commence par -
		//Si l'entete des collones résultats commencent pat - alors on ne retourne que ces colonnes. sinon on retourne toutes les colonnes.		
		$res = array();
		$resAuto = array();
		
//print_r($bArrayRes);
//print_r($ligneTableau);
//print_r($aValEntree);die;		
		
		foreach($ligneTableau as $nomChampBrut=>$valTableau){
			$nomChampBrut = trim($nomChampBrut);
			//cas des alias de champs pour tester plusieurs valeurs sur une colonne. (ET au lieu de OU)
			$nomChamp = explode('#',$nomChampBrut)[0];
			//cas ou le champ commence par un signe de test < ou >
			$bTestSupInf = in_array($nomChamp[0],["<",">"]);		
			if ($bTestSupInf){
				$charTest = $nomChamp[0];
				$nomChamp = substr($nomChamp, 1);
				if ($nomChamp[0] == '='){
					$charTest .= '=';
					$nomChamp = substr($nomChamp, 1);
				}
			}

			$aValTableau = explode(',',$valTableau);
			$aValTableau = array_map('trim', $aValTableau);

			if (isset($aValEntree[$nomChamp])){
				$aValTest = $aValEntree[$nomChamp];
				if (!is_array($aValTest)){
					$aValTest = [$aValTest];
				}

				//test si la valeur correspond (* veut dire toute valeurs possible)
				if (!in_array('*',$aValTableau) ){
					if (count($aValTableau)<=0){
						return false;
					}
					
					//test des supérieurs ou inférieurs
					if ($bTestSupInf){
						if (!$this->testeLigneTableauValeurSupInf($aValTest[0],$charTest,$aValTableau[0])){
							return false;
						}				
					} else {						
						if (!$this->testeLigneTableauValeurDetail($aValTest,$aValTableau)){
							return false;
						}	
					}
				}
			} /*else if ($nomChamp[0] != '-'){
				//on ne peut pas retourner faux car cette fonction est utilisée dans les recherche de valeurs type listeValeur.csv et on ne cherche que sur une colonne par ex.
				//return false;
				print_r($aValEntree);
				print_r($nomChamp);
				die;
				
			}*/

			//resultats
			if ($bArrayRes){
				$valCol=$aValTableau;	
			} else {
				$valCol=trim($ligneTableau[$nomChampBrut]);						
			}				
			$res[$nomChampBrut] = $valCol;
			if ($nomChamp[0]=='-'){	
				$nomChampResAuto = substr($nomChamp, 1);	
				$resAuto[$nomChampResAuto] = $valCol;				
			}
		}
		
		
		if (count($resAuto)){
			return $resAuto;
		} else {
			return $res;
		}	
	}		

	protected function testeLigneTableauValeurDetail($aValTest,$aValTableau){	
		//if (!in_array($valTest,$aValTableau)){ 
		
		foreach ($aValTableau as $valTableau){			
			//cas des ! (not)
			if ($valTableau[0] == '!'){
				$valTableau = substr($valTableau, 1);
				if (!in_array($valTableau,$aValTest)){
					return true;
				}
			  //casl cassique : 
			} else if (in_array($valTableau,$aValTest)){
				return true;
			}
		}
		return false;
		
		/*if (count(array_intersect($aValTest,$aValTableau))==0){ 
			return false;
		}
		return true;*/
	}
	protected function testeLigneTableauValeurSupInf($valTest ,$charTest, $val){	
		$val = $this->floatvalFr($val);
		$valTest = floatval($valTest);
		switch($charTest){
			case "<":
				return ($valTest < $val);
			case "<=":
				return ($valTest <= $val);
			case ">":
				return ($valTest > $val);
			case ">=":
				return ($valTest >= $val);
		}
		return false;
	}

	public function chercheValeurTableauCol($nomTableau,$aValEntree,$nomChampRetour){
		if ($ligne = $this->chercheValeurTableau($nomTableau,$aValEntree)){
			return $ligne[$nomChampRetour];
		} 
		return false;	
	}

	public function getSommeColTableauListeValeur($nomTableau,$nomCampId,$aIDCherche,$nomChampRetour){
		$aCol = $this->getColTableauListeValeur($nomTableau,$nomCampId,$aIDCherche,$nomChampRetour);
		$somme = 0;
		foreach($aCol as $col){
			$somme +=$this->floatvalFr($col);
		}
		return $somme;
	}

	public function getColTableauListeValeur($nomTableau,$nomCampId,$aIDCherche,$nomChampRetour){
		$res = [];
		foreach($aIDCherche as $idCherche){
			$col = $this->getColTableauValeur($nomTableau,$nomCampId,$idCherche,$nomChampRetour);
			if ($col){
				$res[]=$col;
			}
		}
		return $res;
	}
	public function getColTableauValeur($nomTableau,$nomCampId,$idCherche,$nomChampRetour){
		if ($ligne = $this->getLigneTableauValeur($nomTableau,$nomCampId,$idCherche)){
			if (!isset($ligne[$nomChampRetour])){
				echo "$nomChampRetour\n";
				print_r($ligne);
				debug_print_backtrace();
				die;
			}
			return $ligne[$nomChampRetour];
		} 
		return false;	
	}
	
	
	public function getLigneTableauValeur($nomTableau,$nomCampId,$idCherche){
		//on enlève le dieze au début si necessaire
		$idCherche = str_replace('#', '', $idCherche);
		$aLines = $this->getTableauValeur($nomTableau);
		foreach ($aLines as $line){
			if ($line[$nomCampId]==$idCherche){
				return $line;
			}
		}	
		return false;
	}
		
	/*public function getColTableauValeurMin($nomTableau,$nomCampValMin,$valMin,$nomChampRetour){
		if ($ligne = $this->getLigneTableauValeurMin($nomTableau,$nomCampValMin,$valMin)){
			return $ligne[$nomChampRetour];
		} 
		return false;	
	}
	
	
	public function getLigneTableauValeurMin($nomTableau,$nomCampValMin,$valMin){ 
		$res = false;
		$aLines = $this->getTableauValeur($nomTableau);
		foreach ($aLines as $line){
			if ($valMin>=$line[$nomCampValMin]){
				$res = $line;
			} else {
				return $res;
			}
		}
		return $res;
	}	*/
	
	protected function getColTableau($nomTableau,$nomChamp){
		$valTab = $this->getTableauValeur($nomTableau);
		return array_column($valTab,$nomChamp);
	}
	
	protected function getListeValeurDepuisTableau($nomTableau,$nomCampId,$nomCampTexte){
		$valTab = $this->getTableauValeur($nomTableau);
		$aId = array_column($valTab,$nomCampId);
		$aLib = array_column($valTab,$nomCampTexte);
		return array_combine($aId,$aLib);
	}
	
	protected function getListesValeurs(){
		if (!$this->listeValeur){				
			$this->chargeListeTableauValeur('listesValeurs');
			$this->chargeListeTableauValeur('departement');
		}

		return $this->listeValeur;
	}
	

	
	protected function getListeValeurs($nomListe){
		$listeValeur = $this->getListesValeurs();
		if (!isset($listeValeur[$nomListe])){
			return [];
		}
		return $listeValeur[$nomListe];
	}
	
	
	protected function getValChamp($nomLst,$val){	
		$lstValChamp = $this->getListeValeurs($nomLst);		
		
		if ($val && $lstValChamp){		
			if ($val[0] == '#'){
				//enleve le dièze du debut au cas ou.
				$val = substr($val, 1); 
			}		
			if (isset($lstValChamp[$val])){					
				return $lstValChamp[$val];
			}
		}
		return $val;
	}
	
	protected function getValChampLstVal($nomLst,$lstVal){	
		if ($lstVal && isset($lstVal[$nomLst])){
			return $this->getValChamp($nomLst,$lstVal[$nomLst]);
		}
		return '';
	}
	
	protected function getListeValChamp($nomLst,$aVal,$separator = ', <br>'){
		$aRes = [];
		foreach ($aVal as $i=>$val){
			$aRes[] = $this->getValChamp($nomLst,$val);
		}
		return implode($separator,$aRes);
	}
	
	protected function supprimeListeValDieze($lstVal){
		$res = [];
		foreach ($lstVal as $val){
			if ($val && $val[0] != '#'){
				$res[] = $val;
			}
		}
		return $res;
	}
	
	protected function getListeValChampLstVal($nomLst,$lstVal){	
		if ($lstVal && isset($lstVal[$nomLst])){
			return $this->getListeValChamp($nomLst,$lstVal[$nomLst]);
		}
		return '';
	}
			
	protected function getListeImgTypoBat(){	
		$res = [];
		$path = __DIR__.'/../img/imgTypo/*';
		$directories = array_filter(glob($path), 'is_dir');
		foreach($directories as $dir){
			$nomDir = basename($dir);
			$lstImg = glob($dir.'/*.jpg');
			$res[$nomDir]=array_map('basename',$lstImg);
		}
		//$directories = glob($path);
		return $res;		
	}
	
	protected function getTypeChauuffageDecentralise(){
		$res=[];
		$aTypeEmmeteurChauffage = $this->getTableauValeur('typeEmmeteurChauffage');//decentralise
		foreach($aTypeEmmeteurChauffage as $TypeEmmeteurChauffage ){
			if ($TypeEmmeteurChauffage['decentralise'] == 'oui'){
				$id = $TypeEmmeteurChauffage['id'];
				$res[$id] = 'decentralise';
			}
		}
		return $res;
	}

	/***********************************
	* Utils
	************************************/
	protected function floatvalFr($text){
		return floatval(str_replace(',', '.', $text));
	}
	
	protected function strRemoveComments($text){
		$text = preg_replace('#//.*#', '', $text); //remove this comments
		$text = preg_replace('!/\*.*?\*/!s', '', $text); /*remove this comments*/
		$text = preg_replace('/\n\s*\n/', "\n", $text);
		return $text;
	}
	

}

?>
