<?php
require_once 'baseB2C2.php';

class FormB2C2 extends baseB2C2{
	function afficheForm($projId){	
				
		if ($projId){
			//pour le chargement d'un projet
			$this->afficheDonneeProjet($projId);
		}
		?>
			<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/b2c2.css?<?php echo date('Ymd_His'); ?>" type="text/css" />			
			<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/fdr.css?<?php echo date('Ymd_His'); ?>" type="text/css" />
	
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/CMSEffiUtils.js?<?php echo date('Ymd_His'); ?>"></script>
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/apiSolB2C2.js?<?php echo date('Ymd_His'); ?>"></script>
			<script src="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>js/formB2C2.js?<?php echo date('Ymd_His'); ?>"></script>
			<script> let initVal= <?php echo json_encode($this->getInitVal()); ?> ;
					apiSolB2C2.initValues(initVal);
			</script>	
			
			<div class="b2c2Contexte">
			<?php
				$this->afficheDonneesGeneral();
				$this->afficheDonneesBatiment();
				$this->afficheSaisieLot('lotMur','Murs','mur');			
				$this->afficheSaisieLot('lotPlancherHaut','Plancher Haut','plancher haut');
				$this->afficheSaisieLot('lotPlancherBas','Plancher Bas','plancher Bas');
				$this->afficheSaisieLot('lotMenuiserie','Menuiseries','menuiserie');
				$this->afficheSaisieLot('lotEcs','Eau Chaude Sanitaire','eau chaude sanitaire');
				$this->afficheSaisieLot('lotChauffage','Chauffage','chauffage');							
				$this->afficheSaisieLot('lotVentilation','Ventilation','ventilation');	
				$this->afficheFeuilleDeRoute();
				echo '<div class="version">Version V'.baseB2C2::VERSION.'</div>';
			?>		
			</div>


		<?php
		
		}

		function afficheTirroir($id,$titre,$htmlContent,$class=''){
			?>
			<div class="lotContent <?php echo $class; ?>" id="<?php echo $id ; ?>">
				<div class="titreTiroir"> <?php echo $titre ; ?> </div>
				<div class="tiroirContent">
					<?php echo $htmlContent ;?>		
				</div>
			</div>
			<?php
		}

		function afficheSaisieLot($id,$titre, $nom){
			ob_start();
			?>						
				<div class="saisieContent"></div>		
				<div class="addType">				
					<button class='plus'>+</button>
					<button class='moins'>-</button>
					<div class="lib">Ajouter un type <?php 
						echo (stripos('aeiouy', $nom[0])===false) ? "de " : "d'";
						echo $nom ; 
					?></div>
				</div>	
				<div class="grpBouton">
					<button class='validExistant'>Valider</button>
					<button class='validSolution'>Valider les solutions</button>			
				</div>				
			<?php
			$html = ob_get_clean();
			$this->afficheTirroir($id,$titre,$html);
		}
		
		function afficheDonneesGeneral(){
			$id = "lotGeneral";
			$titre = "Données Générales";
			ob_start();
			?>
				<div class="saisieContent"></div>
				<div class="grpBouton">
					<button class='valid'>Valider</button>							
				</div>
			<?php
			$html = ob_get_clean();
			$this->afficheTirroir($id,$titre,$html,'lotGen');
		}
		
		function afficheDonneesBatiment(){
			$id = "lotBatiment";
			$titre = "Données Bâtiment";
			ob_start();
			?>
				<div class="saisieContent"></div>		
				<div class="grpBouton">
					<button class='valid'>Valider</button>		
				</div>
			<?php
			$html = ob_get_clean();
			$this->afficheTirroir($id,$titre,$html,'lotGen');
		}
		
		function afficheFeuilleDeRoute(){
			$id = "feuilleDeRoute";
			$titre = "Feuille de Route";
			ob_start();
			?>
				<div id="contenuFeuille">
					
				</div>
						
				<div class="grpBouton">
					<button class='enregistre'>Enregistrer</button>	
					<button class='imprimer'>Imprimer</button>								
				</div>
			<?php
			$html = ob_get_clean();
			$this->afficheTirroir($id,$titre,$html);
		}
		
		/************************************
		*
		*   Gestion de des projets
		*
		************************************/
		
		function afficheDonneeProjet($projID){
			require_once __DIR__.'/apiSolB2C2.php';
			$apiSolB2C2 = new ApiSolB2C2();
			$res = $apiSolB2C2->chargeDonneeProjet($projID);
			if ($res){
				$sData = htmlspecialchars(json_encode($res));
				echo '<div id="viewProj" data="'.$sData.'"></div>';
			}
		}
		
		function afficheListeProjetUtilisateur(){
			?>
				<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/b2c2.css?<?php echo date('Ymd_His'); ?>" type="text/css" />
			<?php
			
			require_once __DIR__.'/apiSolB2C2.php';
			$apiSolB2C2= new ApiSolB2C2();
			
			$page = isset($_GET['page']) ? $_GET['page'] : 0;
			$res = $apiSolB2C2->getListeProjetUtilisateur($page);
			$html = '';
			if ($res){
				foreach($res['projets'] as $projet){
					$html .='<div class="lgn">'
							.'<div class="col id">'.$projet['id'].'</div>'
							.'<div class="col nom">'
								.$projet['nom']
								.'<div class="dateModif">Modifié le '.$projet['dateModif'].'</div>'
							.'</div>'
							.'<div class="col dateModif">V '.$projet['version'].'</div>'
							.'<a class="col modif" title="Modifier le projet" href="'.CMSEffiUtils::getURLProjet().'?viewProj='.$projet['id'].'" ></a>'
							.'<a class="col copie" title="Dupliquer" href="'.$this->getCurrentUrl().'?copieProjet='.$projet['id'].'" onclick="if (!confirm(\'Voulez-vous dupliquer ce projet?\')) return false;"></a>'
							.'<a class="col delete" title="Supprimer le projet" href="'.$this->getCurrentUrl().'?deleteProj='.$projet['id'].'" onclick="if (!confirm(\'Voulez-vous supprimer ce projet?\')) return false;"></a>'
						.'</div>';
				}
			}
			
			if ($html){
				$html ='<div class="lgn entete">'
							.'<div class="col id"> </div>'
							.'<div class="col nom">Liste des projets enregistrés :</div>'
							.'<div class="col"> </div>'
							.'<div class="col"> </div>'
							.'<div class="col"> </div>'
							.'<div class="col"> </div>'
						.'</div>'.$html;	
			}
			
			$html .=$this->getHtmlPagination($res);
			
			echo '<div class="listeProjet">'.$html.'</div>';
		}
		
		
		function afficheListeProjetAdmin(){
			if (!CMSEffiUtils::userIsManagerB2C2()){
				echo "Vous n'avez pas les droits pour afficher cette page.";
				return ;
			}
			?>
				<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/b2c2.css?<?php echo date('Ymd_His'); ?>" type="text/css" />
			<?php
			
			require_once __DIR__.'/apiSolB2C2.php';
			$apiSolB2C2= new ApiSolB2C2();
			$page = isset($_GET['page']) ? $_GET['page'] : 0;
			$nomprojet = isset($_GET['keywords']) ? $_GET['keywords'] : '';
			$res = $apiSolB2C2->getListeProjetAdmin($nomprojet,$page);
			$html = '';
			
			$html .= '<form class="formRechProj" action="" method="get">'.
						'<input type="text" name ="keywords" value ="'.$nomprojet.'" placeholder="Recherche">'.
						'<input type="submit" value="Rechercher" class="btn" >'.
					'</form>';
			
			$htmlTab='';
			if ($res){
				foreach($res['projets'] as $projet){
					$htmlTab .='<div class="lgn">'
							.'<div class="col id">'.$projet['id'].'</div>'
							.'<div class="col nomuser">'
								.$projet['name'].'<br>'
								.$projet['email']
							.'</div>'
							.'<div class="col nom">'
								.$projet['nom']
								.'<div class="dateModif">Modifié le '.$projet['dateModif'].'</div>'
							.'</div>'
							.'<div class="col dateModif">V '.$projet['version'].'</div>'
							.'<a class="col modif" title="Modifier le projet" href="'.CMSEffiUtils::getURLProjet().'?viewProj='.$projet['id'].'" ></a>'
							
						.'</div>';
				}
			}
			
			
			
			if ($html){
				$html .='<div class="lgn entete">'
							.'<div class="col id"> </div>'
							.'<div class="col nom">Liste des projets enregistrés :</div>'
							.'<div class="col"> </div>'
							.'<div class="col"> </div>'
							.'<div class="col"> </div>'
						.'</div>'.$htmlTab
						;		
			}
			
			
			$html .=$this->getHtmlPagination($res);
			
			echo  '<div class="listeProjet">'.$html.'</div>';
		}	

		function getHtmlPagination($res){
			$html = '';
			$html.= 'Nombre de projets : '.$res['nbProjet'];
			$html.='<div class="pagination">';
			$html.='<a class="page" href="'.$this->getCurrentUrlParams(['page'=>1]).'">&lt;&lt;</a>';
			for ($iPage = 1 ; $iPage<=$res['nbPage'];$iPage ++){
				$html.='<a class="page" href="'.$this->getCurrentUrlParams(['page'=>$iPage]).'">'.($iPage).'</a>';
			}
			$html.='<a class="page" href="'.$this->getCurrentUrlParams(['page'=>$res['nbPage']]).'">&gt;&gt;</a>';
			$html.= '</div>';
			return $html;
		}
		
		
		function getCurrentUrl(){
			return explode('?',$_SERVER['REQUEST_URI'])[0];
		}
		
		function getCurrentUrlParams($paramsSup){
			$aUrl = explode('?',$_SERVER['REQUEST_URI']);
			$url = $aUrl;
			$params = [];
			if (isset($aUrl[1])){
				parse_str($aUrl[1],$params);
			} 
			$params = array_merge($params,$paramsSup);
			
			return 	$aUrl[0].'?'.http_build_query($params);		
		}		
}
?>