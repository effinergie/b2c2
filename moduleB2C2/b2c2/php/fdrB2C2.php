<?php
require_once 'apiSolB2C2.php';

class FdrB2C2 extends ApiSolB2C2{
	protected $bGeneratePDF = false;
	
	/*************************************
	* PDF
	**************************************/		
	public function createPDF($projId){		
		$data = $this->chargeDonneeProjet($projId);
		if (!$data || !$data['dataFdr']){
			echo "Erreur de chargement du projet";
			die;
		}		
		$dataFdr = $data['dataFdr'];
		
		
		//attention TCPDF ne prends pas en compte toutes les normes html et CSS, loin de là...
		$this->bGeneratePDF = true;
		$htmlFdr = $this->cssPDF();
		$htmlFdr .= $this->htmlFdr($dataFdr);
		//echo $htmlFdr ;die;

		
		require_once 'tcpdfB2C2.php';
		
		$pdf = new TcpdfB2C2(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->SetFont('dejavusans', '', 10);
		$pdf->setProject_data([
					'projName'=>$dataFdr['desc']['general']['nomProjet'],
					'version'=>$this::VERSION
					]);
		$pdf->AddPage();
		
		$pdf->WriteHtml($htmlFdr);			
		$pdf->Output('test.pdf', 'I');
		
		die;	
	}
	
	private function cssPDF(){
		return '<style type="text/css">
				
				*{
					font-size:9px;
				}
				
				table.tabFdr{
					font-size:9px;	
					padding:5px;					
				}
				
				.col{
					border:1px solid #ccc;
					
				}
				
				.titreTab{
					line-height:10px;
					text-align:center;
					background-color:#aaa;					
				}
				
				.titreFdr{
					text-align:center;
					background-color:#a97b50;;
					line-height:30px;
					border:1px solid #555;	
					font-weight:bold;
				}
				
				
				.divImgConformite{
					width:40;
				}
				.imgConformite{
					width:30px;
				}
				
				.imgMPR{
					width:20px;
				}
				
				.imgDpe{
					heigth:200px;
				}
				
				.graphLegend{
					font-size:7px;
					font-style: italic;
				}
				

				
				.dpeGes,
				.dpeCep{
					text-align:center;
				}
				
				</style>';
		
				
	}
	
	public function htmlFdr($data){		
		$html = '';
		
		if ($this->bGeneratePDF){			
			$html .= '<img src="'.$this->htmlImg('../../../../images/banners/bandeauHeaderB2C2_2.jpg').'"><br><br><br>';
		}
		
		$html .= $this->htmlFdrDescription($data);
		$html .= $this->htmlFdrListeEtape($data);
		$html .= $this->htmlRecapCriteres($data);
		
		return $html;
	}
	
	public function htmlRecapCriteres($data){
		$html = '';
		
		$tabCrit ='';
		$tabCrit.= $this->htmlRecapMaPrimeRenov($data);
		$tabCrit.= $this->htmlRecapCeeBar164($data);

		$html = 
		'<div class="blocFdr" pagebreak="true">'.
			'<div class="titreFdr">Récapitulatifs des critères d\'aides</div>'.
			'<div class="blocEtape blocCadre">'.						
				$tabCrit.										
			'</div>'.
		'</div>';
		return $html;
	}
	
	public function htmlRecapMaPrimeRenov($data){
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
							$val = '<img class="imgMPR" src="'.$this->htmlImg($pathImg.'OK.png').'">Oui';
							break;
						case 'non':
							$val = '<img class="imgMPR" src="'.$this->htmlImg($pathImg.'warning.png').'">Non';
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
						'</table>';			
		}
		
		return $tabCrit;
	}
	
	public function htmlRecapCeeBar164($data){
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
							$val = '<img class="imgMPR" src="'.$this->htmlImg($pathImg.'OK.png').'">Oui';
							break;
						case 'non':
							$val = '<img class="imgMPR" src="'.$this->htmlImg($pathImg.'warning.png').'">Non';
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
			$tabCrit = '<table class="tabFdr tabmaPrimeRenov" pagebreak="true">'.
						'<tr class="entete">'.$this->htmlFdrChampEtape('titreTab','<b>Respect des critères de la fiche BAR TH 164 & Acte A4 du SARE</b>','colspan="3"').'</tr>'.
						$tabCrit.
						'</table>';			
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
		$html .= '</tbody></table>';
		
		
		$html .= $this->htmlFdrConsommation($data);
		
		$html = $this->htmlTitreFdr('Données Générales',true).
				$this->htmlDivTab($this->htmlNewLine().$html,'class="blocDesc blocCadre"');		
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
				$aCep[] = round($etape['consoTotal']['cep']);
				$aGes[] = round($etape['consoTotal']['ges']);
			}
			$imgCep=$this->getImageDpeENRMulti($aCep,'ENR');
			$imgGes=$this->getImageDpeENRMulti($aGes,'CO2');
							
			/*if ($nEtap>0){
				$htmlConso .= 	'<tr><td class="col colFleche">'.
									'<div class="fleche">Etape n°'.$nEtap.'</div>'.											
								'</td></tr>';
			}*/
			$htmlConso .= '<tr><td class="col">'.
							'<table class="contDpe"><tr>'.
								'<td class="dpeCep">'.
									'<img class="imgDpe" src="'.$this->htmlImg($imgCep,true).'">'.'<br>'.
									'<div class="graphLegend">(en kWhep\n/m².an)</div>'.
									//"CEP : ".number_format($cep,2).
								'</td>'.
								'<td class="dpeGes">'.
									'<img class="imgDpe" src="'.$this->htmlImg($imgGes,true).'">'.'<br>'.
									'<div class="graphLegend">(en kgeqCO2\n/m².an)</div>'.
									//"GES : ".number_format($ges,2).
								'</td>'.
							'</tr></table>'.
						'</td></tr>';
			$nbEtap++;
		
			
			if ($htmlConso){
				//$html .= '<br pagebreak="true"/>';
				$html .='<table class="tabFdr tabConso"><tbody>';
				$html .='<tr class="entete"><td class="col titreTab" >Evolution des consommations</td></tr>';
				//html+='<tr class="entete" >'+htmlEntete+'</tr>';
				$html .=$htmlConso;
				$html .='</tbody></table>';
			}
		}
		
		return $html;
	}	
	
	/*
	protected function htmlFdrConsommation_old($data){
		$html = '';
		$htmlConso = '';
		//Consommation
		if (isset($data['cth']['etape'])){
			$htmlConso = '';
			//let htmlEntete = '';
			$nbEtap =0;
			foreach ($data['cth']['etape'] as $nEtap=> $etape){
				$cep = $etape['consoTotal']['cep'];
				$ges = $etape['consoTotal']['ges'];

				$imgCep=$this->getImageDpeENR(round($cep));
				$imgGes=$this->getImageDpeGES(round($ges));
								
				if ($nEtap>0){
					$htmlConso .= 	'<tr><td class="col colFleche">'.
										'<div class="fleche">Etape n°'.$nEtap.'</div>'.											
									'</td></tr>';
				}
				$htmlConso .= '<tr><td class="col">'.
								'<table class="contDpe"><tr>'.
									'<td class="dpeCep">'.
										'<img class="imgDpe" src="'.$this->htmlImg($imgCep,true).'">'.'<br>'.
										
										"CEP : ".number_format($cep,2).
									'</td>'.
									'<td class="dpeGes">'.
										'<img class="imgDpe" src="'.$this->htmlImg($imgGes,true).'">'.'<br>'.
										"GES : ".number_format($ges,2).
									'</td>'.
								'</tr></table>'.
							'</td></tr>';
				$nbEtap++;
			}
			
			if ($htmlConso){
				$html .= '<br pagebreak="true"/>';
				$html .='<table class="tabFdr tabConso"><tbody>';
				$html .='<tr class="entete"><td class="col titreTab" >Evolution des consommations</td></tr>';
				//html+='<tr class="entete" >'+htmlEntete+'</tr>';
				$html .=$htmlConso;
				$html .='</tbody></table>';
			}
		}
		
		return $html;
	}*/
	
	protected function htmlFdrListeEtape($data){
		$parcour = $data['parcour'];
		$html = '';
		
		$aEtapes = $parcour['etapes'];
		
		foreach ($aEtapes as $iEtap=>$etape){
			$titreFdr = 'Etape n°'. $iEtap .' des travaux';
			if ($iEtap =='nonTraite'){
				$titreFdr = "Lots non taités";
			}
			
			$html .= '<div class="blocFdr" pagebreak="true">'.
						'<div class="titreFdr">'.$titreFdr.'</div>'.
						'<div class="blocEtape blocCadre">'.						
							$this->htmlFdrEtape($etape['lstLot']).
							(($iEtap == 1)? $this->htmlRespectCritere($etape['respectB2C2']) : '').
							(($iEtap == 1)? $this->htmlPermea($data) : '').
													
						'</div>'.
					'</div>';
				
			if ($iEtap !='nonTraite' AND isset($etape['lstInteraction'])){	
				$html .= '<div class="titreFdr" pagebreak="true">Interactions à l\'étape n°'.($iEtap).'</div>'.
						'<div class="blocEtape blocCadre">'.				
							$this->htmlInterEtape($etape['lstInteraction']).						
						'</div>';		
			}
		}
		return $html;
	}
	
    /** Liste travaux Lots **/
	
	protected function htmlFdrEtape($etape){
		$html = '';		
		
		$html .= $this->htmlFdrEnteteEtape(true);
		
		foreach ($etape as $nomLot=>$aTypeLot ){
			$html.= $this->htmlFdrTypeLot($nomLot,$aTypeLot['type'],true);				
		}	
		
		return '<table class="tabFdr"><tbody>'.$html."</tbody></table>";
	}
	
	protected function htmlFdrEnteteEtape($bPerf){
		$ligne = '';
		$ligne .= $this->htmlFdrChampEtape('nomLot titreTab','Lots');
		$ligne .= $this->htmlFdrChampEtape('existant titreTab','Existant');
		$ligne .= $this->htmlFdrChampEtape('descTrav titreTab','Description des travaux');
		if ($bPerf){
			$ligne .= $this->htmlFdrChampEtape('perfMin titreTab','Performance minimum');
		}			
		$ligne .= $this->htmlFdrChampEtape('recom titreTab','Recommandations');
		$ligne ='<tr class="lgn entete">'.$ligne."</tr>";
		return $ligne;
	}
	
	protected function htmlFdrTypeLot($nomLot,$aTypeLot,$bPerf){
		$html = '';
		
		for ($iType = 0 ; $iType < count($aTypeLot) ; $iType++){
			$typeLot = $aTypeLot[$iType];
			$ligne = '';
			if ($iType == 0 ){				
				$sNomLot = $this->getValChamp('lstLot',$nomLot);
				$ligne .= $this->htmlFdrChampEtape('nomLot',$sNomLot, 'style="vertical-align: middle;" rowspan="'.count($aTypeLot).'"');
			}	
			
			//si pas colonne perfMin alors on elargi la colone précédente.
			$colSpan = '';
			$htmlPerfMin = '';
			if ($bPerf && !empty($typeLot['perfMin'])){				
				$txtPref = $this->getTxtPerfMin($nomLot,$typeLot['perfMin']);
				$htmlPerfMin = $this->htmlFdrChampEtape('perfMin',$txtPref);
			} else {
				$colSpan = ' colspan="2" ';
			}				
			
			//colonne existant
			$txtExistant = '<b>' . $typeLot['designLot'] . ' :</b><br>' . $typeLot['existant'];
			$ligne .= $this->htmlFdrChampEtape('existant',$txtExistant);
			
			$ligne .= $this->htmlFdrChampEtapeListeVal('descTrav',$typeLot,$colSpan);
			$ligne .= $htmlPerfMin;
			$ligne .= $this->htmlFdrChampEtapeListeVal('recom',$typeLot);
			$html .='<tr class="lgn" nobr="true">'.$ligne."</tr>";
		}
		return $html;
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

	protected function htmlInterEtape($lstLotInter){	
		$html = '';		
		
		//html += this.htmlFdrEnteteEtape(true);
		foreach ($lstLotInter as $libInter =>$lstInter){
			$htmlLot = '';
			foreach ($lstInter as $i=>$detailIntet){			
				$htmlLigne = '';
				if (!$htmlLot){
					$htmlLigne .=	'<td rowspan="'.count($lstInter).'" class="col lots">'.$libInter.'</td>';
				}
				$htmlLigne.= 	'<td class="col risques">'.$detailIntet['risque'].'</td>'.
								'<td class="col traitement">'.$detailIntet['solution'].'</td>';
				$htmlLot.=  '<tr nobr="true">'.$htmlLigne.'</tr>';		
			}
			$html .= $htmlLot;
		}

		if ($html){						
			return 	'<table class="tabFdr tabInter"><tbody>'.
						'<tr class="entete"><td class="col titreTab" colspan="3">Traitement des interactions</td></tr>'.
						'<tr class="entete"><td class="col lots titreTab">Lots concernés</td><td class="col risques titreTab">Risques</td><td class="col traitement titreTab">Traitements à prévoir</td></tr>'.
						$html.
					"</tbody></table>";
		} else {
			return '';
		}
	}

	/**Respect des critères B2c2 **/
	protected function htmlRespectCritere($aRespect){
		$html = '';
		$html .= '<tr class="entete"><td class="col titreTab">Conformité</td></tr>';
		
		$pathImg = 'img/';
		if (in_array('conformeB2C2',$aRespect)){
			$img = '<img class="imgConformite" src="'.$this->htmlImg($pathImg.'OK.png').'">';
		} else {
			$img = '<img class="imgConformite" src="'.$this->htmlImg($pathImg.'warning.png').'">';
		}
		$conforme ='<table><tr><td width="40" calss="divImgConformite">'.$img .'</td><td>'.$this->getListeValChamp('remarqueSolution',$aRespect).'</td></tr></table>';
		$html .= '<tr><td class="col">'.$conforme.'</td></tr>';
		$html = '<table class="tabFdr conformite"><tbody>'.$html.'</tbody></table>'; 
		return $html;
	}

	/**etancheite à l'air **/
	protected function htmlPermea($data){
		$html = '';
		$html .= '<tr class="entete"><td class="col titreTab">Etanchéité à l\'air</td></tr>';
		$txtPermea = $this->getValChamp('remarqueSolution',$data['permea']);		
		$html .= '<tr><td class="col">'.$txtPermea.'</td></tr>';
		$html = '<table class="tabFdr permea"><tbody>'.$html.'</tbody></table>'; 
		return $html;
	}
	
	/*********************************************
	* Fonctions HTML
	**********************************************/	
	
	protected function htmlImg($src,$bDataSrc = false){
		if ($bDataSrc){
			if ($this->bGeneratePDF){
				$src = '@'.$src;
			} else {
				$src = 'data:image/png;base64,'.$src;
			}			
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
		//tcfpdf gère mal le div mais bien las tables
		
		if (strpos($complement,'class="')===false){
			$complement.=' class="divTabPdf"';
		} else {
			$complement =str_replace('class="','class="divTabPdf ',$complement);
		}
		
		return'<table '.$complement.'><tr><td>'.$content.'</td></tr></table>';
	}
	
	protected function htmlDiv($content,$complement=""){
		//tcfpdf gère mal le div mais bien las tables
		return'<div '.$complement.'>'.$content.'</div>';
	}
	
	protected function htmlNewLine(){
		//return '';
		return '<tcpdf method="newLine"></div>';
		//return '<div></div>';
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
	
	
	protected function getTxtPerfMin($nomLot,$valChamp){
		if ($nomLot=='menuiserie'){
			return 'Uw < '.$valChamp.' W/(m².K)';
		} else {
			return 'R > '.$valChamp.' m².K/W';
		}
	}
	
	
	/*********************************************
	* etiquettes DPE
	**********************************************/
	private function imagecreatefrompngalpha($path,$width=0,$height=0){
		// pareil que imagecreatefrompng mais conserve la transparence
		//list( $width, $height, $type ) = getimagesize( $path );
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
			//imagecolortransparent($targetImage, $alpha_channel); 
			// Fill image
			imagefill($targetImage, 0, 0, $alpha_channel); 
		}

		/*imagecopyresampled( $targetImage, $srcImage, 
                    0, 0, 
                    0, 0, 
                    $width, $height, 
                    $width, $height);*/
		imagecopy($targetImage, $srcImage, 
					0, 0, 
                    0, 0, 
					$srcWidth, $srcHeight);
		imagedestroy($srcImage);
		return $targetImage;
	}
	
	private function getImageDpeENR($val){
		//$aScale = array(50,90,150,230,330,450);
		$aScale = $this->getColTableau('dpeCep','valMax');
		array_pop($aScale);//on ne prends pas la valeur G car elle n'a pas de max
		
		$num = $this->getPosScale($val,$aScale);	
		$offsetY=$num*30;
		
		//image
		$imgDPE = $this->imagecreatefrompngalpha(__DIR__.'/../img/fondDPE_ENR.png');

		$imgCursor = $this->imagecreatefrompngalpha(__DIR__.'/../img/curseurDPE.png');
		imagecopy($imgDPE,$imgCursor,200,62+$offsetY,0,0,imagesx($imgCursor),imagesy($imgCursor));
		
		//texte 
		$textcolor = imagecolorallocate($imgDPE, 255, 255, 255);		
		imagettftext($imgDPE, 16, 0, 200+20, 62+$offsetY+21, $textcolor, __DIR__.'/../img/arial.ttf', intval($val));
		//imagettftext($imgDPE, 11, 0, 200+5, 62+$offsetY+50, 0, __DIR__.'/../img/arial.ttf', "kWhep\n/m².an");		

		ob_start();
		imagepng($imgDPE);
		$imagedata = ob_get_clean();
		
		imagedestroy($imgDPE);
		imagedestroy($imgCursor);
		
		return base64_encode($imagedata);
		//return '<img id="dpe_enr" src="data:image/png;base64,'.base64_encode($imagedata).'" alt="DPE">';
	}	
	/********************************************************/
	private function getImageDpeGES($val){
		//$aScale = array(5,10,20,35,55,80);
		$aScale = $this->getColTableau('dpeGes','valMax');
		array_pop($aScale);//on ne prends pas la valeur G car elle n'a pas de max
		
		$num = $this->getPosScale($val,$aScale);
		$offsetY=$num*30;
		
		$imgDPE = $this->imagecreatefrompngalpha(__DIR__.'/../img/fondDPE_CO2.png');
		$imgCursor = $this->imagecreatefrompngalpha(__DIR__.'/../img/curseurDPE.png');
		imagecopy($imgDPE,$imgCursor,200,62+$offsetY,0,0,imagesx($imgCursor),imagesy($imgCursor));
		
		//texte 
		$textcolor = imagecolorallocate($imgDPE, 255, 255, 255);		
		imagettftext($imgDPE, 16, 0, 200+20, 62+$offsetY+21, $textcolor, __DIR__.'/../img/arial.ttf', intval($val));
		//imagettftext($imgDPE, 11, 0, 200+5, 62+$offsetY+50, 0, __DIR__.'/img/../arial.ttf', "kgeqCO2\n/m².an");		
		
		ob_start();
		imagepng($imgDPE);
		$imagedata = ob_get_clean();
		
		imagedestroy($imgDPE);
		imagedestroy($imgCursor);
		
		return base64_encode($imagedata);
		//return '<img id="dpe_co2" src="data:image/png;base64,'.base64_encode($imagedata).'" alt="DPE">';
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
		
		//$aScale = array(50,90,150,230,330,450);
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
			imagettftext($imgDPE, 11, 0, 200+7+$offsetx, 60-5, $textcolor, __DIR__.'/../img/arial.ttf', $texteEtap );
			imagettftext($imgDPE, 16, 0, 200+20+$offsetx, 62+$offsetY+21, $consocolor, __DIR__.'/../img/arial.ttf', intval($val));
			//imagettftext($imgDPE, 11, 0, 200+5, 62+$offsetY+50, 0, __DIR__.'/../img/arial.ttf', "kWhep\n/m².an");		
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
		//return '<img id="dpe_enr" src="data:image/png;base64,'.base64_encode($imagedata).'" alt="DPE">';
	}	
	
	
}


?>