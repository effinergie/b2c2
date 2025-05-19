<?php
require_once 'baseB2C2.php';

const PERF_OK = 'OK';
const PERF_INSUFFISANTE = 'INSUF';
const PERF_INCONNUE = 'INCONNUE';

class ApiSolB2C2 extends baseB2C2{
	protected $jData;
	protected $jFdr;
	protected $aTrace = [];

	protected $conformePerfEnvPremEtape = [];
	
	
	const OK = 'OK';
	const erreur = 'erreur';
	const warn1 = 'warn1';
	const warn2 = 'warn2';
	const warn3 = 'warn3';
	
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

		if (! $valTrouvee = $this->chercheValeurTableau('typologieBatiment',$aData)){	
			return $this->setResSatusError([]);
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

		//copie des champs nécessaires pour la recherche de typologies dans certains lots
		$aData['anneeConstr'] = $valTrouvee['anneeConstr'];
		$aData['typeBat'] = $valTrouvee['typeBat'];

		$solutionLot = [];
		foreach ($lstLot as $nomLot){
			if ($valTrouvee = $this->chercheValeurTableau('typologie'.ucfirst($nomLot),$aData)){	
				//recherche des valeurs d'entrée en fonction de la typo
				$this->jData['projet']['lstLot'][$nomLot][0]['existant']  = $valTrouvee;
			}		
		}
		
		$this->chercheSolutionProjet();
	
		return $this->setResSatusOK($this->jData);
	}
	
	protected function chercheSolutionProjet(){
		//affecte les customVal		
		$this->affecteCustomVal();
		
		//recherche des solutions pour chaque lots
		foreach ($this->jData['projet']['lstLot'] as $nomLot=>$lot){			
			//recherche des solutions en fonction des valeurs d'entrées
			$aLstSol = $this->chercheSolutionLot($nomLot,$this->jData['projet']['lstLot'][$nomLot]);
			//affectation des solutions
			$this->jData['projet']['lstLot'][$nomLot] = array_replace_recursive($this->jData['projet']['lstLot'][$nomLot],$aLstSol);
		}
	}
	
	public function testeTypologie(){
		$data=array();
		
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

		die;
	}
	
	protected function testeTypologieData($data){
		$res = '';

		$projet = $this->genereTypologie($data);
		foreach ($projet['projet']['lstLot'] as $nomLot=>$aTypeLot){
			foreach($aTypeLot as $numLot => $typeLot){
				$idSol = $typeLot['existant']['idSol'] ?? '';

				if (!isset($typeLot['lstSol'][$idSol])){
					
					$res.= "$nomLot n°$numLot : Solution n°$idSol n'existe pas<br>";
					//affiche solution dispo
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

		$this->jFdr['cth'] = $this->cth();	

		$this->jFdr['parcour']['respectB2C2']['critPremEtape'] = $this->respecteCriterePermEtape();
		$this->jFdr['parcour']['respectB2C2']['complPermEtape'] = $this->exigeancesComplPermEtape();
		$this->jFdr['parcour']['respectB2C2']['critDernEtape'] = $this->respecteCritereDernEtape();
		$this->jFdr['parcour']['respectB2C2']['complDernEtape'] = $this->exigeancesComplDernEtape();

		$this->testGESDegrade();
		
		if (isset($this->jData['getHtml'])){
			$this->jFdr['html'] = $this->htmlFdr($this->jFdr);
		}
		if (isset($this->jData['debug'])){
			$this->jFdr['trace']= $this->aTrace;
		}
		
		return $this->setResSatusOK($this->jFdr);
	}
	
	protected function getDescription(){

		$general = $this->jData['projet']['general'];
		$batiment = $this->jData['projet']['batiment'];
		
		//affichage des surfaces saisies ou par défaut
		$batiment['hautSousPlaf'] = $this->cth_hsp();
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
		$aDataExistant = $this->jData['projet']['lstLot'] ?? '';
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
				
								
				if (isset($typeLot['existant']['idSol']) AND !$this->isTypeLotElementSupprime($typeLot)){
					$ligneParcour = $this->getLigneParcourLot($nomLot,$typeLot);

					$nEtape = $typeLot['existant']['etape'];
					if (isset($ligneParcour['nonTraite'])){
						$nEtape = 'nonTraite';
					}
					
					$res[$nEtape]['lstLot'][$nomLot][] = $ligneParcour ;
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
	
		$nomChampPerfFinal = $this->getNomChampPerfFinal($nomLot);
		
		$solution = $this->getTypeLotSolution($typeLot);
		$typeLotExist = $typeLot['existant'];
		
		$res=[
			'existant'=>[],			
			'descTrav'=>[],
			'recom'=>[]		
		];
	
		//colonne Existant
		foreach($typeLotExist as $nomChamp=>$val){
			if (!in_array($nomChamp,['designLot','idSol','etape','part','surfMenuiserie','inexistant',$nomChampPerfFinal])
				&& strpos($nomChamp,'_customVal')===false && strpos($nomChamp,'_cVal')===false){
					
				$res['existant'][]= $this->getCustomValTxt($nomChamp,$typeLotExist);
				
			}
		}
		
		//Designation du lot
		$res['designLot']= ucfirst($typeLotExist['designLot']);
		
		//colonne description des travaux et Recommandations
		foreach($solution as $nomChamp=>$val){
			if ($val[0] == 'nonTraite'){
				$res['nonTraite'] = true;
				$val[0] = $this->getValChamp('remarqueSolution',$val[0]);				
			}

			if (!in_array($nomChamp,['idSol','perfMin'])){
				//suprime les valeurs commençant par #
				$val = $this->supprimeListeValDieze($val);	
				
				if(strpos($nomChamp,'resRecom')===0){
					$nomColonne = 'recom';
					$txtVal = $this->getListeValChampCol($nomChamp,'lib',$val,'. <br>');
				} else {
					$nomColonne = 'descTrav';
					$txtVal = $this->getListeValChamp($nomChamp,$val,'. <br>');
				}
									
				
				if ($txtVal) {
					$res[$nomColonne][]=$txtVal;
				}
			}
		}
		
		//performance des parois
		//intitailisation
		if (!isset($this->conformePerfEnvPremEtape[$nomLot])) $this->conformePerfEnvPremEtape[$nomLot] = PERF_OK;
		
		if (isset($solution['perfMin'])){			
			if (!isset($typeLotExist[$nomChampPerfFinal]) OR  $typeLotExist[$nomChampPerfFinal] !== 'customVal'){
				//perf Inconnue
				$res['perfFinale'] = $this->getPerfMinTxt($nomLot,$solution);
							
				if ($this->conformePerfEnvPremEtape[$nomLot] == PERF_OK AND $this->travauxEffectues($typeLot,1)){
						$this->conformePerfEnvPremEtape[$nomLot] = PERF_INCONNUE;
				}
			} else {
				$res['perfFinale'] = $this->getCustomValTxt($nomChampPerfFinal,$typeLotExist);
				if (!$this->testePerfFinal($nomLot,$typeLot)){
					//perf insuffisante
					$res['perfFinaleErr'] = 'La performance est insuffisante  : '.$this->getPerfMinTxt($nomLot,$solution).'';
					
					if ($this->travauxEffectues($typeLot,1)){
						$this->conformePerfEnvPremEtape[$nomLot] = PERF_INSUFFISANTE;
					}
				}
			}
		}		
		
		$res['existant'] = $this->arrayToList($res['existant']);
		$res['descTrav'] = $this->arrayToList($res['descTrav']);
		$res['recom'] = $this->arrayToList($res['recom']);
			

		return $res;
	}
	
	protected function getNomChampPerfFinal($nomLot){
		return 'perf'.ucfirst($nomLot).'Final';
	}
	
	protected function testePerfFinal($nomLot,$typeLot){
		$nomChampPerfFinal = $this->getNomChampPerfFinal($nomLot);
		$perfFinal = $typeLot['existant'][$nomChampPerfFinal];
		
		if ($perfFinal !== 'customVal'){
			return true;
		} else {
			$solution = $this->getTypeLotSolution($typeLot);
			$perfMin = $this->floatvalFr($solution['perfMin'][0]);
			$perfFinal = $this->floatvalFr($typeLot['existant'][$nomChampPerfFinal.'_customVal']);
			if ($nomLot=='menuiserie'){
				return $perfFinal <= $perfMin;
			} else {
				return $perfFinal >= $perfMin;
			}
		}		
	}
	
	protected function getPerfFinal($nomLot,$typeLot){
		$nomChampPerfFinal = $this->getNomChampPerfFinal($nomLot);
		$perfFinal = $typeLot['existant'][$nomChampPerfFinal];
		if ($perfFinal !== 'customVal'){
			$solution = $this->getTypeLotSolution($typeLot);
			return $this->floatvalFr($solution['perfMin'][0]);
		} else {
			return $this->floatvalFr($typeLot['existant'][$nomChampPerfFinal.'_customVal']);
		}
	}
	
	protected function getPerfMinTxt($nomLot,$solution){
		if ($nomLot=='menuiserie'){
			return 'le Uw doit être ≤ '.$solution['perfMin'][0].' W/(m².K)';
		} else {
			return 'le R doit être ≥ '.$solution['perfMin'][0].' m².K/W';
		}
	}
	
	
	protected function getCustomValTxt($nomChamp,$lstChampVal){
		$val = $lstChampVal[$nomChamp];
		
		$txtVal = $this->getValChamp($nomChamp,$val);
				
		if ($val=='customVal'){
			$txtVal = $this->getColTableauValeur($nomChamp,'id',$val,'cVal');
			$cVal = round($lstChampVal[$nomChamp.'_customVal'],2);
			$txtVal = str_replace('#',$cVal,$txtVal);
		}		
		
		return $txtVal;
	}
	
	protected function arrayToList($aVal){
		$res = implode('<br>- ',$aVal);
		if ($res) {
			$res = '- '.$res;			
		}
		return $res;
	}
	
	/***************************************************************
	* Crtères Etape 1
	***************************************************************/
	
	protected function respecteCriterePermEtape(){
		$res=[];
		$nbVentilation = 0;
	
		$res['conformeVentil'] = $this->lotTravauxEffectuesAEtape('ventilation',1) ? self::OK : self::erreur;
		
		$aNomLotEnveloppe = ['mur','plancherHaut','plancherBas','menuiserie'];
		$nbPerfOk = 0;
		$nbPerfInsuf = 0;
		$nbEnveloppe = 0;
		foreach ($aNomLotEnveloppe as $nomLot){
			
			$part = $this->getPartLotTraite($nomLot,1);
			if ($part >= 1){
				$nbEnveloppe++;				
				if ($this->conformePerfEnvPremEtape[$nomLot] == PERF_OK){
					$nbPerfOk++;
				} else if ($this->conformePerfEnvPremEtape[$nomLot] == PERF_INCONNUE){ 
					$nbPerfInsuf++;
				}
			}
		}

		$res['conforme100pctPosteTravaux'] = ($nbEnveloppe>=2)? self::OK : self::erreur;
			

		if ($nbPerfOk >= 2){
			$res['conformePerfEnv'] = self::OK ;
		} else if ($nbPerfInsuf >= 2){
			$res['conformePerfEnvInconnue'] = self::warn2;
		} else {
			$res['conformePerfEnv'] = self::erreur; //PERF_INSUFFISANTE
		}
		
	

		$res['conformeEtiquetteC'] = $this->respecteEtiquetteCEtape1()? self::OK : self::erreur;
		
		$res['conformeTroisEtapesMax'] = ($this->getMaxEtape()<=3)? self::OK : self::erreur;
		

		
		if ($this->presenceToitTerrasse()){
			$res['conformePerfToitTerrasse'] = self::warn2;
		}
	
	
		//liste critères : https://www.consultations-publiques.developpement-durable.gouv.fr/arrete-relatif-au-contenu-et-aux-conditions-d-a2826.html

		return $res;
	}
	
	
	


	
	protected function respecteEtiquetteCEtape1(){
		$etiquette = $this->calcResult['etape']['1']['consoTotal']['classeDpe'];
		return $etiquette <="C"; 
	}	
	
	protected function respecteBaisse40pctEtape1(){
		$consoInit = $this->calcResult['etape']['0']['consoTotal']['cep'];
		$consoEtape1 = $this->calcResult['etape']['1']['consoTotal']['cep'];
		$gain = ($consoInit-$consoEtape1) / $consoInit;
		return $gain>0.4; 
	}
	
	protected function exigeancesComplPermEtape(){
		$res = [];
		$res['permeaPremEtape'] = self::warn2;
		
		if ($this->lotTravauxEffectuesAEtape('ventilation',1)){
			//si ventilation traité à l'étape 1 
			$res['ventilationPremEtape'] = self::warn2;
		}
		
		if ($this->lotTravauxEffectuesAEtape('chauffage',1)){
			//si chauffage traité à l'étape 1 
			$res['chauffagePremEtape'] = self::warn2;
		}
		
		return $res;
	}	
	
	protected function presenceToitTerrasse(){
		$aTypeLot = $this->jData['projet']['lstLot']['plancherHaut'];		
		foreach ($aTypeLot as $typeLot){
			if (isset($typeLot['existant']['contactPlancherHaut']) AND $typeLot['existant']['contactPlancherHaut']=='toitTerrasse'){
				return true;
			}
		}
		return false;
	}
	
	/***************************************************************
	* Crtères derniere Etape
	***************************************************************/
	
	protected function respecteCritereDernEtape(){
		$res=[];
		
		$res['conformeEtiquetteAouB'] = $this->respecteEtiquetteAouBDernEtape() ? self::OK : self::erreur;	
		$res['conformeUbat'] = $this->respecteUbatDernEtape();
		
		return $res;
	}	
	
	protected function respecteEtiquetteAouBDernEtape(){
		$nbEtap = $this->getMaxEtape();
		
		$etiquette = $this->calcResult['etape'][$nbEtap]['consoTotal']['classeDpe'];
		return $etiquette <="B"; 
	}
	
	protected function respecteUbatDernEtape(){
		$nbEtap = $this->getMaxEtape();
		
		$ubat = $this->calcResult['etape'][$nbEtap]['ubat']['ubat'] ?? '';
		$ubatBase = $this->calcResult['etape'][$nbEtap]['ubat']['ubatBase'] ?? '';
		
		if ($ubat AND $ubatBase){
			if (floatval($ubat) <= floatval($ubatBase)){
				return self::OK;
			} else {
				return self::erreur;
			}
		} 
		
		return self::warn2;
	}

	protected function exigeancesComplDernEtape(){
		$res = [];
		$res['permeaDernEtape'] = self::warn2;
		
		$res['protectionSolaireDernEtape'] = self::warn2;		
		
		if ($this->lotTravauxEffectuesApresEtape('chauffage',1)){
			$res['chauffageAutreEtape'] = self::warn2;
		}
		

		$res['calorifugeageResaux'] = self::warn2;
		$res['regulationChaudFroid'] = self::warn2;
		
		return $res;
	}	
	
	protected function testGESDegrade(){
		$gesPrec = 0;
		
		foreach ($this->jFdr['cth']['etape'] as $iEtape=>$etape){
			$ges = floatval($etape['consoTotal']['ges']);
			if ($iEtape>0){			
				$this->jFdr['parcour']['etapes'][$iEtape]['GESDegrade'] = ($ges>$gesPrec) ? 'oui':'non';
			}			
			$gesPrec = $ges;	
		}
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
			$this->calcInteractionEtapeLot($nEtap,['menuiserie','plancherBas']);	
			
			$this->calcInteractionEtapeLot($nEtap,['ventilation']);			
			$this->calcInteractionEtapeLot($nEtap,['ventilation','menuiserie']);	
			$this->calcInteractionEtapeLot($nEtap,['ventilation','plancherHaut']);	
			$this->calcInteractionEtapeLot($nEtap,['ventilation','chauffage']);	
			
			$this->calcInteractionEtapeLot($nEtap,['ecs']);			
			$this->calcInteractionEtapeLot($nEtap,['ecs','plancherBas']);			
			$this->calcInteractionEtapeLot($nEtap,['ecs','ventilation']);
			$this->calcInteractionEtapeLot($nEtap,['ecs','chauffage']);

			$this->calcInteractionEtapeLot($nEtap,['chauffage','plancherBas']);			
			$this->calcInteractionEtapeLot($nEtap,['chauffage','plancherHaut']);			
			$this->calcInteractionEtapeLot($nEtap,['chauffage']);
					
		}	

	}
	
	protected function calcInteractionEtapeLot($nEtap,$aNomLot){
	
		$nomFichier = 'interaction';
		foreach($aNomLot as $nomLot){
			$nomFichier.= ucfirst($nomLot);
		}
		
		$aDataEntree = $this->getInteractionDonneEntree($aNomLot,$nEtap);
		
		$aInteracEtape=[];
		$aInteracGlob=[];
		
		foreach ($aDataEntree as $dataEntree ){
			if (count($dataEntree)>0){
				$libInteraction = implode('/',$aNomLot);//$this->getListeValChamp('lstLot',$aNomLot,' / ');
				
				
				//ajout des champs manquant pour faire les tests
				$dataEntree = $this->ajouteChampManquantEntree($nomFichier,$dataEntree);

				$aInteraction = $this->chercheValeurTableau($nomFichier,$dataEntree,false,false);

				//on sépare les intéragtion inter étapes et globales
				foreach($aInteraction as $id =>$lgnInter){					
					if(empty($lgnInter['niveau'])){
						$aInteracEtape[$id] = $lgnInter;
					} else {
						$aInteracGlob[$id] = $lgnInter;
					}
					
				}

					
			}					
		}
		
					
		if (count($aInteracEtape)>0){
			$this->jFdr['parcour']['etapes'][$nEtap]['lstInteracEtape'][$libInteraction] = array_values($aInteracEtape);
		}
		if (count($aInteracGlob)>0){
			$this->jFdr['parcour']['etapes'][$nEtap]['lstInteracGlob'][$libInteraction] = array_values($aInteracGlob);
		}
		
		
	}
	
	protected function ajouteChampManquantEntree($nomTableau,$aData){
		$champManquant = $this->getChampManquantEntree($nomTableau,$aData);
		if ($champManquant!==false){
			foreach ($champManquant as $nomChamp){
				$aData[$nomChamp]=[];
			}
		}
		
		return $aData;
	}
	
	protected function getChampManquantEntree($nomTableau,$aData){
		//renvoie le nom des champ manquant
		$aLignesTableauValeur = $this->getTableauValeur($nomTableau);
		foreach ($aLignesTableauValeur as $nlig=>$ligneTableau){
			$res = [];
			foreach($ligneTableau as $nomChampBrut=>$valTableau){
				if($nomChampBrut[0] !== '-'){
					if (!isset($aData[$nomChampBrut])){
						$res[] =  $nomChampBrut;
					}
				}
			}
			if (count($res)){
				return $res;
			}
			return false;
		}
	}

	protected function getInteractionDonneEntree($aNomLot,$nEtap){
		$aDataCompare = [];	

		if (count($aNomLot) == 1){
			$aNomLot[1] = $aNomLot[0];
		}
		
		$aTypeLot0 = $this->jData['projet']['lstLot'][$aNomLot[0]] ?? '';
		$aTypeLot1 = $this->jData['projet']['lstLot'][$aNomLot[1]] ?? '';
		
		foreach ($aTypeLot0 as $typeLot0){
			foreach ($aTypeLot1 as $typeLot1){
				$lgnCompare = [];
				
				$lgnCompare['planchInter']=($this->jData['projet']['batiment']['nbNiveau'] > 1) ? 'oui' : 'non';	
				$lgnCompare['typeBat']=$this->jData['projet']['batiment']['typeBat'];	
				
				$dataTypeLot = $this->getInteractionDonneEntreeTypeLot($aNomLot[0],$typeLot0,$nEtap);
				$lgnCompare = array_merge_recursive($lgnCompare,$dataTypeLot);
			
				$dataTypeLot = $this->getInteractionDonneEntreeTypeLot($aNomLot[1],$typeLot1,$nEtap);
				$lgnCompare = array_merge_recursive($lgnCompare,$dataTypeLot);

				$aDataCompare[] = $lgnCompare;
			}
		}
		

		return $aDataCompare;
	}
	
	protected function getInteractionDonneEntreeTypeLot($nomLot,$typeLot,$nEtap){
		//##### Penser à exclure les non préconisés

		$res = [];

		if (!$this->isTypeLotEtapeSupprime($typeLot,$nEtap)){

			$dataTypeLot = $this->getTypeLotSolutionEtExistant($typeLot);

			if ($this->travauxEffectuesPlusTard($typeLot,$nEtap)){	
				//si travaux effectues plus tard, alors on renomme les clés en les préfixan par plus tard
				foreach($dataTypeLot as $key => $val){
					$dataTypeLot['apres_'.$key] = $val;
					unset($dataTypeLot[$key]);
				}
				
				$dataTypeLot['lotTraiteApres'] = [$nomLot];// le lot sera traité à étape ultérieure				
				
			}
			
			
			if ($this->travauxEffectues($typeLot,$nEtap)){	
				$dataTypeLot['lotTraite'] = [$nomLot]; //le lot a été traité à cette étape ou à une étape précédente
			}
			
			if ($typeLot['existant']['etape'] == $nEtap and !$this->isTypeLotNonTraite($typeLot)){
				$dataTypeLot['lotEtape'] = [$nomLot]; //le lot a été traité à cette étape exactement
			}				
			$res = array_merge_recursive($res,$dataTypeLot);
		}

		return $res;
	}
	
	
	
	
	
	

	
	
	protected function getTypeLotSolution($typeLot){
		if (!isset($typeLot['existant']['idSol'])){
			return false;
		}
		$idSol = $typeLot['existant']['idSol'];
		return $typeLot['lstSol'][$idSol];
	}
	
	protected function getTypeLotSolutionEtExistant($typeLot){
		$res = $typeLot['existant'];		
		$dataSol = $this->getTypeLotSolution($typeLot);
		if ($dataSol){
			$this->supprimeCarDiezeLstVal($dataSol);//supprime les # sur chaques valeurs car sur les logigrames cetraines valeurs on un # pour ne pas être affichées dans la feuille de route.
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
	
	
	protected function isTypeLotEtapeSupprime($typeLot,$nEtap){
		return (isset($typeLot['existant']['etapeSupprime']) AND $nEtap>=$typeLot['existant']['etapeSupprime']) ;
	}
	
	protected function isTypeLotElementExisteEtape($typeLot,$etape){							
		if ($this->travauxEffectues($typeLot,$etape)){
			return !$this->isTypeLotElementSupprime($typeLot);
		} else {
			return $this->isTypeLotElementExisteEtatInitial($typeLot);
		}			
	}
	
	protected function isTypeLotElementExisteEtatInitial($typeLot){
		return empty($typeLot['existant']['inexistant']);
	}		
	
	protected function isTypeLotElementSupprime($typeLot){		
		$sol = $this->getTypeLotSolution($typeLot);
		if ($sol){
			if ($this->isSolutionElementSupprime($sol)){			
				return true;
			}
		}
		return false;
	}
	
	protected function isSolutionElementSupprime($sol){
		foreach($sol as $aValue){
			if (in_array('elementSupprime',$aValue)){	
				return true;
			}
		}
		return false;
	}
	
	
	protected function isTypeLotNonPrioritaire($typeLot){
		foreach ($typeLot['lstSol'] as $sol){			
			foreach($sol as $aValue){	
if (!is_array($aValue)){
	$aValue = [$aValue];//pour palier au fait que perfMin n'est pas dans un array... Corriger perfMin?
}	
				if (in_array('nonPrioritaire',$aValue,true)){
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
		$aLstLot = $this->jData['projet']['lstLot'] ?? '';
		foreach ($aLstLot as $aTypeLot){
			foreach ($aTypeLot as $typeLot){
				if (!$this->isTypeLotNonTraite($typeLot)){
					$maxEtape = max($maxEtape, $typeLot['existant']['etape']);
				}
			}
		}
		return $maxEtape;
	}
	
	protected function getPartLotTraite($nomLot,$etape){
		$totalPart = 0;
		$totalPartTraite = 0;
		
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			if ($nomLot == 'menuiserie'){
				$partLot = $typeLot['existant']['surfMenuiserie'];
			} else {
				$partLot = $typeLot['existant']['part'];
			}
			// Après discution avec Angélique : une surface est considérée comme traitée même si elle était déjà isolée. et il faut que 100% du lot soit traité, même les surfaces déjà isolées.
			//if ( !$this->isTypeLotNonPrioritaire($typeLot)  // dans les cas où on doit isoler tous les murs sauf un mur qui était déjà suffisamment isolé.			
			//	OR 
			//	$this->travauxEffectues($typeLot,$etape) // dans les cas où on a isolé la toiture, alors qu'elle étai déjà suffisamment isolée
			//){			
				if ($this->isTypeLotElementExisteEtape($typeLot,$etape)){
					$totalPart+=$partLot;					
					if ($this->travauxEffectues($typeLot,$etape)){
						$totalPartTraite+=$partLot;			
					}	
				}	
			//}
		}
		
		if ($totalPart>0){
			return $totalPartTraite/$totalPart;
		} 
		return 0;
	}

	protected function getTypeLotPrinc($nomLot){
		$maxPart = -1;
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
	
	protected function lotTravauxEffectuesAEtape($nomLot,$nEtap,$bConsidereNonPrioritaireCommeTraite = false){
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			if ( !$this->isTypeLotNonPrioritaire($typeLot) OR !$bConsidereNonPrioritaireCommeTraite){ //discuté avec julien , même si non prioritaire, et non traité, le lot n'est pas considéré comme traité
				if (!$this->travauxEffectuesAEtape($typeLot,$nEtap)){
					return false;
				}								
			}
		}
		return true;
	}	
		
		
	protected function lotTravauxEffectuesApresEtape($nomLot,$nEtap,$bConsidereNonPrioritaireCommeTraite = false){
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			if ( !$this->isTypeLotNonPrioritaire($typeLot) OR !$bConsidereNonPrioritaireCommeTraite){ //discuté avec julien , même si non prioritaire, et non traité, le lot n'est pas considéré comme traité
				if (!$this->travauxEffectuesPlusTard($typeLot,$nEtap)){
					return false;		
				}						
			}
		}
		return true;
	}	
				
	
	protected function travauxEffectues($typeLot,$nEtap){
		return ($nEtap >= $typeLot['existant']['etape']) && !$this->isTypeLotNonTraite($typeLot);
	}
	
	protected function travauxEffectuesAEtape($typeLot,$nEtap){
		return ($nEtap == $typeLot['existant']['etape']) && !$this->isTypeLotNonTraite($typeLot);
	}	
	
	protected function travauxEffectuesPlusTard($typeLot,$nEtap){
		return ($nEtap < $typeLot['existant']['etape']) && !$this->isTypeLotNonTraite($typeLot);
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
		if ($this->isTypeLotElementSupprime($typeLot) OR !$this->isTypeLotElementExisteEtatInitial($typeLot)){
			return false;
		}		
		$etapeFinaleLot = $typeLot['existant']['etape'];
		return $this->ecsConnectChauff($typeLot,$etapeFinaleLot);
	}

	protected function ecsPrincConnectChauff_etapeFinale(){
		//return $this->ecsConnectChauff_etapeFinale($this->getTypeLotPrinc('ecs'));	//ne gère pas les éléments supprimés
		$aTypelot = $this->jData['projet']['lstLot']['ecs'];
		foreach($aTypelot as $typeLot){		
			if ($this->ecsConnectChauff_etapeFinale($typeLot)){
				return true;
			}		
		}
		return false;
	}

	/*************************************
	* recherche des Solutions
	**************************************/
	
	public function genereSolutions($aData){		
		$res=array();
		if (isset($aData['projet']['lstLot'])){
			$this->setData($aData);
			$aSol = $this->chercheSolutionListeLot($this->jData['projet']['lstLot']);				
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
			//echo '<pre>';print_r($typeLot);print_r($lstSol);die;
			
			$res[$iType] = ['lstSol'=>$lstSol];
		}		
		return $res;
	}
	
	protected function chercheSolutionLotType($nomLot,$typeLot){
		$infoExsistant = $typeLot['existant'];
		$infoExsistant['typeBat'] = $this->jData['projet']['batiment']['typeBat'];
		
		//pour le chauffage on a besoin du type d'ecs choisie
		if ($nomLot == 'chauffage' AND !isset($infoExsistant['chauffLieEcsApTrav'])){
			$infoExsistant['chauffLieEcsApTrav'] = $this->ecsPrincConnectChauff_etapeFinale()? 'oui' : 'non';
		}
		
		$lstSol = $this->chercheValeurTableau('logigrame'.ucfirst($nomLot),$infoExsistant,true,false);
		//recherhedes perf min
		foreach($lstSol as $idSol => $sol){
			if(in_array($nomLot,['mur','plancherHaut','plancherBas','menuiserie'])){
				if ($this->isSolutionNonTraite($sol)){
					$perfMin = [0];
				} else {
					$perfMin = [$this->cherchePerfMin($nomLot,$infoExsistant,$sol)];
				}
				$lstSol[$idSol]['perfMin'] = $perfMin;
			}
		}
		return $lstSol;
	}
	
	
	protected function affecteCustomVal(){
		foreach ($this->jData['projet']['lstLot'] as $nomLot=>&$lstTypeLot){
			foreach ($lstTypeLot as &$typeLot){
				$this->affecteCustomVal_detail($typeLot['existant']);
			}
		}
	}
	
	protected function affecteCustomVal_detail(&$infoExsistant){
		foreach ($infoExsistant as $nomChamp=>$val){
			if ($this->isChampCustomVal($nomChamp)){	
				$nomChamp_customVal = $nomChamp.'_customVal';
				$nomChampSelect = $nomChamp;
				$valChampSelect = $infoExsistant[$nomChampSelect];
				if ($valChampSelect == 'customVal'){
					$cVal = $infoExsistant[$nomChamp_customVal];
				} else {
					$cVal = $this->getColTableauValeur($nomChampSelect,'id',$valChampSelect,'cVal');
				}
				$infoExsistant[$nomChampSelect.'_cVal']=$cVal;
			}
		}
	}
	
	protected function isChampCustomVal($nomChamp){
		return isset($this->getListeValeurs($nomChamp)['customVal']);
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
			$data['userId'] = $userId;
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

			if($res[0]['userId'] != $userId){
				//si on charge le projet d'un autre, on eface l'id pour enregistrer un nouveau projet
				unset($res[0]['userId']);
				unset($res[0]['id']);
			}
//echo $res[0]['data'];die;
			$jData = json_decode($res[0]['data'],true);
			$this->setData($jData);
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
						/*echo "$nomLot<br>$sOldSol<br>$sNewSol<br>";
						echo '<pre>';
						print_r($typeLot);
						die;*/
					}
				}
			}		
		}
		
		return $bOk;
	}

	public function getListeProjetUtilisateur($page = 0){	
		$limit = 30;
		$offset = ($page-1) * $limit;
		
		$userId = CMSEffiUtils::getCMSUserID();
		$res = CMSEffiUtils::queryCMSData(
			['id','nom','dateModif','version'],
			'projetb2c2',
			"userId=$userId",
			'dateModif DESC',
			[
				'limit'=>$limit,
				'offset'=>$offset,
				'foundRows'=>1
			]);
		$nbProjet = CMSEffiUtils::getQueryFoundRows();	
		return [			
			"nbProjet"=>$nbProjet,
			"nbPage"=>ceil($nbProjet/$limit),
			"projets"=>$res,
			];
	}


	public function getListeProjetAdmin($nomProjet='',$page = 0){	
		$limit = 30;
		$offset = ($page-1) * $limit;
		if (!CMSEffiUtils::userIsManagerB2C2()){	
			return false;
		}
		
		$cond = "u.id=p.userid";
		if ($nomProjet){
			$nomProjet = '%'.str_replace(' ','%',$nomProjet).'%';
			$nomProjet = CMSEffiUtils::quoteCMSData($nomProjet);
			$cond .= " AND (p.nom like $nomProjet OR u.email like $nomProjet OR u.name like $nomProjet )" ;
		}
		
		$res = CMSEffiUtils::queryCMSData(
			['p.id','u.name','u.email','p.nom','p.dateModif','p.version'],
			['projetb2c2 p','users u'],
			$cond,
			'p.dateModif DESC',
			[
				'limit'=>$limit,
				'offset'=>$offset,
				'foundRows'=>1
			]);
		$nbProjet = CMSEffiUtils::getQueryFoundRows();

		return [			
			"nbProjet"=>$nbProjet,
			"nbPage"=>ceil($nbProjet/$limit),
			"projets"=>$res,
			];
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
		$this->affecteCustomVal();
	}
	

	
	
}
	