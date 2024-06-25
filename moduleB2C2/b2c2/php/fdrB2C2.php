<?php
require_once 'apiSolB2C2.php';

class FdrB2C2 extends ApiSolB2C2{

	protected $bGeneratePDF = false;
	protected $bFdrEditeur = false;
	protected $bFdrProprietaire = false;
	protected $bPDFtoString = false;
	
	/*************************************
	* PDF
	**************************************/		
	public function createPDFMenages($projId){	
		$this->bFdrEditeur = true;
		$this->bFdrProprietaire = true;	
		return $this->createPDF($projId);
	}
	
	public function createPDF($projId){		
		$data = $this->chargeDonneeProjet($projId);
		if (!$data || !$data['dataFdr']){
			echo "Erreur de chargement du projet";
			die;
		}

		return $this->createPDFProj($data);
	}
	
	public function createPDFProj($data){		
		$dataFdr = $data['dataFdr'];

		$this->bGeneratePDF = true;
			
		$htmlFdr = $this->cssPDF();
		
		$htmlFdr .= $this->htmlEntetePiedPagePDF($dataFdr);
		$htmlFdr .= $this->htmlFdr($dataFdr);	
		require_once __DIR__.'/../../../../lib/dompdf/autoload.inc.php'; 
		// reference the Dompdf namespace
		//use Dompdf\Dompdf;
		// instantiate and use the dompdf class
		$dompdf = new Dompdf\Dompdf();				
		$dompdf->set_option( 'chroot', realpath(__DIR__ .'/../../../..'));//authorise à aller charcher les immages dans tous les sous répertoires
		//$dompdf->set_option('isRemoteEnabled', true);	
		//$dompdf->set_option("isPhpEnabled", true); //Danger faille de sécurité
		
		$dompdf->loadHtml($htmlFdr);
		// (Optional) Setup the paper size and orientation
		$dompdf->setPaper('A4', 'portrait');
		// Render the HTML as PDF
		$dompdf->render();
		
		
		$this->PDFRemplaceNumPages($dompdf);
		$this->PDFAffichePagination($dompdf);
		
		
		// Output the generated PDF to Browser
		//$dompdf->stream();		
		if ($this->bPDFtoString){
			return $dompdf->output();
		} else {
			return $dompdf->stream("feuille_de_route.pdf", array("Attachment" => false));
		}
		//die();		
	}
	

	public function htmlFdr($data){					
		if ($this->bFdrProprietaire){
			return $this->htmlFdrProprietaire($data);
		}
		
		$html = '';
		
		if ($this->bGeneratePDF){			
			$html .= '<img style="margin-top:-30px;" src="'.$this->htmlImg('img/bandeauHeaderB2C2_2.jpg').'"><br>';
		}
		
		if (!$this->bFdrEditeur){
			$html .= $this->htmlFdrDescription($data);
		}
				
		if ($this->bFdrEditeur){
			$html .= $this->htmlWarning();
		}
		
		$html .= '<div class="titreFdr">Respect des critères BBC par étapes</div>'.$this->htmlFdrRespectCriteresB2C2($data);
		
		$html .= $this->htmlFdrListeEtape($data);
		
		if (!$this->bFdrEditeur){
			$html .= $this->htmlFdrCalculEnergieDetail($data);
		}	
		return $html;
	}
	
	
	
	protected function htmlWarning(){
		$res = '';
		foreach ($this->aWarning as $msg){
			$res .= '<li>'.$msg.'</li>';
		}

		if ($res){
			$res =  '<ul>'.$res.'</ul>';
			
			$res = 	'<table class="tabFdr">'.
					'<tr class="entete"><td class="col warningApi">WARNING</td></tr>'.
					'<tr><td class="col">'.$res.'</td></tr>'.
					'</table><br>';
		}
		
		return $res;
	}
	
	
	
	private function htmlEntetePiedPagePDF($dataFdr){

		
		if ($this->bFdrProprietaire){
			return $this->htmlPropEntetePiedPagePDF();
		}
		
		
		$html = '';

		$html.= '<div id="header">
					<div style="float:right;">'.@$this->jData['projet']['general']['nomProjet'].'</div>
					<div>Projet BBC par étapes</div>					
				</div>

				<div id="footer">
					<div>V'.@$this::VERSION.'</div>
				</div>';
		
		
		return $html;
	}
	
	private function PDFencodeString($str){
		//car dans les PDF les chaines sont encodées en utf-16
		$res = '';
		for ($i = 0; $i < strlen($str); $i++) {
			$res .= chr(0).$str[$i];
		}
		return $res;
		

	}
				
	private function PDFstringRegEx($str){
		//remplace le charactères par leur code
		$res = '';
		for ($i = 0; $i < strlen($str); $i++) {
			$res .= '\\x00'.$str[$i];
		}
		return $res;
	}
	
	private function PDFRemplaceNumPages(&$dompdf){
		//hack pour remplacer les chaines '%%pagewriteXXXX%' par le numéros de pages où se trouve la chaine '%%pagereadXXXX%' 
				
		$aObj = &$dompdf->get_canvas()->get_cpdf()->objects;

		$aPagesRef=[];
		$aPagesIndex=[];
		$aObjToWrite=[];


		$regExp = '/'.$this->PDFstringRegEx('%%page').'.+'.$this->PDFstringRegEx('%%').'/';

		foreach ($aObj as &$obj){
			if ($obj['t']=='pages'){
				//store pageNum Corresponding to pagesIndex
				$aPagesIndex = array_flip( $obj['info']['pages']);
			}
			
			if ($obj['t']=='contents'){	
				
				if (preg_match_all($regExp, $obj['c'], $matches)) {				
					foreach ($matches[0] as $match) {
						
						if (strpos($match,$this->PDFencodeString('%%pageread'))!==false){
							$aPagesRef[$match] = $obj['onPage'];
							
							$obj['c'] = str_replace($match,'',$obj['c']);
							
						} else if (strpos($match,$this->PDFencodeString('%%pagewrite'))!==false){
							//store objects where we have to replace the text
							$aObjToWrite[] = &$obj; 
							
						}				
					}
				}		
			}
		}
		
	
		foreach ($aObjToWrite as &$obj){
			foreach($aPagesRef as $ref=>$indexPage){
				$numPage = $aPagesIndex[$indexPage]+1;
				$refWrite = str_replace($this->PDFencodeString('%%pageread'),$this->PDFencodeString('%%pagewrite'),$ref);
			
				$obj['c'] = str_replace($refWrite,$this->PDFencodeString(''.$numPage),$obj['c']);
				
			}
		}
		
	}
	
	private function PDFAffichePagination(&$dompdf){
		$text = "{PAGE_NUM}/{PAGE_COUNT}";
		$size = 10;
		$fontMetrics = $dompdf->getFontMetrics();
		$font = $fontMetrics->getFont("DejaVu Sans");
		$width = $fontMetrics->get_text_width($text, $font, $size) / 2;
		$x = $dompdf->getCanvas()->get_width() - $width - 45;
		$y = $dompdf->getCanvas()->get_height() - 43;
		$dompdf->getCanvas()->page_text($x, $y, $text, $font, $size);
		
		
	}
	
	private function cssPDF(){		
		$fichierCSS = 'fdrDomPDF.css';
		if ($this->bFdrProprietaire){
			$fichierCSS = 'fdrPropPDF.css';
		}
		
		return '<style type="text/css">'.file_get_contents(__DIR__.'/../css/'.$fichierCSS).' </style>';		
	}
		
	//////////////////////////////////////////////
	//
	//  Calcul energetique détailés
	//
	/////////////////////////////////////////////	
	
	public function htmlFdrCalculEnergieDetail($data){
		$html = '';
		
		$tabCrit ='';
		$tabCrit.= $this->htmlCalculEnergieParUsage($data);
		$tabCrit.= '<br><br>'.$this->htmlCalculEconomieCout($data);

		$html = 
		'<div class="blocFdr pbrb">'.
			'<div class="titreFdr">Calculs énergétiques détaillés</div>'.
			'<div class="blocEtape blocCadre">'.						
				$tabCrit.										
			'</div>'.
		'</div>';
		return $html;
	}
	

	public function htmlCalculEnergieParUsage($data){
		$res = '';
		$tabEnergieParUsage = '';
		if (isset($data['cth']['calculEnergetiqueDetail']['energieParUsage'])){
			$lgnUsage = [];
			foreach ($data['cth']['calculEnergetiqueDetail']['energieParUsage']['etape'] as $nEtape=>$etape){
				foreach($etape as $usage=>$val){
					if(!isset($lgnUsage[$usage])){
						switch ($usage){
							case 'total':
								$lgnUsage[$usage] = $this->htmlFdrChampEtape('lib vallignMiddle','Total','rowspan="2"');
								break;
							case 'totalSurf':
								$lgnUsage[$usage] = '';
								break;
							default : 
								$lgnUsage[$usage] = $this->htmlFdrChampEtape('lib',$this->getValChamp('usagesRt',$usage));
						}
					}
					$lgnUsage[$usage] .= $this->htmlFdrChampEtape('val textRight',$etape[$usage]);//$this->numberFormat(,2)
				}			
			}
			
			
			$tabEnergieParUsage .= '<tr class="entete">';
			$tabEnergieParUsage .= $this->htmlFdrChampEtape('titreTab','');
			$tabEnergieParUsage .= $this->htmlFdrChampEtape('titreTab','Etat Init.');			
			$nbEtap = count($data['cth']['calculEnergetiqueDetail']['energieParUsage']['etape']);
			for ($i=1;$i<$nbEtap;$i++){
				$tabEnergieParUsage .= $this->htmlFdrChampEtape('titreTab','Etape '.$i);
			}
			$tabEnergieParUsage .= $this->htmlFdrChampEtape('titreTab','Unité');
			$tabEnergieParUsage .= '</tr>';
			
			foreach($lgnUsage as $usage=>$lgn){
				if (!isset($unite)){
					$unite = $this->htmlFdrChampEtape('val vallignMiddle textRight','kWhEP/m²SHAB.an','rowspan="6"');
				} else if ($usage=='totalSurf'){
					$unite = $this->htmlFdrChampEtape('val textRight','kWhEP.an');
				} else {
					$unite = '';
				}
				$tabEnergieParUsage .= '<tr>'.$lgn.$unite.'</tr>';
			}			
		}
		
		
		if ($tabEnergieParUsage){		
			$res = '<table class="tabFdr tabEnergieParUsage">'.
						'<tr class="entete">'.$this->htmlFdrChampEtape('titreTab','<b>Détail des consommations par usages</b>','colspan="'.($nbEtap+2).'"').'</tr>'.
						$tabEnergieParUsage.
						'</table><br><br>';			
		}
		
		return $res;
	}
	
	

	public function htmlCalculEconomieCout($data){
		$res = '';
		$htmlTab = '';
		if (isset($data['cth']['calculEnergetiqueDetail']['recapEconomie'])){
			$economieEnergie = '';
			$economieCout = '';
			$entete = '';
			foreach ($data['cth']['calculEnergetiqueDetail']['recapEconomie']['etape'] as $nEtape=>$etape){
				$entete	.= $this->htmlFdrChampEtape('titreTab','Etape '.$nEtape);
				$economieEnergie	.= $this->htmlFdrChampEtape('val textRight',$etape['economieEnergie']);
				$economieCout	.= $this->htmlFdrChampEtape('val textRight',$etape['economieCout']);
			}	
			
			$htmlTab .= '<tr class="entete">'.$this->htmlFdrChampEtape('titreTab','').$entete.'</tr>';
			$htmlTab .= '<tr>'.$this->htmlFdrChampEtape('lib','Économies d’énergie en kWhEP/m²SHAB.an').$economieEnergie.'</tr>';
			$htmlTab .= '<tr>'.$this->htmlFdrChampEtape('lib','Économies en €.an').$economieCout.'</tr>';
		}
		
		
		if ($htmlTab){
			$nbEtap = count($data['cth']['calculEnergetiqueDetail']['recapEconomie']['etape']);
			$res = '<table class="tabFdr tabRecapEconomie">'.
						'<tr class="entete">'.$this->htmlFdrChampEtape('titreTab','<b>Economies générées </b>','colspan="'.($nbEtap+1).'"').'</tr>'.
						$htmlTab.
						'</table><br><br>';			
		}
		return $res;
	}	
	
	//////////////////////////////////////////////
	//
	//  Critères d'aides
	//
	/////////////////////////////////////////////
	
	public function _a_mettre_ajour_htmlRecapCriteres($data){
		$html = '';
		
		$tabCrit ='';
		$tabCrit.= $this->htmlRecapMaPrimeRenov($data);
		$tabCrit.= $this->htmlRecapCeeBar164($data);

		$html = 
		'<div class="blocFdr pbrb">'.
			'<div class="titreFdr">Récapitulatifs des critères d\'aides</div>'.
			'<div class="blocEtape blocCadre">'.						
				$tabCrit.										
			'</div>'.
		'</div>';
		return $html;
	}
	
	public function _a_mettre_ajour_htmlRecapMaPrimeRenov($data){
		$pathImg = 'img/';
		$tabCrit = "";
		if (isset($data['cth']['recapCriteres']['maPrimeRenov'])){
			foreach ($data['cth']['recapCriteres']['maPrimeRenov']['etape'] as $nEtape=>$etape){
				$tabCrit.= '<tr class="entete">'.$this->htmlFdrChampEtape('titreTab',$this->htmlFdrGetNomEtape($nEtape),'colspan="3"').'</tr>';
				$tabCrit.= '<tr class="entete">';
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Indicateur','width="50%"');
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Valeur','width="20%"');
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Unité','width="30%"');
				$tabCrit.= '</tr>';
				foreach($etape as $lib=>$val){					
					$unite = $this->getColTableauValeur('tabRecap','id',$lib,'unite');
					$libTxt = $this->getColTableauValeur('tabRecap','id',$lib,'lib');
					switch ($val){
						case 'oui':
							$val = $this->htmlIconAndText($this->htmlImgOk(),'Oui');
							break;
						case 'non':
							$val = $this->htmlIconAndText($this->htmlImgWarning(),'Non');
					}
					$tabCrit.= '<tr>';
					$colspan = $unite ? '' : 'colspan="2"';
					$tabCrit.=$this->htmlFdrChampEtape('lib',$libTxt);
					$tabCrit.='<td class="col val" '.$colspan.'>'.$val.'</td>';
					if ($unite){
						$tabCrit.=$this->htmlFdrChampEtape('unite',$unite);
					}
					$tabCrit.= '</tr>';
				}
			}
		}
		
		if ($tabCrit){
			$tabCrit = '<table class="tabFdr tabmaPrimeRenov">'.
						'<tr class="entete">'.$this->htmlFdrChampEtape('titreTab','<b>Respect des critères de l\'audit Ma Prim\'Rénov</b>','colspan="3"').'</tr>'.
						$tabCrit.
						'</table><br><br>';			
		}
		
		return $tabCrit;
	}
	
	public function _a_mettre_ajour_htmlRecapCeeBar164($data){
		$pathImg = 'img/';
		$tabCrit = '';

		if (isset($data['cth']['recapCriteres']['ceeBar164'])){
			foreach ($data['cth']['recapCriteres']['ceeBar164']['etape'] as $nEtape=>$etape){
				$tabCrit.= '<tr class="entete">'.$this->htmlFdrChampEtape('titreTab',$this->htmlFdrGetNomEtape($nEtape),'colspan="3"').'</tr>';
				$tabCrit.= '<tr class="entete">';
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Indicateur','width="50%"');
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Valeur','width="20%"');
				$tabCrit.= $this->htmlFdrChampEtape('titreTab','Unité','width="30%"');
				$tabCrit.= '</tr>';
				foreach($etape as $lib=>$val){					
					$unite = $this->getColTableauValeur('tabRecap','id',$lib,'unite');
					$libTxt = $this->getColTableauValeur('tabRecap','id',$lib,'lib');
					switch ($val){
						case 'oui':
							$val = $this->htmlIconAndText($this->htmlImgOk(),'Oui');
							break;
						case 'non':
							$val = $this->htmlIconAndText($this->htmlImgWarning(),'Non');
					}
					$tabCrit.= '<tr>';
					$colspan = $unite ? '' : 'colspan="2"';
					$tabCrit.=$this->htmlFdrChampEtape('lib',$libTxt);
					$tabCrit.='<td class="col val" '.$colspan.'>'.$val.'</td>';
					if ($unite){
						$tabCrit.=$this->htmlFdrChampEtape('unite',$unite);
					}
					$tabCrit.= '</tr>';
				}
			}
		}
		
		if ($tabCrit){
			$numFiche = '164';
			if ($this->isTypeBatLC()){
				$numFiche = '145';
			}
			$tabCrit = '<table class="tabFdr tabmaPrimeRenov pbrb" >'.
						'<tr class="entete">'.
							$this->htmlFdrChampEtape('titreTab','<b>Respect des critères de la fiche BAR TH '. $numFiche .' & Acte A4 du SARE</b>','colspan="3"').
						'</tr>'.
						$tabCrit.
						'</table><br><br>';			
		}
		
		return $tabCrit;
	}
	
	protected function htmlFdrGetNomEtape($nEtape){
		if ($nEtape == 0 ){
			return 'Etat initial';
		} else {
			return 'Etape n°'.$nEtape;
		}
	}
	
	protected function htmlFdrDescription($data){
		$desc=$data['desc'];
		$html = '';
		$descBat = '';

		$html .= '<table class="tabFdr"><tbody>';
		$html .= '<tr class="entete"><td class="col titreTab">Général</td><td class="col titreTab">Bâtiment</td></tr>';
		$html .= '<tr><td class="col">';
		
			foreach ($desc['general'] as $nomChamp=>$valChamp){
				$html .= $this->htmlFdrChampVal($nomChamp, $valChamp);
			}
		$html .= '</td><td class="col">';
			foreach($desc['batiment'] as $nomChamp=>$valChamp){
				if ($nomChamp=='description'){
					$descBat = $valChamp;
				} else {
					$html .= $this->htmlFdrChampVal($nomChamp, $valChamp);
				}
			}
		$html .= '</td></tr>';	
		if ($descBat){
			$html .='<tr class="entete"><td class="col titreTab" colspan="2">Description</td></tr>';
			$html .='<tr><td class="col" colspan="2">'.$descBat.'</td></tr>';
		}
		$html .= '</tbody></table><br><br>';
		
		
		$html .= $this->htmlFdrConsommation($data);
		
		$html = $this->htmlTitreFdr('Données Générales',true).
				$this->htmlDivTab($html,'class="blocDesc blocCadre"');	
//echo $html; die;				
		return $html;
	}
	
	protected function htmlFdrConsommation($data){
		$html = '';
		$htmlConso = '';
		//Consommation
		if (isset($data['cth']['etape'])){
			$htmlConso = '';
			//let htmlEntete = '';
			$aCep = [];
			$aGes = [];
			$nbEtap =0;
			foreach ($data['cth']['etape'] as $nEtap=> $etape){
				$aCep[] = floor($etape['consoTotal']['cep']);
				$aGes[] = floor($etape['consoTotal']['ges']);
				$aClasseDpe[] = $etape['consoTotal']['classeDpe'];
				
				$aClasseGes[] = $etape['consoTotal']['classeGes'];
			}
			$imgCep=$this->getImageDpeBilan_2022($aCep,$aGes,$aClasseDpe);	
			$imgGes=$this->getImageDpeGes_2022($aGes,$aClasseGes);	
			
			$htmlConso .= '<tr><td class="col">'.
							'<table class="contDpe"><tr>'.
								'<td class="dpeCep">'.
									'<img class="imgDpe" src="'.$this->htmlImg($imgCep,true).'">'.'<br>'.
									'<div class="graphLegend">(en kWhep/m².an)</div>'.
					
								'</td>'.
								'<td class="dpeGes">'.
									'<img class="imgDpe" src="'.$this->htmlImg($imgGes,true).'">'.'<br>'.
									'<div class="graphLegend">(en kgeqCO2/m².an)</div>'.
								
								'</td>'.
							'</tr></table>'.
			
							(($this->bFdrEditeur) ? 
								'<div class="warning"><b>Attention : l\'évaluation énergétique utilisée ici n\'est pas assimilable à un DPE. </b></div>'
								: ''
							).
							
						'</td></tr>';
			$nbEtap++;
		
			
			if ($htmlConso){
			
				$html .='<table class="tabFdr tabConso"><tbody>';
				$html .='<tr class="entete"><td class="col titreTab" >Evolution des consommations</td></tr>';

				$html .=$htmlConso;
				$html .='</tbody></table>';
			}
		}
		$html .='<div style="page-break-before:always"></div>';
		return $html;
	}	
	
	protected function htmlFdrRespectCriteresB2C2($data){
		$parcour = $data['parcour'];
		$aEtapes = $parcour['etapes'];
		$html = '';
		//teste le respect des criètres dès la 1ere étape.


		$html .= ''.
					
					'<div class="blocEtape blocCadre pbra">'.
						$this->htmlRespectCritere($data['parcour']['respectB2C2']['critPremEtape'],'Respect des critères à la première étape').
						$this->htmlRespectCritere($data['parcour']['respectB2C2']['complPermEtape'],'Exigences complémentaires à la première étape').
						$this->htmlRespectCritere($data['parcour']['respectB2C2']['critDernEtape'],'Respect des critères à la dernière étape').
						$this->htmlRespectCritere($data['parcour']['respectB2C2']['complDernEtape'],'Exigences complémentaires à la dernière étape').
						'<table class="legendRisq"><tbody>'.
									'<tr><td width="1px">'.$this->htmlImgOk().'</td><td>Critère respecté</td></tr>'.
									'<tr><td>'.$this->htmlImgWarning(2).'</td><td>Critère à vérifier</td></tr>'.
									'<tr><td>'.$this->htmlImgCoss().'</td><td>Critère non respecté</td></tr>'.
						'</tbody></table>'.					
					'</div>'.
				'';
				

		
		return $html;
	}
	
	protected function htmlFdrListeEtape($data){
		$parcour = $data['parcour'];
		$html = '';
		
		$aEtapes = $parcour['etapes'];
		
		foreach ($aEtapes as $iEtap=>$etape){
			if (
				isset($etape['lstLot']) //pour l'apiExt, si il n'y aucun travaux à une étape, il peut quand même y avoir le calcul thermique récupéré dans le XML
				AND (
						!($iEtap =='nonTraite' AND $this->bFdrEditeur) //on affiche pas le bloc "lots non traités" pour les éditeurs.
					)
				){ 
				$titreFdr = 'Etape n°'. $iEtap .' des travaux';
				if ($iEtap =='nonTraite'){
					$titreFdr = "Lots non taités";
				}
				
				$html .= '<div class="blocFdr pbrb">'.
							'<div class="titreFdr">'.$titreFdr.'</div>'.
							'<div class="blocEtape blocCadre">'.	
								$this->htmlFdrEtape($etape).
								$this->htmlTestGESDegrade($etape).											
							'</div>'.
						'</div>';
					
				if ($iEtap !='nonTraite'){
					$htmlInteractions = '';
					$htmlInteractions .= $this->htmlInterGlobale($etape,$iEtap);
					$htmlInteractions .= $this->htmlInterEtape($etape);
					
					if ($htmlInteractions ){	
						$html .= '<div class="titreFdr pbrb">Interactions à l\'étape n°'.($iEtap).'</div>'.
								'<div class="blocEtape blocCadre">'.
									$htmlInteractions.																			
								'</div>';		
					}
				}
			}
		}
		return $html;
	}
	
    /** Liste travaux Lots **/
	
	protected function htmlFdrEtape($etape){
		$aLot = $etape['lstLot'];
		$html = '';		
		
		$html .= $this->htmlFdrEnteteEtape(true);
		
		foreach ($aLot as $nomLot=>$aTypeLot ){
			$html.= $this->htmlFdrTypeLot($nomLot,$aTypeLot,true);				
		}	
		
		return '<table class="tabFdr tabEtape"><tbody>'.$html."</tbody></table>";
	}
	

	protected function htmlFdrEnteteEtape($bPerf){
		$ligne = '';
		$ligne .= $this->htmlFdrChampEtape('nomLot titreTab','Lots');
		if (!$this->bFdrEditeur){
			$ligne .= $this->htmlFdrChampEtape('existant titreTab','Existant');
		}
		$ligne .= $this->htmlFdrChampEtape('descTrav titreTab','Description des travaux');
		if ($bPerf){
			$ligne .= $this->htmlFdrChampEtape('perfFinale titreTab','Performance finale');
		}			
		$ligne .= $this->htmlFdrChampEtape('recom titreTab','Recommandations');
		$ligne ='<tr class="lgn entete">'.$ligne."</tr>";
		return $ligne;
	}
	
	protected function htmlFdrTypeLot($nomLot,$aTypeLot,$bPerf){
		$html = '';
		$nbLigne = 0;
		$derniereLigne = "";
		for ($iType = 0 ; $iType < count($aTypeLot) ; $iType++){
			$typeLot = $aTypeLot[$iType];
			$ligne = '';
			
			
			
			if (!$this->bFdrEditeur){				
				$txtExistant = '<b>' . $typeLot['designLot'] . ' :</b><br>' . $typeLot['existant'];
				$ligne .= $this->htmlFdrChampEtape('existant',$txtExistant);
			}
			
			//si pas colonne perfFinale alors on elargi la colone précédente.
			$colSpan = '';
			$htmlPerfFinale = '';
			if ($bPerf && !empty($typeLot['perfFinale'])){				
				$txtPref = $typeLot['perfFinale'];
				if (isset($typeLot['perfFinaleErr'])){
					$txtPref .= '<br>*<span class="perfErr"*>'.$typeLot['perfFinaleErr'].'*</span*>';
				}
				$htmlPerfFinale = $this->htmlFdrChampEtape('perfFinale',$txtPref);
			} else {
				$colSpan = ' colspan="2" ';
			}				
			
			//colonne existant
		
			//description des travaux
			$descTrav = '';
			if ($this->bFdrEditeur){				
				$descTrav = '<b>' . $typeLot['designLot'] . ' :</b><br>';							
			}
			$descTrav .=  $typeLot['descTrav'];
			
			$ligne .= $this->htmlFdrChampEtape('descTrav',$descTrav,$colSpan);
			$ligne .= $htmlPerfFinale;
			$ligne .= $this->htmlFdrChampEtapeListeVal('recom',$typeLot);
			
			if ($derniereLigne != $ligne){
				$derniereLigne = $ligne;

				if ($iType == 0 ){				
					$sNomLot = $this->getValChamp('lstLot',$nomLot);
					$ligne = $this->htmlFdrChampEtape('nomLot sansBordBas',$sNomLot, '') . $ligne;
				}	else {
					//contournement de la mauvaise gestion des rowspan par domPDF
					$ligne = $this->htmlFdrChampEtape('nomLot sansBord2','', '').$ligne;
				}

				$html .='<tr class="lgn" nobr="true">'.$ligne."</tr>";
				$nbLigne++;
			}
		}
	
		//met à jour le rowspan
		$html = str_replace('***nbLigne***',$nbLigne,$html);
		
		//contournement de la mauvaise gestion des rowspan par domPDF
		if ($nbLigne ==1){
			$html = str_replace('sansBordBas', '', $html);	
		}else {
			$html = $this->str_lreplace('sansBord2', 'sansBordHaut', $html);	
		}
		
		
		return $html;
	}

	protected function str_lreplace($search, $replace, $subject){
		//contournement de la mauvaise gestion des rowspan par domPDF
		$pos = strrpos($subject, $search);

		if($pos !== false)
		{
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}

		return $subject;
	}
	
	protected function htmlFdrChampEtapeListeVal($nomChamp,$listeVal,$complement=''){
		return $this->htmlFdrChampEtape($nomChamp,$listeVal[$nomChamp],$complement);
	}
	
	protected function htmlPDFSpecialCars($val){
		//a optimiser avec des epressions régulières,
		//car les signes > et < perturbent le PDF
		$val = str_replace('<br>','#*br*#',$val); //on ne remlplace pas les <br>
		$val = str_replace('<b>','#*b*#',$val); //on ne remlplace pas les <br>*/
		$val = str_replace('</b>','#*/b*#',$val); //on ne remlplace pas les <br>
		
		$val = str_replace('*<','#*',$val); 
		$val = str_replace('*>','*#',$val); 
		
		$val = str_replace('<','&lt;',$val); 
		$val = str_replace('≤','&le;',$val); 		
		$val = str_replace('>','&gt;',$val);
		
		//restore original tags
		$val = str_replace('#*','<',$val); 
		$val = str_replace('*#','>',$val); 
		return $val;
	}
	
	protected function htmlFdrChampEtape($sClass,$val,$complement = ''){
		$val = $this->htmlPDFSpecialCars($val);
		return '<td class="col '.$sClass.'" '.$complement.'>'. $val . '</td>';
	}
	
	/** Liste interaction **/	

	protected function htmlInterEtape($etape){	
		if (!isset($etape['lstInteracEtape'])) return '';
		$lstLotInter = $etape['lstInteracEtape'];
		
		$html = '';		

		//html += this.htmlFdrEnteteEtape(true);
		foreach ($lstLotInter as $sLotsInter =>$lstInter){
			
			$htmlLot = '';
			foreach ($lstInter as $i=>$detailInter){					
				$htmlLigne = '';
				if (!$htmlLot){
					$aLotsInter = explode('/',$sLotsInter);
					$libInter = $this->getListeValChamp('lstLot',$aLotsInter,' / ');
					$htmlLigne .=	'<td './*'rowspan="'.count($lstInter).'"'.*/' class="col lots sansBordBas">'.$libInter.'</td>';
				} else {
					//contournement de la mauvaise gestion des rowspan par domPDF
					$htmlLigne .=	'<td class="col lots sansBord2"></td>';
				}
				
				$risque = $detailInter['risque'];
				$traitement = 	trim($detailInter['solution']);
													
				$htmlLigne.= 	'<td class="col risques ">'.$risque.'</td>'.
								'<td class="col traitement">'.$traitement.'</td>';
				$htmlLot.=  '<tr nobr="true">'.$htmlLigne.'</tr>';		
			}
			
			//contournement de la mauvaise gestion des rowspan par domPDF
			if (count($lstInter) ==1){
				$htmlLot = str_replace('sansBordBas', '', $htmlLot);	
			}else {
				$htmlLot = $this->str_lreplace('sansBord2', 'sansBordHaut', $htmlLot);	
			}
		
			$html .= $htmlLot;
		}
				
		$titreTab =   "Traitement des interactions inter-etape";		

		
		if ($html){						
			return 	'<table class="tabFdr tabInter"><tbody>'.
						'<tr class="entete"><td class="col titreTab" colspan="3">'.$titreTab .'</td></tr>'.
						'<tr class="entete"><td class="col lots titreTab">Lots concernés</td><td class="col risques titreTab">Risques</td><td class="col traitement titreTab">Traitements à prévoir</td></tr>'.
						$html.
					"</tbody></table><br><br>"
						//.$html
					;
		} else {
			return '';
		}
	}
	

	
	protected function htmlInterGlobale($etape,$nEtap){	
		if (!isset($etape['lstInteracGlob'])) return '';
		$lstLotInter = $etape['lstInteracGlob'];
		
		$html = '';		
		$niveau = '';
		//html += this.htmlFdrEnteteEtape(true);
		foreach ($lstLotInter as $libInter =>$lstInter){
			
			$htmlLot = '';
			foreach ($lstInter as $i=>$detailInter){			
				$htmlLigne = '';
				
				$niveau = $detailInter['niveau'];
				$risque = $detailInter['risque'];
				$risque = explode('/',$risque);
				
				$traitement = 	trim($detailInter['solution']).
								$this->ajouteLienFicheAdeme($detailInter['numFicheAdeme']);
				
				$img = '';
				if ($niveau == 'risqNiv3') $img = $this->htmlImgWarning(3);
				if ($niveau == 'risqNiv2') $img = $this->htmlImgWarning(2);
				if ($niveau == 'risqNiv1') $img = $this->htmlImgWarning(1);
								
				$htmlLigne.= 	'<td class="col risques '.$niveau.'">'.trim($risque[0]).'</td>'.
								'<td class="col risques '.$niveau.'">'.trim($risque[1]).'</td>'.
								'<td class="col traitement">'.$this->htmlIconAndText($img,$traitement).'</td>';
				$htmlLot.=  '<tr nobr="true">'.$htmlLigne.'</tr>';		
			}
			$html .= $htmlLot;
		}
				
		$titreTab = "Traitement des interactions globales" ;		
 
		if ($html){						 
			$html =	'<table class="tabFdr tabInter"><tbody>'.
						'<tr class="entete"><td class="col titreTab surtitre" colspan="3">'.$titreTab .'</td></tr>'.
						'<tr class="entete">'.
							'<td class="col lots titreTab">Etape n°'.$nEtap.'</td>'.
							'<td class="col risques titreTab">Etapes Ultérieures</td>'.
							'<td class="col traitement titreTab">Impact</td>'.
						'</tr>'.
						$html.
					"</tbody></table>";
			//legende		
			$html .='<table class="legendRisq"><tbody>'.
								'<tr><td width="1px">'.$this->htmlImgWarning(1).'</td><td>'.$this->getValChamp('interactions',"risqNiv1").'</td></tr>'.
								'<tr><td>'.$this->htmlImgWarning(2).'</td><td>'.$this->getValChamp('interactions',"risqNiv2").'</td></tr>'.
								'<tr><td>'.$this->htmlImgWarning(3).'</td><td>'.$this->getValChamp('interactions',"risqNiv3").'</td></tr>'.
					'</tbody></table>'.
					'<div class="notesBasPage">* fiches créées par Doremi et Enertech en partenariat avec l\'Ademe</div>';
			return $html;
		} else {
			return '';
		}
	}
	
	protected function ajouteLienFicheAdeme($numFicheAdeme){
		$res = "";
		if ($numFicheAdeme){
			$lien = '';
			$aNumFiches = explode(',',$numFicheAdeme);
			foreach($aNumFiches as $sNumFiche){
				//la fiche numéro 3 commence à la page 13 et il y a 2 pages par fiches
				//$numPage = ( 13 + ( 2 * (intval($sNumFiche)-3) ) );
				//$url = 'https://librairie.ademe.fr/cadic/6998/travaux-par-etape-points-de-vigilance.pdf#page='.$numPage;
				$lib = $this->getValChamp('ficheAdeme',$sNumFiche);
				$url = $this->getColTableauValeur('ficheAdeme','id',$sNumFiche,'url');
	
				if ($url){
					if ($lien) {
						$lien.= ', ';
					}
					$lien.= '<a href="'.$url.'" target="__BLANK'.$sNumFiche.'">'.
					"Fiche #".
					$sNumFiche.' - '.$lib.
					'</a>';
				}
			}
			
			$lien = (count($aNumFiches) > 1 ? ' aux ' : ' à la ') . $lien;
			
			
			$res.= " Pour plus d’informations, veuillez vous référer".
					$lien.
					" de Dorémi*";
					//" du rapport de l'ADEME : Travaux par étapes : les points de vigilance.";
	
		}
		
		if ($res){
			$res.= " et ";
		} else {
			$res.= " Pour plus d’informations,";
		}
		
		$res.= ' rapprochez-vous de votre <a href="https://france-renov.gouv.fr/services-france-renov" target="__BLANK">Espace Conseil France Rénov’</a> le plus proche.';
		
		return $res;
	}

	/**Respect des critères B2c2 **/
	protected function htmlRespectCritere($aRespect,$titre){
		$html = '';
		$html .= '<tr class="entete"><td class="col titreTab">'.$titre.'</td></tr>';
		
		foreach ($aRespect as $critere=>$respect){			
			if (strpos($respect,'warn') !== false){
				$niv = str_replace('warn','',$respect);
				$img = $this->htmlImgWarning($niv);
			} else if ($respect == self::OK){
				$img = $this->htmlImgOk();
			} else {
				$img = $this->htmlImgCoss();
			}		
			$conforme =$this->htmlIconAndText($img,$this->getValChamp('remarqueSolution',$critere));			
			$html .= '<tr><td class="col">'.$conforme.'</td></tr>';
		}

		$html = '<table class="tabFdr conformite"><tbody>'.$html.'</tbody></table>';
	
		return $html;
	}

	protected function testeRespectCriteresB2C2($data){
		$res = ['premier'=>true,'final'=>true];
		foreach ($data['parcour']['respectB2C2'] as $nomCrit=>$aRespect){
			foreach ($aRespect as $critere=>$respect){			
				if ($respect == self::erreur){
					if ($nomCrit == 'critPremEtape' OR $nomCrit == 'complPermEtape'){
						$res['premier']=false;
					}
					if ($nomCrit == 'critDernEtape' OR $nomCrit == 'complDernEtape'){
						$res['final']=false;
					} 
					
				} 
			}
		}
		return $res;	
	}

	protected function htmlTestGESDegrade($etape){
		$html = '';
		if (isset($etape['GESDegrade'])){
			$html .= '<tr class="entete"><td class="col titreTab">Emissions GES</td></tr>';
			
			
			if ($etape['GESDegrade']=='non'){
				$img = $this->htmlImgOk();
				$txt = 'Diminution des émissions de GES entre l’état avant et après travaux.';
			} else {
				$img = $this->htmlImgWarning();
				$txt = 'Augmentation des émissions de GES entre l’état avant et après travaux.';
			}
			
			$txtPerteNivGES = $this->htmlIconAndText($img,$txt);
								
			$html .= '<tr><td class="col">'.$txtPerteNivGES.'</td></tr>';
			$html = '<table class="tabFdr GESDegrade"><tbody>'.$html.'</tbody></table>'; 
		}
		return $html;
	}
	
	/*********************************************
	* Fonctions HTML
	**********************************************/
	
	protected function htmlImgCoss(){
		$pathImg = 'img/';
		return '<img class="imgConformite" src="'.$this->htmlImg($pathImg.'cross.png').'">';//passé de png à jpg car bug quand on utilise plusieurs PNG sur des pages différentes
	}
	
	
	protected function htmlImgOk(){
		$pathImg = 'img/';
		return '<img class="imgConformite" src="'.$this->htmlImg($pathImg.'OK.png').'">';//passé de png à jpg car bug quand on utilise plusieurs PNG sur des pages différentes
	}

	protected function htmlImgWarning($num='2'){
		$pathImg = 'img/';
		return '<img class="imgConformite" src="'.$this->htmlImg($pathImg.'warning'.$num.'.png').'">';
	}
	
	
	protected function htmlIconAndText($img,$text,$class=''){
		$pathImg = 'img/';
		return '<table><tr><td class="iconAndTextIcon '.$class.'">'.$img.'</td><td class="iconAndTextText '.$class.'">'.$text.'</td></tr></table>';
	}
	
	protected function htmlImg($src,$bDataSrc = false){
		if ($bDataSrc){
			$src = 'data:image/png;base64,'.$src;	
		} else {
			if ($this->bGeneratePDF){	
				$src = __DIR__.'/../'.$src;				
			} else {
				$src = CMSEffiUtils::getCMSUriProjBase().$src;
			}
		}
		
		return $src;
	}

	protected function htmlDivTab($content,$complement=""){
		return'<div '.$complement.'>'.$content.'</div>';
		
		if (strpos($complement,'class="')===false){
			$complement.=' class="divTabPdf"';
		} else {
			$complement =str_replace('class="','class="divTabPdf ',$complement);
		}
		
		return'<table '.$complement.'><tr><td>'.$content.'</td></tr></table>';
	}
	
	protected function htmlDiv($content,$complement=""){
		return'<div '.$complement.'>'.$content.'</div>';
	}
	
	protected function htmlTitreFdr($lib,$bOpen = false){
		return $this->htmlDivTab($lib,'class="titreFdr'.($bOpen ? ' opened' : '').'"');
	}
	
	protected function htmlFdrChampVal($nomChamp,$val){
		if (!$val) return '';
		$text = $this->getValChamp($nomChamp,$val);
		$lib = $this->getValChamp('lstChamp',$nomChamp);
		
		return $this->htmlFdrChamp($lib,$text,$nomChamp);
	}
	
	protected function htmlFdrChamp($lib,$val,$sClass){
		return 	'<table class="champFdr '.$sClass.'">'.
				'<tr>'.
					'<td class="lib">'.$lib.'</td>'.
					'<td class="val">'.$val.'</td>'.
				'</tr>'.
				'</table>';
	}
	
	
	/*********************************************
	* etiquettes DPE
	**********************************************/
	private function imagecreatefrompngalpha($path,$width=0,$height=0){
		
		$srcImage = imagecreatefrompng($path);
		$srcWidth = imagesx($srcImage);
		$srcHeight = imagesy($srcImage);
		
		if (!$width){
			$width = $srcWidth;
		}
		
		if (!$height){
			$height = $srcHeight;
		}
		
		$targetImage = imagecreatetruecolor( $width, $height );   
		imagealphablending( $targetImage, false );
		imagesavealpha( $targetImage, true );
		
		
		if ($width != $srcWidth OR $height != $srcHeight){
			$alpha_channel = imagecolorallocatealpha($targetImage, 0, 0, 0, 127); 
			
			imagefill($targetImage, 0, 0, $alpha_channel); 
		}


		imagecopy($targetImage, $srcImage, 
					0, 0, 
                    0, 0, 
					$srcWidth, $srcHeight);
		imagedestroy($srcImage);
		return $targetImage;
	}
	/********************************************************/
	function imagettftext($image,$size,$angle,$x, $y, $color, $font_filename,$text){
		//pour palier au bug d'OVH : Sur certaines Webm (instance de serveurs web) la fonction imagettftext()
		//tombe en erreur car il y a un problème de configuration de certaines de leurs webm au niveau du support des polices true type par la bibliothèque GD de PHP
		if (function_exists('imagettftext')){
			return imagettftext($image,$size,$angle,$x, $y, $color, $font_filename,$text);
		} else {
			$font = 5;
			file_put_contents(__dir__.'/log_'.date("j.n.Y").'.php', date("F j, Y, g:i a") .' '.php_uname().' '.$_SERVER['REMOTE_ADDR'].' '.(function_exists('imagettftext')? 'OK' : 'ERROR')."\n", FILE_APPEND);
			return imagestring($image,$font,$x,$y-15,$text,$color);
		}
	}
	/********************************************************/
	private function getPosScale($val,$aScale){
		$num=0;
		while (($num<count($aScale)) and ($val>$aScale[$num])){
			$num++;
		}	
		return $num;
	}
	/********************************************************/
	private function getImageDpeENRMulti($aVal,$typeImage){
		//image
		$imgDPE = $this->imagecreatefrompngalpha(__DIR__.'/../img/fondDPE_'.$typeImage.'.png',280+80*count($aVal));
		$imgCursor = $this->imagecreatefrompngalpha(__DIR__.'/../img/curseurDPE.png');
		

		if ($typeImage == 'ENR'){
			$aScale = $this->getColTableau('dpeCep','valMax');
		} else {
			$aScale = $this->getColTableau('dpeGes','valMax');
		}
		array_pop($aScale);//on ne prends pas la valeur G car elle n'a pas de max
		
		//couleurs 
		$textcolor = imagecolorallocate($imgDPE, 0, 0, 0);
		$consocolor = imagecolorallocate($imgDPE, 255, 255, 255);
		
		foreach ($aVal as $nEtap=>$val){
			$num = $this->getPosScale($val,$aScale);
			$offsetx=$nEtap*65;
			$offsetY=$num*30.4;
			imagecopy($imgDPE,$imgCursor,200+$offsetx,62+$offsetY,0,0,imagesx($imgCursor),imagesy($imgCursor));
			
			//texte 
			$texteEtap = ($nEtap == 0 ) ? 'Etat init.' : 'Etape '.$nEtap;
			$this->imagettftext($imgDPE, 11, 0, 200+7+$offsetx, 60-5, $textcolor, __DIR__.'/../img/arial.ttf', $texteEtap );
			$this->imagettftext($imgDPE, 16, 0, 200+20+$offsetx, 62+$offsetY+21, $consocolor, __DIR__.'/../img/arial.ttf', intval($val));
	
			if ($nEtap !=0){
				$x = 200+$offsetx ;
				imageline ( $imgDPE , $x , 60-20 , $x , 60+220 , $textcolor );
			}
			
		}
		

		ob_start();
		imagepng($imgDPE);
		$imagedata = ob_get_clean();
		
		imagedestroy($imgDPE);
		imagedestroy($imgCursor);
		
		return base64_encode($imagedata);

	}	
	/********************************************************/
	private function getImageDpeGes_2022($aVal,$aClasseGes){
		
		//image
		$offsetY=40+60;
		$offsetX=20;	
		$widthImg = 400+200*count($aVal);
		$heightImg = 530+60*count($aVal);

		
		$imgDPE = imagecreatetruecolor($widthImg,$heightImg);
		$white = imagecolorallocate($imgDPE, 255, 255, 255);
		imagefill($imgDPE, 0, 0, $white);


		$textcolor = imagecolorallocate($imgDPE, 0, 0, 0);		
		$linecolor = imagecolorallocate($imgDPE, 0, 0, 0);
		imagesetthickness($imgDPE,3);
		
		$aOffsetY = [];
		$imgCadre = imagecreatefrompng(__DIR__.'/../img/DPE2022/uniteGES.png');
		for ($i=0;$i<7;$i++){
			$prefix = 'p';			
			
			for ($iVal = count($aVal)-1 ; $iVal >=0 ;$iVal--){				
				$valEtiq = $aVal[$iVal];			
				
				$classeEtiq = $aClasseGes[$iVal];
				$nivEtiq = ord(strtoupper($classeEtiq)) - ord('A');				

				
				if ($i == $nivEtiq){
					$prefix = 'g';	
					$txtValX = $offsetX+400 + $iVal*200;					
					$txtValY = $offsetY+65;
					
					//carré blanc si le trait noir passe derrière
					imagefilledrectangle($imgDPE, $txtValX-40,$txtValY-50, $txtValX+150,$txtValY+25, imagecolorallocate($imgDPE, 255, 255, 255));
					
					imagecopy($imgDPE,$imgCadre,$txtValX+70,$txtValY-25,0,0,imagesx($imgCadre),imagesy($imgCadre));				
					$this->imagettftext($imgDPE, 30, 0, $txtValX, $txtValY, $textcolor, __DIR__.'/../img/arial.ttf', floor($valEtiq));
					imageline ($imgDPE,$offsetX,$txtValY-15,$txtValX-40,$txtValY-15,$linecolor);	
				}
			}
						
			$imgNiveau = imagecreatefrompng(__DIR__.'/../img/DPE2022/'.$prefix.'fg'.($i+1).'.png');
			$imgY = imagesy($imgNiveau);
			imagecopy($imgDPE,$imgNiveau,$offsetX,$offsetY,0,0,imagesx($imgNiveau),$imgY);
			imagedestroy($imgNiveau);
			
			$aOffsetY[$i] = $offsetY;
			$offsetY += $imgY+10;
		}
		
		imagedestroy($imgCadre);
		
		//libA et libF
		$imgLib = imagecreatefrompng(__DIR__.'/../img/DPE2022/libga.png');
		imagecopy($imgDPE,$imgLib,$offsetX,$aOffsetY[0]-30,0,0,imagesx($imgLib),imagesy($imgLib));
		imagedestroy($imgLib);
		$imgLib = imagecreatefrompng(__DIR__.'/../img/DPE2022/libgf.png');
		imagecopy($imgDPE,$imgLib,$offsetX,$offsetY,0,0,imagesx($imgLib),imagesy($imgLib));
		imagedestroy($imgLib);		
		
		//avant après 
		imagesetthickness($imgDPE,2);

		foreach ($aVal as $iVal=>$valEtiq){	
			if ($iVal ==0 ){
				$txtEtap = 'Etat Init.';
			} else {
				$txtEtap = 'Etape '.$iVal;
			}
			
			$x = $offsetX+560+$iVal*200;
			if ($iVal< (count($aVal)-1)){
				imageline ($imgDPE, $x, $aOffsetY[0],$x,$offsetY,$linecolor);
			}
			$this->imagettftext($imgDPE, 30, 0,  $x - 160,$aOffsetY[0]-50, 0, __DIR__.'/../img/arial.ttf',$txtEtap );
		}
		
		
		//rogne la partie basse inutile de l'immage.
		$imgDPE = imagecrop($imgDPE, ['x' => 0, 'y' => 0, 'width' => $widthImg,'height' =>  min($heightImg,$offsetY+50) ]);
		

		//drawImage
		ob_start();
		imagejpeg($imgDPE);
		$imagedata = ob_get_clean();
		
		imagedestroy($imgDPE);
		
		return base64_encode($imagedata);
	}	
	
	/********************************************************/
	private function getImageDpeBilan_2022($aValCep,$aValGes,$aClasseDpe){

		$niv=1;//$this->getNiveauDPE_ENR_GES_2022($val,$valCo2);	
		$nivInit=3;//$this->getNiveauDPE_ENR_GES_2022($valinit,$valCo2init);	
		
		$largeurCadre = 235;
		
		//image
		$offsetY=40 + 60;
		$offsetX=30+ count($aValCep)*$largeurCadre;	
		$widthImg = 360 + count($aValCep)*$largeurCadre;
		$heightImg = 530 + count($aValCep)*60;
		
		$xPassoire = $offsetX-50;	
		
		$imgDPE = imagecreatetruecolor($widthImg,$heightImg);//imagecreatefrompng(__DIR__.'/img/fondDPE_ENR.png');	
		$white = imagecolorallocate($imgDPE, 255, 255, 255);
		imagefill($imgDPE, 0, 0, $white);


		$textcolor = imagecolorallocate($imgDPE, 0, 0, 0);		

		
		$aOffsetY = [];
		$imgCadre = imagecreatefrompng(__DIR__.'/../img/DPE2022/cadre.png');
		
		for ($i=0;$i<7;$i++){
			$prefix = 'p';
			
			foreach ($aValCep as $iVal=>$valCep ){				
				$valGes = $aValGes[$iVal];
				
				$classeEtiq = $aClasseDpe[$iVal];
				$nivEtiq = ord(strtoupper($classeEtiq)) - ord('A');				
				
				
				if ($i == $nivEtiq){
					
					$cadreX = $offsetX + 25 - (count($aValCep) -$iVal)*$largeurCadre;
					$cadreY = $offsetY-34;
					
					$prefix = 'g';
					//cadre									
					imagecopy($imgDPE,$imgCadre,$cadreX,$cadreY,0,0,imagesx($imgCadre),imagesy($imgCadre));				
					$this->imagettftext($imgDPE, 30, 0, $cadreX+20, $cadreY+85, $textcolor, __DIR__.'/../img/arial.ttf', floor($valCep));
					$this->imagettftext($imgDPE, 30, 0, $cadreX+120, $cadreY+85, $textcolor, __DIR__.'/../img/arial.ttf', floor($valGes));
					
					//texte passoire		
						
					if ($nivEtiq>=5){
						$xPassoire = min($xPassoire,$cadreX-48);
					}				
					
				}
			}

			
			$imgNiveau = imagecreatefrompng(__DIR__.'/../img/DPE2022/'.$prefix.'f'.($i+1).'.png');
			$imgY = imagesy($imgNiveau);
			imagecopy($imgDPE,$imgNiveau,$offsetX,$offsetY,0,0,imagesx($imgNiveau),$imgY);
			imagedestroy($imgNiveau);
			
			$aOffsetY[$i] = $offsetY;
			$offsetY += $imgY+10;
		}
		
		imagedestroy($imgCadre);
		
		//libA et libF
		$imgLib = imagecreatefrompng(__DIR__.'/../img/DPE2022/liba.png');
		imagecopy($imgDPE,$imgLib,$offsetX,$aOffsetY[0]-30,0,0,imagesx($imgLib),imagesy($imgLib));
		imagedestroy($imgLib);
		$imgLib = imagecreatefrompng(__DIR__.'/../img/DPE2022/libf.png');
		imagecopy($imgDPE,$imgLib,$offsetX,$offsetY,0,0,imagesx($imgLib),imagesy($imgLib));
		imagedestroy($imgLib);		
				
		//avant après 
		imagesetthickness($imgDPE,2);
		$linecolor = imagecolorallocate($imgDPE, 0, 0, 0);
		foreach ($aValCep as $iVal=>$valEtiq){	
			if ($iVal ==0 ){
				$txtEtap = 'Etat Init.';
			} else {
				$txtEtap = 'Etape '.$iVal;
			}
			
			$x = $offsetX +248 -(count($aValCep) -$iVal)*$largeurCadre;
			if ($iVal< (count($aValCep)-1)){
				imageline ($imgDPE, $x, $aOffsetY[0],$x,$offsetY,$linecolor);
			}
			$this->imagettftext($imgDPE, 30, 0,  $x - 180,$aOffsetY[0]-50, 0, __DIR__.'/../img/arial.ttf',$txtEtap );
		}
		
		
		
		
		$y1 = $aOffsetY[5];
		$y2 = $offsetY-10;
		
		$imgPassoire = imagecreatefrompng(__DIR__.'/../img/DPE2022/passoire.png');
		$yImage = $y1 + ($y2-$y1)/2 - imagesy($imgPassoire)/2 ;	
		imagecopy($imgDPE,$imgPassoire,$xPassoire,$yImage,0,0,imagesx($imgPassoire),imagesy($imgPassoire));
		imagedestroy($imgPassoire);

		$linecolor = imagecolorallocate($imgDPE, 156, 156, 156);
		imagesetthickness($imgDPE,8);		
		imageline ($imgDPE,$xPassoire+35, $y1,$xPassoire+35,$y2,$linecolor);

		
		
		//rogne la partie basse inutile de l'immage.
		$imgDPE = imagecrop($imgDPE, ['x' => 0, 'y' => 0, 'width' => $widthImg,'height' =>  min($heightImg,$offsetY+50) ]);


		//drawImage
		ob_start();
		imagejpeg($imgDPE);
		$imagedata = ob_get_clean();
		
		imagedestroy($imgDPE);

		return base64_encode($imagedata);
	}	
	
	
	/*******************************************************
	*
	* Feuille de route Propriétaires
	*
	*******************************************************/	
	private function htmlFdrProprietaire($data){
		$html = '';
		
		$html .= $this->htmlPropPresentation();
		$html .= $this->htmlPropParcour($data);		
		$html .= $this->htmlPropLstDetailEtape($data);
		$html .= $this->htmlPropRespectCriteresB2C2($data);
	
		
		return $html;
	}
	
	
	

	private function htmlPropEntetePiedPagePDF(){
		$html = '';
		

		$html.= '			
				<div id="header">
					<img id="headerLogo" src="'.$this->htmlImg('img/BBC_par_etapes.png').'">											
				</div>
				
				<div id="footer">
					<img class="footerLogo Pouget" src="'.$this->htmlImg('img/PougetConsultant.png').'">
					<img class="footerLogo Effi" src="'.$this->htmlImg('img/effinergie.png').'">
					<div id="versionB2C2">V'.@$this::VERSION.'</div>
					<img class="footerLogo Ademe" src="'.$this->htmlImg('img/ademe.jpg').'">
				</div>';
		
		return $html;
	}

		
	private function htmlPropPresentation(){
		$html = '';
			
		$html .= '	<div class="titre1">Votre rénovation performante par étapes</div>
					<div class="soutTitre1">Guide travaux personnalisé</div>';
		
		$html .= '<div class="blocTexte">
					<i>La rénovation globale, avec des travaux réalisés en une seule fois c’est l’idéal, mais ce n’est pas toujours possible.
					Vous pouvez aussi faire votre rénovation en plusieurs étapes pour arriver au même niveau de performance, suivez le guide !<br>
			 		<br>
					Ce guide est le complément opérationnel du scénario par étapes de votre audit. Il vous permettra de mettre toutes les chances 
					de votre côté pour qu’à l’issue des étapes prévues, votre logement ait bénéficié d’une rénovation hautement performante.</i>
				</div>';
				
		$html .= '<div class="blocTexte">
					<div class="titre2">Quatre bonnes raisons de suivre le parcours proposé ici</div>
					En plus, vous pourrez envisager de demander le label BBC Effinergie Rénovation.
					<table>
						<tr>
							<td class="tRaisonL">
								<img src="'.$this->htmlImg('img/maisonDPE.svg').'"><br>
								Être assuré d’atteindre une étiquette DPE A ou B lorsque la dernière étape sera achevée.
							</td>
							<td class="tRaisonR">
								<img src="'.$this->htmlImg('img/maisonConfort.svg').'"><br>
								Bénéficier d’un logement confortable, économe en énergie, et préservant votre santé.
							</td>
						</tr>
						<tr>
							<td class="tRaisonL">
								<img src="'.$this->htmlImg('img/maisonEconome.svg').'"><br>
								Mettre votre logement à un niveau de performance qui sera à la hauteur de réglementations qui deviennent de plus en plus contraignantes.
							</td>
							<td class="tRaisonR">
								<img src="'.$this->htmlImg('img/maisonTravaux.svg').'"><br>
								Réaliser les travaux de fond dans le bon ordre et prévoir dès à présent les travaux à venir pour éviter les problèmes techniques ultérieurs.
							</td>
						</tr>
					</table>
				</div>';
				
		$html .= '<div class="blocTexte pbra">
					<div class="titre2">Comment utiliser ce document ? </div>
						<p>Ce document vous aide à organiser vos travaux dans une logique de rénovation performante par étapes.
						Il se base sur une analyse personnalisée de la situation de votre logement.</p>
						
						<p>Pour chaque étape, vous trouverez les différents lots à traiter avec : la description des travaux à faire, les niveaux de performance à respecter, les recommandations de mise en œuvre, les points de vigilance à vérifier entre les travaux.</p>
						
						<p>Ce guide est un support qui doit vous aider tout au long de votre projet : pour préciser vos besoins auprès des entreprises consultées, pour analyser les devis et choisir vos entreprises, pour vérifier que les recommandations de mise en œuvre sont bien respectées en cours de chantier.</p>
						
						<p>N\'hésitez pas échanger sur ce document avec les professionnels qui vous accompagnent dans votre projet de travaux (conseiller France Renov’, entreprises…).
						
						Conservez-le dans votre carnet d’information du logement. Il vous sera utile lors des étapes de travaux ultérieures, et il fera foi en cas de vente de votre logement en le valorisant auprès des futurs acquéreurs.</p>

				</div>';
		return $html;
	}
	

	
	private function htmlPropParcour($data){
		$html = '';
		

		$html .= '<div class="titreSection">Votre parcours travaux par étapes</div></div>';
		
		$html .= '<div class="blocTexte" >
					<div class="titre2">Les règles d’or de la rénovation performante par étapes</div>
					<ul class="listNum">
						<li><b>Traiter l’isolation <u>et la ventilation</u> dès la première phase</b> des travaux.</li>
						<li><b>Organiser ses travaux en deux étapes si possible</b> (et trois au maximum) car la multiplication des étapes engendre une perte de performance énergétique. </li>
						<li><b>Être particulièrement vigilant à la manière dont les travaux se combinent</b> entre eux et s’assurer que les entreprises en tiennent compte.</li>
						<li><b>Choisir des matériaux et équipements suffisamment qualitatifs</b> pour éviter d’avoir à y revenir et s’assurer de leur mise en œuvre dans les règles de l’art.</li>
					</ul>
					Et bien sûr, traiter en priorité les autres problématiques qui peuvent affecter votre logement : sécurité électrique, traitement de l’amiante et du plomb, renforcement structurel, remontées capillaires...
				</div>';

		

		$aEtapes = $data['parcour']['etapes'];	
		
		$maxEtape = $this->getMaxEtape();
		
		$classeDpeInit = $data['cth']['etape'][0]['consoTotal']['classeDpe'];	
		$htmlLabelBBC = $this->htmlPropLabelBBC($data);
		
		$html .= '<table class="tabParcour pbrNone"><tbody>';
		
		$line = 0;
		//$aEtapes[3] = $aEtapes[2];$maxEtape = 3; $data['cth']['etape'][3] = $data['cth']['etape'][2];	
		foreach ($aEtapes as $iEtap=>$infoEtape){	
			if ($iEtap !=0 AND $iEtap !='nonTraite'){
				$bLigneImpair = !! ($line % 2);
				$bDernLigne = ($iEtap == $maxEtape);
				
				$sFirstLine = '';
				if ($line == 0) {
					$sFirstLine .= ' firstLine';
				}				
				if ($iEtap == $maxEtape) {
					$sFirstLine .= ' lastLine';
				}
				
				$classeDpe = $data['cth']['etape'][$iEtap]['consoTotal']['classeDpe'];	
				
				if ($bDernLigne) {			
					$imgChemDroit = 'img/cheminFleche.svg';
				} else {
					$imgChemDroit = 'img/imgCheminCentre.svg';
				}	
				
				//on fait un z-index inverse pour éviter le chevauchement de la ligne du dessous sur celle du dessus (logo label effinergie)
				$style= 'style="z-index:'.($maxEtape - $iEtap).'"';
				
				$html .='<tr class="'.($bLigneImpair ? 'linePair' : 'lineImpair').' '.$sFirstLine.'" '.$style.'>'.							
							$this->htmlPropParcourCellChemin($line,$bDernLigne,$classeDpeInit,$classeDpe,$htmlLabelBBC,'G').
							'<td class="parcourCellCentre">'.
								'<img class="imgCheminCentre" src="'.$this->htmlImg($imgChemDroit).'">'.
								'<div class="blocParcourEtape bordColor'.$this->getNumColorEtape($iEtap).'" >'.
									'<div class="parcourTitre color'.$this->getNumColorEtape($iEtap).'">Etape '.$iEtap.'</div>'.
									$this->htmlPropParcourTravauxEtape($iEtap,$infoEtape).
								'</div>'.
								
							'</td>'.
							$this->htmlPropParcourCellChemin($line,$bDernLigne,$classeDpeInit,$classeDpe,$htmlLabelBBC,'D').
						'</tr>';
				$line++;
			}
			
		}		
		$html .= '</tbody></table>';
		
		$html .= '<div class="parcourLegend">* Le label peut être délivré par un organisme certificateur sous réserve de respecter les critères du label.</div>';
		return $html;
	}
	
	private function htmlPropParcourTravauxEtape($nEtap,$infoEtape){
		$html='';
		foreach ($infoEtape['lstLot'] as $nomLot=>$infoLot){
			$html.='<li>'.
					'<a href="#detailEtap'.$nEtap.'Lot'.$nomLot.'" class="lienPage" >'.
						$this->getValChamp('lstLot',$nomLot).' (P.<div class="numPageWrite">%%pagewriteEtap'.$nEtap.'Lot'.$nomLot.'%%)</div>'.
					'</a>'.
					'</li>';
		}
		
		
		
		if ($html){
			$html='&nbsp;&nbsp;<b>Lots Traités :</b><ul class="parcourLots">'.$html.'</ul>';
		}
		
		$html.= $this->htmlPropParcourInterGlobale($nEtap,$infoEtape);
		
		return $html;
	}
	
	private function htmlPropParcourMiniEtiquetteDPE($classeDpe){
		return	'<div class="classeDPE">'.
					'<img src="'.$this->htmlImg('img/miniEtiquette/miniEtiquette'.$classeDpe.'.png').'">'.
				'</div>';
	}
	
	protected function htmlPropParcourInterGlobale($nEtap,$infoEtape){	
		if (!isset($infoEtape['lstInteracGlob'])) return '';
		$lstLotInter = $infoEtape['lstInteracGlob'];
	
		$maxLev = 0;
		
		foreach ($lstLotInter as $libInter =>$lstInter){			
			$htmlLot = '';
			foreach ($lstInter as $i=>$detailInter){	
				$risqNiv=str_replace('risqNiv','',$detailInter['niveau']);
				$maxLev = max($maxLev, intval($risqNiv));
			}
		}
		if ($maxLev){
			$text = '<b>Points de vigilance<br>entre-étapes</b> (P.<div class="numPageWrite">%%pagewriteInter'.$nEtap.'%%)</div>';
			$text = '<a class="lienPage interGlob" href="#interGlob'.$nEtap.'">'.$text.'</a>';
			return $this->htmlIconAndText($this->htmlImgWarning($maxLev),$text,'interGlob');
		}
		return '';
	}	
	
	private function htmlPropLabelBBC($data){
		$txt = '<a href="#critereLabel" class="lienPage">'.
				'<b>Critères</b> (P.'.
					'<div class="numPageWrite">%%pagewritecritereLabel%%)</div>'.
				'</a>';
		$htmlRespCrit = '<div class="parcourRespCrit">'.
							$this->htmlIconAndText($this->htmlImgWarning(3),$txt).
						'</div>';
		
		$res = [
			'premier'=>'<img src="'.$this->htmlImg('img/bbcEffinergieReno1er.png').'">*',
			'final'=>'<img src="'.$this->htmlImg('img/bbcEffinergieReno.png').'">*',			
		];
		
		$bRespectCritB2C2 = $this->testeRespectCriteresB2C2($data);
		
		if (!$bRespectCritB2C2['premier']){
			$res['premier'] .= $htmlRespCrit;
		}
		
		if (!$bRespectCritB2C2['final']){
			$res['final'] .= $htmlRespCrit;
		}
		
		$res['premier'] = '<div class="labelBBC">'.$res['premier'].'</div>';
		$res['final'] = '<div class="labelBBC">'.$res['final'].'</div>';
		
		return $res;
	}
	
	private function htmlPropParcourCellChemin($line,$bDernLigne,$classeDpeInit,$classeDpe,$htmlLabelBBC,$cote){
		$bLigneImpair = !! ($line % 2);
	
		$labelBBC = '';
		$etiquette ='';
		$img = 'img/cheminTurn.svg';

		if (($bLigneImpair AND $cote == 'G') OR (!$bLigneImpair AND $cote == 'D')){
			$etiquette = $this->htmlPropParcourMiniEtiquetteDPE($classeDpe);
			if ($bDernLigne ) {
				$img = '';
				$labelBBC = $htmlLabelBBC['final'];
			}
		}
		
		if ($line===0 AND $cote == 'G') {
			$etiquette = $this->htmlPropParcourMiniEtiquetteDPE($classeDpeInit);
			$img = 'img/cheminDroit.svg';
		}

		if ($line===0  AND $cote == 'D' AND !$bDernLigne) {
			$labelBBC = $htmlLabelBBC['premier'];	
		}
		
		if ($cote == 'G'){
			$flip = ( $bLigneImpair ) ? '' : 'flipY' ;				
		} else {
			$flip = ( $bLigneImpair ) ? 'flipXY' : 'flipX';
		}
		
		
		$htmlCellTxt = '<td class="parcourCellTxt cell'.$cote.'">'.
							$labelBBC.
							$etiquette.
						'</td>';
	
		$htmlCellChem = '<td class="parcourCellChem">'.
							( $img ? '<img class="imgChemin '.$flip.'" src="'.$this->htmlImg($img).'">' : '').
						'</td>';

		if ($cote == 'G'){
			return $htmlCellTxt.$htmlCellChem;
		} else {
			return $htmlCellChem.$htmlCellTxt;
		}
				
	}
	
	/**************************************************
	*  Detail Etapes
	***************************************************/
	
	private function htmlPropLstDetailEtape($data){
		$html ='';
		$aEtapes = $data['parcour']['etapes'];
		foreach ($aEtapes as $iEtap => $etape){
			if ($iEtap !=0 AND $iEtap !='nonTraite'){
				$html .= $this->htmlPropDetailEtape($etape,$iEtap);
			}
		}
		
		return $html;		
	}
	
	
	private function getNumColorEtape($iEtap){
		return (($iEtap-1)%3)+1;
	}
	private function htmlPropTitreEtape($iEtap){
		return '<div class="titreSection color'.$this->getNumColorEtape($iEtap).'">Etape n°'.$iEtap.'</div>';	
	}
	
	private function htmlPropDetailEtape($etape,$iEtap){
		$html ='';
		$html .= '<div class="detailEtape">';				
		$html .= $this->htmlPropTitreEtape($iEtap);						
		$html .= $this->htmlPropIntroEtape($iEtap);		
		$html .= $this->htmlPropDetailEtapeLstLot($etape,$iEtap);
		$html .= $this->htmlPropInterGlobale($etape,$iEtap);
		
		$html .= '</div>';
		
		return $html;
	}
	
	
	private function htmlPropIntroEtape($iEtap){
		if ($iEtap==1){
			$html ='Les lots indiqués dans cette partie seront traités en première étape de travaux. Vous trouvez ici des recommandations techniques de mise en œuvre et les traitements à prévoir pour gérer les interfaces au sein de cette étape de travaux. Les éléments indiqués peuvent vous permettre de faire des demandes aux entreprises que vous allez consulter.';
		} else {
			$html ='Les lots indiqués dans cette partie seront traités en '.$iEtap.'ième étape de travaux.';
		}
		return '<div class="introEtape" >'.$html.'</div>';
	}
	
	protected function htmlPropDetailEtapeLstLot($etape,$iEtap){
		$aLot = $etape['lstLot'];
		$html = '';		
	
		foreach ($aLot as $nomLot=>$aTypeLot ){	
			$html.= '<div class="detailLot nEtape'.$this->getNumColorEtape($iEtap).'">'.						
						'<a name="detailEtap'.$iEtap.'Lot'.$nomLot.'">'.
							'<div class="numPageRead">%%pagereadEtap'.$iEtap.'Lot'.$nomLot.'%%</div>'.
						'</a>'.
						$this->htmlPropTitreLot($nomLot).
				
						$this->htmlPropDetailEtapeLot($nomLot,$aTypeLot).

						$this->htmlPropInterEtape($nomLot,$etape,$iEtap).						
					'</div>';		
						
		}	
		
		return $html;
	}
	
	protected function htmlPropPictoLot($nomLot){
		return '<img class="pictoLot" src="'.$this->htmlImg('img/pictoLot/'.$nomLot.'.png').'">';
	}
	
	protected function htmlPropTitreLot($nomLot){
		return  '<div class="titreLot">'.
					$this->htmlPropPictoLot($nomLot).
					$this->getValChamp('lstLot',$nomLot).
				'</div>';
	}	
	
	protected function htmlPropDetailEtapeLot($nomLot,$aTypeLot){
		$html = '';		
		
		$html .= '<tr class="lgn entete">' .
			$this->htmlFdrChampEtape('descTrav titreTab','Description des travaux').
			$this->htmlFdrChampEtape('recom titreTab','Recommandations').
		"</tr>";

		
		$derniereLigne = "";
		for ($iType = 0 ; $iType < count($aTypeLot) ; $iType++){
			$typeLot = $aTypeLot[$iType];
			$ligne = '';
						
			//si pas colonne perfFinale alors on elargi la colone précédente.
		
			$txtPref='';
			if (!empty($typeLot['perfFinale'])){				
				$txtPref = $typeLot['perfFinale'];
				if (isset($typeLot['perfFinaleErr'])){
					$txtPref .= '<br>*<span class="perfErr"*>'.$typeLot['perfFinaleErr'].'*</span*>';
				}
			}
						
			//description des travaux
			$descTrav = '<b>' . $typeLot['designLot'] . ' :</b><br>';							
			
			$descTrav .=  $typeLot['descTrav'];
			if ($txtPref){
				$descTrav .= '<br>- <b>'.$txtPref.'</b>';
			}
			
			$ligne .= $this->htmlFdrChampEtape('descTrav',$descTrav);
		
			$ligne .= $this->htmlFdrChampEtape('recom',$typeLot['recom']);
			
			if ($derniereLigne != $ligne){
				$derniereLigne = $ligne;
				$html .='<tr class="lgn" nobr="true">'.$ligne."</tr>";			
			}
		}
		
		
		
		return '<table class="tabFdr tabEtape"><tbody>'.$html."</tbody></table>";
	}
	
	/* interactions */	



	protected function htmlPropInterEtape($nomLot,$etape,$iEtap){	
		if (!isset($etape['lstInteracEtape'])) return '';
		$lstLotInter = $etape['lstInteracEtape'];
		
		$htmlTraitement = '';		
		$htmlInterfaces = '';		

		//html += this.htmlFdrEnteteEtape(true);
		foreach ($lstLotInter as $sLotsInter =>$lstInter){
			if (strpos($sLotsInter,$nomLot)!==false){			
				
				$htmlLot = '';
				foreach ($lstInter as $i=>$detailInter){					
					$htmlLigne = '';

					
					$risque = $detailInter['risque'];
					$traitement = 	trim($detailInter['solution']);
														
					$htmlLigne.= 	'<td class="col risques ">'.$risque.'</td>'.
									'<td class="col traitement">'.$traitement.'</td>';
					$htmlLot.=  '<tr nobr="true">'.$htmlLigne.'</tr>';
				}
				
				
				if ($htmlLot){					
					$nomLotInter = trim(str_replace(['/',$nomLot],'',$sLotsInter));
					if (!$nomLotInter){
						$htmlTraitement .= 	'<table style="page-break-inside:avoid;"><tbody>'.
												'<td>'.
													'<table class="tabFdr tabInter"><tbody>'.
														'<tr class="entete"><td class="col lots titreTab" colspan="2">Traitements à prévoir</td></tr>'.
														$htmlLot.
													'</tbody></table>'.								
												'</td></tr>'.
											'</tbody></table>';
					} else {
						$htmlInterfaces .= 	'<table style="page-break-inside:avoid;"><tbody>'.
												'<td>'.
													'<table class="tabFdr tabInter"><tbody>'.
														'<tr class="entete"><td class="col lots titreTab" colspan="2">
															Interfaces entre '.$this->htmlPropPictoLot($nomLot ).$this->getValChamp('lstLot',$nomLot).
															' et '.$this->htmlPropPictoLot($nomLotInter ).$this->getValChamp('lstLot',$nomLotInter).
														'</td></tr>'.
														$htmlLot.
													'</tbody></table>'.								
												'</td></tr>'.
											'</tbody></table>';
					}
				}
			}
		}
				
		$titreTab =   "Traitement des interactions inter-etape";		

		$html = '';
		$html .= $htmlTraitement;
		
		if ($htmlInterfaces){						
			$html .= '<div class="introEtape" >'.
						'Au sein d’une même étape de travaux, il est essentiel d’être vigilant sur les interfaces entre les lots traités afin d’éviter les ponts thermiques et d’assurer une bonne étanchéité à l’air.'.
					'</div>'.
						$htmlInterfaces;
		}
		
		return $html;
		
	}
	
	protected function htmlPropInterGlobale($etape,$iEtap){		
		$html = '';
		
		$htmlInterGlobale = $this->htmlInterGlobale($etape,$iEtap);	
		
		if ($htmlInterGlobale){	
			$html .='<div class="titreSection pbrb color'.$this->getNumColorEtape($iEtap).'">'.		
						'<a name="interGlob'.$iEtap.'">'.
							'Les points de vigilance entre deux étapes de travaux'.							
						'</a>'.
					'</div>'.	
					'<div class="numPageRead">%%pagereadInter'.$iEtap.'%%</div>'. 
					'<div class="introEtape" >'.
						'Dans le cas d’une rénovation par étapes, certains travaux ne sont pas traités simultanément et des points de vigilance sont 
						à identifier et à traiter. Votre parcours travaux peut présenter 
						des interactions entre deux étapes de travaux (entre un lot traité à l’étape n°'.$iEtap.' et un lot qui sera traité ultérieurement), mais 
						également au sein d’une même étape.'.
					'<div class="introEtape" ><br>'.
					'</div>'.
						'Certains lots de travaux présentent des risques s’ils sont traités dans deux étapes séparées. Cela pourrait 
						créer des risques majeurs, des pathologies ou des surcoûts importants.'.
					'</div>
					<div class="nEtape'.$iEtap.'">'.
					$htmlInterGlobale.
					'</div>';
		}
		
		return $html;
	}
	
	/*critères B2C2*/
	
	protected function htmlPropRespectCriteresB2C2($data){
		$html= '';
		
		$html .='<div class="titreSection" >'.
					'Respect des critères BBC par étapes'.
				'</div>'.
				'<a name="critereLabel">'.
					'<div class="numPageRead">%%pagereadcritereLabel%%</div>'. 
				'</a>'.
				'<div class=""><b>Vous souhaitez faire labelliser votre projet de rénovation, suivez la check-list.</b></div>'.				
				
				'<div class="introEtape" >'.
					'Dans le cadre de votre  projet de rénovation, vous pourriez envisager de faire une demande de labellisation BBC Rénovation 2024 ou BBC Rénovation première étape. Celle-ci sera envisageable à condition que l’ensemble des exigences listées ci-dessous soient respectées'.
				'</div>';
				
		$html .= $this->htmlFdrRespectCriteresB2C2($data);

		return $html;
	}
	
	
	
	
}


?>