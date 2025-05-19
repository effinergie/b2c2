<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'cthB2C2.php';

class ApiFdrB2c2 extends CthB2C2{
	protected $maxEtapeExt = 0;
	protected $bSortiePDF = false;
	protected $bDebugApi = '';
	protected $debugNEtape = 0;
	protected $debugNomLot = 'général';
	protected $enumScenario = 1;
	protected $aNomChampApiExt = [];
	protected $aCorrespRefLib = [];
	protected $aListeRefTravaux = [];

	function __construct(){
		parent::__construct();
		$this->aWarning = [];
		$this->chargeListeTableauValeur('apiExtCorrespondance');
		$this->initNomChampApiExt();
	}
	
	function extGenereFdr($data){

		return $this->callException(array($this,'extGenereFdrException'),$data);		
	}
	
	function extGenereFdrException($data){
		$this->bDebugApi = (!empty($data['debugMode']));
		$this->bPDFtoString = (!empty($data['PDFtoString']));
		$this->bSortiePDF = (stripos($data['typeRetour'],'pdf') !==false);	
		
		$this->jData = [];
		$sXml= $data['auditXML'];
		$sXml= str_replace('xsi:nil=','xsinil=',$sXml); //pour palier au bug de izuba
		$xml = simplexml_load_string($sXml);										
		
		if (isset($data['enumScenario'])){
			$this->enumScenario = intval($data['enumScenario']);
			if ($this->enumScenario >5 OR $this->enumScenario <1 ){
				trigger_error('Le paramètre "enumScenario" doit être compris entre 1 et 5.');
			}
		}
				
		switch ($data['typeRetour']){
			case 'recapTravauxXml':
				return $this->recapTravauxXml($xml);
				break;
		}

		$this->parseDataGeneral($xml);
		$this->parseDataBatiment($xml);		


		$logement_collection = $this->prepareLogementCollection($xml);	


		$this->parseDataLot($logement_collection,'mur');
		$this->parseDataLot($logement_collection,'plancherHaut');
		$this->parseDataLot($logement_collection,'plancherBas');
		$this->parseDataLot($logement_collection,'menuiserie');
		$this->parseDataLot($logement_collection,'ecs');
		$this->parseDataLot($logement_collection,'chauffage');
		$this->parseDataLot($logement_collection,'ventilation');
		
		
		$this->parseDataThermique($logement_collection);	
		
		$this->bFdrAPI = true;
		switch ($data['typeRetour']){			
			case 'auditEditeurPdf':				
			case 'auditProprietairePdf':			
			case 'auditPdf':
				switch ($data['typeRetour']){				
					case 'auditEditeurPdf':				
						$this->bFdrEditeur = true;
						break;
					case 'auditProprietairePdf':
						$this->bFdrEditeur = true;
						$this->bFdrProprietaire = true;			
						break;
				}
			
				$fdr = $this->genereFeuilleDeRoute($this->jData);
				return $this->createPDFProj( ['dataFdr' => $fdr] );
				break;
			case 'auditJson':		
				return $this->genereFeuilleDeRoute($this->jData);
				break;
			default:
				return $this->jData;
		}

		
	}
	
	function prepareLogementCollection($xml){
		//caclule le numéro maximal d'étapes 
		//et met dans l'ordre des étapes les logementColleciton
		
		$this->maxEtapeExt = 0;//$this->get_maxNumeroEtape($xml);
		$logement_collection = ($xml->xpath('logement_collection/logement') ?? '');
		$logement_derniereEtape = '';
		
		//$nom_scenario = '';
		
		$res = [];
		foreach ($logement_collection as &$xmlLogement){
			if ($this->estEtatInitial($xmlLogement)){//0:etat intial,	
				$res[0] = $xmlLogement;
			} else if ($this->get_enum_scenario_id($xmlLogement) == $this->enumScenario ){// 1:scénario multi étapes \"principal\
							
				$enum_etape_id = (int) ($xmlLogement->xpath('caracteristique_generale/enum_etape_id')[0] ?? '');				
				if ($enum_etape_id == 2 ){ //derniere Etape					
					$logement_derniereEtape = $xmlLogement; //on affectera la dernière étape au bon endroid du tableau à la fin
					$listeRef_derniereEtape = $this->getListeRefTravaux($xmlLogement);
				} else {
					$etape = $this->get_numeroEtape($xmlLogement);	
					$res[$etape] = $xmlLogement;
					$this->aListeRefTravaux[$etape]=$this->getListeRefTravaux($xmlLogement);
				}
			}
		}
		
		if (!isset($res[0])){
			trigger_error("Impossible de trouver la première étape dans le XML.");
		}

		if ($logement_derniereEtape === ''){
			trigger_error("Impossible de trouver la dernière étape dans le XML.");
		}
		
		
		$this->maxEtapeExt = count($res);
		$res[$this->maxEtapeExt] = $logement_derniereEtape;
		$this->aListeRefTravaux[$this->maxEtapeExt] = $listeRef_derniereEtape ;	
		ksort($res);
		
		return $res;
	}
	

	
	
	function recapTravauxXml($xml){
		
		$aCorLot = [
				"1"=> "murs",
				"2"=> "planchers bas",
				"3"=> "toiture/plafond",
				"4"=> "portes et fenêtres",
				"5"=> "système de chauffage",
				"6"=> "système d'ecs",
				"7"=> "sytème de refroidissement",
				"8"=> "système de ventilation",
				"9"=> "energie renouvelable",
				"10"=> "autre"	
			];
			
		$aCorTrav = [	
				"0"=> "isolation murs en ite",
				"1"=> "isolation murs en iti",
				"2"=> "isolation sous rampants (combles aménagés)",
				"3"=> "isolation des combles non aménagés",
				"4"=> "isolation toiture terrasse",
				"5"=> "isolation planchers bas",
				"6"=> "installation menuiseries double vitrage",
				"7"=> "installation menuiseries triple vitrage",
				"8"=> "installation vmc simple flux",
				"9"=> "installation vmc double flux",
				"10"=> "installation pac géothermique",
				"11"=> "installation pac eau/eau",
				"12"=> "installation pac air/eau",
				"13"=> "installation pac air/air (climatiseur inclu)",
				"14"=> "installation chaudière à gaz",
				"15"=> "installation chaudière à biomasse",
				"16"=> "installation poêle/insert à bois/granulés",
				"17"=> "installation radiateur électrique",
				"18"=> "installation radiateur hydraulique",
				"19"=> "installation plancher/plafond chauffant hydraulique",
				"20"=> "installation chauffe-eau thermodynamique",
				"21"=> "installation ballon d'ecs à effet joule",
				"22"=> "installation panneaux solaire thermique",
				"23"=> "installation panneaux solaire photovoltaïque",
				"24"=> "autre"	];		
		
		$logement_collection = $this->prepareLogementCollection($xml);
		$res = [];
		foreach ($logement_collection as $xmlLogement){
			$etape = $this->get_numeroEtape($xmlLogement);
			$aTravaux = ($xmlLogement->xpath('etape_travaux/travaux_collection/travaux') ?? '');
			$aLstTrav = [];
			
			foreach ($aTravaux as $xmlTravaux){
				$detailTrav = '';
				$detailTrav .= ($aCorLot[(string) ($xmlTravaux->xpath('enum_lot_travaux_audit_id')[0] ?? '')] ?? '') . ' - ';
				$detailTrav .= ($aCorTrav[(string) ($xmlTravaux->xpath('enum_type_travaux_id')[0] ?? '')] ?? '') . ' - ';
				$aDesc = $xmlTravaux->xpath('description_travaux_collection/description_travaux/description');
				foreach ($aDesc as $xmlDesc){
					$detailTrav .= '||'. (string) $xmlDesc ;
				}
				$aLstTrav[] = $detailTrav;
			}
			
			$res['Etape '.$etape] = $aLstTrav;
		}
		return $res;
	}
	
	
	function parseDataLot($logement_collection,$nomLot){
		$this->debugNomLot = $nomLot;
		
		foreach ($logement_collection as $xmlLogement){
			
			$ucNomLot = ucfirst($nomLot);
			
			if ($this->estEtatInitial($xmlLogement)){//0:etat intial,
				$this->debugNEtape = 0;
				call_user_func_array(array($this,"parseDataInit".$ucNomLot), array($xmlLogement));
			} else if ($this->get_enum_scenario_id($xmlLogement)==$this->enumScenario ){// 1:scénario multi étapes \"principal\
				$this->debugNEtape = $this->get_numeroEtape($xmlLogement);	
				call_user_func_array(array($this,"parseDataSolution".$ucNomLot), array($xmlLogement));
			}
			

		}
		
		$this->affecteSolutionLotNonTraite($nomLot);
	}



	
	function parseDataThermique($logement_collection){
		$calcThXmlAudit = [];
		
		foreach ($logement_collection as $xmlLogement){
			if ($this->estEtatInitial($xmlLogement) OR $this->get_enum_scenario_id($xmlLogement)==$this->enumScenario ){//0:etat intial,	
				$numEtape = $this->get_numeroEtape($xmlLogement);
				
				$surf = (float) $xmlLogement->xpath('sortie/ep_conso/ep_conso_5_usages')[0] / (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_5_usages_m2')[0] ?? '');
				
				$calcThXmlAudit['etape'][$numEtape]['consoTotal'] = [
					'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_5_usages')[0] ?? '')/$surf ,
					'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_5_usages')[0] ?? '')/$surf ,
					'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_5_usages')[0] ?? '')/$surf,
					'classeDpe'=> (string) ($xmlLogement->xpath('sortie/ep_conso/classe_bilan_dpe')[0] ?? ''),
					'classeGes'=> (string) ($xmlLogement->xpath('sortie/emission_ges/classe_emission_ges')[0] ?? '')
					];
					
				$calcThXmlAudit['etape'][$numEtape]['consoLot'] =[
						"chauffage"=>[
							'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_ch')[0] ?? '')/$surf,
							'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_ch')[0] ?? '')/$surf,
							'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_ch')[0] ?? '')/$surf,
							'cout'=> (float) ($xmlLogement->xpath('sortie/cout/cout_ch')[0] ?? '')/$surf,
						],
						"ecs"=>[
							'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_ecs')[0] ?? '')/$surf,
							'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_ecs')[0] ?? '')/$surf,
							'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_ecs')[0] ?? '')/$surf,
							'cout'=> (float) ($xmlLogement->xpath('sortie/cout/cout_ecs')[0] ?? '')/$surf,
						],
						"clim"=>[
							'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_fr')[0] ?? '')/$surf,
							'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_fr')[0] ?? '')/$surf,
							'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_fr')[0] ?? '')/$surf,
							'cout'=> (float) ($xmlLogement->xpath('sortie/cout/cout_fr')[0] ?? '')/$surf,
						],
						"eclairage"=>[
							'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_eclairage')[0] ?? '')/$surf,
							'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_eclairage')[0] ?? '')/$surf,
							'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_eclairage')[0] ?? '')/$surf,
							'cout'=> (float) ($xmlLogement->xpath('sortie/cout/cout_eclairage')[0] ?? '')/$surf,
						],
						"auxetvent"=>[
							'cef'=> (float) ($xmlLogement->xpath('sortie/ef_conso/conso_totale_auxiliaire')[0] ?? '')/$surf,
							'cep'=> (float) ($xmlLogement->xpath('sortie/ep_conso/ep_conso_totale_auxiliaire')[0] ?? '')/$surf,
							'ges'=> (float) ($xmlLogement->xpath('sortie/emission_ges/emission_ges_totale_auxiliaire')[0] ?? '')/$surf,
							'cout'=> (float) ($xmlLogement->xpath('sortie/cout/cout_total_auxiliaire')[0] ?? '')/$surf,
						]
					];	
				$calcThXmlAudit['etape'][$numEtape]['ubat'] = [
					'ubat'=> (float) ($xmlLogement->xpath('sortie/qualite_isolation/ubat')[0] ?? ''),
					'ubatBase'=> (float) ($xmlLogement->xpath('sortie/qualite_isolation/ubat_base')[0] ?? '')
					];
			}
		}
		$this->jData['calcThXmlAudit'] = $calcThXmlAudit;
	}


	
	
	/*************************************************/
	/*     P A R S E    */
	/*************************************************/	
	

	
	protected function get_numeroEtape($xmlLogement){		
		/*"0": "état initial",
		"1": "étape première",
		"2": "étape finale",
		"3": "étape intermédiaire 1",
		"4": "étape intermédiaire 2",
		"5": "étape intermédiaire 3"		*/
		$enum_etape_id = (int) ($xmlLogement->xpath('caracteristique_generale/enum_etape_id')[0] ?? '');
		if ($enum_etape_id>=3){
			return $enum_etape_id -1;
		}
		if ($enum_etape_id==2){
			return $this->maxEtapeExt;
		}		
		return $enum_etape_id;
		
	}
	
	
	protected function get_enum_scenario_id ($xmlLogement){	
		return ($xmlLogement->xpath('caracteristique_generale/enum_scenario_id')[0] ?? '');
	}	
	
	protected function estEtatInitial($xmlLogement){	
		$enum_scenario_id = $this->get_enum_scenario_id($xmlLogement);
		$enum_etape_id =  ($xmlLogement->xpath('caracteristique_generale/enum_etape_id')[0] ?? '');

		return $enum_scenario_id==0 OR $enum_etape_id==0 /*OR $pasDeTravaux*/;
	}
	/*************************************************/
	/*     U T I L I T A I R E    */
	/*************************************************/	
	
	protected function getSolutionLotNonTraite($nomLot){
		$lstSolLot = $this->getTableauValeur('logigrame'.ucfirst($nomLot));
		foreach ($lstSolLot as $sol){				
			if (in_array('nonTraite',$sol) ){
				return $sol;
			}
		}
		return false;
	}

	protected function affecteSolutionLotNonTraite($nomLot){
	$alot = &$this->jData['projet']['lstLot'][$nomLot];
		foreach($alot as $nLot=>&$typeLot){
			unset($typeLot['existant']['cleLot']);
			if (!$typeLot['existant']['designLot']) $typeLot['existant']['designLot'] = $this->getValChamp('lstLot',$nomLot);
			if (!isset($typeLot['existant']['idSol'])){		
				$sol = $this->getSolutionLotNonTraite($nomLot);
				if ($sol){			
					$idSol = $sol['-idSol'];
					$typeLot['existant']['idSol'] = $idSol;
					$typeLot['existant']['etape'] = 1;
					$typeLot['lstSol'][$idSol] = $this->solutionEnleveTirerValTab($sol);							
					
				}
			}
		}
	}

	protected function  solutionEnleveTirerValTab($tab){
		// met chaque valeur d'un tableau dans un tableau 
		// et enlève les champ sans tirer et enlève le tiret en début de champ
		$res = [];
		foreach ($tab as $nomChamp=>$val){
			if ($nomChamp[0]=='-'){
				$nomChampSansTiret = substr($nomChamp, 1);
				$res[$nomChampSansTiret] = [$val];
			}
		}
		return $res;
	}
	

	protected function valTabToTab(&$tab){
		//met chaque valeurs d'un tableau dans un tableau 
		foreach ($tab as $champ=>$val){
			$tab[$champ] = [$val];
		}
		return $tab;
	}
	
	/************************************************
	*  CLEs LOT et REFFERENCE
	************************************************/
	

	protected $aCleLotLibre= [];
	
	protected function deleteNode(&$xmlLot,$nomBaliseASupprimer){
		$balisesASupprimer = $xmlLot->xpath("//$nomBaliseASupprimer");

		foreach ($balisesASupprimer as $balise) {
			unset($balise[0][0]);
		}
	}
	
	protected function initNomChampApiExt(){
		$aChampsXmlUtilises = ['resistance_isolation'=>1,'uw'=>1,'enum_orientation_id'=>1,'epaisseur_structure'=>1]; //champs résistance des parois
		foreach ($this->listeTableauValeur as $nomTableau=>$dataTab) {
			if (strPos($nomTableau,'apiExtCor')===0){
				$aNomChamp = array_keys($dataTab[0]);
				foreach ($aNomChamp as $nomChamp){
					if (strpos($nomChamp,'-')===false){ //on ne prends pas les champ qui commencent par -
						$aChampsXmlUtilises[$nomChamp] = 1;
					}
				}
				
			}
		}
		$this->aNomChampApiExt = array_keys($aChampsXmlUtilises);
	}
	
	protected function getCleLot($nomLot,$xmlLot){		
		// calcule la référence du lot à partir du xml du lot.
		
		$xmlLotCopie = $this->simpleXMLClone($xmlLot); //copie car sinon Xpath chèrche aussi dans les neuds parents , donc dant le fichier xml complet
		$aValToCompare = [];
		foreach ($this->aNomChampApiExt as $nomChamp){
			$aVal = $xmlLotCopie->xpath("//$nomChamp");
			foreach ($aVal as $val){
				$aValToCompare[$nomChamp] = (string) $val;
			}
		}		
		
		$cleLot = json_encode($aValToCompare);
		
		$label = implode(' - ',array_map('trim',$xmlLotCopie->xpath('//description') ?? ''));
		
		if (!isset($this->aCorrespRefLib[$cleLot])) {
			$this->aCorrespRefLib[$cleLot] = [];
		}
		$this->aCorrespRefLib[$cleLot][] = $label;
				
		return $cleLot;
	}
	
	protected function getLibelleFromCleLot($cleLot){		
		return implode(' || ',array_unique($this->aCorrespRefLib[$cleLot]));
	}
	
	protected function getValXmlFromCleLot($cleLot){
		return json_decode($cleLot,true);
	}


	protected function &getListesCleLotInit($nomLot){
		$listeRef = [];
		foreach ($this->jData['projet']['lstLot'][$nomLot] as &$typeLot){
			$cleLot = $typeLot['existant']['cleLot'];
			$listeRef[$cleLot][] = &$typeLot;
		}
		return $listeRef;
	}
	
	protected function prepareLotsATraiter($nomLot,$aLot,$etape){

		$aCleLotLibres = &$this->getListesCleLotInit($nomLot);

		$aXmlLotATraiter = [];	
	
		//vérifie si les "Clés Lot" sont bien présente dans le tableau des "ClésLot" initiales
		// pour obtenir une liste des "Clés Lot" non présentes (c.a.d. libres pour y faire correspondre l'élément rénové)
		foreach ($aLot as $numLot=>$xmlLot){
			
			//on cherche si la "Clé Lot" existe dans les étapes précédentes. 
			//Si elle existe c'est que l'élément n'a pas été modifié. sinon, il faut considérer l'élément comme rénové.
			if ($this->cleLotLibreATraiter($nomLot,$xmlLot,$aCleLotLibres,$etape)){
				$enum_etat_composant_id = (string) ($xmlLot->xpath('donnee_entree/enum_etat_composant_id')[0] ?? '');
				if ($enum_etat_composant_id != 2){
					$ref = $this->getCleLot($nomLot,$xmlLot);
					$this->warning("Pour le lot '$nomLot' l'élément '".$this->getLibelleFromCleLot($ref)."' a été modifié à l'étape n°$etape, mais le champ 'enum_etat_composant_id' est different de 2 ");
				} 
				
				$aXmlLotATraiter[] = $xmlLot;
			}
		}	



		$this->aCleLotLibre[$nomLot][$etape] = &$aCleLotLibres;

		foreach ($this->aCleLotLibre[$nomLot][$etape] as &$aTypeLot){
			foreach ($aTypeLot as &$typeLot){
				// vérifie que dans les libres il n'y ait pas dejà des lots ou on a déja trouvé des solutions : 
				// si l'étape est déjà défini alors on ne traite pas l'info car on a déjà choisis une solution les étapes précédentes.
				if (isset($typeLot['existant']['etape'])){
					$ref = $typeLot['existant']['cleLot'];
					
					if (!isset($typeLot['existant']['etapeSupprime']) AND !$this->isTypeLotElementSupprime($typeLot) ){ //si pas déjà suppimé
						if (!empty($this->bDebugApi)){								
							$this->warning("Pour le lot '$nomLot' l'élément '".$this->getLibelleFromCleLot($ref)."' a été traité à une étape précédente mais n'est plus présent à l'etape n°$etape");
						}
						//on dit que l'étélément sera suprimé à l'étape en question
						$typeLot['existant']['etapeSupprime'] = $etape;
					}
					$this->unsetCleLotLibre($aCleLotLibres,$ref);
				}
			}			
		}	
		return $aXmlLotATraiter;
	}
	
	
		
	protected function ajoutRobinetTh($valXmlAvant,$valXmlApres){
		//renvoie vrai si il y avait des radiateurs à eau avant et après. 
		//pour le cas où on conserve la charretier branchée à des radiateurs, l’outil B2c2 ne considère pas ça comme des travaux.
		$aRadiateurEau = [
			24,28,
			25,29,
			26,30,
			27,31,
			32,36,
			33,37,
			34,38,
			35,39
		];
		
		if (
			in_array($valXmlAvant['enum_type_emission_distribution_id'],$aRadiateurEau )
			OR 
			in_array($valXmlApres['enum_type_emission_distribution_id'],$aRadiateurEau )
			){
			return true;
		}
		return false ;
		/*
		Ancien fonctionnement ne concernait que les robinets thermostatiques. 
		Mais il ne traitait pas les cas ou juste un système de régulation était mis en place
		//liste des enum correspondants à des robinets thermostatiques , avant et après travaux
		$aCoupleAvecSansRobinetsTh = [
			[24,28],
			[25,29],
			[26,30],
			[27,31],
			[32,36],
			[33,37],
			[34,38],
			[35,39]
		];
					
		foreach($aCoupleAvecSansRobinetsTh as $valSansAvec){
			if (	$valXmlAvant['enum_type_emission_distribution_id'] == $valSansAvec[0]
					AND 
					$valXmlApres['enum_type_emission_distribution_id'] == $valSansAvec[1]
				){
					return true;
				}
			
		}
		return false;
		*/
	}
	
	protected function cleLotLibreATraiter($nomLot,$xmlLot,&$aCleLotLibres,$etape){
		//renvoie Vrai si le lot est à traiter
		$refLot = $this->getCleLot($nomLot,$xmlLot);
		
		//Cas particuliers où on ne change que les robinets thermostatiques
		if 	($nomLot=="chauffage"){			
			$valXmlLot = $this->getValXmlFromCleLot($refLot);
			foreach($aCleLotLibres as $refLibre=>$val){
				$valXmlLibre = $this->getValXmlFromCleLot($refLibre);
				
				//si le générateur ne change pas et que l'émetteur passe de 'sans robinets thermostatiques' à 'avec';
				// alors on ne considère pas que le chauffage a été traité
				if ($valXmlLot['enum_type_generateur_ch_id'] == $valXmlLibre['enum_type_generateur_ch_id']
					AND
						$this->ajoutRobinetTh($valXmlLibre,$valXmlLot)
					){
					$this->unsetCleLotLibre($aCleLotLibres,$refLibre);	
					return false;				
				}		
			}
		}	
	
		
		//si la "Clé Lot" existe dans les étapes précédentes. alors le lot n'a pas changé donc pas à traiter
		//plus utile car maintenant on ne se base que sur la liste des travaux
	/*	if (isset($aCleLotLibres[$refLot])){
			$this->unsetCleLotLibre($aCleLotLibres,$refLot);
			return false;
		}	*/	

		// si le lot ne fait pas partie de la liste des travaux alors on ne le traite pas.
		if (! $this->xmlLotEstDansListeTravaux($xmlLot,$etape)){
			if (isset($aCleLotLibres[$refLot])){
				$this->unsetCleLotLibre($aCleLotLibres,$refLot);
			} else {
				if (!empty($this->bDebugApi)){				
					$this->warning("Pour le lot '$nomLot' à l'étape n°$etape, l'élément '". ($xmlLot->xpath('donnee_entree/description')[0] ?? '')/*$this->getLibelleFromCleLot($refLot)*/
							."' a été modifié mais n'est pas référencé dans la liste de travaux");
				}
			}
			
			return false;
		}	
	
		return true;
	}
		
	protected function getCleLotLibre($nomLot,$xmlLot,$etape){
		$aCleLotLibres = &$this->aCleLotLibre[$nomLot][$etape];
	
		if (count($aCleLotLibres)==0){		
			//si on a pas trouvé de 'Clé Lot' Libre, alors on crée un lot 'inexistant' à l'etat init
			if (!empty($this->bDebugApi)){
				$this->warning("Il y a plus de lots '$nomLot' à l'étape n°$etape qu'à l'etat initial. Un élément 'inexistant' a été créé à l'etat initial pour le lot '$nomLot' ");
			}
			$newRef = $this->getCleLot($nomLot,$xmlLot,$etape);			
			$newlot = [];
			$newlot['existant']['designLot'] = '';
			$newlot['existant']['cleLot'] = $newRef;	
			$newlot['existant']['inexistant'] = 1;	
			if ($nomLot == 'menuiserie'){
				$newlot['existant']['surfMenuiserie'] = 0;		
			} else {
				$newlot['existant']['part'] = $this->getPartXmlLot($nomLot,$xmlLot);
			}
			$this->jData['projet']['lstLot'][$nomLot][] = &$newlot;
			return $newRef;
		}
		
		reset($aCleLotLibres);
		$cleLotLibre = key($aCleLotLibres);
		
		$this->unsetCleLotLibre($aCleLotLibres,$cleLotLibre);
		return $cleLotLibre;
	}
	
	protected function unsetCleLotLibre(&$aCleLotLibres,$ref){
		
		array_shift($aCleLotLibres[$ref]);
		if (count($aCleLotLibres[$ref]) == 0){
			unset($aCleLotLibres[$ref]);
		}
	}
	
	protected function chercheLotSupprimes($nomLot,$etape){			
		foreach ($this->aCleLotLibre[$nomLot][$etape] as &$aTypeLot){
			foreach ($aTypeLot as &$typeLot){
				//pour tous les lots non utilisés, créér une solution 'lot supprimé'
				$idSol="998";
				$typeLot['lstSol'][$idSol] = [];//$this->valTabToTab($solutionExt);
				$typeLot['lstSol'][$idSol]['idSol'] = [$idSol];
				$typeLot['lstSol'][$idSol]['resRecom'.ucfirst($nomLot)] = ['elementSupprime'];
				$typeLot['existant']['idSol'] = $idSol;				
				$typeLot['existant']['etape'] = $etape;	
				if (!empty($this->bDebugApi)){
					$this->warning("un lot '$nomLot' a été supprimé à l'étape n°$etape ");
				}
			}
		}
	}	
	
	protected function &getLotInitalCorrespondant($nomLot,$xmlLot,$etape){	
	
		$cleLot = $this->getCleLotLibre($nomLot,$xmlLot,$etape);

		$aLot = &$this->jData['projet']['lstLot'][$nomLot];		
		
		foreach ($aLot as $numLot=>&$lot){		
							
			if ($lot['existant']['cleLot'] == $cleLot){	
				$newRef = $this->getCleLot($nomLot,$xmlLot,$etape);
				$lot['existant']['cleLot'] = $newRef;//on remplace la référence à l'état initial par la référtence du lot rénové pour pouvoir le retrouver aux étapes ultérieures.
				return $lot;
			}		
		}
		
		trigger_error("impossible de trouver le lot $nomLot ayant la référence '$cleLot' dans l'état initial.");
		
		
	}
	
	
	protected function affecteLotSolutionExt($nomLot,&$typeLot,$solutionExt){
		
		$this->affecteCustomVal_detail($typeLot['existant']);
		
		$aSolLot = $this->chercheSolutionLotType($nomLot,$typeLot);
				
		$res = $this->chercheValeurTableau($aSolLot,$solutionExt,true,true);

		if ($res && isset($res['idSol'][0])){
			$idSol = $res['idSol'][0];
			$typeLot['lstSol'] = $aSolLot;		
			$typeLot['existant']['idSol'] = $idSol;				
		} else {
			$libSolutionNonRecommandee = $this->getValChamp('remarqueSolution','solutionNonRecommandee');
			
			$idSol="999";
			$typeLot['lstSol'][$idSol] = $this->valTabToTab($solutionExt);
			$typeLot['lstSol'][$idSol]['idSol'] = [$idSol];
			$typeLot['lstSol'][$idSol]['resRecom'.ucfirst($nomLot)] = [$libSolutionNonRecommandee];
			
			
			//perfMin
			$premSol = reset($aSolLot);
			if (isset($premSol['perfMin'])){
				$typeLot['lstSol'][$idSol]['perfMin'] = $premSol['perfMin'];				
			}
			
			
			$typeLot['existant']['idSol'] = $idSol;
			
			if (!empty($this->bDebugApi)){
				$this->warning($libSolutionNonRecommandee." pour le lot $nomLot : ".$this->getLibelleFromCleLot($typeLot['existant']['cleLot']),print_r($solutionExt,true).print_r($aSolLot,true));
			}
		}
	}
	
	protected function xmlLotGetRefListeTravaux($xmlLot){
		//renvoie la liste des références du lot (il peut y en avoir plusieurs dans le cas du chauffage par exemeple... ref emeteur, ref générateur...)
		$aRefXml = [] ;
		$aRefXml[] = (string) ($xmlLot->xpath('donnee_entree/reference')[0] ?? '');
		
		//ref générateur émetteur
		$aRefAux = $xmlLot->xpath('generateur_chauffage_collection/generateur_chauffage/donnee_entree/reference');
		foreach ($aRefAux as $refAux){
			$aRefXml[] = (string) $refAux;
		}
		
		$aRefAux = $xmlLot->xpath('emetteur_chauffage_collection/emetteur_chauffage/donnee_entree/reference');
		foreach ($aRefAux as $refAux){
			$aRefXml[] = (string) $refAux;
		}
		$aRefAux = $xmlLot->xpath('generateur_ecs_collection/generateur_ecs/donnee_entree/reference');
		foreach ($aRefAux as $refAux){
			$aRefXml[] = (string) $refAux;
		}
		
		//référence des générateurs mixtes
		$aRefAux = $xmlLot->xpath('generateur_chauffage_collection/generateur_chauffage/donnee_entree/reference_generateur_mixte');
		foreach ($aRefAux as $refAux){
			$aRefXml[] = (string) $refAux;
		}
		$aRefAux = $xmlLot->xpath('generateur_ecs_collection/generateur_ecs/donnee_entree/reference_generateur_mixte');
		foreach ($aRefAux as $refAux){
			$aRefXml[] = (string) $refAux;
		}			
		
		return $aRefXml;
	}	
	
	protected function xmlLotGetTypeTravaux($xmlLot,$etape){		
		$res = [];
		$aRefXml = $this->xmlLotGetRefListeTravaux($xmlLot);
		
		foreach ($aRefXml as $refXML){
			if ($refXML AND isset($this->aListeRefTravaux[$etape][$refXML]['enum_type_travaux_id'])){
				$res[] = $this->aListeRefTravaux[$etape][$refXML]['enum_type_travaux_id'];
			}
		}
		return $res;
	}
	
	protected function xmlLotEstDansListeTravaux($xmlLot,$etape){		
		$aRefXml = $this->xmlLotGetRefListeTravaux($xmlLot);
		
		foreach ($aRefXml as $refXML){
			if ($refXML AND isset($this->aListeRefTravaux[$etape][$refXML])){
				return true;
			}
		}
		return false;
	}

	protected function getListeRefTravaux($xmlLogement){
		$res = [];
		
		$aTravaux = ($xmlLogement->xpath('etape_travaux/travaux_collection/travaux') ?? '');
		foreach ($aTravaux as $travaux){
			$aReference = ($travaux->xpath('reference_collection/reference') ?? '');
			$enum_type_travaux_id = (string) ($travaux->xpath('enum_type_travaux_id')[0] ?? '');
			$infoTravaux = [
				'enum_type_travaux_id' => $enum_type_travaux_id
				];
			foreach ($aReference as $ref){
				$res[(string) $ref]	= $infoTravaux;
			}
		}
	
		return $res;
	}	
	

	protected function getSommeXml($xml){
		$total = 0;
		foreach ($xml as $xmldet){
			$total += floatval($xmldet[0]);
		}
		return $total;
	}
	
	protected function xml2array ( $xmlObject, $out = [] )
	{
		
		if (is_array($xmlObject)){
			foreach ( (array) $xmlObject as $index => $node ){
				$out = array_merge($out,$this->xml2array ( $node ));
			}
		} else {
			foreach ( (array) $xmlObject as $index => $node ){
				$out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;
			}
		}
		

		return $out;
	}	
	

		
	/*************************************************/
	/*     B A T I M E N T      */
	/*************************************************/
	
	protected function parseDataGeneral($xml){
		$res = [];
		//data[genereSolutions][projet][general][id]: 
		//$res['nomProjet'] = (string) ($xml->xpath('administratif/nom_proprietaire')[0] ?? '');
		$res['nomProjet'] = (string) ($xml->xpath('numero_audit')[0] ?? '');
		if (!$res['nomProjet']){
			$res['nomProjet'] = (string) ($xml->xpath('administratif/numero_dpe')[0] ?? '');
		}
		
		$res['logiciel'] = (string) ($xml->xpath('/audit/administratif/auditeur/version_logiciel')[0] ?? '');
		$res['logiciel'] .= '|'.(string) ($xml->xpath('/audit/administratif/auditeur/version_moteur_calcul')[0] ?? '');
		$res['logiciel'] .= '|'.(string) ($xml->xpath('/audit/administratif/auditeur/usr_logiciel_id')[0] ?? '');
		
		$cp = (string) ($xml->xpath('administratif/geolocalisation/adresses/adresse_bien/code_postal_brut')[0] ?? '');
		
		if ($cp){
			$res['departement'] = substr(str_pad($cp, 5, "0", STR_PAD_LEFT),0,2);
		} else { //si pas de CP, on recherche la zone climatique
			$meteo = $this->xml2array($xml->xpath('logement_collection/logement/meteo') ?? '');
			$res['departement'] = $this->getCorrespApiB2c2('apiExtCorDepartement',$meteo);
		}
		

		$this->jData['projet']['general'] = $res;
	}
	
	
	
	protected function parseDataBatiment($xml){
		$this->jData['projet']['batiment'] = [];
		$res = &$this->jData['projet']['batiment'];
		
		$caracteristique_generale = $this->xml2array($xml->xpath('logement_collection/logement/caracteristique_generale')[0] ?? '');
		
		$meteo = $this->xml2array($xml->xpath('logement_collection/logement/meteo') ?? '');
				

		$res['typeBat'] = $this->getCorrespApiB2c2('apiExtCorTypeBat',$caracteristique_generale);
	
		$res['anneeConstr'] = $this->getCorrespApiB2c2('apiExtCorAnneeConstruction',$caracteristique_generale);
			
		if (isset($meteo['altitude'])){
			$res['altitude'] = $meteo['altitude'];
		} else {
			$res['altitude'] = $this->getCorrespApiB2c2('apiExtCorAltitude',$meteo);
		}
		
		$res['nbNiveau'] = isset($caracteristique_generale['nombre_niveau_immeuble']) ? $caracteristique_generale['nombre_niveau_immeuble'] : 1;	

		
		$res['nbLogement'] = isset($caracteristique_generale['nombre_appartement']) ? $caracteristique_generale['nombre_appartement'] : 1;
		
		
		$res['surface']="";//valeur par défaut
		if (isset($caracteristique_generale['surface_habitable_immeuble'])){
			$res['surface'] = $caracteristique_generale['surface_habitable_immeuble'];
		} else if(	isset($caracteristique_generale['surface_habitable_logement']) 
					AND ( 
						isset($caracteristique_generale['nombre_appartement'])
						OR
						!$this->isTypeBatLC()//si MI => nobre logement est à 1
						)
				){
			$res['surface'] = $caracteristique_generale['surface_habitable_logement'] * $res['nbLogement'];
		}
		
		
		$res['hautSousPlaf'] = $caracteristique_generale['hsp'];
		$res['surfPlancherHaut'] = ''; //valeur par défaut;
		$res['surfPlancherBas'] = ''; //valeur par défaut;		
		$res['permeaInit'] = ''; //valeur par défaut; 
		$res['permeaFin'] = ''; //valeur par défaut; 
		$res['description'] = ''; //valeur par défaut; 
		$res['formeBat'] = 'moyen'; //valeur par défaut; 
		$res['mitoyen']= 'isole'; //par défaut.
		$res['orientationBat']= 'est'; //par défaut.
		
		$res['combleHabit'] = 'non'; //valeur par défaut; sera mis à oui si type planche haut = rampants

	}
	
	
	protected function getPartXmlLot($nomLot,$xmlLot){
		//dans l'api on ne stoque pas en % mais en valeur.
		switch($nomLot){
			case 'mur':
				return floatval($xmlLot->xpath('donnee_entree/surface_paroi_opaque')[0] ?? '');
				break;
			case 'plancherHaut':
				return floatval($xmlLot->xpath('donnee_entree/surface_paroi_opaque')[0] ?? '');
				break;
			case 'plancherBas':
				return floatval($xmlLot->xpath('donnee_entree/surface_paroi_opaque')[0] ?? '');
				break;
			case 'menuiserie':
				return floatval($xmlLot->xpath('donnee_entree/surface_totale_baie')[0] ?? '');
				break;
			case 'ecs':
				return floatval($xmlLot->xpath('donnee_intermediaire/conso_ecs')[0] ?? '');
				break;
			case 'chauffage':
				$emet = $xmlLot->xpath('emetteur_chauffage_collection/emetteur_chauffage')[0];
				return floatval($emet->xpath('donnee_entree/surface_chauffee')[0] ?? '');
				break;
			case 'ventilation':
				return floatval($xmlLot->xpath('donnee_entree/surface_ventile')[0] ?? '');
				break;
		}
		return 0;
	}
	
	public function getLogiciel(){
		return $this->jData['projet']['general']['logiciel'] ?? '';
	}
	
	/*************************************************/
	/*     lots    */
	/*************************************************/
	
	protected function getDesignLot($xmlLot,$generateur=null,$emet=null){
		$aLib = [trim((string) ($xmlLot->xpath('donnee_entree/description')[0] ?? ''))];
		
		if ($generateur!==null){
			$aLib[] = trim((string) ($generateur->xpath('donnee_entree/description')[0] ?? ''));
		}
		
		if ($emet!==null){
			$aLib[] = trim((string) ($emet->xpath('donnee_entree/description')[0] ?? ''));
		}
		
		return implode(' - ',array_filter($aLib));
	}
	
	protected function setPeformancesInit(&$resLot,$nomLot,$perf){
		$sUcNomLot = ucfirst($nomLot);

		$resLot['perf'.$sUcNomLot.'Init_customVal'] = (float) $perf;
		$resLot['perf'.$sUcNomLot.'Init']='customVal';
		
		$resLot['perf'.$sUcNomLot.'Final_customVal'] = ''; //sera écrasé plus tard par la solution finale.
		$resLot['perf'.$sUcNomLot.'Final'] = 'defaut';
		
	}
	
	/*************************************************/
	/*     M U R S     */
	/*************************************************/	
	
	protected function parseDataInitMur($logement){
		$res = [];
		$surfTotale = $this->getSommeXml($logement->xpath('enveloppe/mur_collection/mur/donnee_entree/surface_paroi_opaque'));
		$nomLot = 'mur';
		$this->jData['projet']['batiment']['surfMur'] = $surfTotale;//la surface des menuiserie sera ajouté plus tard
		
		$aLot = $logement->xpath('enveloppe/mur_collection/mur');	
		
		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');
			$resLot['strucMur'] = $this->getCorrespApiB2c2('apiExtCorStrucMur',$valEntre);
			$resLot['typeIsoMur'] = $this->getCorrespApiB2c2('apiExtCorTypeIsoMur', $valEntre);
			
			$resLot['part'] = $this->getPartXmlLot($nomLot,$xmlLot);
			
			$this->setPeformancesInit($resLot,$nomLot,$xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
			
			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			
			$res[] = ['existant' => $resLot];
		}
		$this->jData['projet']['lstLot']['mur'] = $res;		
	}
	
	
	protected function parseDataSolutionMur($logement){
		$aLot = $logement->xpath('enveloppe/mur_collection/mur');
		
		$nomLot = 'mur';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);
	
		foreach ($aLotATraiter as $numLot=>$xmlLot){
		
				
			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
			
			$typeLot['existant']['etape'] = $etape;

			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');

			//on écrase les valeurs du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			$typeLot['existant']['strucMur'] = $this->getCorrespApiB2c2('apiExtCorStrucMur',$valEntre);
			$typeLot['existant']['typeIsoMur'] = 'non';//on considère que le mur n'est pas isolé à l’état initial car on ne peu faire le lien avec l’état init ou le mur peut être inexistant
							
			$solutionExt = [];
			$solutionExt['resTypeIsoMur'] =  $this->getCorrespApiB2c2('apiExtCorResTypeIsoMur', $valEntre);		
			
			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);
								
			$resistance = (string) ($xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
			if ($resistance !=='' ){
				$typeLot['existant']['perfMurFinal_customVal'] = (float) $resistance;
				$typeLot['existant']['perfMurFinal']='customVal';
			}
			
		}
		
		$this->chercheLotSupprimes($nomLot,$etape);
		
	}
	
	/*************************************************/
	/*    P L A N C H E R           H A U T     	 */
	/*************************************************/	

	protected function parseDataInitPlancherHaut($logement){
		$res = [];
		
		$nomLot = 'plancherHaut';	
		
		$aLot = $logement->xpath('enveloppe/plancher_haut_collection/plancher_haut');
		
		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');			
			$valEntre['enum_type_travaux_id'] = '';//de toute façon on ne se sert plus de l’état initial.	
			
			$resLot['contactPlancherHaut'] = $this->getCorrespApiB2c2('apiExtCorContactPlancherHaut',$valEntre);
			$resLot['structurePlancherHaut'] = $this->getCorrespApiB2c2('apiExtCorStructurePlancherHaut',$valEntre);
			
			
			$this->setPeformancesInit($resLot,$nomLot,$xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
			
			
			$resLot['part'] = $this->getPartXmlLot($nomLot,$xmlLot);

			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			$res[] = ['existant' => $resLot];
			
			
			if ($resLot['contactPlancherHaut']=='rampant'){
				$this->jData['projet']['batiment']['plancherHaut']='oui';
			}	
			
		}
		
		$this->jData['projet']['lstLot']['plancherHaut'] = $res;
		
	}	
	
	protected function parseDataSolutionPlancherHaut($logement){
		$aLot = $logement->xpath('enveloppe/plancher_haut_collection/plancher_haut');

		$nomLot = 'plancherHaut';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);


		foreach ($aLotATraiter as $numLot=>$xmlLot){			
			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
			
			$typeLot['existant']['etape']=$etape;					
			
			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');
			
			//on écrase les valeurs du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre['enum_type_travaux_id'] = $this->xmlLotGetTypeTravaux($xmlLot,$etape);//pour savoir si c'est un toit terrasse
			$typeLot['existant']['contactPlancherHaut'] = $this->getCorrespApiB2c2('apiExtCorContactPlancherHaut',$valEntre);
			$typeLot['existant']['structurePlancherHaut'] = $this->getCorrespApiB2c2('apiExtCorStructurePlancherHaut',$valEntre);

			//on en a besoin dans les tableaux de correspondance ci-après
			$valEntre['contactPlancherHaut'] = $typeLot['existant']['contactPlancherHaut'];		
			

			$solutionExt = [];
			$solutionExt['resTypeIsoPlancherHaut'] =  $this->getCorrespApiB2c2('apiExtCorResTypeIsoPlancherHaut', $valEntre);	
				
			$resistance = (string) ($xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
			if ($resistance !=='' ){
				$typeLot['existant']['perfPlancherHautFinal_customVal'] = (float) $resistance;
				$typeLot['existant']['perfPlancherHautFinal'] = 'customVal';
			}

			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);
					
			
		}
		$this->chercheLotSupprimes($nomLot,$etape);
	}
	


	/*************************************************/
	/*    P L A N C H E R            B A S           */
	/*************************************************/	

	protected function parseDataInitPlancherBas($logement){
		$res = [];
		
		$nomLot = 'plancherBas';	
		
		$aLot = $logement->xpath('enveloppe/plancher_bas_collection/plancher_bas');
		
		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');

			$resLot['typePlancherBas'] = $this->getCorrespApiB2c2('apiExtCorTypePlancherBas',$valEntre);
			
			$resLot['structurePlancherBas'] = $this->getCorrespApiB2c2('apiExtCorStructurePlancherBas',$valEntre);
			
			$this->setPeformancesInit($resLot,$nomLot,$xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
			
			$resLot['part'] = $this->getPartXmlLot($nomLot,$xmlLot);
			
			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			$res[] = ['existant' => $resLot];
			
		}
		
		$this->jData['projet']['lstLot']['plancherBas'] = $res;
		

	}	
	
	
	protected function parseDataSolutionPlancherBas($logement){
		$aLot = $logement->xpath('enveloppe/plancher_bas_collection/plancher_bas');

		$nomLot = 'plancherBas';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);


		foreach ($aLotATraiter as $numLot=>$xmlLot){

				$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
				
				$typeLot['existant']['etape']=$etape;
				
								
				$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');
				
				//on écrase les valeurs du lot car on est pas sur que le lien entre l'initial et le final soit respecté
				$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
				$typeLot['existant']['typePlancherBas'] = $this->getCorrespApiB2c2('apiExtCorTypePlancherBas',$valEntre);
				$typeLot['existant']['structurePlancherBas'] = $this->getCorrespApiB2c2('apiExtCorStructurePlancherBas',$valEntre);
									
				//on en a besoin dans les tableaux de correspondance ci-après
				$valEntre['typePlancherBas'] = $typeLot['existant']['typePlancherBas'] ;				
				
				$solutionExt = [];
				$solutionExt['resTypeIsoPlancherBas'] =  $this->getCorrespApiB2c2('apiExtCorResTypeIsoPlancherBas', $valEntre);	
				$solutionExt['resCaracIsoPlancherBas'] =  $this->getCorrespApiB2c2('apiExtCorResCaracIsoPlancherBas', $valEntre);	
				
				$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);
					
				$resistance = (string) ($xmlLot->xpath('donnee_entree/resistance_isolation')[0] ?? '');
				if ($resistance !=='' ){
					$typeLot['existant']['perfPlancherBasFinal_customVal'] = (float) $resistance;
					$typeLot['existant']['perfPlancherBasFinal'] = 'customVal';
				}		
			
		}
		$this->chercheLotSupprimes($nomLot,$etape);
	}
	
	
	
	/*************************************************/
	/*     M E N U I S E R I E S     */
	/*************************************************/	

	protected function parseDataInitMenuiserie($logement){
		$res = [];
		
		$nomLot = 'menuiserie';
		
		$surfTotale = 0;
		
		$aLot = $logement->xpath('enveloppe/baie_vitree_collection/baie_vitree');

		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');

			$resLot['protectionSolaireMenuiserie'] = $this->getCorrespApiB2c2('apiExtCorProtectionSolaireMenuiserie',$valEntre);
			$resLot['fenetreDeToit'] = $this->getCorrespApiB2c2('apiExtCorFenetreDeToit',$valEntre);
			
			
			$this->setPeformancesInit($resLot,$nomLot,$xmlLot->xpath('donnee_intermediaire/uw')[0] ?? '');
		
			
			$surface = $this->getPartXmlLot($nomLot,$xmlLot);	
			$resLot['surfMenuiserie'] = $surface;
			$this->jData['projet']['batiment']['surfMur'] +=  $surface; //on ajoute la surf des menuiseries aux murs


			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			$res[] = ['existant' => $resLot];

		}
			
		$this->jData['projet']['lstLot']['menuiserie'] = $res;
		

	}	


	
	protected function parseDataSolutionMenuiserie($logement){
		$aLot = $logement->xpath('enveloppe/baie_vitree_collection/baie_vitree');


		$nomLot = 'menuiserie';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);

		foreach ($aLotATraiter as $numLot=>$xmlLot){

			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);

			$typeLot['existant']['etape']=$etape;
			
			//on ecrase la désignation du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			
			$enum_etape_id = (int) $logement->xpath('caracteristique_generale/enum_etape_id')[0] ?? '';

			$valEntre = $this->xml2array($xmlLot->xpath('donnee_entree') ?? '');
			
			
			
			$solutionExt = [];
			$solutionExt['resTypePoseMenuiserie'] =  $this->getCorrespApiB2c2('apiExtCorResTypePoseMenuiserie', $valEntre);	
		
			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);
				
			$resistance = (string) ($xmlLot->xpath('donnee_intermediaire/uw')[0] ?? '');
			if ($resistance !=='' ){
				$typeLot['existant']['perfMenuiserieFinal_customVal'] = (float) $resistance;
				$typeLot['existant']['perfMenuiserieFinal'] = 'customVal';
			}					
			
		}
		$this->chercheLotSupprimes($nomLot,$etape);
	}
	
	

	
	/*************************************************/
	/*     E C S              */
	/*************************************************/	
	
	protected function parseDataInitEcs($logement){
		$res = [];
		
		$nomLot = 'ecs';	
		
		$aLot = $logement->xpath('installation_ecs_collection/installation_ecs');
		
		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			
			$valEntre = [];
			$valEntre['enum_type_installation_solaire_id'] = '';//le champ n'est pas forcément remplis.			
			$valEntre['reseau_distribution_isole'] = '';//le champ n'est pas forcément remplis.
			
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('generateur_ecs_collection/generateur_ecs/donnee_entree') ?? ''));
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));		
			
			$resLot['typeEcs'] = $this->getCorrespApiB2c2('apiExtCorTypeEcs',$valEntre);
			
			
			$resLot['typeReseauEcs'] = $this->getCorrespApiB2c2('apiExtCorTypeReseauEcs',$valEntre);

			$resLot['part'] = $this->getPartXmlLot($nomLot,$xmlLot);

	
			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			$res[] = ['existant' => $resLot];
		}

		
		$this->jData['projet']['lstLot']['ecs'] = $res;
		
	}	

	
	
	protected function parseDataSolutionEcs($logement){
		$aLot = $logement->xpath('installation_ecs_collection/installation_ecs');

		//met le enum_etat_composant_id à 2  si le générateur a été remplacé
		foreach ($aLot as $numLot=>$xmlLot){
			$enum_etat_gen = intval($xmlLot->xpath('generateur_ecs_collection/generateur_ecs/donnee_entree/enum_etat_composant_id')[0] ?? '');
			if ($enum_etat_gen==2){
				$xmlLot->xpath('donnee_entree/enum_etat_composant_id')[0][0]=2;
			}
		}


		$nomLot = 'ecs';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);

		foreach ($aLotATraiter as $numLot=>$xmlLot){
			
			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
			
			
	
			$typeLot['existant']['etape']= $etape;
			
			//on ecrase la désignation du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			
			$valEntre = [];
			$valEntre['typeBat'] = $this->jData['projet']['batiment']['typeBat'];					
			$valEntre['enum_type_installation_solaire_id'] = '';//le chmap n'est pas forcément remplis.	
				
					
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('generateur_ecs_collection/generateur_ecs/donnee_entree') ?? ''));
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));						
			
			$valEntre = array_merge($typeLot['existant'],$valEntre);
			
			$solutionExt = [];
			$solutionExt['resTypeProdEcs'] =  $this->getCorrespApiB2c2('apiExtCorResTypeProdEcs', $valEntre);	
			
			
			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);	
				
					
				
			
		}
		$this->chercheLotSupprimes($nomLot,$etape);
	}

	/*************************************************/
	/*     C H A U F F A G E             */
	/*************************************************/	
	

	protected function simpleXMLAppend($xmlSource,$path,$xmlChild){
		// Create new DOMElements from the two SimpleXMLElements		
		$domSource = dom_import_simplexml($xmlSource->xpath($path)[0]);
		$domChild  = dom_import_simplexml($this->simpleXMLClone($xmlChild));

		// Import the <xmlChild> into the dictionary document
		$domChild  = $domSource->ownerDocument->importNode($domChild, TRUE);

		// Append the <xmlChild> to <xmlSource> in the dictionary
		$domSource->appendChild($domChild);
	}
	
	protected function simpleXMLClone($sXml){
		return new SimpleXMLElement($sXml->asXML());		
	}
	
	
	protected function ajouteEmetGen($insallSansGenNiEmet,$emet,$gen = false){
		$newInstall = $this->simpleXMLClone($insallSansGenNiEmet);	

		$enum_etat_composant_id_emet = (string) ($emet->xpath('donnee_entree/enum_etat_composant_id')[0] ?? '');
		if ($gen){			
			$enum_etat_composant_id_gen = (string) ($gen->xpath('donnee_entree/enum_etat_composant_id')[0] ?? '');
		} else {
			$enum_etat_composant_id_gen = 1 ;
		}	
		
		//gestion du enum_etat_composant_id
		if ( $enum_etat_composant_id_emet == 2 OR $enum_etat_composant_id_gen == 2 ){
			$newInstall->xpath('donnee_entree/enum_etat_composant_id')[0][0] = 2;
		}
		
		$this->simpleXMLAppend($newInstall,'emetteur_chauffage_collection',$emet);		
		if ($gen!==false){
			$this->simpleXMLAppend($newInstall,'generateur_chauffage_collection',$gen);
		}
		
		return $newInstall;
	}
	
	protected function getListeChauffageEmetGen($logement){
		//fait un jointure entre les emetteurs et les générateurs et renvoie la liste.
		$res = [];
		
		$aInstall = $logement->xpath('installation_chauffage_collection/installation_chauffage');
		
		foreach ($aInstall as $numInstall=>$install){
			
			$insallSansGenNiEmet = $this->simpleXMLClone($install);	
	
			$aGen = $insallSansGenNiEmet->xpath('generateur_chauffage_collection/generateur_chauffage');				
			//supression des générateurs
			foreach ($aGen as $gen ){	
				unset($gen[0][0]);
			}			
			//supression des emetteurs
			$aEmet = $insallSansGenNiEmet->xpath('emetteur_chauffage_collection/emetteur_chauffage');				
			foreach ($aEmet as $emet){	
				unset($emet[0][0]);
			}
			
			$aGen = $install->xpath('generateur_chauffage_collection/generateur_chauffage');			
			$aEmet = $install->xpath('emetteur_chauffage_collection/emetteur_chauffage');	
			
			$cptAjout = 0;
			foreach ($aEmet as $emet ){	
				$aInstallEmet = [];	
				
				$idLien = (string) ($emet->xpath('donnee_entree/enum_lien_generateur_emetteur_id')[0] ?? '');
				
				//lien avec le générateur
				foreach ($aGen as $gen){
					$idLienCurrent = (string) ($gen->xpath('donnee_entree/enum_lien_generateur_emetteur_id')[0] ?? '');
					if ($idLien == $idLienCurrent) {
						//ajout d'une ligne dans 
						$aInstallEmet[] = $this->ajouteEmetGen($insallSansGenNiEmet,$emet,$gen);
						$cptAjout++;
					}
				}
				
				//si pas de générateur
				if (count($aInstallEmet) ==0){
					//ajout d'une ligne dans 
					$aInstallEmet[] = $this->ajouteEmetGen($insallSansGenNiEmet,$emet/*,$gen*/);
					$cptAjout++;
				} else {
					//répartition de la surface de chauffage si plusieurs générateur pour un même emetteur
					foreach ($aInstallEmet as $installEmet){
						$surface = $installEmet->xpath('emetteur_chauffage_collection/emetteur_chauffage/donnee_entree/surface_chauffee');
						$surface[0][0] = floatval($surface[0]) / count($aInstallEmet);						
					}
				}
				
				$res = array_merge($res,$aInstallEmet); //dans ce cas précis, array_merge rajoute un élément au tableau $res
			}
			
			if ($cptAjout ==0){
				trigger_error("Installation de chauffage sans émetteur.");
			}
		}
		
		return $res;
	}
	
	protected function parseDataInitChauffage($logement){
		
		$aInstall = $this->getListeChauffageEmetGen($logement);
		
		$res = [];
		$nomLot = 'chauffage';
				
		foreach ($aInstall as $numInstall=>$xmlLot){		
				
			$emet = $xmlLot->xpath('emetteur_chauffage_collection/emetteur_chauffage')[0];
			$generateur = $xmlLot->xpath('generateur_chauffage_collection/generateur_chauffage')[0];
			
			
			$resLot = [];
					
			$resLot['designLot'] = $this->getDesignLot($xmlLot,$generateur,$emet);
			
			$valEntre = [];
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));	
			$valEntre = array_merge($valEntre,$this->xml2array($emet->xpath('donnee_entree') ?? ''));
							
			$valEntre['enum_type_generateur_ch_id'] = '';
			$valEntre['enum_type_energie_id'] = '';
			if ($generateur){
				$valEntre = array_merge($valEntre,$this->xml2array($generateur->xpath('donnee_entree') ?? ''));
		
			}
		
			$resLot['chauffageCollectif'] = $this->getCorrespApiB2c2('apiExtCorChauffageCollectif',$valEntre);
			$resLot['typeProdChauffage'] = $this->getCorrespApiB2c2('apiExtCorTypeProdChauffage',$valEntre);
			$resLot['typeEmmeteurChauffage'] = $this->getCorrespApiB2c2('apiExtCorTypeEmmeteurChauffage',$valEntre);
			

			$resLot['part'] =$this->getPartXmlLot($nomLot,$xmlLot);

	
			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
	
			$res[] = ['existant' => $resLot];
			
		}
		
		
		$this->jData['projet']['lstLot']['chauffage'] = $res;

	}
	
	
	protected function parseDataSolutionChauffage($logement){
		
		$aLot = $this->getListeChauffageEmetGen($logement);

		$nomLot = 'chauffage';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);


		foreach ($aLotATraiter as $xmlLot){			
		
			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
			
			
			$emet = $xmlLot->xpath('emetteur_chauffage_collection/emetteur_chauffage')[0];
			$generateur = $xmlLot->xpath('generateur_chauffage_collection/generateur_chauffage')[0] ?? '';
			
			
			$typeLot['existant']['etape']=$etape;
			
			//on ecrase la désignation du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			
			$valEntre = [];
			$valEntre['typeBat'] = $this->jData['projet']['batiment']['typeBat'];	
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));	
			$valEntre = array_merge($valEntre,$this->xml2array($emet->xpath('donnee_entree') ?? ''));
							
			$valEntre['enum_type_generateur_ch_id'] = '';
			$valEntre['enum_type_energie_id'] = '';
			if ($generateur){
				$valEntre = array_merge($valEntre,$this->xml2array($generateur->xpath('donnee_entree') ?? ''));
			} 
			
			$solutionExt = [];

			$solutionExt['resTypeProd'] =  $this->getCorrespApiB2c2('apiExtCorResTypeProd', $valEntre);	
			$solutionExt['resEmetteurChauffage'] =  $this->getCorrespApiB2c2('apiExtCorResEmetteurChauffage', $valEntre);	


			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);			
			
		}
		$this->chercheLotSupprimes($nomLot,$etape);
	}
	

	/*************************************************/
	/*     V E N T I L A T I O N            */
	/*************************************************/	
	protected function parseDataInitVentilation($logement){

		$res = [];
		
		$nomLot = 'ventilation';
		
		$aLot = $logement->xpath('ventilation_collection/ventilation');
		
		foreach ($aLot as $numLot=>$xmlLot){
			$resLot = [];			
			$resLot['designLot'] = $this->getDesignLot($xmlLot);
			
			$valEntre = [];
			
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('ventilation_collection/ventilation/donnee_entree') ?? ''));
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));	
			$valEntre['typeProdChauffage'] = $this->getTypeLotPrinc('chauffage')['existant']['typeProdChauffage'];
	
			$resLot['chauffageDependantVentilation'] = $this->getCorrespApiB2c2('apiExtCorChauffageDependantVentilation',$valEntre);
			$resLot['typeVentilation'] =  $this->getCorrespApiB2c2('apiExtCorTypeVentilation',$valEntre);

			$resLot['part'] = $this->getPartXmlLot($nomLot,$xmlLot);
			
			$resLot['cleLot'] = $this->getCleLot($nomLot,$xmlLot);
			$res[] = ['existant' => $resLot];
			
		}
		
		$this->jData['projet']['lstLot']['ventilation'] = $res;
		
	}		
	
	
	
	protected function parseDataSolutionVentilation($logement){
		$aLot = $logement->xpath('ventilation_collection/ventilation');
		
		$nomLot = 'ventilation';	
		$etape = $this->get_numeroEtape($logement);		
		$aLotATraiter = $this->prepareLotsATraiter($nomLot,$aLot,$etape);

		foreach ($aLotATraiter as $numLot=>$xmlLot){
					
			$typeLot = &$this->getLotInitalCorrespondant($nomLot,$xmlLot,$etape);
			
			$typeLot['existant']['etape']=$etape;	
			
			
			
			$valEntre = [];
			$valEntre['typeBat'] = $this->jData['projet']['batiment']['typeBat'];
			$valEntre = array_merge($valEntre,$this->xml2array($xmlLot->xpath('donnee_entree') ?? ''));	
			
			//on ecrase la désignation du lot car on est pas sur que le lien entre l'initial et le final soit respecté
			$typeLot['existant']['designLot'] = $this->getDesignLot($xmlLot);
			$valEntre['typeProdChauffage'] = $this->getTypeLotPrinc('chauffage')['existant']['typeProdChauffage'];	//on en a besoin pour calculer apiExtCorChauffageDependantVentilation
			$typeLot['existant']['chauffageDependantVentilation'] = $this->getCorrespApiB2c2('apiExtCorChauffageDependantVentilation',$valEntre);
			$typeLot['existant']['typeVentilation'] =  $this->getCorrespApiB2c2('apiExtCorTypeVentilation',$valEntre);
	
	
			$solutionExt = [];
			$solutionExt['resTypeVentilation'] =  $this->getCorrespApiB2c2('apiExtCorResTypeVentilation', $valEntre);	

			$this->affecteLotSolutionExt($nomLot,$typeLot,$solutionExt);			
			
		}
		
		$this->chercheLotSupprimes($nomLot,$etape);
	}

		
	protected function warning($msg,$detail=''){
		if (!empty($this->bDebugApi) /*!$this->bSortiePDF*/){
			$msg.=$detail;
		}
		$this->aWarning[] = $msg;
	}
	
	

	
	protected function getCorrespApiB2c2($nomTableau,$aData,$champRes = 'idB2c2'){	
	
		$champsManquant = $this->getChampManquantEntree($nomTableau,$aData);
		if ($champsManquant!==false){
			trigger_error/*$this->warning*/("Il manque les champs '".implode("', '",$champsManquant)."' pour de trouver la correspondace du champ '$nomTableau' ".print_r($aData,true));
		}		
		
		$res = $this->chercheValeurTableau($nomTableau,$aData);

		
		if (isset($res[$champRes])){
			$valRetour = $res[$champRes];
		} else {
			$valRetour = 'nonPreconise';
		}
		
		switch ($valRetour){
			case 'erreur' : 
				trigger_error($res['libB2c2']);
				break;
			case 'nonPreconise':
			case 'erreurSaisie':
				$msg = $this->getValChamp('remarqueSolution',$valRetour);
				if($this->bDebugApi){
					$msg .= " Champ '$nomTableau'";
				}
				$msg .= " A l'étape n°".$this->debugNEtape.", pour le lot ".$this->debugNomLot.". ";
				$this->warning($msg,print_r($aData,true));
				break;
		}
		
		return $valRetour;
	}
		

	
	
	
	function extAfficheFormTest(){		
		ob_start();
		?>			
			<style>
				#fromTestApiExt > *{
					display:block;
				}				
				#fromTestApiExt > textarea{
					width : 100%;
					margin: 20px 0;
				}
				
				#fromTestApiExt #typeRetour,
				#fromTestApiExt  #btTestApiExt{
					display:inline-block;
				}
				
				.json-viewer ul{
					overflow:auto;
				}
			</style>
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/apiFdrB2C2.js?<?php echo date('Ymd_His'); ?>"></script>
			
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/lib/xml-viewer/simpleXML.js?<?php echo date('Ymd_His'); ?>"></script>			
			<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/lib/xml-viewer/simpleXML.css">
			
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/lib/json-viewer/json-viewer.js"></script>
			<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/lib/json-viewer/json-viewer.css">			
			<script>
					apiFdrB2C2.URI_BASE = '<?php echo CMSEffiUtils::getCMSUriBase(); ?>';
			</script>
						
			<div id="fromTestApiExt">
				<br><br>
				<h3>1. Données d'entrée</h3><hr>
				<div>text-in / drag'n drop ou <input type="file"  id="inputFile" name="inputFile" accept="text/xml" style="color: rgba(0, 0, 0, 0)"/></div>	
				<textarea name="inputVal" id="inputVal"></textarea>				
				<div>Data viewer in</div>
				
				<div id="dataViewerIn"></div>
				
				<br><br><br>
				<h3>2. Traitement</h3><hr>
				<select id="typeRetour" >
					<option value="auditProprietairePdf">Audit Proprietaire PDF</option>
					<option value="auditEditeurPdf" selected>Audit Editeurs PDF</option>
				<?php
				if (CMSEffiUtils::userIsManagerB2C2()){
				?>
					<option value="auditPdf" >Audit PDF</option>
				<?php
				}
				?>		
					<option value="auditJson" >Audit JSON</option>
					<option value="projetJson" >Projet JSON</option>	
				<?php
				if (CMSEffiUtils::userIsManagerB2C2()){
				?>
					<option value="recapTravauxXml" >Recap Travaux Xml</option>
				<?php
				}
				?>		
				</select>
			<?php
			if (CMSEffiUtils::userIsManagerB2C2()){
			?>
				<select id="debugMode" >
					<option value="" selected>Debug OFF</option>
					<option value="on" >Debug ON</option>
				</select>
			<?php
			}
			?>
				<br>enumScenario à traiter : <input type="text" value="1" name="enumScenario" id="enumScenario">	
				<input type="button" value="Lancer le traitement" name="btTestApiExt" id="btTestApiExt">	
				<div id="loading"><img src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>img/loadding.gif" ></div>
				
				<br><br><br>
				<h3>3. Données de sortie</h3><hr>
				<div>text-out</div>	
				<textarea name="outputVal" id="outputVal"></textarea>
				<div>Data viewer out</div>
				<div id="dataViewerOut"></div>
			</div>
			
		<?php
		$res = ob_get_clean();
		echo $res;
	}	

	

	
}
?>