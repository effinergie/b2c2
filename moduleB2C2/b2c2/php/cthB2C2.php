<?php
require_once 'fdrB2C2.php';

class CthB2C2 extends FdrB2C2{
	protected $aGVEtape=[];
	protected $calcResult=[];
	
	/*************************************
	* Calcul Thermique
	**************************************/	
	public function cth(){	
		$this->setErrorToException();

		try {	
				
			if (/*false and */isset($this->jData['calcThXmlAudit'])){
				$this->calcResult = $this->jData['calcThXmlAudit'];
			} else {	
				$this->cth_conso(0);
				foreach	($this->jFdr['parcour']['etapes'] as $nEtap=>$etap){
					if ($nEtap != 'nonTraite'){
						$this->cth_conso($nEtap);
					}
				}
			}
		
			
			
			$this->calcResult['calculEnergetiqueDetail'] = $this->cth_calculEnergetiqueDetail();
			return $this->calcResult;
		} catch (Throwable $e) {
			$data = $e->__toString();
			trigger_error(/*$this->trace(*/"#### ERREUR #### : " . $e->getMessage()."<br><pre>\n" .$data .'</pre>');
	
			return [];
		} 
	}

	public function cth_conso($nEtap){
		$this->trace ('*******************************************************','hl');
		$this->trace ("* CALCUL CEP ETAPE N°$nEtap",'hl');
		$this->trace ('*******************************************************','hl');
		$res = 0;
		$consoChauffage = $this->cth_consoChauffage($nEtap);
		$consoECS = $this->cth_consoEcs($nEtap);
		$cepClim = $this->cth_CepClim($nEtap);
		$cepEclairage = $this->cth_CepEclairage($nEtap);
		$cepAuxEtVent = $this->cth_CepAuxEtVent($nEtap);
		
		$this->calcResult['etape'][$nEtap]['consoLot']['chauffage'] = $consoChauffage;
		$this->calcResult['etape'][$nEtap]['consoLot']['ecs'] = $consoECS;
		$this->calcResult['etape'][$nEtap]['consoLot']['clim'] = $cepClim;
		$this->calcResult['etape'][$nEtap]['consoLot']['eclairage'] = $cepEclairage;
		$this->calcResult['etape'][$nEtap]['consoLot']['auxetvent'] = $cepAuxEtVent;
		
		

		$cef = $consoChauffage['cef'] + $consoECS['cef'] + $cepClim['cef'] + $cepEclairage['cef'] + $cepAuxEtVent['cef'];
		$cep = $consoChauffage['cep'] + $consoECS['cep'] + $cepClim['cep'] + $cepEclairage['cep'] + $cepAuxEtVent['cep'];
		$ges = $consoChauffage['ges'] + $consoECS['ges'] + $cepClim['ges'] + $cepEclairage['ges'] + $cepAuxEtVent['ges'];
				
		$this->trace('cth_conso() : $cep = $consoChauffage[cep] + $consoECS[cep] + $cepClim[cep] + $cepEclairage[cep] + $cepAuxEtVent[cep]; ','hl');
		$this->trace("cth_conso() : $cep = $consoChauffage[cep] + $consoECS[cep] + $cepClim[cep] + $cepEclairage[cep] + $cepAuxEtVent[cep]; ",'hl');
		$this->trace('cth_conso() : $ges = $consoChauffage[ges] + $consoECS[ges] + $cepClim[ges] + $cepEclairage[ges] + $cepAuxEtVent[ges]; ');
		$this->trace("cth_conso() : $ges = $consoChauffage[ges] + $consoECS[ges] + $cepClim[ges] + $cepEclairage[ges] + $cepAuxEtVent[ges]; ");
		
// pour test
//		$cep = 389;
//		$ges = 79;
//		$this->jData['projet']['batiment']['altitude'] = 800;
//		$this->jData['projet']['general']['departement'] = 34;
		
		$res = [
				'cef'=>$cef,				
				'cep'=>$cep,				
				'ges'=>$ges,
				'classeDpe'=>$this->cth_niveauDpeCepGes($cep,$ges),
				'classeGes'=>$this->cth_niveauDpeGes($ges)
				];
				
		$this->calcResult['etape'][$nEtap]['consoTotal'] = $res;

	}
	/*************************************
	* Chauffage
	**************************************/		
	protected function cth_consoChauffage($nEtap){
		$consoChauffageTotal = [];
		
		foreach($this->jData['projet']['lstLot']['chauffage'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$consoChauffageType = $this->cth_consoChauffageType($typeLot,$nEtap);
			$consoChauffageTotal = $this->assocArraySummByKey([$consoChauffageTotal,$consoChauffageType],$prct/100 );
		}
		
		
		return $consoChauffageTotal;		
	}

	protected function assocArraySummByKey($aArr,$coef = 1){
		$res = [];
		foreach ($aArr as $arr){
			foreach ($arr as $key=>$val){
				if (!isset($res[$key])){
					$res[$key] = 0;
				}
				$res[$key]+=$val*$coef;
			}
		}		
		return $res;
	}


	protected function cth_consoChauffageType($typeChauffage,$nEtap){
		$bCh = $this->cth_BchTotal($typeChauffage,$nEtap);
		return $this->cth_consoChauffageType_detail($bCh,$typeChauffage,$nEtap);	
	}


	protected function cth_consoChauffageType_detail($bCh,$typeChauffage,$nEtap){
		$Rg = $this->cth_Rg_Chaufage($typeChauffage,$nEtap);		

		$typeEnergie = $this->paramChampAvantApres($typeChauffage,$nEtap,'typeProdChauffage','typeEnergie','resTypeProd','typeEnergie');				
		
		$facteurEfEp = $this->floatvalFr($this->cth_coefEfEpTypeEnergie($typeEnergie));				
		$facteurGES = $this->floatvalFr($this->cth_coefGESTypeEnergie($typeEnergie));				
		$facteurCoutEf = $this->floatvalFr($this->cth_coefCoutEfTypeEnergie($typeEnergie));				
		$Rr = $this->cth_Rr_Chaufage($typeChauffage,$nEtap);
		$Re = $this->cth_Re_Chaufage($typeChauffage,$nEtap);
		$Rd =$this->cth_Rd_Chaufage($typeChauffage,$nEtap);

		$ich = 0;

		if ($Rr*$Rd*$Re*$Rg){
			$ich = 1/($Rr*$Rd*$Re*$Rg);			
		}
		$this->trace ('cth_consoChauffageType_detail() : $ich = 1/($Rr*$Rd*$Re*$Rg);');
		$this->trace ("cth_consoChauffageType_detail() : $ich = 1/($Rr*$Rd*$Re*$Rg);");
		
		$cefChauffage = $bCh * $ich;
		$this->trace ('cth_consoChauffageType_detail() : $cefChauffage = $bCh * $ich');
		$this->trace ("cth_consoChauffageType_detail() : $cefChauffage = $bCh * $ich");
		
		$cepChauffage = $facteurEfEp * $cefChauffage;
		$gesChauffage = $facteurGES * $cefChauffage;
		$coutChauffage = $facteurCoutEf * $cefChauffage;

		$this->trace ('cth_consoChauffageType_detail() : $cepChauffage = $facteurEfEp * $cefChauffage;');
		$this->trace ("cth_consoChauffageType_detail() : $cepChauffage = $facteurEfEp * $cefChauffage;");
		
		$this->trace ('cth_consoChauffageType_detail() : $gesChauffage = $facteurGES * $cefChauffage;');
		$this->trace ("cth_consoChauffageType_detail() : $gesChauffage = $facteurGES * $cefChauffage;");
		
		
		$this->trace ('cth_consoChauffageType_detail() : $coutChauffage = $facteurCoutEf * $cefChauffage;');
		$this->trace ("cth_consoChauffageType_detail() : $coutChauffage = $facteurCoutEf * $cefChauffage;");
		
		$res = [
				'cef'=>$cefChauffage,
				'cep'=>$cepChauffage,
				'ges'=>$gesChauffage,
				'cout'=>$coutChauffage
			];
			
		$this->cth_consoChauffageAppoint($bCh,$typeChauffage,$nEtap,$res);
		
		return $res;
	}
	
	protected function cth_consoChauffageAppoint($bCh,$typeChauffage,$nEtap,&$consoChauffPrinc){
		$aAppoint = $this->cth_infoChauffageAppoint($typeChauffage,$nEtap);
		
		if ($aAppoint){
			$prodAppoint = $aAppoint[0];
			$partAppoint = $this->floatvalFr($aAppoint[1]);			
			$partChPrinc = 1-$partAppoint;
			$emmetAppoint = $prodAppoint;	
			
			$copieTypeChauffage = $typeChauffage;
			if (!$this->travauxEffectues($typeChauffage,$nEtap)){
				$copieTypeChauffage['existant']['typeProdChauffage'] = $prodAppoint ;
				$copieTypeChauffage['existant']['typeEmmeteurChauffage'] = $emmetAppoint ;
				$copieTypeChauffage['existant']['appoint'] = '' ;
			} else {
				$idSol = $copieTypeChauffage['existant']['idSol'];
				$copieTypeChauffage['lstSol'][$idSol]['resTypeProd'] = [$prodAppoint] ;
				$copieTypeChauffage['lstSol'][$idSol]['resEmetteurChauffage'] = [$emmetAppoint] ;
				$copieTypeChauffage['lstSol'][$idSol]['appoint'] = [] ;
			}
			$consoAppoint = $this->cth_consoChauffageType_detail($bCh,$copieTypeChauffage,$nEtap);
			$consoChauffPrinc['cep'] = $partChPrinc * $consoChauffPrinc['cep'] + $partAppoint * $consoAppoint['cep'];
			$consoChauffPrinc['ges'] = $partChPrinc * $consoChauffPrinc['ges'] + $partAppoint * $consoAppoint['ges'];
			$consoChauffPrinc['cef'] = $partChPrinc * $consoChauffPrinc['cef'] + $partAppoint * $consoAppoint['cef'];
			$consoChauffPrinc['cout'] = $partChPrinc * $consoChauffPrinc['cout'] + $partAppoint * $consoAppoint['cout'];
			
			
			
			$this->trace ('cth_consoChauffageAppoint() : $consoChauffPrinc[cep] = $partChPrinc * $consoChauffPrinc[cep] + $partAppoint * $consoAppoint[cep];');
			$this->trace ("cth_consoChauffageAppoint() : $consoChauffPrinc[cep] = $partChPrinc * $consoChauffPrinc[cep] + $partAppoint * $consoAppoint[cep];");
		}		
	}
	
	protected function cth_infoChauffageAppoint($typeChauffage,$nEtap){
		$appoint = $this->paramChampAvantApres($typeChauffage,$nEtap,'typeProdChauffage','appoint','resTypeProd','appoint');
		
		if ($appoint){
			return explode('|',$appoint);
		}		
		
		return false;
	}
	
	protected function cth_Rg_Chaufage($typeChauffage,$nEtap){
		$nomChampAvant = ( $this->cth_chauffageCollectif($typeChauffage,$nEtap) ? 'RgChaufCol' : 'RgChaufInd');
		//attention, apres travaux, la valeur dépends du type de batiment et pas de si c'est un chauffage collectif.
		$nomChampApres = ( $this->isTypeBatLC() ? 'RgChaufCol' : 'RgChaufInd');
		$valRg = $this->paramChampAvantApres($typeChauffage,$nEtap,'typeProdChauffage',$nomChampAvant,'resTypeProd',$nomChampApres);

		if (in_array($valRg,['CopPacAirAir','CopPacAirEau'])){ //recherche le COP airair ou aireau
			$valRg = $this->cth_paramZoneClim($valRg);			
		}
		
		return $this->floatvalFr($valRg);
	}
	
	protected function cth_Rr_Chaufage($typeChauffage,$nEtap){
		$nomChampApres = $this->isTypeBatLC() ? 'RrChCol' : 'RrChInd';
		return $this->floatvalFr($this->paramChampAvantApres($typeChauffage,$nEtap,'typeEmmeteurChauffage','Rr','resTypeProd',$nomChampApres));
	}		
	protected function cth_Re_Chaufage($typeChauffage,$nEtap){
		$nomChampApres = $this->isTypeBatLC() ? 'ReChCol' : 'ReChInd';
		return $this->floatvalFr($this->paramChampAvantApres($typeChauffage,$nEtap,'typeEmmeteurChauffage','Re','resTypeProd',$nomChampApres));
	}		
	protected function cth_Rd_Chaufage($typeChauffage,$nEtap){
		$nomChampAvant = $this->cth_chauffageCollectif($typeChauffage,$nEtap) ? 'RdChCol' : 'RdChInd';
		//attention, apres travaux, la valeur dépends du type de batiment et pas de si c'est un chauffage collectif.
		$nomChampApres = $this->isTypeBatLC() ? 'RdChCol' : 'RdChInd';
		return $this->floatvalFr($this->paramChampAvantApres($typeChauffage,$nEtap,'typeEmmeteurChauffage',$nomChampAvant,'resTypeProd',$nomChampApres));
	}		

	protected function cth_BchTotal($typeChauffage,$nEtap){		
		$BchTotal=0;
		for ($iMoi = 1;$iMoi<=12;$iMoi++){
			$this->trace ("*** Chauffage Moi $iMoi ****");
			$BchTotal+=$this->cth_BchMoi($typeChauffage,$nEtap,$iMoi);			
		}
		$this->trace('cth_BchTotal() : $BchTotal','hl');
		$this->trace("cth_BchTotal() : $BchTotal",'hl');
		return $BchTotal;
	}

	protected function cth_BchMoi($typeChauffage,$nEtap,$iMoi){
		$Gv = $this->cth_Gv($nEtap);
		
		$solicitationExt = $this->cth_solicitationsExt($nEtap,$iMoi);
		$DhrefMoi = $this->floatvalFr($solicitationExt['DH19']);
		$NrefMoi = $this->floatvalFr($solicitationExt['Nref19']);
		$EMoi = $this->floatvalFr($solicitationExt['E']);
		
		$shab = $this->cth_shab();
					
		
		$fMoi = $this->cth_fMoi($iMoi,$Gv,$DhrefMoi,$NrefMoi,$EMoi,$nEtap); 	
		$bV = $Gv * (1-$fMoi);
		$this->trace ('cth_BchMoi($iMoi) : $bV = $Gv * (1-$fMoi); ');
		$this->trace ("cth_BchMoi($iMoi) : $bV = $Gv * (1-$fMoi); ");		
		
		$ratEcs = 1;
		
		$bEcsMoi = $this->cth_BEcsMoi($nEtap,$iMoi);
		
		$lvc = 0.2*$ratEcs*$shab;

		if ($this->partEcsCollective()>=50){
			$QdwVcMoi = 0.112*$bEcsMoi;
			$this->trace ('cth_BchMoi($iMoi) : $QdwVcMoi = 0.112*$bEcsMoi;');
			$this->trace ("cth_BchMoi($iMoi) : $QdwVcMoi = 0.112*$bEcsMoi;");			
		} else {
			$QdwVcMoi = ((0.5*$lvc)/$shab)*$bEcsMoi;
			$this->trace ('cth_BchMoi($iMoi) : $QdwVcMoi = ((0.5*$lvc)/$shab)*$bEcsMoi;');
			$this->trace ("cth_BchMoi($iMoi) : $QdwVcMoi = ((0.5*$lvc)/$shab)*$bEcsMoi;");			
		}
		
		$QRecChaufMoi = 0.48*$NrefMoi*$QdwVcMoi/8760;
		
		$this->trace ('cth_BchMoi($iMoi) : $QRecChaufMoi = 0.48*$NrefMoi*$QdwVcMoi/8760;');
		$this->trace ("cth_BchMoi($iMoi) : $QRecChaufMoi = 0.48*$NrefMoi*$QdwVcMoi/8760;");
		
		$infoECS = $this->cth_calculECS(null,$typeChauffage,$nEtap);
		$Qgw = $infoECS['Qgw'];
		$QgWrecMoi = 0.48*$NrefMoi*$Qgw/8760;
		$Pn = $infoECS['Pn'];	
		$Qp0 = 0.04*$Pn*1000; //calculThermique!D416 Todo : en attente de formule;
		$Qp0 = $infoECS['Qp0'];	
		$Cper = 0.5;



		$Dper_P1 = ($Pn == 0) ? 0 : 1.3*($bV*$DhrefMoi/1000)/(0.3*$Pn);
		$Dper_P2 = $NrefMoi*1790/8760;
		if($this->partEcsLieChauffage($nEtap)>=50){
			$DperMoi = min($NrefMoi , $Dper_P1+$Dper_P2);
			$this->trace ('cth_BchMoi($iMoi) : $DperMoi = min($NrefMoi , $Dper_P1+$Dper_P2)');
			$this->trace ('cth_BchMoi($iMoi) : $DperMoi = min($NrefMoi , $Dper_P1+$Dper_P2)');
		} else {
			$DperMoi = min($NrefMoi , $Dper_P1)+$Dper_P2;
			$this->trace ('cth_BchMoi($iMoi) : $DperMoi = min($NrefMoi , $Dper_P1)+$Dper_P2');
			$this->trace ("cth_BchMoi($iMoi) : $DperMoi = min($NrefMoi , $Dper_P1)+$Dper_P2");
		}
		
		$QgenrecMoi = 0.48 * $Cper * $Qp0 * $DperMoi;
		$this->trace ('cth_BchMoi($iMoi) : $QgenrecMoi = 0.48 * $Cper * $Qp0 * $DperMoi;');
		$this->trace ("cth_BchMoi($iMoi) : $QgenrecMoi = 0.48 * $Cper * $Qp0 * $DperMoi;");
			
		$hsp =  $this->cth_hsp();
		$G = $Gv/($shab *$hsp);
		$I0 = $this->cth_I0($typeChauffage,$nEtap); //Todo : calculThermiqueD310 // Calcul en fonction des emeteur ou du générateur... à voir avec julien.
		$intermitance = $I0/(1+0.1*($G-1)); 
		$this->trace ('cth_BchMoi($iMoi) : $intermitance = $I0/(1+0.1*($G-1)); ');
		$this->trace ("cth_BchMoi($iMoi) : $intermitance = $I0/(1+0.1*($G-1)); ");
		
		$bChMoi = $bV * $DhrefMoi / 1000 - ($QRecChaufMoi + $QgWrecMoi + $QgenrecMoi) / 1000 ; 
		$this->trace ('cth_BchMoi($iMoi) : $bChMoi = $bV * $DhrefMoi / 1000 - ($QRecChaufMoi + $QgWrecMoi + $QgenrecMoi) / 1000 ; ');
		$this->trace ("cth_BchMoi($iMoi) : $bChMoi = $bV * $DhrefMoi / 1000 - ($QRecChaufMoi + $QgWrecMoi + $QgenrecMoi) / 1000 ; ");
				
		$bchMoiFinal = $bChMoi * $intermitance / $shab;
		$this->trace ('cth_BchMoi($iMoi) : $bchMoiFinal = $shab * $bChMoi / $intermitance;','hl');
		$this->trace ("cth_BchMoi($iMoi) : $bchMoiFinal = $shab * $bChMoi / $intermitance;",'hl');
		
		return $bchMoiFinal;
	}
	
	
	public function cth_Tbase(){			
		$altitude = $this->getAltitude();			
		if ($altitude<400){
			$acolAlt = 'TbaseInf400';
		} else if ($altitude<800){
			$acolAlt = 'TbaseSup400inf800';
		} else {
			$acolAlt = 'TbaseSup800';
		}
		$Tbase = $this->floatvalFr($this->cth_paramZoneClim($acolAlt));
		return $Tbase;				
	}
	
	public function cth_I0($typeChauffage,$nEtap){	
		if(!$this->isTypeBatLC()){
			//MI
			$nomChampRg = ($this->cth_inertieBat($nEtap) == 'lourde')? 'I0IndLourd' : 'I0IndMoy';
		} else {
			//LC
			$nomChampRg = $this->cth_chauffageCollectif($typeChauffage,$nEtap) ? 'I0ColChCol' : 'I0ColChInd';
		}
		//attention : depuis la V15 Xls, le I0 Dépends des émetteurs à l'etat initial et de la production à l'etat final.
		return $this->floatvalFr($this->paramChampAvantApres($typeChauffage,$nEtap,'typeEmmeteurChauffage',$nomChampRg,'resTypeProd',$nomChampRg));
	}
	
	public function cth_chauffageCollectif($typeChauffage,$nEtap){	
		if ($this->travauxEffectues($typeChauffage,$nEtap)){
			if($this->isTypeBatLC()){
				//LC
				$valChamp = $this->getTypeLotSolution($typeChauffage)['resTypeProd'][0];		
				return $this->getColTableauValeur('resTypeProd','id',$valChamp,'chauffageCollectif')=='oui'; 				
			} else {
				//MI
				return false;
			}
		} else {
			return $typeChauffage['existant']['chauffageCollectif'] == 'oui';
		}	
	}
	
	public function cth_fMoi($iMoi,$Gv,$DhrefMoi,$NrefMoi,$EMoi,$nEtap){
		$shab = $this->cth_shab();		
		$Sse = $this->cth_Sse($nEtap);

		$AsMoi =  $Sse * $EMoi * 1000;	
		$this->trace ('cth_fMoi($iMoi) : $AsMoi =  $Sse * $EMoi * 1000');
		$this->trace ("cth_fMoi($iMoi) : $AsMoi =  $Sse * $EMoi * 1000");

		$Nadeq = $this->cth_Nadeq();

		$AiMoi = ((3.18+0.34)*$shab+90*(132/168)*$Nadeq)*$NrefMoi;
		$this->trace ('cth_fMoi($iMoi) : $AiMoi = ((3.18+0.34)*$shab+90*(132/168)*$Nadeq)*$NrefMoi;');
		$this->trace ("cth_fMoi($iMoi) : $AiMoi = ((3.18+0.34)*$shab+90*(132/168)*$Nadeq)*$NrefMoi;");		

		$xMoi = 0;
		if(($Gv*$DhrefMoi)!=0){
			$xMoi = ($AsMoi + $AiMoi)/($Gv*$DhrefMoi);
		}
		$this->trace ('cth_fMoi($iMoi) : $xMoi = ($AsMoi + $AiMoi)/($Gv*$DhrefMoi)');
		$this->trace ("cth_fMoi($iMoi) : $xMoi = ($AsMoi + $AiMoi)/($Gv*$DhrefMoi)");

		//$a = $this->floatvalFr($this->cth_paramBat('anneeConstr','alpha'));
		$a = ($this->cth_inertieBat($nEtap) ==  'lourde')? 3.6 : 2.9 ;

		$fMoi = 0;		
		if((1-($xMoi**$a)) !=0){
			$fMoi = ($xMoi-($xMoi**$a))/(1-($xMoi**$a));
		}

		$this->trace ('cth_fMoi($iMoi) : $fMoi = ($xMoi-($xMoi**$a))/(1-($xMoi**$a))');
		$this->trace ("cth_fMoi($iMoi) : $fMoi = ($xMoi-($xMoi**$a))/(1-($xMoi**$a))");		

		return $fMoi;
	}

	protected function cth_Nadeq(){
		$Nmax = $this->cth_nMax();
		
		$nbLogement = $this->cth_nbLogement();
		if($Nmax <1.75){
			return $nbLogement * $Nmax;
		} else {
			return $nbLogement * ( 1.75 + 0.3 * ($Nmax-1.75));
		}
	}

	protected function cth_nMax(){
		$shabLogement = $this->cth_shabLogement();
		
		if ($this->isTypeBatLC()){
			if($shabLogement<10){
				return 1;
			} else if ($shabLogement<50){
				return 1.75 - 0.01875 * (50 - $shabLogement);
			} else {
				return 0.035 * $shabLogement;
			}
		} else {
			if ($shabLogement<30){
				return 1;
			} else if ($shabLogement<70){
				return 1.75-0.01875*(70-$shabLogement);
			} else {
				return 0.025*$shabLogement;
			}
		}
	}

	protected function cth_Sse($nEtap){
		$sseTotal = 0;
		foreach($this->jData['projet']['lstLot']['menuiserie'] as $ntype => $typeLot){			
			$sse = $this->cth_SseTypeMen($typeLot,$nEtap);			
			$sseTotal += $sse;
		}
		
		$this->trace ('cth_Sse() : $sseTotal ');
		$this->trace ("cth_Sse() : $sseTotal ");
		
		return $sseTotal;
	}
	
	protected function cth_SseTypeMen($typeMen,$nEtap){
		$solMen = $this->getTypeLotSolution($typeMen);
		$bFenToit = $typeMen['existant']['fenetreDeToit'] == 'oui';

		$surfMenuiserie =  $typeMen['existant']['surfMenuiserie'];		
		
		if ($this->travauxEffectues($typeMen,$nEtap)){
			$Sw = 0.4;
		} else {
			$Sw = 0.5; 			
		}
		if ($solMen['resTypePoseMenuiserie'] == 'appliqueExt'){
			$Sw += 0.05;
		} 

		
		$mitoyen = $this->jData['projet']['batiment']['mitoyen'];
		if ($mitoyen =='isole'){
			$nomChamp_C1 = "coefOriFen_batIsole";
		} else {
			$nomChamp_C1 = "coefOriFen_batMitoyen";
		}
		if ($bFenToit){
			$nomChamp_C1 = "coefOriFen_fenDeToit";
		}
		$c1 = $this->floatvalFr($this->cth_paramBat('orientationBat',$nomChamp_C1));		
		
		
		$Fe = $this->isTypeBatLC() ?  0.7 : 1;
		if ($bFenToit){
			$Fe = 1;
		}
		
		$sseType =  $surfMenuiserie * $Sw * $c1 * $Fe;
		$this->trace ('cth_SseTypeMen() : $sseType =  $surfMenuiserie * $Sw * $c1 * $Fe; ');
		$this->trace ("cth_SseTypeMen() : $sseType =  $surfMenuiserie * $Sw * $c1 * $Fe; ");
		
		return  $sseType ;
	}

	/*************************************
	* Ecs
	**************************************/		
	protected function cth_consoEcs($nEtap){
		$consoEcsTotal = [];
		foreach($this->jData['projet']['lstLot']['ecs'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$consoEcsType = $this->cth_consoEcsType($typeLot,$nEtap);
			$consoEcsTotal = $this->assocArraySummByKey([$consoEcsTotal,$consoEcsType],$prct/100 );
		}
		
		return $consoEcsTotal;		
	}
	
	protected function cth_consoEcsType($typeLot,$nEtap){
		$shab = $this->cth_shab();
		
		$bEcsTotal = $this->cth_BEcsTotal($typeLot,$nEtap);

		$infoEcs = $this->cth_calculECS($typeLot,null,$nEtap,$bEcsTotal);
				
		$Iecs = $infoEcs['Iecs'] ;
				
		$Fecs = $this->cth_Fecs($typeLot,$nEtap);
		
		$bEcsSruf = ($bEcsTotal/$shab)/1000;
		$this->trace ('cth_consoEcsType() : $bEcsSruf = ($bEcsTotal/$shab)/1000');
		$this->trace ("cth_consoEcsType() : $bEcsSruf = ($bEcsTotal/$shab)/1000");
		
		$cefEcs = $Iecs *$bEcsSruf*(1-$Fecs);
		$this->trace ('cth_consoEcsType() : $cefEcs = $Iecs *$bEcsSruf*(1-$Fecs);');
		$this->trace ("cth_consoEcsType() : $cefEcs = $Iecs *$bEcsSruf*(1-$Fecs);");

		$typeEnergie = $this->cth_ecsTypeEnergie($typeLot,$nEtap);		
		$facteurEfEp = $this->floatvalFr($this->cth_coefEfEpTypeEnergie($typeEnergie));				
		$facteurGES = $this->floatvalFr($this->cth_coefGESTypeEnergie($typeEnergie));
		$facteurCoutEf = $this->floatvalFr($this->cth_coefCoutEfTypeEnergie($typeEnergie));	
		
		$cepEcs = $facteurEfEp * $cefEcs;
		$GesEcs = $facteurGES * $cefEcs;
		$CoutEcs = $facteurCoutEf * $cefEcs;
		
		$this->trace ('cth_consoEcsType() : $cepEcs = $facteurEfEp * $cefEcs;');
		$this->trace ("cth_consoEcsType() : $cepEcs = $facteurEfEp * $cefEcs;");
		
		$this->trace ('cth_consoEcsType() : $GesEcs = $facteurGES * $cefEcs;');
		$this->trace ("cth_consoEcsType() : $GesEcs = $facteurGES * $cefEcs;");
		
		$this->trace ('cth_consoEcsType() : $CoutEcs = $facteurCoutEf * $cefEcs;');
		$this->trace ("cth_consoEcsType() : $CoutEcs = $facteurCoutEf * $cefEcs;");
		
		return [
			'cef'=>$cefEcs,
			'cep'=>$cepEcs,
			'ges'=>$GesEcs,
			'cout'=>$CoutEcs
		];	
	}
	
		
	public function cth_calculECS($typeEcs,$typeChauffage,$nEtap,$bEcsTotal=0){
		//si on passe en parametre $bEcsTotal, alors on peut calculer le Rg_ECS et le I_ECS
		$zoneClim = $this->getZoneClim();
		
		if (!$typeEcs){
			$typeEcs = $this->getTypeLotPrincipal('ecs');
		}
		$typeLot = $typeEcs;
		$nomChampAvant = 'typeEcs';
		$nomChampApres = 'resTypeProdEcs';	
		if ($this->ecsConnectChauff($typeLot,$nEtap)){
			if (!$typeChauffage){
				$typeChauffage = $this->getTypeLotPrincipal('chauffage');	
			}
			$typeLot = $typeChauffage;
			$nomChampAvant = 'typeProdChauffage';
			$nomChampApres = 'resTypeProd';			
		}
		$methodeECS = $this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,'methodeECS',$nomChampApres,'methodeECS');
		
		$Qgw = 0;
		$Pn = 0;
		$Qp0 = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,'Qp0',$nomChampApres,'Qp0'));
		
		$RgRs=0;
		
		
		$Rpn = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,'Rpn',$nomChampApres,'Rpn'));
		$pnVeil = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,'pnVeil',$nomChampApres,'pnVeil'));
		$nomChampRd = (!$this->isTypeBatLC()) ? 'RdEcsInd' : 'RdEcsCol' ;
		$Rd = $this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,$nomChampRd,$nomChampApres,$nomChampRd);
		if ($Rd == 'voirTypeReseauEcs'){
			$Rd = $this->floatvalFr($this->getColTableauValeur('typeReseauEcs','id',$typeEcs['existant']['typeReseauEcs'],'RdEcsCol'));
		}
		$Rd = $this->floatvalFr($Rd);

		switch($methodeECS){	
			case 'gazInstantane':
				//methode 1
				$Pn = 24;
				
				if ($bEcsTotal!=0 AND $Rpn!=0){
					$RgRs=1/(1/$Rpn+(1790*$Qp0*1000*$Pn/$bEcsTotal)+ 7103*$pnVeil/$bEcsTotal);
				}
				break;
				
			case 'balonThermo':
				//methode 2
				$nomChampCOP = ($zoneClim=="H3")? 'COPH3' : 'COPH1H2';
				$COP = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,$nomChampAvant,$nomChampCOP,$nomChampApres,$nomChampCOP));
				$RgRs = $COP;				
				break;
				
			case 'reseauCh':
				//methode 3
				$RgRs=0.75;
				if (	
					$typeEcs['existant']['typeReseauEcs'] == 'collectifIsole' 
					OR
					$this->travauxEffectues($typeLot,$nEtap)
					){
					$RgRs=0.9;
				}
				break;
				
			case 'balonElec': 
				//methode 4
				
				if (!$this->isTypeBatLC()){
					$Qgw = 8592*45/24*200*0.23; // MI
				} else {
					$Qgw = 8592*45/24*150*0.23; // LC					
				}
				
				$facteur = 1;
				if ($this->ecsConnectChauff($typeLot,$nEtap)){
					$facteur = 1.08;
				}
				
				if ($bEcsTotal){
					$RgRs = $facteur/(1+$Qgw*$Rd/$bEcsTotal);
					$this->trace ('cth_calculECS() : $RgRs = $facteur/(1+$Qgw*$Rd/$bEcsTotal);');
					$this->trace ("cth_calculECS() : $RgRs = $facteur/(1+$Qgw*$Rd/$bEcsTotal);");
				}
				break;
				
			case 'generateurMixte':
				//methode 5
				$Vs = 100;
				$Qgw = 8592*$Vs*4.2*($Vs**(-0.45))*45/24;
											
				if (!$this->isTypeBatLC()){ 
						$Pn = 25; //MI
				} else {
					//LC
					if ($this->travauxEffectues($typeLot,$nEtap)){
						$Gv = $this->cth_Gv($nEtap);
					} else {
						$Gv = $this->cth_Gv(0);
					}
					
					$Tbase = $this->cth_Tbase();
					$ReCh = $this->cth_Re_Chaufage($typeChauffage,$nEtap);
					$RdCh =$this->cth_Rd_Chaufage($typeChauffage,$nEtap);	
					$RrCh = $this->cth_Rr_Chaufage($typeChauffage,$nEtap);						
					$Pn = 1.2*$Gv*(19-$Tbase)/1000/$ReCh/$RdCh/$RrCh;
					$this->trace ('cth_calculECS() : $Pn = 1.2*$Gv*(19-$Tbase)/1000/$ReCh/$RdCh/$RrCh;');
					$this->trace ("cth_calculECS() : $Pn = 1.2*$Gv*(19-$Tbase)/1000/$ReCh/$RdCh/$RrCh;");
				}
					

				if ($bEcsTotal!=0 AND $Rpn!=0){
					$RgRs = 1/(1/$Rpn+(1790*$Qp0 *$Pn*1000+$Qgw)/$bEcsTotal+7103*0.5*$pnVeil/$bEcsTotal);
				}
				break;
				
			default:		
				
		}
		$this->trace ('cth_calculECS() : $methodeECS, $Qgw, $Pn, $Qp0, $RgRs, $Rpn, $pnVeil, $Rd,');
		$this->trace ("cth_calculECS() : $methodeECS, $Qgw, $Pn, $Qp0, $RgRs, $Rpn, $pnVeil, $Rd,");
		
		$Iecs = 0;
		if ($Rd*$RgRs != 0 ){
			$Iecs = (1/($Rd*$RgRs));
			$this->trace ('cth_calculECS() : $Iecs = (1/($Rd*$RgRs));');
			$this->trace ("cth_calculECS() : $Iecs = (1/($Rd*$RgRs));");
		}
		
		return [
				'Qgw'=>$Qgw,
				'Pn'=>$Pn,
				'Qp0'=>$Qp0,
				'Iecs'=>$Iecs
				];
	}	
		

	
	protected function cth_Fecs($typeLot,$nEtap){		
		$presenceSolaire = $this->paramChampAvantApres($typeLot,$nEtap,'typeEcs','solaire','resTypeProdEcs','solaire');

		if ($presenceSolaire == 'oui'){
			if($this->isTypeBatLC()){				
				$nomColFecs = 'FecsCol';
			} else {
				$nomColFecs = 'FecsInd';
			}
			$zoneClim = $this->getZoneClim();
			return $this->floatvalFr($this->getColTableauValeur('zoneClim','id',$zoneClim,$nomColFecs));
		}
		
		return 0;
	}
	
	
	protected function cth_ecsTypeEnergie($typeLot,$nEtap){
		if ($this->ecsConnectChauff($typeLot,$nEtap)){
			$typeChauffage = $this->getTypeLotPrincipal('chauffage');
			$typeEnergie = $this->paramChampAvantApres($typeChauffage,$nEtap,'typeProdChauffage','typeEnergie','resTypeProd','typeEnergie');
		} else {	
			$typeEnergie = $this->paramChampAvantApres($typeLot,$nEtap,'typeEcs','typeEnergie','resTypeProdEcs','typeEnergie');
		}
		return $typeEnergie;
	}
		
	protected function cth_BEcsTotal($typeLot,$nEtap){
		$BEcsTotal=0;
		for ($iMoi = 1;$iMoi<=12;$iMoi++){
			$this->trace("*** ECS Moi $iMoi ****");
			$BEcsTotal+=$this->cth_BEcsMoi($nEtap,$iMoi);			
		}
		$this->trace('cth_BEcsTotal() : $BEcsTotal','hl');
		$this->trace("cth_BEcsTotal() : $BEcsTotal",'hl');
		return $BEcsTotal;
	}	

	protected function cth_BEcsMoi($nEtap,$iMoi){
		$solicitationExt = $this->cth_solicitationsExt($nEtap,$iMoi);	
		$TeauFroide = $this->floatvalFr($solicitationExt['Tefs']);

		$Nadeq = $this->cth_Nadeq();
		$nbJourOccup = $this->getColTableauValeur('moi','id',$iMoi,'nbJourOccup'); 

		$bEcsMoi = 1.163 * $Nadeq  * 56 * (40 - $TeauFroide) * $nbJourOccup;

		$this->trace ('cth_BchMoi($iMoi) : $bEcsMoi = 1.163 * $Nadeq  * 56 * (40 - $TeauFroide)*$nbJourOccup ');
		$this->trace ("cth_BchMoi($iMoi) : $bEcsMoi = 1.163 * $Nadeq  * 56 * (40 - $TeauFroide)*$nbJourOccup ");
		
		return $bEcsMoi;
	}
	
	/*************************************
	* Climatisation
	**************************************/		
	protected function cth_CEPClim($nEtap){
		return [
			'cef'=>0,
			'cep'=>0,
			'ges'=>0,
			'cout'=>0
			];

	}
	
	
	protected function cth_Sclim(){
		$shab = $this->cth_shab();
		
		$SClim = $shab * 0.75;
		return $SClim ;
	}
	
	/*************************************
	* Eclairage
	**************************************/		
	protected function cth_CepEclairage($nEtap){
		$nbHEclTotal = 0;
		for ($iMoi = 1;$iMoi<=12;$iMoi++){
			$zoneClim = $this->getZoneClim();
			$nomChampNbHEcl = "hEcl_".$zoneClim ;
			$nbHEcl = $this->floatvalFr($this->getColTableauValeur('moi','id',$iMoi,$nomChampNbHEcl)); 
			$nbJourOccup = $this->floatvalFr($this->getColTableauValeur('moi','id',$iMoi,'nbJourOccup')); 
			$nbHEclTotal += $nbHEcl * $nbJourOccup;
		}		
		
		$coefC = 0.9;
		$pEcl = 1.4;
		
		$cef = $coefC * $pEcl * $nbHEclTotal/1000;
		
		$facteurEfEp = $this->floatvalFr($this->cth_coefEfEpTypeEnergie('electrique'));
		$facteurGES = $this->floatvalFr($this->cth_coefGESTypeEnergie('electrique'));
		$facteurCoutEf = $this->floatvalFr($this->cth_coefCoutEfTypeEnergie('electrique'));
		
		$cepEclairage =  $facteurEfEp * $cef ;
		$gesEclairage =  $facteurGES * $cef ;
		$coutEclairage =  $facteurCoutEf * $cef ;
		
		$this->trace ('cth_CepEclairage() : $cepEclairage =  $facteurEfEp * $cef = $coefC * $pEcl * $nbHEclTotal/1000');	
		$this->trace ("cth_CepEclairage() : $cepEclairage =  $facteurEfEp * $cef = $coefC * $pEcl * $nbHEclTotal/1000");	
		
		$this->trace ('cth_CepEclairage() : $gesEclairage =  $facteurGES * $cef');	
		$this->trace ("cth_CepEclairage() : $gesEclairage =  $facteurGES * $cef");	
		
		return [
			'cef'=>$cef,
			'cep'=>$cepEclairage,
			'ges'=>$gesEclairage,
			'cout'=>$coutEclairage
			];
	}

	/*************************************
	* Auxiliares
	**************************************/		
	protected function cth_CepAuxiliares($nEtap){

		$facteurEfEp = $this->floatvalFr($this->cth_coefEfEpTypeEnergie('electrique'));
		$facteurGES = $this->floatvalFr($this->cth_coefGESTypeEnergie('electrique'));
		$facteurCoutEf = $this->floatvalFr($this->cth_coefCoutEfTypeEnergie('electrique'));

		$typeChauffage = $this->getTypeLotPrincipal('chauffage');
		
		$cef = 0.5;
		if ($this->cth_chauffageCollectif($typeChauffage,$nEtap)){
			$cef = 1;
		}
		
		$cepAux = $facteurEfEp * $cef;
		$gesAux = $facteurGES * $cef;
		$coutAux = $facteurCoutEf * $cef;
		
		
		$this->trace ('cth_CepAuxiliares() : $cepAux =  $facteurEfEp * $cef');	
		$this->trace ("cth_CepAuxiliares() : $cepAux =  $facteurEfEp * $cef");	
		
		$this->trace ('cth_CepAuxiliares() : $coutAux = $facteurCoutEf * $cef;');	
		$this->trace ("cth_CepAuxiliares() : $coutAux = $facteurCoutEf * $cef;");	
		

		return [
			'cef'=>$cef,
			'cep'=>$cepAux,
			'ges'=>$gesAux,
			'cout'=>$coutAux 
		];
	}
	
	protected function cth_CepAuxEtVent($nEtap){ 
		$consoAux = $this->cth_CepAuxiliares($nEtap)	;
		$consoVent = $this->cth_CepVentilation($nEtap)	;
				
		return $this->assocArraySummByKey([$consoAux,$consoVent]);		
	}

	/*************************************
	* Ventilation
	**************************************/		
	protected function cth_CepVentilation($nEtap){
		$consoVentil = [];
		$gesVentil = 0;
		
		foreach($this->jData['projet']['lstLot']['ventilation'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$CepVentilType = $this->cth_CepVentilationType($typeLot,$nEtap);
			$consoVentil = $this->assocArraySummByKey([$consoVentil,$CepVentilType],$prct/100 );
		}
		
		return $consoVentil;		
	}
	
	protected function cth_CepVentilationType($typeLot,$nEtap){
		$anneeConstr = $this->jData['projet']['batiment']['anneeConstr'];
		$shab = $this->cth_shab();
		
		$PventDefaut = 0.46;//'BBD thermique'!D1243
		
		$Qvarepconv = $this->cth_Qvarepconv($typeLot,$nEtap);					
		
		$ratioTemps = 1;
		if($this->isTypeBatLC()){
			$nomChampVal = "PventCol_m2";				
			$PventColl_m2 = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,'typeVentilation',$nomChampVal,'resTypeVentilation',$nomChampVal));			
			$Qvarepconv = $this->cth_Qvarepconv($typeLot,$nEtap);
			$PventMoy = $shab*$PventColl_m2*$Qvarepconv;
		} else {
			$nomChampVal = "PventInd";				
			$PventMoy = $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,'typeVentilation',$nomChampVal,'resTypeVentilation',$nomChampVal));				
		}	
		
		//cas particulier des ventilations naturelles assistées (apres travaux)
		if ($this->travauxEffectues($typeLot,$nEtap) && $this->getTypeLotSolution($typeLot)['resTypeVentilation']=="naturelleAssit"){
			$ratioTemps = $this->floatvalFr($this->cth_paramBat('typeBat','ratioTmpVentMeca'));
		}
	
		$nbHeure = 24 * 365; //=8760
		$nbHeureMarche = $nbHeure * $ratioTemps;		
		
		$Cventil =  $nbHeureMarche * $PventMoy / 1000;		
		
		$this->trace ('cth_CepVentilation() : $Cventil =  $nbHeureMarche * $PventMoy / 1000;');
		$this->trace ("cth_CepVentilation() : $Cventil =  $nbHeureMarche * $PventMoy / 1000;");
		
		$facteurEfEp = $this->floatvalFr($this->cth_coefEfEpTypeEnergie('electrique'));
		$facteurGES = $this->floatvalFr($this->cth_coefGESTypeEnergie('electrique'));
		$facteurCoutEf = $this->floatvalFr($this->cth_coefCoutEfTypeEnergie('electrique'));
		$cefVentilation = $Cventil / $shab;
		$cepVentilation = $Cventil * $facteurEfEp/$shab;
		$gesVentilation = $Cventil * $facteurGES/$shab;
		$coutVentilation = $Cventil * $facteurCoutEf/$shab;
		
		$this->trace ('cth_CepVentilation() : $cepVentilation = $Cventil * $facteurEfEp/$shab;');
		$this->trace ("cth_CepVentilation() : $cepVentilation = $Cventil * $facteurEfEp/$shab;");
		
		$this->trace ('cth_CepVentilation() : $gesVentilation = $Cventil * $facteurGES/$shab;');
		$this->trace ("cth_CepVentilation() : $gesVentilation = $Cventil * $facteurGES/$shab;");	
		
		$this->trace ('cth_CepVentilation() : $coutVentilation = $Cventil * $facteurCoutEf/$shab;');
		$this->trace ("cth_CepVentilation() : $coutVentilation = $Cventil * $facteurCoutEf/$shab;");
		
		return [
			'cef'=>$cefVentilation,
			'cep'=>$cepVentilation,
			'ges'=>$gesVentilation,
			'cout'=>$coutVentilation
			];
		
	}

	/******************************
	*   CALCUL DES DEPERDITIONS
	*******************************/
	protected $dpPTEtape0;//a supprimer. en attendant la nouvelle methode de julien
	public function cth_Gv($nEtap){
		//on ne calcule le GV qu'une seule fois pour chaque etapes.
		if (!isset($this->aGVEtape[$nEtap])){
			$dpMuExt = $this->cth_dpMurExt($nEtap) ;
			$dpMuInt = $this->cth_dpMurInt($nEtap) ;
			$dpPlancherHaut = $this->cth_dpPlancherHaut($nEtap) ;
			$dpPlancherBas = $this->cth_dpPlancherBas($nEtap) ;
			$dpMenuiseries = $this->cth_dpMenuiseriesExt($nEtap) ;
			$dpMenuiseriesInt = $this->cth_dpMenuiseriesInt($nEtap) ;
			
			
			$dpParois = $dpMuExt + $dpMuInt + $dpPlancherHaut + $dpPlancherBas + $dpMenuiseries + $dpMenuiseriesInt;
			
			$this->trace('cth_Gv($nEtap) : $dpParois = $dpMuExt + $dpMuInt + $dpPlancherHaut + $dpPlancherBas + $dpMenuiseries + $dpMenuiseriesInt','hl');
			$this->trace("cth_Gv($nEtap) : $dpParois = $dpMuExt + $dpMuInt + $dpPlancherHaut + $dpPlancherBas + $dpMenuiseries + $dpMenuiseriesInt",'hl');
			
			$dpPT = $this->cth_dpPontsThermiques($nEtap,$dpParois);
			//a supprimer. en attendant la nouvelle methode de julien
			if ($nEtap == 0){
				$this->dpPTEtape0 = $dpPT ;
			} else {
				$dpPT = $this->dpPTEtape0 ;
			}
			
			$dpAR = $this->cth_dpRenouvAir($nEtap,$dpParois);
			
			$Gv = $dpParois + $dpPT + $dpAR;
			
			$this->trace('cth_Gv($nEtap) : $Gv = $dpParois + $dpPT + $dpAR','hl');
			$this->trace("cth_Gv($nEtap) : $Gv = $dpParois + $dpPT + $dpAR",'hl');
		
			$this->trace("*******************************************");
			
			$this->aGVEtape[$nEtap] = $Gv;
		}
		return $this->aGVEtape[$nEtap];
	}
	
	/* MURS EXTERIEURS*/
	/* calcul des déperdition*/
	public function cth_dpMurExt($nEtap){
		$surfMurSansFenetre = $this->cth_surfMurExtSansFenetre();
		$dpMurTotal = 0;
		foreach($this->jData['projet']['lstLot']['mur'] as $ntype => $typeMur){
			$prct = $typeMur["existant"]["part"];
			$uMurExt = $this->cth_uMurExt($typeMur,$nEtap);
			$dpMur = $surfMurSansFenetre * $uMurExt * $prct/100 ;
			$this->trace ('cth_dpMur() : $dpMur = $surfMurSansFenetre * $uMurExt * $prct/100 ');
			$this->trace ("cth_dpMur() : $dpMur = $surfMurSansFenetre * $uMurExt * $prct/100 ");
			$dpMurTotal += $dpMur;
		}
		$this->trace ('cth_dpMur() : $dpMurTotal ');
		$this->trace ("cth_dpMur() : $dpMurTotal ");
		return $dpMurTotal;
	}	
	
	/* calcul des U */
	public function cth_uMurExt($typeMur,$nEtap){
		$idSol = $typeMur["existant"]["idSol"];
	
		//rMurSeul
		$strucMur = $typeMur["existant"]["strucMur"];	
		$uMurSeul = $this->floatvalFr($this->getColTableauValeur('strucMur','id',$strucMur,'uMur'));	
		$rEnduit = $this->floatvalFr($this->getColTableauValeur('strucMur','id',$strucMur,'rEnduit'));	
		$rMurSeul = (1/$uMurSeul) + $rEnduit;
		$this->trace ('cth_uMur() : $rMurSeul = (1/$uMurSeul) + $rEnduit');
		$this->trace ("cth_uMur() : $rMurSeul = (1/$uMurSeul) + $rEnduit");

		//rIsoAvant	
		$rIsoAvant = $this->floatvalFr($typeMur["existant"]["perfMurInit_cVal"]);			
		if ($rIsoAvant<1 && $typeMur["existant"]["typeIsoMur"] == "non"){
			$rIsoAvant = 0;
		}
		
		//rIsoApres
		$rIsoApres= 0;
		if ($this->travauxEffectues($typeMur,$nEtap)){
			$rIsoApres = $this->getPerfFinal('mur',$typeMur);// floatvalFr($typeMur["lstSol"][$idSol]["perfMin"][0]);
		}
		
		//si meme type d'isolation Avant et Apres, on ne compte pas l'isolant avant ca il a surement été enlevé.
		$typeIsoMur = $typeMur["existant"]["typeIsoMur"];
		$lstResTypeIsoMur = $typeMur["lstSol"][$idSol]["resTypeIsoMur"];
		if ($rIsoApres AND in_array($typeIsoMur,$lstResTypeIsoMur)){
			$rIsoAvant = 0;
		}	
		
		$rTotal = $rMurSeul + $rIsoAvant + $rIsoApres;
		$uMur = 1/$rTotal;
		//echo "$rTotal =  $rMurSeul + $rIsoAvant + $rIsoApres  ";
		
		$this->trace ('cth_uMur() : $uMur = 1/$rTotal =  $rMurSeul + $rIsoAvant + $rIsoApres');
		$this->trace ("cth_uMur() : $uMur = 1/$rTotal =  $rMurSeul + $rIsoAvant + $rIsoApres");
		
		return $uMur ;
	}
	
	
	/* surface */	
	public function cth_surfMurExt(){
		if ($res = $this->jData['projet']['batiment']['surfMur']) {
			return $res;
		}
		
		//calcul surface par défaut		
		$mitoyen = $this->jData['projet']['batiment']['mitoyen'];
		$coefMit = $this->floatvalFr($this->getColTableauValeur('mitoyen','id',$mitoyen,'coefSurfMur'));
		
		$coefFor =  $this->cth_coefFor();
		
		$sh = $this->cth_shab()*1.2; // tableau  BBD thermique: l 257
		$niv = $this->cth_nbNiveauCorrige();
		$coefComble = 0.8;
		if ($this->jData['projet']['batiment']['combleHabit']=='oui'){
			$coefComble = 1;
		}
		
		
		$hsp =  $this->cth_hsp();
		
		$res = $coefMit * $coefFor * sqrt($sh / $niv) * $niv * $coefComble * $hsp;
		
		$this->trace ('cth_surfMurExt() $res = $coefMit * $coefFor * sqrt ( $sh / $niv ) * $niv * $coefComble * $hsp');
		$this->trace ("cth_surfMurExt() $res = $coefMit * $coefFor * sqrt ( $sh / $niv ) * $niv * $coefComble * $hsp");
		
		return $res;
	}

	/* surface */	
	public function cth_surfMurExtSansFenetre(){
		return $this->cth_surfMurExt() - $this->cth_surfMenuiserieExtVerticale();
	}
	
	/* MURS INTERIEURS*/
	/* calcul des déperdition*/
	public function cth_dpMurInt($nEtap){
		$dpTotalMurInt = 0;
		foreach($this->jData['projet']['lstLot']['mur'] as $ntype => $typeMur){
			$prct = $typeMur["existant"]["part"];
			$dpMurIntTypeMur = $this->cth_dpMurIntTypeMur($typeMur,$nEtap);
			$dpMurInt = $dpMurIntTypeMur * $prct/100 ;
			$this->trace ('cth_dpMurInt() : $dpMurInt = $dpMurIntTypeMur * $prct/100 ');
			$this->trace ("cth_dpMurInt() : $dpMurInt = $dpMurIntTypeMur * $prct/100 ");
			$dpTotalMurInt += $dpMurInt;
		}
		$this->trace ('cth_dpMurInt() : $dpTotalMurInt ');
		$this->trace ("cth_dpMurInt() : $dpTotalMurInt ");
		return $dpTotalMurInt;
	}
	
	public function cth_dpMurIntTypeMur($typeMur,$nEtap){
		$etape = $typeMur["existant"]["etape"];
		
		$uMurInt = 3.33;
		$b = 0;
		$SurfMurInt = 0 ; 
		if ($this->isTypeBatLC()){
			//calcul de b
			$anneeConstr = $this->jData['projet']['batiment']['anneeConstr'];
			$b = $this->floatvalFr($this->getColTableauValeur('anneeConstr','id',$anneeConstr,'bMurIntColl'));						
			if ($this->travauxEffectues($typeMur,$nEtap)){				
				$rIsoApres = $this->getPerfFinal('mur',$typeMur);//$this->floatvalFr($typeMur["lstSol"][$idSol]["perfMin"][0]);
				//si le mur rénové on multiblie b par 0.5
				if ($rIsoApres){
					$b = $b * 0.5;
				}
			}
			
			//calcul de surface
			$surfLog = $this->cth_shabLogement();	
			$this->trace('cth_dpMurIntTypeMur() $surfLog');
			$this->trace("cth_dpMurIntTypeMur() $surfLog");
			$partSurf = $this->floatvalFr($this->chercheValeurTableauCol('partSurfMurInt',['surf'=>$surfLog],'partSurf'));
			$surfMurExtSansFenetre = $this->cth_surfMurExtSansFenetre();
			$SurfMurInt = $partSurf * $surfMurExtSansFenetre ;
			$this->trace('cth_dpMurIntTypeMur() $SurfMurInt = $partSurf * $surfMurExtSansFenetre ');
			$this->trace("cth_dpMurIntTypeMur() $SurfMurInt = $partSurf * $surfMurExtSansFenetre ");			
		}
		
		$dpMurInt = $b * $uMurInt * $SurfMurInt;
		$this->trace('cth_dpMurIntTypeMur() $dpMurInt = $b * $uMurInt * $SurfMurInt');
		$this->trace("cth_dpMurIntTypeMur() $dpMurInt = $b * $uMurInt * $SurfMurInt");
		return $dpMurInt;
	}	
	
	
	
	/* PLANCHERES HAUT */
	/* surface */
	public function cth_surfPlancherHaut(){
		if ($res = $this->jData['projet']['batiment']['surfPlancherHaut']) {
			return $res;
		}		
		
		$niv = $this->jData['projet']['batiment']['nbNiveau'];
		if ($this->jData['projet']['batiment']['combleHabit']=='oui'){
			$coef = sqrt(2)/$niv;	
		} else {
			$coef = 1/$niv;
		}
		$shab = $this->cth_shab();
		
		$cth_surfPlancherHaut = $shab * $coef ;
		
		$this->trace('cth_surfPlancherHaut() $cth_surfPlancherHaut = $shab * $coef ');
		$this->trace("cth_surfPlancherHaut() $cth_surfPlancherHaut = $shab * $coef ");
		
		return $cth_surfPlancherHaut; 
	}
	
	/* calcul des U */
	public function cth_uPlancherHaut($typeLot,$nEtap){		
		$idSol = $typeLot["existant"]["idSol"];
		
		//rPHSeul
		$structurePlancherHaut = $typeLot["existant"]["structurePlancherHaut"];	
		$uPHSeul = $this->floatvalFr($this->getColTableauValeur('structurePlancherHaut','id',$structurePlancherHaut,'u'));
		$rPHSeul = (1/$uPHSeul);
		$this->trace ('cth_uPlancherHaut() : $rPHSeul = (1/$uPHSeul)');
		$this->trace ("cth_uPlancherHaut() : $rPHSeul = (1/$uPHSeul)");

		//rIsoAvant
		$rIsoAvant = $this->floatvalFr($typeLot["existant"]["perfPlancherHautInit_cVal"]);			
	
			

		//rIsoApres
		$rIsoApres= 0;
		if ($this->travauxEffectues($typeLot,$nEtap)){
			$rIsoApres = $this->getPerfFinal('plancherHaut',$typeLot);//$this->floatvalFr($typeLot["lstSol"][$idSol]["perfMin"][0]);
		}
		
		if ($rIsoApres>0){
			$rIsoAvant = 0;
		}
		
		//coef b
		$contactPlancherHaut = $typeLot["existant"]["contactPlancherHaut"];	
		$b = $this->floatvalFr($this->getColTableauValeur('contactPlancherHaut','id',$contactPlancherHaut,'b'));
		
		$rTotal = $rPHSeul + $rIsoAvant + $rIsoApres;
		$uPH = $b * (1/$rTotal);
		
		$this->trace ('cth_uPlancherHaut() : $uPH = $b * (1/$rTotal) = $rPHSeul + $rIsoAvant + $rIsoApres');
		$this->trace ("cth_uPlancherHaut() : $uPH = $b * (1/$rTotal) = $rPHSeul + $rIsoAvant + $rIsoApres");
		
		return $uPH;
	}
	
	/* calcul des déperdition*/
	public function cth_dpPlancherHaut($nEtap){
		$surfPH = $this->cth_surfPlancherHaut();
		$dpPHTotal = 0;
		foreach($this->jData['projet']['lstLot']['plancherHaut'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$uPH = $this->cth_uPlancherHaut($typeLot,$nEtap);
			$dpPH = $surfPH * $uPH * $prct/100 ;
			$this->trace ('cth_dpPlancherHaut() : $dpPH = $surfPH * $uPH * $prct/100  ');
			$this->trace ("cth_dpPlancherHaut() : $dpPH = $surfPH * $uPH * $prct/100  ");
			$dpPHTotal += $dpPH;
		}
		$this->trace ('cth_dpPlancherHaut() : $dpPHTotal ');
		$this->trace ("cth_dpPlancherHaut() : $dpPHTotal ");
		return $dpPHTotal;
	}		
	
	/* PLANCHERES BAS */
	/* surface */
	public function cth_surfPlancherBas(){
		if ($res = $this->jData['projet']['batiment']['surfPlancherBas']) {
			return $res;
		}		
		$niv = $this->jData['projet']['batiment']['nbNiveau'];		
		$coef = 1/$niv;		
		return $this->cth_shab() * $coef;
	}

	/* calcul des U */
	public function cth_uPlancherBas($typeLot,$nEtap){		
		$idSol = $typeLot["existant"]["idSol"];
		
		//rPBSeul
		$structurePlancherBas  = $typeLot["existant"]["structurePlancherBas"];	
		$uPBSeul = $this->floatvalFr($this->getColTableauValeur('structurePlancherBas','id',$structurePlancherBas,'u'));
		$rPBSeul = (1/$uPBSeul);
		$this->trace ('cth_uPlancherBas() : $rPBSeul = (1/$uPBSeul)');
		$this->trace ("cth_uPlancherBas() : $rPBSeul = (1/$uPBSeul)");

		//rIsoAvant
		
		$rIsoAvant = $this->floatvalFr($typeLot["existant"]["perfPlancherBasInit_cVal"]);			
	
			

		//rIsoApres
		$rIsoApres= 0;
		if ($this->travauxEffectues($typeLot,$nEtap)){
			$rIsoApres =  $this->getPerfFinal('plancherBas',$typeLot);//$this->floatvalFr($typeLot["lstSol"][$idSol]["perfMin"][0]);
		}
		
		if ($rIsoApres>0){
			$rIsoAvant = 0;
		}
		
		//coef b
		$typePlancherBas = $typeLot["existant"]["typePlancherBas"];	
		$b = $this->floatvalFr($this->getColTableauValeur('typePlancherBas','id',$typePlancherBas,'b'));
		
		$rTotal = $rPBSeul + $rIsoAvant + $rIsoApres;
		$uPb = $b * (1/$rTotal);
		
		$this->trace ('cth_uPlancherBas() : $uPb = $b * (1/$rTotal) = $rPBSeul + $rIsoAvant + $rIsoApres');
		$this->trace ("cth_uPlancherBas() : $uPb = $b * (1/$rTotal) = $rPBSeul + $rIsoAvant + $rIsoApres");
		
		return $uPb;
	}

	/* calcul des déperdition*/
	public function cth_dpPlancherBas($nEtap){
		$surfPB = $this->cth_surfPlancherBas();
		$dpPBTotal = 0;
		foreach($this->jData['projet']['lstLot']['plancherBas'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$uPB = $this->cth_uPlancherBas($typeLot,$nEtap);
			$dpPB = $surfPB * $uPB * $prct/100 ;
			$this->trace ('cth_dpPlancherBas() : $dpPB = $surfPB * $uPB * $prct/100  ');
			$this->trace ("cth_dpPlancherBas() : $dpPB = $surfPB * $uPB * $prct/100  ");
			$dpPBTotal += $dpPB;
		}
		$this->trace ('cth_dpPlancherBas() : $dpPBTotal ');
		$this->trace ("cth_dpPlancherBas() : $dpPBTotal ");
		return $dpPBTotal;
	}

	/* MENUISERIES */

	public function cth_surfMenuiserieExt($bSeulementVerticale= false){
		$surfMenuiserie = 0;
		foreach($this->jData['projet']['lstLot']['menuiserie'] as $ntype => $typeLot){
			if (!($bSeulementVerticale && $typeLot["existant"]["fenetreDeToit"]=='oui')){
				$surfMenuiserie += $typeLot["existant"]["surfMenuiserie"];
			}
		}

		return $surfMenuiserie;
	}
	
	public function cth_surfMenuiserieExtVerticale(){
		return $this->cth_surfMenuiserieExt(true);
	}	

	public function cth_uMenuiseriesExt($typeLot,$nEtap){	
		$idSol = $typeLot["existant"]["idSol"];
		
		//uwAvant
		$uwAvant = $this->floatvalFr($typeLot["existant"]["perfMenuiserieInit_cVal"]);			

		//uwApres
		$uwApres= 0;
		if ($this->travauxEffectues($typeLot,$nEtap)){
			$uwApres = $this->getPerfFinal('menuiserie',$typeLot);
		}
		
		if ($uwApres){
			return $uwApres;
		} else {
			return $uwAvant;
		}	
	}
	
	/* calcul des déperdition*/
	public function cth_dpMenuiseriesExt($nEtap){		
		$dpMenTotal = 0;
		foreach($this->jData['projet']['lstLot']['menuiserie'] as $ntype => $typeLot){
			$surfMenuiserie = $typeLot["existant"]["surfMenuiserie"];
			$uw = $this->cth_uMenuiseriesExt($typeLot,$nEtap);
			$dpMen = $surfMenuiserie * $uw ;
			$this->trace ('cth_dpMenuiseriesExt() : $dpMen = $surfMenuiserie * $uw ;');
			$this->trace ("cth_dpMenuiseriesExt() : $dpMen = $surfMenuiserie * $uw ;");
			$dpMenTotal += $dpMen;
		}
		$this->trace ('cth_dpMenuiseriesExt() : $dpMenTotal ');
		$this->trace ("cth_dpMenuiseriesExt() : $dpMenTotal ");
		return $dpMenTotal;
	}	
	
	/* MENUISERIES INTERIEURES*/
	/* calcul des déperdition*/
	public function cth_dpMenuiseriesInt($nEtap){
		$surfMenInt = $this->cth_surfPorte();
		
		if (!$this->lotTraite('menuiserie',$nEtap)){
			$anneeConstr = $this->jData['projet']['batiment']['anneeConstr'];
			$uMenInt = $this->floatvalFr($this->getColTableauValeur('anneeConstr','id',$anneeConstr,'uPorteInt'));	
		} else {
			$uMenInt = 2;
		}
		
		$dpMenInt = $surfMenInt * $uMenInt  ;
		
		$this->trace ('cth_dpMenuiseriesInt() : $dpMenInt = $surfMenInt * $uMenInt ');
		$this->trace ("cth_dpMenuiseriesInt() : $dpMenInt = $surfMenInt * $uMenInt ");

		return $dpMenInt;
	}
	
	
	/* PONTS THERMIQUES*/
	public function cth_coefPontsThermiques_methodeSimpl($typeMur,$nEtap){		
				
		
		//uAvant
		$anneeConstr = $this->jData['projet']['batiment']['anneeConstr'];
		$coefPT = $this->floatvalFr($this->getColTableauValeur('anneeConstr','id',$anneeConstr,'coefPT'));	
		
		$coefPTiso = $this->floatvalFr($this->paramChampAvantApres($typeMur,$nEtap,'typeIsoMur','coefPT','resTypeIsoMur','coefPT'));
		
		return $coefPT * $coefPTiso;

	}
	
	public function cth_dpPontsThermiques_methodeSimpl($nEtap,$dpParois){		
		$dpPTTotal = 0;
		foreach($this->jData['projet']['lstLot']['mur'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$coefPT = $this->cth_coefPontsThermiques_methodeSimpl($typeLot,$nEtap);
			$dpPT = $dpParois * $coefPT * $prct/100 ;
			$this->trace ('cth_dpPontsThermiques_methodeSimpl() : $dpPT = $dpParois * $coefPT * $prct/100 ');
			$this->trace ("cth_dpPontsThermiques_methodeSimpl() : $dpPT = $dpParois * $coefPT * $prct/100 ");
			$dpPTTotal += $dpPT;
		}
		$this->trace ('cth_dpPontsThermiques_methodeSimpl() : $dpPTTotal ');
		$this->trace ("cth_dpPontsThermiques_methodeSimpl() : $dpPTTotal ");
		return $dpPTTotal;
	}
	
	
	public function cth_dpPontsThermiques_methodeDPE($nEtap,$dpParois){	
		$typeMur = $this->getTypeLotPrinc('mur');
		$solMur = $this->getTypeLotSolution($typeMur);
		$etapeMur = $typeMur['existant']['etape'];
		$typeIso = ($solMur['resTypeIsoMur'][0]=='ite') ? 'ITE' : 'ITI'; //ITI ou ITE
		$coefFor = $this->cth_coefFor();
		$surfPlancherBas=$this->cth_surfPlancherBas();
		
		// Liaison Planchers BAS		
		$perimetrePlancheBas = $coefFor * sqrt($surfPlancherBas);
		$this->trace ('cth_dpPontsThermiques_methodeDPE() : $perimetrePlancheBas = $coefFor * sqrt($surfPlancherBas); ');		
		$this->trace ("cth_dpPontsThermiques_methodeDPE() : $perimetrePlancheBas = $coefFor * sqrt($surfPlancherBas); ");
		$typePlancherBas = $this->getTypeLotPrinc('plancherBas')["existant"]["typePlancherBas"];
		$kPB_mur = $this->floatvalFr($this->getColTableauValeur('typePlancherBas','id',$typePlancherBas,'k_PT_'.$typeIso));
		$lPB_mur = $perimetrePlancheBas ;
		
		// Liaison Planchers Haut
		$typePlancherHaut = $this->getTypeLotPrinc('plancherHaut');
		$contactPlancherHaut = $typePlancherHaut['existant']['contactPlancherHaut'];
		$etapePH = $typePlancherHaut['existant']['etape'];
		$resTypeIsoPlancherHaut = $this->getTypeLotSolution($typePlancherHaut)['resTypeIsoPlancherHaut'][0];		
		$kPH_mur = $this->floatvalFr($this->getColTableauValeur('resTypeIsoPlancherHaut','id',$resTypeIsoPlancherHaut,'k_PT_'.$typeIso));
		$this->trace ('cth_dpPontsThermiques_methodeDPE() : $kPH_mur');
		$this->trace ("cth_dpPontsThermiques_methodeDPE() : $kPH_mur");
		if ($etapePH != $etapeMur){
			$kPH_mur = 1.3 * $kPH_mur;	
			$this->trace ('cth_dpPontsThermiques_methodeDPE() : etape mur diferent de l etape plancher haut => on multiplie par 1.3');
		}				
		$lPH_mur = $perimetrePlancheBas;
		$coefPerimetreToit_L1L2 = 1;
		if ($contactPlancherHaut == 'rampant'){ //isolantion en diagonale => le perimetre est différent
			$this->trace ('cth_dpPontsThermiques_methodeDPE() : isolant en diagonale (rampants)');
			$coefPerimetreToit_L1L2 = $this->coefPerimetreToit_L1L2();			
		}
		$lPH_mur = $perimetrePlancheBas * $coefPerimetreToit_L1L2;
		$this->trace ('cth_dpPontsThermiques_methodeDPE() : $lPH_mur = $perimetrePlancheBas * $coefPerimetreToit_L1L2; ');
		$this->trace ("cth_dpPontsThermiques_methodeDPE() : $lPH_mur = $perimetrePlancheBas * $coefPerimetreToit_L1L2; ");
	
		//Liaison Fenetres
		$typeMenuiserie = $this->getTypeLotPrinc('menuiserie');
		$resTypePoseMenuiserie = $this->getTypeLotSolution($typeMenuiserie)['resTypePoseMenuiserie'][0];	
		$etapeMen = $typePlancherHaut['existant']['etape'];		
		$kMen_mur = $this->floatvalFr($this->getColTableauValeur('resTypePoseMenuiserie','id',$resTypePoseMenuiserie,'k_PT_'.$typeIso));;		
		$this->trace("cth_dpPontsThermiques_methodeDPE() : $kMen_mur =  $resTypePoseMenuiserie $typeIso");
		if ($etapeMen != $etapeMur){
			$kMen_mur = 1.3 * $kMen_mur;	
			$this->trace('cth_dpPontsThermiques_methodeDPE() : etape mur diferent de l etape menuiserie => on multiplie par 1.3');
		}			
		$lMen_mur = $this->cth_surfMenuiserieExt()*(1.35*2+2)/(1.35);

		$kPI_mur = $typeIso == 'ITI' ? 0.5 : 0.1;
		$lPI_mur = $perimetrePlancheBas * ($this->cth_nbNiveauCorrige()-1);
		
		$kRefend_mur = $typeIso == 'ITI' ? 0.6 : 0.08;		
		$lRefend_mur = 2 * $this->cth_hsp();;							
		if ($this->isTypeBatLC()){
			$lRefend_mur = $lRefend_mur * $this->cth_nbLogement()/$this->cth_nbNiveauCorrige();
		}
		
		$dpPontTh = $kPB_mur * $lPB_mur + $kPH_mur * $lPH_mur + $kMen_mur * $lMen_mur + $kPI_mur * $lPI_mur + $kRefend_mur * $lRefend_mur	;
		$this->trace ('cth_dpPontsThermiques_methodeDPE() : $dpPontTh = $kPB_mur * $lPB_mur + $kPH_mur * $lPH_mur + $kMen_mur * $lMen_mur + $kPI_mur * $lPI_mur + $kRefend_mur * $lRefend_mur	 ');
		$this->trace ("cth_dpPontsThermiques_methodeDPE() : $dpPontTh = $kPB_mur * $lPB_mur + $kPH_mur * $lPH_mur + $kMen_mur * $lMen_mur + $kPI_mur * $lPI_mur + $kRefend_mur * $lRefend_mur	 ");
		
		
		return $dpPontTh;
	}
	
	public function cth_dpPontsThermiques($nEtap,$dpParois){		
		//to do : julien à dit que la methode était à revoir xlsV24
		
		if ($nEtap ==0){
			return $this->cth_dpPontsThermiques_methodeSimpl($nEtap,$dpParois);
		} else {
			return $this->cth_dpPontsThermiques_methodeDPE($nEtap,$dpParois);
		}
	}
	
	
	/* RENOUVELLEMENT D'AIR*/
	public function cth_dpRenouvAir($nEtap){
		$dpVentilTotal = 0;
		
		foreach($this->jData['projet']['lstLot']['ventilation'] as $ntype => $typeLot){
			$prct = $typeLot["existant"]["part"];
			$hVent = $this->cth_hVent($typeLot,$nEtap);
			$hInf = $this->cth_hInfilt($typeLot,$nEtap);
			$dpVentil = ($hVent + $hInf) * $prct/100 ;
			$this->trace ('cth_dpVentil() : $dpVentil = ($hVent + $hInf) * $prct/100 ;');
			$this->trace ("cth_dpVentil() : $dpVentil = ($hVent + $hInf) * $prct/100 ;");
			$dpVentilTotal += $dpVentil;
		}
		$this->trace ('cth_dpVentil() : $dpVentilTotal ');
		$this->trace ("cth_dpVentil() : $dpVentilTotal ");
		return $dpVentilTotal;
	}
	
	public function cth_hInfilt($typeLot,$nEtap){
		$hsp =  $this->cth_hsp();
		$shab = $this->cth_shab();		
		$permea = $this->cth_permea($nEtap);
		$surfMurExt = $this->cth_surfMurExt();
		$surfPH = $this->cth_surfPlancherHaut();		
		$surfPorte = $this->cth_surfPorte();		
		$Q4Paenv = $permea * ( $surfMurExt + $surfPH + $surfPorte); /*+ les fenetres sont inclus dans les murs D97*/ 
		
		$this->trace ('cth_hInfilt() : $Q4Paenv = $permea * ( $surfMurExt + $surfPH + $surfPorte);  ');
		$this->trace ("cth_hInfilt() : $Q4Paenv = $permea * ( $surfMurExt + $surfPH + $surfPorte);  ");
		
		$Smeaconv = $this->cth_Smeaconv($typeLot,$nEtap);
		$Q4Pa = $Q4Paenv + (0.45 * $Smeaconv * $shab);
		$this->trace ('cth_hInfilt() : $Q4Pa = $Q4Paenv + (0.45 * $Smeaconv * $shab);  ');
		$this->trace ("cth_hInfilt() : $Q4Pa = $Q4Paenv + (0.45 * $Smeaconv * $shab);  ");
		
		$n50 = $Q4Pa / ( ( (4/50)**(2/3) ) * $hsp * $shab);
		$this->trace ('cth_hInfilt() : $n50 = $Q4Pa / ( ( (4/50)**(2/3) ) * $hsp * $shab); ');
		$this->trace ("cth_hInfilt() : $n50 = $Q4Pa / ( ( (4/50)**(2/3) ) * $hsp * $shab); ");
		
		$coefProtecE = $this->floatvalFr($this->cth_paramBat('mitoyen','coefProtecE'));
		$coefProtecF = $this->floatvalFr($this->cth_paramBat('mitoyen','coefProtecF'));
		$Qvasoufconv = $this->cth_Qvasoufconv($typeLot,$nEtap);;
		$Qvarepconv = $this->cth_Qvarepconv($typeLot,$nEtap);
		
		$Qinf = ($hsp * $shab * $n50 * $coefProtecE)/(1+($coefProtecF/$coefProtecE) * (($Qvasoufconv - $Qvarepconv) / ($hsp * $n50))**2);
		$this->trace ('cth_hInfilt() : $Qinf = ($hsp * $shab * $n50 * $coefProtecE)/(1+($coefProtecF/$coefProtecE) * (($Qvasoufconv - $Qvarepconv) / ($hsp * $n50))**2); ');
		$this->trace ("cth_hInfilt() : $Qinf = ($hsp * $shab * $n50 * $coefProtecE)/(1+($coefProtecF/$coefProtecE) * (($Qvasoufconv - $Qvarepconv) / ($hsp * $n50))**2); ");
		
		
		$chaleurVolumiqueAir = $this->cth_chaleurVolumiqueAir();
		$hInf =  $chaleurVolumiqueAir * $Qinf ;
		$this->trace ('cth_hInfilt() : $hInf =  $chaleurVolumiqueAir * $Qinf ; ');
		$this->trace ("cth_hInfilt() : $hInf =  $chaleurVolumiqueAir * $Qinf ; ");		
		return $hInf;
	}
	
	public function cth_hVent($typeLot,$nEtap){	
		$chaleurVolumiqueAir = $this->cth_chaleurVolumiqueAir();
		$shab = $this->cth_shab();		

		$Qvarepconv = $this->cth_Qvarepconv($typeLot,$nEtap);

		$hVent = $chaleurVolumiqueAir * $Qvarepconv * $shab;

		$this->trace ('cth_hVent() : $hVent = $chaleurVolumiqueAir * $Qvarepconv * $shab');
		$this->trace ("cth_hVent() : $hVent = $chaleurVolumiqueAir * $Qvarepconv * $shab");

		return $hVent ;
	}
		
	public function cth_Qvarepconv($typeLot,$nEtap){
		$nomChampParam = ($this->isTypeBatLC()? 'QvarepconvCol' : 'QvarepconvInd');
		return $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,'typeVentilation',$nomChampParam,'resTypeVentilation',$nomChampParam));
		//todo : la valeur pou rla VMR dans le tableau est à revoir
	}
	public function cth_Qvasoufconv($typeLot,$nEtap){
		$nomChampParam = ($this->isTypeBatLC()? 'QvasoufconvCol' : 'QvasoufconvInd');
		return $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,'typeVentilation',$nomChampParam,'resTypeVentilation',$nomChampParam));
		//todo : la valeur pou rla VMR dans le tableau est à revoir
	}
	public function cth_Smeaconv($typeLot,$nEtap){
		$nomChampParam = ($this->isTypeBatLC()? 'SmeaconvCol' : 'SmeaconvInd');
		return $this->floatvalFr($this->paramChampAvantApres($typeLot,$nEtap,'typeVentilation',$nomChampParam,'resTypeVentilation',$nomChampParam));
		//todo : la valeur pou rla VMR dans le tableau est à revoir
	}
	
	/**********************/
	/* Valeurs d'entrées  */
	/**********************/

	public function cth_chaleurVolumiqueAir(){
		return 0.34;
	}

	public function cth_surfPorte(){		
		return $this->floatvalFr($this->cth_paramBat('typeBat','surfPorteInt'));
	}

	protected function cth_solicitationsExt($nEtap,$iMoi){
		$inertieLourde = ($this->cth_inertieBat($nEtap) ==  'lourde')? 'oui':'non';
		$altitude = $this->getAltitude();
		$zoneClim = $this->getZoneClim();
		$solicitationExt = $this->chercheValeurTableau('solicitationExt',['moi'=>$iMoi,'zoneClim'=>$zoneClim,'alt'=>$altitude,'inertieLourde'=>$inertieLourde]);
		return $solicitationExt;
	}

	protected function cth_inertieBat($nEtap){	
		if ($this->partMursITI($nEtap)>=50){
			return $this->cth_paramBat('anneeConstr','inertieAvecIti');
		} else {
			return $this->cth_paramBat('anneeConstr','InetrieSansIti');
		}		
	}

	protected function cth_permea_init(){	
		$Q4paconv = $this->jData['projet']['batiment']['permeaInit'];
		if (!$Q4paconv){
			$Q4paconv = $this->cth_permeaDefaut();
		}
		return $Q4paconv ;
	}
	
	protected function cth_permea_final(){	
		$Q4paconv = $this->jData['projet']['batiment']['permeaFin'];
		if (!$Q4paconv){				
			$Q4paconvMin = 1.7;
	
			$Q4paconv = min($this->cth_permeaDefaut(),$Q4paconvMin);
		}
		return $Q4paconv;
	}
	
	protected function cth_permeaDefaut(){	
		if ($this->isTypeBatLC()){
			return $this->floatvalFr($this->cth_paramBat('anneeConstr','Q4paconv_m2_col'));
		} else {
			return $this->floatvalFr($this->cth_paramBat('anneeConstr','Q4paconv_m2_ind'));
		}
	}	
	
	protected function cth_permea($nEtap){	
		$bPlancherHautTraite = $this->lotTraite('plancherHaut',$nEtap);
		$bMursTraite = $this->lotTraite('mur',$nEtap);
		$bMenuiserieTraite = $this->lotTraite('menuiserie',$nEtap);	

		$this->trace ('cth_permea() : $bPlancherHautTraite , $bMursTraite, $bMenuiserieTraite');		
		$this->trace ("cth_permea() : $bPlancherHautTraite , $bMursTraite, $bMenuiserieTraite");		
		
		if ($bPlancherHautTraite AND $bMursTraite AND $bMenuiserieTraite){			
			$this->trace ('cth_permea() : valeur finale');
			//final
			$valPermea = $this->cth_permea_final();
		} else if ($bPlancherHautTraite OR $bMursTraite OR $bMenuiserieTraite){
			$this->trace ('cth_permea() : valeur intermediaire');
			//intermediaire (moyenne de init + final)
			$valPermea = ($this->cth_permea_final() + $this->cth_permea_init())/2;
		} else {
			$this->trace ('cth_permea() : valeur initiale');
			//initial
			$valPermea = $this->cth_permea_init();
		}	
		$this->trace ('cth_permea() : $valPermea');
		$this->trace ("cth_permea() : $valPermea");
		return $valPermea ;
	}
	
	protected function cth_paramZoneClim($nomChampVal){
		$zoneClim = $this->getZoneClim();
		return $this->getColTableauValeur('zoneClim','id',$zoneClim,$nomChampVal);
	}
	
	protected function cth_paramBat($nomChampBat,$nomChampVal){
		$valChampBat = $this->jData['projet']['batiment'][$nomChampBat];
		return $this->getColTableauValeur($nomChampBat,'id',$valChampBat,$nomChampVal); 
	}	
	

		
	
	protected function cth_coefEfEpTypeEnergie($typeEnergie){
		return $this->floatvalFr($this->getColTableauValeur('typeEnergie','id',$typeEnergie,'coefEfEp'));
	}
	
	protected function cth_coefGESTypeEnergie($typeEnergie){
		return $this->floatvalFr($this->getColTableauValeur('typeEnergie','id',$typeEnergie,'coefGES'));
	}	
	
	protected function cth_coefCoutEfTypeEnergie($typeEnergie){
		return $this->floatvalFr($this->getColTableauValeur('typeEnergie','id',$typeEnergie,'coutEf'));
	}	
	
	protected function cth_hsp(){
		//hauteur sous plafond
		if ($res = $this->jData['projet']['batiment']['hautSousPlaf']) {
			return $res;
		}	
		return $this->floatvalFr($this->cth_paramBat('anneeConstr','hsp'));
	}
	
	protected function cth_shab(){
		return $this->jData['projet']['batiment']['surface'];
	}
	
	protected function cth_shon(){
		$anneeConstr = $this->jData['projet']['batiment']['anneeConstr'];
		$coef = 1.2;
		if ($anneeConstr == 'av1948'){
			$coef = 1.35;
		}
		return $this->cth_shab()*$coef;
	}
	
	
	protected function cth_nbNiveauCorrige(){
		$niv = $this->jData['projet']['batiment']['nbNiveau'];
		if ($this->jData['projet']['batiment']['combleHabit']=='oui'){
			$niv-=0.5;
		}
		return $niv;
	}
	protected function cth_shabLogement(){
		return $this->jData['projet']['batiment']['surface'] / $this->cth_nbLogement();
	}
	
	protected function cth_nbLogement(){
		return $this->jData['projet']['batiment']['nbLogement'];
	}
	
	protected function cth_coefFor(){
		$formeBat = $this->jData['projet']['batiment']['formeBat'];		
		return  $this->floatvalFr($this->getColTableauValeur('formeBat','id',$formeBat,'coefFor'));
	}
	
	protected function coefPerimetreToit_L1L2(){
		$formeBat = $this->jData['projet']['batiment']['formeBat'];		
		return  $this->floatvalFr($this->getColTableauValeur('formeBat','id',$formeBat,'coefPerimetreToit_L1+L2'));
	}
	

	protected function partMursITI($nEtap){
		$partTotal = 0;
		$aTypelot = $this->jData['projet']['lstLot']['mur'];
		foreach($aTypelot as $typeLot){
			if ($this->travauxEffectues($typeLot,$nEtap)){
				$sol = $this->getTypeLotSolution($typeLot);				
				if (in_array('iti',$sol['resTypeIsoMur'])){
					$partTotal += $typeLot['existant']['part'];
				}
			} else {
				if ($typeLot['existant']['typeIsoMur'] == 'iti'){
					$partTotal += $typeLot['existant']['part'];
				}
			}
		}
		return $partTotal;
	}
	

		
	protected function lotTraite($nomLot,$nEtap){
		//renvoie vrai si 50% des lots ont étés traités à cette etape.
		return $this->partLotTraite($nomLot,$nEtap)>=50;
	}
	
	protected function partLotTraite($nomLot,$nEtap){
		if ($nomLot =='menuiserie'){
			return $this->partMenuiserieTraite($nEtap);
		}		
		
		$partTotal = 0;
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){
			if ($this->travauxEffectues($typeLot,$nEtap)){
				$partTotal += $typeLot['existant']['part'];
			}
		}
		return $partTotal ;
	}
	
	protected function partMenuiserieTraite($nEtap){
		$surfTotal = 0;
		$surfMenTraite = 0;
		$aTypelot = $this->jData['projet']['lstLot']['menuiserie'];
		foreach($aTypelot as $typeLot){
			$surfTotal += $typeLot['existant']['surfMenuiserie'];
			if ($this->travauxEffectues($typeLot,$nEtap)){
				$surfMenTraite += $typeLot['existant']['surfMenuiserie'];
			}
		}
		return round(100*$surfMenTraite/$surfTotal) ;
	}
	
	protected function partEcsLieChauffage($nEtap){
		$partTotal = 0;
		$aTypelot = $this->jData['projet']['lstLot']['ecs'];
		foreach($aTypelot as $typeLot){
			if ($this->ecsConnectChauff($typeLot,$nEtap)){
				$partTotal += $typeLot['existant']['part'];
			}
		}
		return $partTotal ;
	}	

	protected function partEcsCollective(){
		$partTotal = 0;
		$aTypelot = $this->jData['projet']['lstLot']['ecs'];
		foreach($aTypelot as $typeLot){
			if ($typeLot['existant']['typeReseauEcs']!='individuel'){
				$partTotal += $typeLot['existant']['part'];
			}
		}
		return $partTotal ;
	}	
	
	protected function getTypeLotPrincipal($nomLot){
		$partMax = 0;
		$typeLotPrinc = false; 
		$aTypelot = $this->jData['projet']['lstLot'][$nomLot];
		foreach($aTypelot as $typeLot){			
			if ($typeLot['existant']['part']>$partMax){
				$partMax = $typeLot['existant']['part'];
				$typeLotPrinc = $typeLot;
			}			
		}
		return $typeLotPrinc ;		
	}
	
	
	/********************************************
	/*Recapiltulatifs des respects des critères*/
	/*******************************************/
	
	protected function cth_recapCriteres(){
		return [
			'maPrimeRenov'=>$this->cth_criteresMaPrimeRenov(),
			'ceeBar164'=>$this->cth_criteresCeeBar164(),
		];
	}
	
	protected function _a_mettre_ajour_cth_criteresMaPrimeRenov(){
		$res = [];
		$nbEtape = count($this->calcResult['etape']);
		foreach ($this->calcResult['etape'] as $nEtap=>$aCalculs){
			$rapShonShab = $this->cth_shab() /  $this->cth_shon();
						
			$cep = round($rapShonShab*$aCalculs['consoTotal']['cep']);
			$cef = round($rapShonShab*$aCalculs['consoTotal']['cef']);
			$ges = round($rapShonShab*$aCalculs['consoTotal']['ges']);
			$resEtape = [
				'cep'=>$cep,
				'etiqCep'=> $this->cth_niveauDpeCep($cep),
				'cef'=>$cef,
				'ges'=>$ges,
				'etiqGes'=>$aCalculs['consoTotal']['classeGes']//$this->cth_niveauDpeGes($ges),	
				];			
				
			switch ($nEtap){
				case 0:
					$resEtape['shon'] =  $this->cth_shon();
					break;
				case 1:
					$resEtape['crit1cepInf330'] = ($resEtape['cep']<330)? 'oui' : 'non';
					$gain = ($res['etape'][0]['cep']-$resEtape['cep']) / $res['etape'][0]['cep'];
					$resEtape['crit2gain30'] = ($gain>0.3)? 'oui' : 'non';
					break;
				case ($nbEtape-1):
					$resEtape['bbcAtteint'] = $this->cth_niveauBBC()>=$resEtape['cep'] ? 'oui' : 'non' ;
					break;
			}
			$res['etape'][$nEtap]=$resEtape;
		}
		return $res;
	}
	
	protected function cth_niveauDpeCepGes($cep,$ges){	
		$etiqCep = $this->cth_niveauDpeCep($cep);
		$etiqGes = $this->cth_niveauDpeGes($ges);
		return max($etiqCep , $etiqGes);
	}
	
	protected function cth_niveauDpeCep($cep){	
		$altitude = $this->getAltitude();
		$zoneClim = $this->getZoneClim();
		$etiq = $this->chercheValeurTableauCol('dpeCep',['zoneClim'=>$zoneClim,'alt'=>$altitude,'cep'=>$cep],'classeCEP');
		return $etiq;
	}
	
	protected function cth_niveauDpeGes($ges){
		$altitude = $this->getAltitude();
		$zoneClim = $this->getZoneClim();
		$etiq = $this->chercheValeurTableauCol('dpeGes',['zoneClim'=>$zoneClim,'alt'=>$altitude,'ges'=>$ges],'classeGES');
		return $etiq;
	}	
	
	protected function cth_niveauBBC(){		
		$a = $this->floatvalFr($this->cth_paramZoneClim('coefBBC'));
		$altitude = $this->getAltitude();
		$b = 0;
		if ($altitude>400 && $altitude<=800){
			$b = 0.1;
		} else {
			$b = 0.2;
		}
		$niveauBBC = 80 * ($a+$b);
		$this->trace ('cth_niveauBBC() : $niveauBBC = 80 * ($a+$b)');		
		$this->trace ("cth_niveauBBC() : $niveauBBC = 80 * ($a+$b)");		
		return $niveauBBC;
	}
	
	protected function _a_mettre_ajour_cth_criteresCeeBar164(){
		$res = [];
		$nbEtape = count($this->calcResult['etape']);
		foreach ($this->calcResult['etape'] as $nEtap=>$aCalculs){		
			$cep = round($aCalculs['consoLot']['chauffage']['cep'] + $aCalculs['consoLot']['ecs']['cep']);
			$cef = round($aCalculs['consoLot']['chauffage']['cef'] + $aCalculs['consoLot']['ecs']['cef']);
			$ges = round($aCalculs['consoLot']['chauffage']['ges'] + $aCalculs['consoLot']['ecs']['ges']);
			$resEtape = [
				'cep164'=>$cep,				
				'cef164'=>$cef,								
				'ges164'=>$ges	
				];
				
			switch ($nEtap){
				case 0:
					$resEtape['shab'] =  $this->cth_shab();
					$gesInit = $resEtape['ges164'];
					break;
				default :
					$resEtape['crit1cepInf331'] = ($resEtape['cep164']<331)? 'oui' : 'non';
					$gain = ($res['etape'][0]['cep164']-$resEtape['cep164']) / $res['etape'][0]['cep164'];
					
					if ($this->isTypeBatLC()){
						$resEtape['crit2gain35'] = ($gain>0.35)? 'oui' : 'non';
					} else {
						$resEtape['crit2gain55'] = ($gain>0.55)? 'oui' : 'non';
					}
					$resEtape['crit3GesSup'] = ($resEtape['ges164']<$gesInit)? 'oui' : 'non';
					break;
			}
			$res['etape'][$nEtap]=$resEtape;
		}
		return $res;
	}	
	
	
	protected function cth_calculEnergetiqueDetail(){
		$res = [];
		$nbEtape = count($this->calcResult['etape']);
		$shab = $this->cth_shab();
		$shon = $this->cth_shon();
		//energieParUsage
		foreach ($this->calcResult['etape'] as $nEtap=>$aCalculs){
			$resEtape = [];
			foreach ($aCalculs['consoLot'] as $nomLot=>$consoLot){
				$resEtape[$nomLot] = round($consoLot['cep'] /** $shab/$shon*/) ;
			}
			$resEtape['total'] = round($aCalculs['consoTotal']['cep'] /** $shab/$shon*/) ;
			$resEtape['totalSurf'] = round($aCalculs['consoTotal']['cep'] * $shab );

			$res['energieParUsage']['etape'][$nEtap]=$resEtape;
		}
		
		
		//coutConso
		$reapEconomie = [];
		foreach ($this->calcResult['etape'] as $nEtap=>$aCalculs){
			$sommeEnergie = 0;
			$sommeCout = 0;			
					
			foreach ($aCalculs['consoLot'] as $nomLot=>$consoLot){
				$sommeEnergie += $consoLot['cep'] /** $shab/$shon*/;
				$coutLot = $consoLot['cout'] * $shab;
				$sommeCout += $coutLot ;
				$this->trace ('cth_calculEnergetiqueDetail($nEtap) $nomLot : $coutLot = $consoLot[cout] * $shab');
				$this->trace ("cth_calculEnergetiqueDetail($nEtap) $nomLot : $coutLot = $consoLot[cout] * $shab");
			}
			
			$sommeCoutAbonnement = 0;//$this->cth_coutAbonnementChauffage($nEtap);				methode à revoir selon julien
			$sommeCout = $sommeCout + $sommeCoutAbonnement;
			
			$this->trace ('cth_calculEnergetiqueDetail($nEtap) : $sommeCout');
			$this->trace ("cth_calculEnergetiqueDetail($nEtap) : $sommeCout");	
			
			
			if ($nEtap==0){
				$energieInit = $sommeEnergie;
				$coutInit = $sommeCout;
			} else {
				$res['recapEconomie']['etape'][$nEtap] = [
					'economieEnergie' => round($energieInit - $sommeEnergie),
					'economieCout' => round($coutInit - $sommeCout )
				];
			}
		}		
		
		return $res;
	}
	
	protected function cth_coutAbonnementChauffage($nEtap){
		$coutAboElec = 0;
		$coutAboChSomme = 0;
		$coutAboTotal = 0;
		foreach($this->jData['projet']['lstLot']['chauffage'] as $ntype => $typeLot){
			
			if (!$this->isTypeBatLC()){
				//MI
				$nomChampAvant = 'coutAboMI';
				$nomChampApres = 'coutAboMI';
			} else {
				//LC
				$nomChampAvant = $this->cth_chauffageCollectif($typeLot,$nEtap) ?  'coutAboLC_chCol' : 'coutAboLC_chInd';
				$nomChampApres = 'coutAboLC';
			}
			
			$coutAboCh = $this->paramChampAvantApres($typeLot,$nEtap,'typeProdChauffage',$nomChampAvant,'resTypeProd',$nomChampApres);				
			$coutAboCh = explode('+',$coutAboCh);
			$coutAboElec = max($coutAboCh[1],$coutAboElec);
			
			$this->trace ('************************************************');
			$this->trace ('cth_coutAbonnementChauffage($nEtap) : $coutAboCh[0]');
			$this->trace ("cth_coutAbonnementChauffage($nEtap) : $coutAboCh[0]");		
			
			$coutAboChSomme += $coutAboCh[0];
	
			
		}
		
		$coutAboTotal = $coutAboChSomme + $coutAboElec;
		
		$this->trace ('cth_coutAbonnementChauffage($nEtap) : $coutAboTotal = $coutAboChSomme + $coutAboElec;');
		$this->trace ("cth_coutAbonnementChauffage($nEtap) : $coutAboTotal = $coutAboChSomme + $coutAboElec;");
		
		return $coutAboTotal;
	}
	
	
}

?>