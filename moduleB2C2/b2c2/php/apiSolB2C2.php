<?php
require_once 'baseB2C2.php';

class ApiSolB2C2 extends baseB2C2{
	protected $jData;
	protected $jFdr;
	protected $aTrace = [];
	
	function __construct(){
		$this->initListeTableauValeur();
	}
	
	
	/*************************************
	*  Genere Typologie
	**************************************/		
	public function genereTypologie($aData){
		
		$this->jData = [
			'projet'=>[]
		];
//		$this->setData($aData);
		
		//$typoBat= $aData->typoBat;
		//$aData[typoBat] = 'toto';
		if (! $valTrouvee = $this->chercheValeurTableau('typologieBatiment',$aData)){	
			return $this->setResSatus([],'ERROR');
		}
		
		$this->jData['projet']['batiment'] = $valTrouvee;			
		$this->jData['projet']['general'] = $aData;
		

		
		//typologies
		$lstLot = [ 'mur',
					'plancherHaut',
					'ecs',
					'chauffage',					
					'plancherBas',
					'menuiserie',
					'ventilation'
					];

		//copie des champs necessaires pour la recherche de typologies dans certains lots
		$aData['anneeConstr'] = $valTrouvee['anneeConstr'];
		$aData['typeBat'] = $valTrouvee['typeBat'];

		foreach ($lstLot as $nomLot){
			if ($valTrouvee = $this->chercheValeurTableau('typologie'.ucfirst($nomLot),$aData)){	
				//recherche des valeurs d'entrée en fonction de la typo
				$this->jData['projet']['lstLot'][$nomLot][0]['existant']  = $valTrouvee;
				//recherche des solutions en fonction des valeurs d'entrées
				$aLstSol = $this->chercheSolutionLot($nomLot,$this->jData['projet']['lstLot'][$nomLot]);
				//affectation des solutions
				$this->jData['projet']['lstLot'][$nomLot] = array_replace_recursive($this->jData['projet']['lstLot'][$nomLot],$aLstSol);
			}		
		}	
	
		//solutions		
		//$solutions = $this->genereSolutions($res);
		
		//$res['projet']['lstLot'] = array_replace_recursive($res['projet']['lstLot'],$solutions['solutions']);
//echo'<pre>';print_r($solutions );die;
		return $this->setResSatusOK($this->jData);
	}
	
	
	public function testeTypologie(){
		$data=array();
		//$data['id']: 
		$data['nomProjet']='test';
		$data['departement']= 34;
		$data['genTypo']= 'oui';
		$data['typoBat']= 'maisRuralAv1948';
		$data['choixEner']= 'elec';
		
		echo '<pre>';
		$lstTypoBat = $this->getListeValeurs('typoBat');
		$lstChoixEner = $this->getListeValeurs('choixEner');		
		
		
		foreach($lstTypoBat as $typoBat=>$libtypoBat){
			foreach($lstChoixEner as $choixEner=>$libchoixEner){
				$data['typoBat'] = $typoBat;
				$data['choixEner'] = $choixEner;				
				$this->testeTypologieData($data);
				
			}
		}
		
		echo 'Verification terminée';
		//print_r($res);
		die;
	}
	
	protected function testeTypologieData($data){
		$res = '';
		//test
		$projet = $this->genereTypologie($data);
		foreach ($projet['projet']['lstLot'] as $nomLot=>$aTypeLot){
			foreach($aTypeLot as $numLot => $typeLot){
				$idSol = @$typeLot['existant']['idSol'];

				if (!isset($typeLot['lstSol'][$idSol])){
					
					$res.= "$nomLot n°$numLot : Solution n°$idSol n'existe pas<br>";
					//affiche solution dipo
					$res.= "Solutions possible : <br>";
					foreach ($typeLot['lstSol'] as $sol){
						$res.= '- ';
						foreach ($sol as $nomChamp =>$val){
							$res.= "$nomChamp : ".implode(',',$val)." | ";
						}
						$res.= '<br>';
					}
					$res.= '<br>';
				}
				
			}
		}
		
		if ($res){	
			$typeBat = $projet['projet']['batiment']['typeBat'];
			$typoBat = $projet['projet']['general']['typoBat'];
			$choixEner = $projet['projet']['general']['choixEner'];
			echo " **** ERREUR Typologie $typeBat $typoBat $choixEner : ".$data['typoBat']." ".$data['choixEner']."<br>";
			echo $res;
		}	
		
	}
	
	protected function getZoneClim(){
		$zoneClim = $this->paramDepartement('zoneClim');
		$this->jData['projet']['general']['zoneClim'] = $zoneClim;
		return $zoneClim;
	}
	
	protected function getZoneClimEte(){
		return $this->paramDepartement('zoneClimEte');
	}
	
	protected function getAltitude(){
		$altitude = $this->floatvalFr($this->jData['projet']['batiment']['altitude']);
		if (!$altitude){
			$altitude = $this->floatvalFr($this->paramDepartement('altitude'));
		}
		return $altitude ;
	}
	
	protected function paramDepartement($nomChampVal){
		$numDep = $this->jData['projet']['general']['departement'];
		return $this->getColTableauValeur('departement','numDep',$numDep,$nomChampVal); 
	}	
	
	/*************************************
	*  Genere Feuille de route
	**************************************/
	public function genereFeuilleDeRoute($aData){
		$this->setData($aData);
		$this->getZoneClim();//calcul de la zone clim
		$this->jFdr = [];
		$this->jFdr['desc'] = $this->getDescription();
		$this->jFdr['parcour'] = $this->getParcour();
		$this->calcInteraction();
		$this->jFdr['parcour']['etapes'][1]['respectB2C2']= $this->respecteCritereEtape1();
		$this->jFdr['permea']= $this->remarquesPermea();
		$this->jFdr['cth'] = $this->cth();		
		if (isset($this->jData['getHtml'])){
			$this->jFdr['html'] = $this->htmlFdr($this->jFdr);
		}
		if (isset($this->jData['debug'])){
			$this->jFdr['trace']= $this->aTrace;
		}
		
		return $this->setResSatusOK($this->jFdr);
	}
	
	protected function getDescription(){
		//echo '<pre>';print_r($this->jData);die;
		//$res = [];
		//if ($jBatiment = @$this->jData['projet']['batiment']){
		//	$res = $jBatiment;
		//}
		//return $res;
		$general = $this->jData['projet']['general'];
		$batiment = $this->jData['projet']['batiment'];
		
		//affichage des surfaces saisies ou par défaut
		$batiment['surfMur'] = round($this->cth_surfMurExt());
		$batiment['surfPlancherHaut'] = round($this->cth_surfPlancherHaut());
		$batiment['surfPlancherBas'] = round($this->cth_surfPlancherBas());
		$batiment['altitude'] = $this->getAltitude();
		$batiment['permeaInit'] = $this->cth_permea_init();
		$batiment['permeaFin'] = $this->cth_permea_final();

		return [
				'general' => $general ,
				'batiment'=> $batiment 
				];		
	}
	
	protected function getParcour(){
		$res = [];
		$aDataExistant = @$this->jData['projet']['lstLot'];
		if ($aDataExistant){
			$nEtap = 1;				
			$aEtapes = $this->getParcourEtape($aDataExistant);	
			$res = array('etapes'=>$aEtapes);
		}
		return $res;
	}
	
	protected function getParcourEtape($aLot){
		$res = [];
		foreach($aLot as $nomLot=>$aTypeLot){
			foreach ($aTypeLot as $typeLot){
				
								
				if (isset($typeLot['existant']['idSol'])){
					$ligneParcour = $this->getLigneParcourLot($nomLot,$typeLot);
										
					$nEtape = $typeLot['existant']['etape'];
					if (isset($ligneParcour['nonTraite'])){
						$nEtape = 'nonTraite';
					}
					
					
					/*if (!isset($res[$nEtape])){
						$res[$nEtape] = ['lstLot'=>[]];
					}
					if (!isset($res[$nEtape]['lstLot'][$nomLot])){
						$res[$nEtape]['lstLot'][$nomLot] = ['type'=>[]];
					}*/
					$res[$nEtape]['lstLot'][$nomLot]['type'][] = $ligneParcour ;
				}
			}
		}
		if (count($res) == 0){
			return null;
		}
		ksort($res,SORT_STRING );//pour afficher les etapes dans l'ordre et les non traités à la fin
		return $res;
	}
	
	protected function getLigneParcourLot($nomLot,$typeLot){		
		$res=[];
	
		$idSol = $typeLot['existant']['idSol'];
		
		$solution = $typeLot['lstSol'][$idSol];
		$typeLotExist = $typeLot['existant'];
		
		$res=[
			'existant'=>[],			
			'descTrav'=>[],
			'recom'=>[]		
		];
	
		//colonne Existant
		foreach($typeLotExist as $nomChamp=>$val){
			if (!in_array($nomChamp,['designLot','idSol','etape','part','surfMenuiserie'])){
				$res['existant'][]=$this->getValChamp($nomChamp,$val);
			}
		}
		
		//Designation du lot
		$res['designLot']= $typeLotExist['designLot'];
		
		//colonne description des travaux et Recommandations
		foreach($solution as $nomChamp=>$val){
			if ($val[0] == 'nonTraite'){
				$res['nonTraite'] = true;		
			}

			if (!in_array($nomChamp,['idSol','perfMin'])){
				if(strpos($nomChamp,'resRecom')===0){
					$nomColonne = 'recom';
				} else {
					$nomColonne = 'descTrav';
				}
				//suprime les valeurs commençant par #
				$val = $this->supprimeListeValDieze($val);						
				$txtVal = $this->getListeValChamp($nomChamp,$val,', <br>');
				if ($txtVal) {
					$res[$nomColonne][]=$txtVal;
				}
			}
		}
		
		
		
		//performance des parois
		if (isset($solution['perfMin'])){
			$res['perfMin']=$solution['perfMin'][0] ;
		}			
		
		
		$res['existant'] = $this->arrayToList($res['existant']);
		$res['descTrav'] = $this->arrayToList($res['descTrav']);
		$res['recom'] = $this->arrayToList($res['recom']);
			

		return $res;
	}
	
	protected function arrayToList($aVal){
		$res = implode('<br>- ',$aVal);
		if ($res) {
			$res = '- '.$res;			
		}
		return $res;
	}
	
	protected function respecteCritereEtape1(){
		$res=[];
		$nbVentilation = 0;
		
		if (!$this->respecteLotEtape('ventilation',1)){
			$res[] = 'nonConformeVentil';
		}
		
		$nbEnveloppe = 0;
		if ($this->respecteLotEtape('mur',1)){
			$nbEnveloppe++;
		}
		if ($this->respecteLotEtape('plancherHaut',1)){
			$nbEnveloppe++;
		}
		if ($this->respecteLotEtape('plancherBas',1)){
			$nbEnveloppe++;
		}
		if ($this->respecteLotEtape('menuiserie',1)){
			$nbEnveloppe++;
		}
		
		if ($nbEnveloppe<2){
			$res[] = 'nonConformeEnvelop';
		}
		
		if(!count($res)){
			$res[] = 'conformeB2C2';
		}
		
		return $res;
	}
	
	protected function respecteLotEtape($nomLot,$etape=''){
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			//if ( !$this->isTypeLotNonPrioritaire($typeLot) ){ //discuté avec julien , même si non prioritaire, et non traité, le lot n'est pas considéré comme traité
				if ($etape && $etape != $typeLot['existant']['etape']){
					return false;
				}
				if ($this->isTypeLotNonTraite($typeLot)){
					return false;
				}
			//}
		}
		return true;
	}
	
	protected function remarquesPermea(){
		$res = 'permeaRecomande';
		if ($this->respecteLotEtape('plancherBas')){
			$res = 'permeaPlancherBas';
		} else if ($this->presenceIti()) {
			$res = 'permeaIti';
		}
		return $res;
	}
	
	protected function presenceIti(){
		$aTypelot = $this->jData['projet']['lstLot']['mur'];
		foreach($aTypelot as $typeLot){
			if (isset($typeLot['existant']['idSol'])){
				$sol = $this->getTypeLotSolution($typeLot);
				if (in_array('iti',$sol['resTypeIsoMur'])) {
					return true;
				}
			}
			
		}
		return false;
	}
	
	/*************************************
	* recherche des interaction
	**************************************/	
	protected function calcInteraction(){
		$res = [];
		$nbEtap = $this->getMaxEtape();
		
		for ($nEtap = 1; $nEtap<=$nbEtap ;$nEtap++){			
			$this->calcInteractionEtapeLot($nEtap,['mur']);
			$this->calcInteractionEtapeLot($nEtap,['mur','plancherHaut']);
			$this->calcInteractionEtapeLot($nEtap,['mur','plancherBas']);
			$this->calcInteractionEtapeLot($nEtap,['mur','menuiserie']);
			$this->calcInteractionEtapeLot($nEtap,['mur','ventilation']);	
			$this->calcInteractionEtapeLot($nEtap,['mur','ecs']);	
			$this->calcInteractionEtapeLot($nEtap,['mur','chauffage']);	
			
			$this->calcInteractionEtapeLot($nEtap,['plancherHaut']);
			
			$this->calcInteractionEtapeLot($nEtap,['plancherBas']);	
			
			$this->calcInteractionEtapeLot($nEtap,['menuiserie']);	
			$this->calcInteractionEtapeLot($nEtap,['menuiserie','plancherHaut']);	
			
			$this->calcInteractionEtapeLot($nEtap,['ventilation']);			
			$this->calcInteractionEtapeLot($nEtap,['ventilation','menuiserie']);	
			$this->calcInteractionEtapeLot($nEtap,['ventilation','plancherHaut']);	
			$this->calcInteractionEtapeLot($nEtap,['ventilation','chauffage']);	
			
			$this->calcInteractionEtapeLot($nEtap,['ecs']);			
			$this->calcInteractionEtapeLot($nEtap,['ecs','plancherBas']);			
			$this->calcInteractionEtapeLot($nEtap,['ecs','ventilation']);
			$this->calcInteractionEtapeLot($nEtap,['ecs','chauffage']);

			$this->calcInteractionEtapeLot($nEtap,['chauffage','plancherBas']);			
			$this->calcInteractionEtapeLot($nEtap,['chauffage']);	
					
		}		
	}
	

	
	protected function calcInteractionEtapeLot($nEtap,$aNomLot){
	
		$nomFichier = 'interaction';
		$dataEntree= [];	
		$dataEntree['planchInter']=($this->jData['projet']['batiment']['nbNiveau'] > 1) ? 'oui' : 'non';	
		$dataEntree['typeBat']=$this->jData['projet']['batiment']['typeBat'];;	
		foreach($aNomLot as $nomLot){
			$nomFichier.= ucfirst($nomLot);
			$data = $this->getInteractionDonneEntree($nomLot,$nEtap);
			
			$dataEntree = array_merge_recursive($dataEntree,$data);	
		}

		if (count($dataEntree)>0){
			$libInteraction = $this->getListeValChamp('lstLot',$aNomLot,' / ');
			$aInteraction = $this->chercheValeurTableau($nomFichier,$dataEntree,false,false);
			if (count($aInteraction)>0){
				$this->jFdr['parcour']['etapes'][$nEtap]['lstInteraction'][$libInteraction] = $aInteraction;
			}
		}
		
	}
	
	protected function getInteractionDonneEntree($nomLot,$nEtap){
		$res = [];
		//$res[$nomLot.'Traite'] = 'non';
		$lotEtape = [];		
		$lotTraite = [];		
		
		$aTypeLot = @$this->jData['projet']['lstLot'][$nomLot];
		
		foreach ($aTypeLot as $typeLot){
		
			if ($this->travauxEffectues($typeLot,$nEtap)){	
				//$res[$nomLot.'Traite'] = 'oui';
				$lotTraite[$nomLot] = 1;	
			}
			if ($typeLot['existant']['etape'] == $nEtap and !$this->isTypeLotNonTraite($typeLot)){
				$lotEtape[$nomLot] = 1;		
			}	

			$dataTypeLot = $this->getTypeLotSolutionEtExistant($typeLot,$nEtap);		
			$res = array_merge_recursive($res,$dataTypeLot);
		}
		
		$res['lotEtape']=array_keys($lotEtape);
		$res['lotTraite']=array_keys($lotTraite);
		
		return $res;
	}
	
	protected function getTypeLotSolution($typeLot){
		if (!isset($typeLot['existant']['idSol'])){
			return false;
		}
		$idSol = $typeLot['existant']['idSol'];
		return $typeLot['lstSol'][$idSol];
	}
	
	protected function getTypeLotSolutionEtExistant($typeLot,$nEtap){
		$res = $typeLot['existant'];		
		$dataSol = $this->getTypeLotSolution($typeLot);
		if ($dataSol){
			$res = array_merge($res,$dataSol);
		}
		return $res;
		
	}
	
	protected function isTypeLotNonTraite($typeLot){
		
		$sol = $this->getTypeLotSolution($typeLot);
		if ($sol){
			if ($this->isSolutionNonTraite($sol)){			
				return true;
			}
		}
		return false;
	}
	
	protected function isSolutionNonTraite($sol){
		foreach($sol as $aValue){
			if (in_array('nonTraite',$aValue)){	
				return true;
			}
		}
		return false;
	}
	
	protected function isTypeLotNonPrioritaire($typeLot){
		foreach ($typeLot['lstSol'] as $sol){			
			foreach($sol as $aValue){				
				if (in_array('nonPrioritaire',$aValue)){
					return true;
				}
				
			}
		}
		return false;
	}
	
	protected function isTypeBatLC(){
		return ($this->jData['projet']['batiment']['typeBat']=='LC');
	}
	
	protected function getMaxEtape(){
		$maxEtape = 0;
		$aLstLot = @$this->jData['projet']['lstLot'];
		foreach ($aLstLot as $aTypeLot){
			foreach ($aTypeLot as $lot){
				$maxEtape = max($maxEtape, $lot['existant']['etape']);
			}
		}
		return $maxEtape;
	}
	

	protected function getTypeLotPrinc($nomLot){
		$maxPart = 0;
		$typeLotRes = null;
		
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			if ($nomLot == 'menuiserie'){
				$partLot = $typeLot['existant']['surfMenuiserie'];
			} else {
				$partLot = $typeLot['existant']['part'];
			}
			if($partLot > $maxPart){
				$typeLotRes = $typeLot;
				$maxPart = $typeLotRes;
			}
		}
		return $typeLotRes; 
	}
	
	protected function paramChampAvantApres($typeLot,$nEtap,$champAvant,$paramAvant,$champApres,$paramApres){	
		if ($this->travauxEffectues($typeLot,$nEtap)){	
			$valChamp = $this->getTypeLotSolution($typeLot)[$champApres][0];		
			return $this->getColTableauValeur($champApres,'id',$valChamp,$paramApres); 
		} else {
			$valChamp = $typeLot['existant'][$champAvant];
			return $this->getColTableauValeur($champAvant,'id',$valChamp,$paramAvant); 
		}
	}
	
	protected function travauxEffectues($typeLot,$nEtap){
		return ($nEtap >= $typeLot['existant']['etape']) && !$this->isTypeLotNonTraite($typeLot);
	}

	protected function ecsConnectChauff($typeLot,$nEtap){	
		$typeEcs = $this->paramChampAvantApres($typeLot,$nEtap,'typeEcs','id','resTypeProdEcs','id');
		if (strpos($typeEcs,'connectChauf')===0){
			return true;
		} else {
			return false;
		}
	}
	
	protected function ecsConnectChauff_etapeFinale($typeLot){	
		$etapeFinaleLot = $typeLot['existant']['etape'];
		return $this->ecsConnectChauff($typeLot,$etapeFinaleLot);
	}
	
	/*************************************
	* recherche des Solutions
	**************************************/
	
	public function genereSolutions($aData){
		$this->setData($aData);
		
		$res=array();
		//echo '<pre>'; print_r($aData);die;
		
		$aDataProjet = @$aData['projet']['lstLot'];
		if ($aDataProjet){
			$aSol = $this->chercheSolutionListeLot($aDataProjet);				
			$res['solutions'] = $aSol;			
		}
		
		return $this->setResSatusOK($res);	
		
	}

	protected function chercheSolutionListeLot($aLstLot){
		$aRes = array();
		//on cherche les solutions que pour le premier lot de la liste
		$nomLot = array_keys($aLstLot)[0];	
		$dataLot = $aLstLot[$nomLot];
	
		$solLot = $this->chercheSolutionLot($nomLot,$dataLot);
		$aRes[$nomLot] = $solLot;
		
		return $aRes;
	}
	
	
	protected function chercheSolutionLot($nomLot,$dataLot){
		$res=array();
		
		foreach ($dataLot as $iType=>$typeLot){
			$lstSol = $this->chercheSolutionLotType($nomLot,$typeLot);
			$res[$iType] = ['lstSol'=>$lstSol];
		}		
		return $res;
	}
	
	protected function chercheSolutionLotType($nomLot,$typeLot){
		$infoExsistant = $typeLot['existant'];
		$infoExsistant['typeBat'] = $this->jData['projet']['batiment']['typeBat'];
		//pour le chauffage on a besoin du type d'ecs choisie
		if ($nomLot == 'chauffage'){
			$infoExsistant['chauffLieEcsApTrav'] = $this->ecsConnectChauff_etapeFinale($this->getTypeLotPrinc('ecs'))? 'oui' : 'non';
		}
		
		$lstSol = $this->chercheValeurTableau('logigrame'.ucfirst($nomLot),$infoExsistant,true,false);
		//recherhedes perf min
		foreach($lstSol as $idSol => $sol){
			if(in_array($nomLot,['mur','plancherHaut','plancherBas','menuiserie'])){
				if ($this->isSolutionNonTraite($sol)){
					$pergMin = 0;
				} else {
					$pergMin = [$this->cherchePerfMin($nomLot,$infoExsistant,$sol)];
				}
				$lstSol[$idSol]['perfMin'] = $pergMin;
			}
		}
		return $lstSol;
		
	}
	
	protected function cherchePerfMin($nomLot,$infoExsistant,$infoSolution){
		$infoParaoi = [];
		$infoParaoi["nomLot"] = $nomLot;
		$infoParaoi["typeBat"] = $this->jData['projet']['batiment']['typeBat'];
		$zoneClim = $this->getZoneClim();
		$infoParaoi["zoneClim"] = subStr($zoneClim,0,2);
		$infoParaoi["resTypeIsoMur"] = isset($infoSolution['resTypeIsoMur'][0]) ? $infoSolution['resTypeIsoMur'][0] : '';
		$infoParaoi["contactPlancherHaut"] = isset($infoExsistant['contactPlancherHaut']) ? $infoExsistant['contactPlancherHaut'] : '';	

		$perfMin = $this->chercheValeurTableau('performanceParois',$infoParaoi);
		if ($perfMin){
			return $perfMin['objectif'];
		}
		return '';
	}
	
	

	/*************************************
	* Gestion des projets
	**************************************/
	
	
	
	public function enregistreProjet($jData){		
						
		$userId = CMSEffiUtils::getCMSUserID();
		if (!$userId){
			return ['status'=>'NO_USER_ID'];
		}		
		
		$this->setData($jData);
		$data = [
			'userId'=>$userId,
			'nom'=>$this->jData['projet']['general']['nomProjet'],
			'data'=>json_encode($this->jData),
			'dateModif'=>date('Y-m-d H:i:s'),
			'version'=>$this::VERSION
		];
		//print_r(json_encode($this->jData)); die;
		
		if (!empty($this->jData['projet']['general']['id'])){
			//modification
			$projId = $this->jData['projet']['general']['id'];
			$res = CMSEffiUtils::updateCMSData($data,'projetb2c2'," id=$projId AND userId=$userId");			
		} else {
			//nouvel enregistrement
			$res = CMSEffiUtils::saveCMSData($data,'projetb2c2');	
			if ($res){ $projId = $res ;}
		}
		
		if (!$res){
			return ['status'=>'SAVE_ERROR'];
		}
		
		return $this->setResSatusOK(['id'=>$projId]);
	}
	
	public function copieProjet($projId){
		$data = $this->chargeDonneeProjet($projId);
		if ($data){
			unset($data['projet']['general']['id']);
			$data['projet']['general']['nomProjet'].='-(copie)';
			return $this->enregistreProjet($data);
		}
		return ['status'=>'COPY_ERROR'];
	}
	
	public function supprimeProjet($projId){
		$projId	= intval($projId);
		$userId = CMSEffiUtils::getCMSUserID();
		$res = CMSEffiUtils::deleteCMSData(" id=$projId AND userId=$userId",'projetb2c2' );
		if (!$res){
			return ['status'=>'SAVE_ERROR'];
		}
		
		return $this->setResSatusOK([]);		
	}
	
	public function chargeDonneeProjet($projId){
		$projId	= intval($projId);
		$userId = CMSEffiUtils::getCMSUserID();
		$res = CMSEffiUtils::queryCMSData(['id','data','userId','version'],'projetb2c2', " id=$projId");
		if ($res AND count($res)){
			
			if (!(
					($res[0]['userId'] == $userId OR 
					CMSEffiUtils::userIsManagerB2C2())
				)){
				return false;
			}			
			
			$this->jData = json_decode($res[0]['data'],true);
			if ($res[0]['version'] != $this::VERSION){
				$this->jData['versionDifferent'] = true;				
			}
			
			if (!$this->verifieSolutionsVersion()){
				$this->jData['solutionDifferent'] = true;
			}			
			
			$this->jData['projet']['general']['id'] = $res[0]['id'];
			//echo '<pre>'; var_dump($data);die;
			return $this->jData;
		}
		return false;
	}
	
	protected function verifieSolutionsVersion(){
		//verifie si les solutions dans la nouvelles version sont les mêmes. sinon on effece les solutions
		//$newSol = $this->genereSolutions($data);
		$bOk = true;
		
		foreach($this->jData['projet']['lstLot'] as $nomLot => &$lstTypeLot){
			foreach($lstTypeLot as $iTypeLot => &$typeLot){
				if (isset($typeLot['lstSol'])){
					//on utilise l'option JSON_NUMERIC_CHECK pour que les valeurs "0" soit égales aux valeurs 0 (php encode diférament de javascript)
					$sOldSol = json_encode($typeLot['lstSol'],JSON_NUMERIC_CHECK);
					$jNewSol = 	$this->chercheSolutionLotType($nomLot,$typeLot);
					$sNewSol = json_encode($jNewSol,JSON_NUMERIC_CHECK);			
					if ($sOldSol<>$sNewSol){
						$bOk = false;
						unset($typeLot['lstSol']);
						unset($typeLot['existant']['idSol']);
					}
				}
			}		
		}
		return $bOk;
	}

	public function getListeProjetUtilisateur(){								
		$userId = CMSEffiUtils::getCMSUserID();
		$res = CMSEffiUtils::queryCMSData(['id','nom','dateModif','version'],'projetb2c2',"userId=$userId",'dateModif DESC');
		return $res;
	}

	
	/*************************************
	* Utils
	**************************************/	
	protected function trace($msg,$class=''){
		if (isset($this->jData['debug'])){
			if ($class){
				$msg = '<span class="'.$class.'">'.$msg.'</span>';
			}
			$this->aTrace[] = $msg;
		}
	}
	
	public function setData($jData){
		$this->jData = $jData;
	}
	
	protected function setResSatusOK($res=[]){
		return $this->setResSatus($res,'OK');
	}
	
	protected function setResSatus($res=[],$status){
		$res['status']=$status;
		return $res;
	}
	
	
}
	