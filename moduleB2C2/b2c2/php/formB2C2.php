<?php
require_once 'baseB2C2.php';

class FormB2C2 extends baseB2C2{
	function afficheForm($projId){	
		//$this->afficheMenu();
				
		if ($projId){
			//pour le chargement d'un projet
			$this->afficheDonneeProjet($projId);
		}
			//echo '<pre>'; print_r($this->getListesValeurs());die;
			//echo '<pre>'; print_r($this->getLogigrame('mur'));die;
		?>
			<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/b2c2.css?<?php echo date('Ymd_His'); ?>" type="text/css" />
	
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
				<div class="intro">Veuillez définir l'état existant : </div>						
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
				<div class="intro">Veuillez saisir les données générales : </div>	
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
				<div class="intro">Veillez saisir les données du bâtiment : </div>	
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
				<div class="contenuFeuille">
					
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
			//$this->afficheMenu();
			?>
				<link rel="stylesheet" href="<?php echo CMSEffiUtils::getCMSUriProjBase(); ?>css/b2c2.css?<?php echo date('Ymd_His'); ?>" type="text/css" />
			<?php
			
			require_once __DIR__.'/apiSolB2C2.php';
//echo '<pre>';print_r($_SERVER);die;
			$apiSolB2C2= new ApiSolB2C2();
			$res = $apiSolB2C2->getListeProjetUtilisateur();
			//echo '<pre>';print_r($res);die;
			$html = '';
			if ($res){
				foreach($res as $projet){
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
				$html = '<div class="listeProjet">'.$html.'</div>';
			}
			
			echo $html;
			//print_r($res);die;
		}
		
		/*function afficheMenu(){
			echo '<div class="menuProjetsB2C2">';
			echo '<a href="'.$this->getCurrentUrl().'">Nouveau Projet</a>';
			echo '<a href="'.$this->getCurrentUrl().'?listeProjet=1">Liste des Projets</a>';
			echo '</div>';
		}*/
		
		function getCurrentUrl(){
			return explode('?',$_SERVER['REQUEST_URI'])[0];
		}
}
?>