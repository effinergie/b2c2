"use strict";

var formB2C2 ={
	SAISIE:'SAISIE',
	CHOIX_SOL:'CHOIX_SOL',
	cptDebug:0,
	bDebug:false,
	
	init:function(){
		
		/*let s='';
		for (let nomLst in lstVal){
			let lst = lstVal[nomLst];
			s += '-'+nomLst+'\n';
			for (let id in lst){
				let val = lst[id];
				s += id+'\t'+val+'\n';
			}
		}
		console.log(s);*/
		
		
		this.initLot();

	
		//gestion de l'accordeon
	
		this.initTiroir();

		this.aJqLot = {};

		for (let nomLot in this.aInfoChampSaisie){
			this.aJqLot[nomLot] = jQuery('#lot'+this.ucfirst(nomLot));
			
			this.aJqLot[nomLot].find('.plus').on('click',()=>this.ajouteLotLigneSaisie(nomLot));
			this.aJqLot[nomLot].find('.moins').on('click',()=>this.supprimeLotLigneSaisie(nomLot));
			this.aJqLot[nomLot].find('.validExistant').on('click',()=>this.ouvreTiroirSuivant(nomLot));
			this.aJqLot[nomLot].find('.validSolution').on('click',()=>this.ouvreTiroirSuivant(nomLot));
			this.aJqTiroir[nomLot].fVerifTiroir = (nomLot)=> this.verifLot(nomLot);
			this.reinitLot(nomLot);
		}
		
		this.ajouteSaisieGeneral();
		this.ajouteSaisieBatiment();
		
		this.ouvreTiroir('general');
		
		this.initFeuilleDeRoute();
		
		//chargement des projets
		let valProj = jQuery('#viewProj').attr('data');
		if (valProj){
			this.chargeProjet(JSON.parse(valProj));
		}
		
		
		//mode débug : on clique 5 fois sur la version
		let jqCssDebug = jQuery("<style> .b2c2Contexte .debug{display:none !important; </style>");
		jQuery('head').append(jqCssDebug);
		
		let jQversion = jQuery(".b2c2Contexte .version");
		jQversion.on('click',()=>{
			this.cptDebug++;
			if (this.cptDebug>=5 && !this.bDebug){
				this.bDebug=true;
				//affichage des champs cachés
				jqCssDebug.remove();			
			}
		});
		//ajout de la zone de trace
		this.jqDebugTrace = jQuery('<div id="debugTrace" class="debug"></div>');
		jQversion.after(this.jqDebugTrace);			
	},

	
	initLot:function(){
		this.aInfoChampSaisie={	};
		this.aInfoChampSaisie['mur'] = [
			/*{
				id:'contact',
				lib:'Contact',
				type:'select',
				
			},*/
			{
				id:'strucMur',
				lib:'Matériau / structure',
				type:'select',				
			},
			/*{
				id:'finitExtMur',
				lib:'Finition extérieure',
				type:'select',
				options:lstVal.lstFinitExtMur
			},*/
			{
				id:'typeIsoMur',
				lib:'Type d\'isolation',
				type:'select',		
			},
			{
				id:'resistMur',
				lib:'Résistance',
				type:'select',
			},
			/*{
				id:'surfMur',
				lib:'Surface du mur (%)',
				type:'input',
			},
			{
				id:'pathologieMur',
				lib:'Pathologies',
				type:'select',
				options:lstVal.lstPathologieMur
			}*/
		];
		
		
		this.aInfoChampSaisie['plancherHaut'] = [
			{
				id:'contactPlancherHaut',
				lib:'Contact',
				type:'select',
			},	
			{
				id:'structurePlancherHaut',
				lib:'Structure',
				type:'select',
			},
			{
				id:'resistPlancherHaut',
				lib:'Résistance',
				type:'select',
			},

		];
		
		this.aInfoChampSaisie['plancherBas'] = [			
			{
				id:'typePlancherBas',
				lib:'Type',
				type:'select',
			},
			{
				id:'structurePlancherBas',
				lib:'Structure',
				type:'select',
			},
			{
				id:'resistPlancherBas',
				lib:'Résistance',
				type:'select',
			},

		];

		
		this.aInfoChampSaisie['menuiserie'] = [			
			{
				id:'perfMenuiserie',
				lib:'Performance',
				type:'select',
			},
			{
				id:'fenetreDeToit',
				type:'select',
			},
			{
				id:'protectionSolaireMenuiserie',
				lib:'Protection solaire',
				type:'select',
			},	
			{
				id:'surfMenuiserie',
				lib:'Surface',
				type:'input',	
				unite:'m²'				
			},			
			
		];	

		this.aInfoChampSaisie['ecs'] = [			
			/*{
				id:'chauffageLieEcs',
				lib:'Relié au sytème de chauffage',
				type:'select',
			},*/
			{			
				id:'typeEcs',
				lib:'Type d\'ECS',
				type:'select',
			},				
			{			
				id:'typeReseauEcs',
				lib:'Type de réseau',
				type:'select',
			},	
		
		],		

		this.aInfoChampSaisie['chauffage'] = [	
			/*{
				id:'ageChauffage',
				lib:'Age du système',
				type:'select',
			},*/	
			/*{ //déterminé par la solution ECS Choisie
				id:'chauffageLieEcs',
				lib:'Système relié à l\'ECS',
				type:'select',
			},*/		
			{
				id:'chauffageCollectif',
				type:'select',
			},	
			{
				id:'typeProdChauffage',
				lib:'Types de production',
				type:'select',
			},	
			{
				id:'typeEmmeteurChauffage',
				lib:'Types d\'émmeteurs',
				type:'select',
			},			
		

		];
		

		
		

		this.aInfoChampSaisie['ventilation'] = [						
			{
				id:'chauffageDependantVentilation',
				lib:'Système de chauffage',
				type:'select',
			},
			{
				id:'typeVentilation',
				lib:'Type',
				type:'select',
			},		
		]
	},
	
	
	/********************************************************
	* Saisie Général
	*********************************************************/	

	ajouteSaisieGeneral:function(){

		let aInfoChampsGen = [
			{
				id:'id',
				type:'input',
				disabled:true,
				classSup:'debug'
			},
			{
				id:'nomProjet',
				type:'input',				
			},
			{
				id:'departement',
				type:'select',				
			},	
			{
				id:'libTypo',
				type:'freetext',				
			},	
			{
				id:'genTypo',
				type:'select',
			},			
			{
				id:'typoBat',
				type:'select',				
			},			
			{
				id:'imgTypo',
				type:'freetext',
				lib:'...',
			},	
			{
				id:'choixEner',
				type:'select',				
			},	
		];

		formB2C2.aJqTiroir['general'].find('.saisieContent').append('<div class="typeLot">'+
						'<div class="ligneSaisie">'+
							'<div class="blocCadre">'+
								this.getHtmlListeChamp(aInfoChampsGen)+
							'</div>'+
						'</div>'+
						//'<div class="solutionListe"></div>'+
					'</div>');	
		
		this.aJqTiroir['general'].find('.saisieContent [name=departement]:input').on('change',()=>{
			this.modeSaisieTousLot();
		});
					
		this.aJqTiroir['general'].fValideTiroir = () => this.valideGeneral();
		this.aJqTiroir['general'].fVerifTiroir = () => this.verifGeneral();
		this.aJqTiroir['general'].find('button.valid').on('click',()=>this.ouvreTiroirSuivant('general'));
		this.aJqTiroir['general'].find('.saisieContent [name=genTypo]:input').on('change',()=>{
			this.updateGenTypo();
		});		
		this.aJqTiroir['general'].find('.saisieContent [name=typoBat]:input').on('change',()=>{
			this.updateImgTypo();
		});
		this.updateImgTypo();
		this.updateGenTypo();
	},
	
	valideGeneral:function(){
		let jData = this.getJsonTiroir('general');		
		if (jData['genTypo']=='oui'){			
			if (!this.bProjetChage){				
				this.genereTypologie();
				return false; //on ouvre pas le tirroir suivant
			} else {
				//if (confirm('Voulez-vous générer automatiquement les données du bâtiment et ecraser les valeurs déja saisies?')){
				dialogTool.message('Voulez-vous générer automatiquement les données du bâtiment et ecraser les valeurs déja saisies?',
					{
						btnList:['Oui','Non'],
						callback:(iBtn)=>{
								if (iBtn==0){
									this.genereTypologie();
								} else {
									this.ouvreTiroirSuivant('general',true);
								}
							},
					}
				);					
				return false; //on ouvre pas le tirroir suivant				
			}			
		} 
		return true;
	},
	
	verifGeneral:function(){
		jQuery('.aRemplir').removeClass('aRemplir');;
		let jData = this.getJsonTiroir('general');
		let bErreur=false;
		for (let nomChamp in jData){			
			let bTest = true;
			switch(nomChamp){
				case 'id':
					bTest = false;
					break;
				case 'typoBat':
				case 'choixEner':
					if (jData['genTypo']=='non'){
						bTest = false;
					}
					break;
			}
			if (bTest && !jData[nomChamp]){
				this.aJqTiroir['general'].find('.champLot.'+nomChamp).addClass('aRemplir');
				bErreur=true;
			}
		}
		
		if (bErreur){
			dialogTool.message('Veuillez renseigner toutes les valeurs.');
			return false;
		}		
		
		return true;
	},
	
	
	updateImgTypo:function(){	
		let typoBat = this.aJqTiroir['general'].find('.saisieContent [name=typoBat]:input').val();
		let jqImgTypo = this.aJqTiroir['general'].find('.saisieContent .champLot.imgTypo');
		
		let lstImg = apiSolB2C2.imgTypoBat[typoBat];
		
		if (!lstImg){
			typoBat = 'default';
			lstImg = ['default.jpg'];
		}
		
		let htmlImg = '';
		for (let i = 0 ; i < lstImg.length ; i++){	
			let nomImg = lstImg[i];
			htmlImg += '<div class="imgContent"><img src="'+apiSolB2C2.URI_PROJ_BASE+'img/imgTypo/'+typoBat+'/'+nomImg+'"></div>';
		}
		
		jqImgTypo.html('<div class="lib"></div><div class="grpImgTypo">'+htmlImg+'</div>');
	},
	
	updateGenTypo:function(){		
		let jqGenTypo = formB2C2.aJqTiroir['general'].find('.saisieContent [name=genTypo]:input');
		let jqTypoBat = formB2C2.aJqTiroir['general'].find('.saisieContent .champLot.typoBat');
		let jqChoixEner = formB2C2.aJqTiroir['general'].find('.saisieContent .champLot.choixEner');
		let jqImgTypo = formB2C2.aJqTiroir['general'].find('.saisieContent .champLot.imgTypo');
		
		if (!jqGenTypo.val()){
			jqGenTypo.val('non');
		}
		
		if (jqGenTypo.val() == 'oui'){
			jqTypoBat.show(500);
			jqChoixEner.show(500);
			jqImgTypo.show(500);
		} else {
			jqTypoBat.hide(500);
			jqChoixEner.hide(500);
			jqImgTypo.hide(500);
		}
	},
	
	/********************************************************
	* Saisie Batiment
	*********************************************************/	
	
	ajouteSaisieBatiment:function(){

		let aInfoChampsBat = [		
			{
				id:'typeBat',
				type:'select'				
			},	
			{
				id:'anneeConstr',
				type:'select'				
			},	
			{
				id:'nbNiveau',
				type:'input'
			},
			{
				id:'combleHabit',
				type:'select'
			},			
			{
				id:'surface',
				type:'input',				
			},			
			{
				id:'mitoyen',
				type:'select'				
			},	
			{
				id:'nbLogement',
				type:'input',				
			},		
			{
				id:'formeBat',
				type:'select',				
			},	
			{
				id:'imgForme',
				type:'freetext',
				lib:'...',
			},	
			{
				id:'orientationBat',
				type:'select',	
			},				
			{
				id:'surfMur',
				type:'number',
				unite:'m²'
			},
			{
				id:'surfPlancherHaut',
				type:'number',
				unite:'m²'
			},
			{
				id:'surfPlancherBas',
				type:'number',
				unite:'m²'
			},	
			{
				id:'permeaInit',
				type:'number',
			},
			{
				id:'permeaFin',
				type:'number',
			},	
			{
				id:'altitude',
				type:'number',
			},				
			{
				id:'description',
				type:'textarea'				
			},	

		];
		
		this.aJqTiroir['batiment'].fVerifTiroir = () => this.verifBatiment();
		
		this.aJqTiroir['batiment'].find('.saisieContent').append('<div class="typeLot">'+
						'<div class="ligneSaisie">'+							
							'<div class="blocCadre">'+
								this.getHtmlListeChamp(aInfoChampsBat)+
							'</div>'+
						'</div>'+
						//'<div class="solutionListe"></div>'+
					'</div>');					
		this.aJqTiroir['batiment'].find('button.valid').on('click',()=>this.ouvreTiroirSuivant('batiment'));
		
		//si on change le type de bât ou la zone clim(dep), ça impacte les solutions de chauffage, ecs et ventil
		//ça impacte aussi les perfMin des parois
		this.aJqTiroir['batiment'].find('.saisieContent [name=typeBat]:input').on('change',()=>{
			this.modeSaisieTousLot();
		});

		this.aJqTiroir['batiment'].find('.saisieContent [name=formeBat]:input').on('change',()=>{
			this.updateImgForme();
		});
		this.updateImgForme();
		
		this.ajouteChampValDef('surfMur');
		this.ajouteChampValDef('surfPlancherHaut');
		this.ajouteChampValDef('surfPlancherBas');
		this.ajouteChampValDef('altitude');
		this.ajouteChampValDef('permeaInit');
		this.ajouteChampValDef('permeaFin');
	},	
	
	verifBatiment:function(){
		this.updateTouteChampValDef();
		jQuery('.aRemplir').removeClass('aRemplir');
		let bErreur=false;
		let bTest;
		let jData = this.getJsonTiroir('batiment');		
		for (let nomChamp in jData){	
			bTest = true;
			
			if (['description','surfMur','surfPlancherHaut','surfPlancherBas','altitude','permeaInit','permeaFin'].includes(nomChamp)){
				bTest = false;
			}
			if (!jData[nomChamp] && bTest){
				this.aJqTiroir['batiment'].find('.champLot.'+nomChamp).addClass('aRemplir');
				bErreur=true;
			}
		}
		if (bErreur){
			dialogTool.message('Veuillez renseigner toutes les valeurs.');
			return false;
		}			
		return true;
	},
		
	
	
	updateImgForme:function(){	
		let formeBat = this.aJqTiroir['batiment'].find('.saisieContent [name=formeBat]:input').val();
		let jqImgForme = this.aJqTiroir['batiment'].find('.saisieContent .champLot.imgForme');
		
		let htmlImg = "";
		
		if (formeBat){
			htmlImg = '<div class="imgContent">(Vue de dessus)<br><img src="'+apiSolB2C2.URI_PROJ_BASE+'img/formeBat/'+formeBat+'.png"></div>';
		}
		
		jqImgForme.html(htmlImg);
	},	
	
	ajouteChampValDef:function(nomChamp ){
		let jqCkbDef = jQuery('<input type="checkbox" class="notJson"><label> Valeur par défaut</label>');
		let jqCont = jQuery('<div class="ckbValDef"></div>').append(jqCkbDef);
		let jqInput = formB2C2.aJqTiroir['batiment'].find('.saisieContent [name='+nomChamp+']:input');
		formB2C2.aJqTiroir['batiment'].find('.saisieContent .champLot.'+nomChamp).append(jqCont);
		jqCkbDef.on('change',function(){
			if (this.checked){
				jqInput.attr('oldVal',jqInput.val());
				jqInput.val('');
				jqInput.prop( "disabled", true );
			} else {
				jqInput.prop( "disabled", false );
				let oldVal = jqInput.attr('oldVal');
				if (oldVal){
					jqInput.val(oldVal);
				}
			}
		});
	},
	
	updateTouteChampValDef:function(){
		this.updateChampValDef('surfMur');
		this.updateChampValDef('surfPlancherBas');
		this.updateChampValDef('surfPlancherHaut');
		this.updateChampValDef('altitude');
		this.updateChampValDef('permeaInit');
		this.updateChampValDef('permeaFin');		
	},
	
	updateChampValDef:function(nomChamp){
		let jqInput = formB2C2.aJqTiroir['batiment'].find('.saisieContent [name='+nomChamp+']:input');
		let jqCkbDef = formB2C2.aJqTiroir['batiment'].find('.saisieContent .champLot.'+nomChamp+' :checkbox');
		if (jqInput.val()) {
			jqCkbDef.prop( "checked", false );
			jqInput.prop( "disabled", false );
		} else {
			jqCkbDef.prop( "checked", true );
			jqInput.prop( "disabled", true );
		}
		
	},
	
	/**************************************
	*
	*	Saisie des lots
	*
	**************************************/
	
	
	reinitTousLot:function(){
		this.updateGenTypo();
		this.updateImgTypo();
		this.updateImgForme();
		this.updateTouteChampValDef();
		
		for (let nomLot in this.aInfoChampSaisie){
			this.reinitLot(nomLot);
		}
	},
	
	reinitLot:function(nomLot){
		this.aJqLot[nomLot].find('.saisieContent').empty();
		this.modeSaisieLot(nomLot);
		this.ajouteLotLigneSaisie(nomLot);
	},
	
	ajouteLotLigneSaisie:function(nomLot){

		let nbType = this.aJqLot[nomLot].find('.typeLot').length;
		//pas plus de 4 type de lot
		if (nbType>=4){
			return;
		}
		
		let aInfoChamps = this.aInfoChampSaisie[nomLot];		
		let libType = 'Type de ';
		if (/[aeiou]/i.test(nomLot[0])){
			libType = "Type d'";
		}
		let html =  this.htmlChampSaisie({
				id:'designLot',
				lib:'Désignation',
				type:'input',
				val:libType+apiSolB2C2.getValChamp('lstLot',nomLot)+ ' n°'+(nbType+1)
			});
		html += this.getHtmlListeChamp(aInfoChamps);

		html +=  this.htmlChampSaisie({
				id:'etape',
				lib:'Etape des travaux',
				type:'select',
				lstVal:{1:'Etape 1',2:'Etape 2'/*,3:'Etape 3',4:'Etape 4'*/}
			});

		if (nomLot!='menuiserie'){	
			html +=  this.htmlChampSaisie({
					id:'part',
					lib:'Part',
					type:'number',
					unite:'%'
				});
		}
			
		
		
		this.modeSaisieLot(nomLot);
		var jqNouvelleLigneLot = jQuery('<div class="typeLot blocCadre" id="type_'+(nbType)+'">'+
										//'<div class="">Type : <br><br></div>'+
										'<div class="ligneSaisie">'+html+'</div>'+
										'<div class="solutionListe"></div>'+
									'</div>');
		this.aJqLot[nomLot].find('.saisieContent').append(jqNouvelleLigneLot);
		jqNouvelleLigneLot.find('.ligneSaisie :input')
				.not('.ligneSaisie .etape :input')
				.not('.ligneSaisie .designLot :input')
				.not('.ligneSaisie .part :input')
				.on('change',()=>this.testChangeModeSaisie(nomLot));
				
		this.repartiPartLot(nomLot);

		//cas particulier des chauffage decentralisés
		if (nomLot=='chauffage'){
			this.gestionChampChauffage(jqNouvelleLigneLot);
		}
	},
	
	gestionChampChauffage:function(jqNouvelleLigneLot){
		//couple la saisie des valeurs typeProdChauffage et typeEmmeteurChauffage pour les chauffage décentralisés où l'émetteur est à la fois la production de chauffage (convecteur, poelles)
		var jqTypeProdChauffage = jqNouvelleLigneLot.find('.ligneSaisie .typeProdChauffage :input');
		var jqTypeEmmeteurChauffage = jqNouvelleLigneLot.find('.ligneSaisie .typeEmmeteurChauffage :input');		

		
		jqTypeProdChauffage.on('change',()=>{
			if (apiSolB2C2.typeChauffageDecentralise[jqTypeProdChauffage.val()]){
				jqTypeEmmeteurChauffage.val(jqTypeProdChauffage.val());
			} else if (apiSolB2C2.typeChauffageDecentralise[jqTypeEmmeteurChauffage.val()]){
				jqTypeEmmeteurChauffage.val('');
			}			
		});
		
		jqTypeEmmeteurChauffage.on('change',()=>{			
			if (apiSolB2C2.typeChauffageDecentralise[jqTypeEmmeteurChauffage.val()]){
				jqTypeProdChauffage.val(jqTypeEmmeteurChauffage.val());
			} else if (apiSolB2C2.typeChauffageDecentralise[jqTypeProdChauffage.val()]){
				jqTypeProdChauffage.val('');
			}
			
		});		
	},

	supprimeLotLigneSaisie:function(nomLot){
		var jqLstligne = this.getJqLstTypeLot(nomLot);	
		if (jqLstligne.length>1){
			jqLstligne.last().remove();
			this.repartiPartLot(nomLot);
		}
	},
	
	repartiPartLot:function(nomLot){
		var jqLstligne = this.getJqLstTypeLot(nomLot);			
		let nbLigne = jqLstligne.length;
		let somme = 0;
		jqLstligne.each(function(iType){
				let newVal;
				let jqPart = jQuery(this).find('.ligneSaisie .champLot.part :input');
				let val = jqPart.val();
				if (iType< (nbLigne-1)){
					newVal = Math.round(100/nbLigne);
					somme += newVal;
				} else {
					newVal = 100 - somme;
				}
				jqPart.val(newVal);
		});
	},
	
	verifLot:function(nomLot){
		jQuery('.aRemplir').removeClass('aRemplir');;
		let sErreur='';
		let sommePart = 0;
		//mode saisie
		let jData = this.getJsonLot(nomLot);
		for (let i = 0 ; i < jData.length ; i++){
			let jTypeLot = jData[i];
			sommePart += parseInt(jTypeLot.existant['part']);
			for (let nomChamp in jTypeLot.existant){
				//mode saisie testé dans tous les cas
				if (!jTypeLot.existant[nomChamp]){
					this.getJqTypeLot(nomLot,i).find('.champLot.'+nomChamp).addClass('aRemplir');
					//this.aJqTiroir['nomLot'].find('.champLot.'+nomChamp).addClass('aRemplir');
					sErreur='Veuillez renseigner toutes les valeurs.\n';
					this.modeSaisieLot(nomLot);
				}				
				if (this.aJqLot[nomLot].modeSaisie==this.CHOIX_SOL){
					//mode choix des solutions
					if (!jTypeLot.existant.idSol){
						this.getJqTypeLot(nomLot,i).find('.champSol').addClass('aRemplir');
						sErreur='Merci de choisir une solution proposée.\n';
					}
				}
			}
		}
		
		if (sommePart!=100 && nomLot!='menuiserie'){
			this.getJqLstTypeLot(nomLot).find('.champLot.part').addClass('aRemplir');
			sErreur+='La somme des parts doit être égale à 100%.\n';
		}
		
		if (sErreur){
			dialogTool.message(sErreur);
			return false;
		}	
		
		if (this.aJqLot[nomLot].modeSaisie!=this.CHOIX_SOL){
			this.ajaxGenereSolutionsLot(nomLot);
			return false;
		}
		
		return true;
	},
		
	getJqLstTypeLot:function(nomLot){
		var jqligne = this.aJqLot[nomLot];
		return jqligne.find('.typeLot');	
	},
	
	getJqTypeLot:function(nomLot,iType){
		var jqligne = this.aJqLot[nomLot];
		return jqligne.find('.typeLot#type_'+iType);
	},

	getHtmlListeChamp:function(aInfoChamps){
		let html = "";
		for (let i = 0 ; i < aInfoChamps.length ; i++){
			let infoChamp = aInfoChamps[i];
			infoChamp.class = infoChamp.id;			
			html += this.htmlChampSaisie(infoChamp);
		}
		return html;
	},	

	htmlChampSaisie:function(option){
		let val;
		let htmlChamp = "";
		let sDisabled = option.disabled ? ' disabled ' : '';		
		let lib = option.lib || apiSolB2C2.getValChamp('lstChamp',option.id);
		let classSup=option.classSup || '';
		
		htmlChamp = '<div class = "lib" >' + lib + ' :</div>';
		
		switch (option.type){
			case 'select':
				htmlChamp += '<select name="'+option.id+'" '+sDisabled+' title="'+lib+'">';
				htmlChamp += '<option value="">- '+lib+' -</option>';
				let lstValeurs = option.lstVal || apiSolB2C2.getListeVal(option.id);
				for (let key in lstValeurs){					
					htmlChamp += '<option value="'+key+'">'+lstValeurs[key]+'</option>';
				}
				htmlChamp += '</select>';
				break;
			case 'number':
			case 'input':
				let inputType = "text";
				if (option.type == "number") inputType = "number";
				val = option.val ? ' value="'+option.val +'" ' : '';
				htmlChamp += '<input type="'+inputType+'" name="'+option.id+'" placeholder="'+lib+'" '+sDisabled+' '+val+' title="'+lib+'">';
				if (option.unite){
					htmlChamp += '<div class="unite">'+option.unite+'</div>'
				}
				break;	
			case 'textarea':
				val =  option.val || '';
				htmlChamp += '<textarea  rows="5" cols="33" name="'+option.id+'" placeholder="'+lib+'" '+sDisabled+' '+val+' title="'+lib+'">'+val+'</textarea>';
				break;			
			case 'freetext':				
				htmlChamp = '<div class="freetext">'+lib+'</div>';
				break;
		}	
		
		return '<div class="champLot '+option.id+" "+classSup+' ">'+htmlChamp+'</div>';
	},
	



	modeSaisieLot:function(nomLot){
		if (this.aJqLot[nomLot] && this.aJqLot[nomLot].modeSaisie!=this.SAISIE){
			this.effaceSolutionsLot(nomLot);
			this.aJqLot[nomLot].find('.validExistant').show();
			this.aJqLot[nomLot].find('.validSolution').hide();
			this.aJqLot[nomLot].modeSaisie=this.SAISIE;

			this.modeSaisieChauffageSiModifEcs(nomLot);
		}
	},
	
	modeChoixSolutionLot:function(nomLot){
		if (this.aJqLot[nomLot] && this.aJqLot[nomLot].modeSaisie!=this.CHOIX_SOL){		
			this.aJqLot[nomLot].find('.validExistant').hide();
			this.aJqLot[nomLot].find('.validSolution').show();
			this.aJqLot[nomLot].modeSaisie=this.CHOIX_SOL;
			
			this.modeSaisieChauffageSiModifEcs(nomLot);
		}
	},
	
	modeSaisieChauffageSiModifEcs:function(nomLot){
		//on efface les solutions de chauffage car elles dépendent de la solution ecs 
		if (nomLot == 'ecs'){			
			this.modeSaisieLot('chauffage');
		}
	},

	testChangeModeSaisie:function(nomLot){
		if (this.aJqLot[nomLot].modeSaisie==this.CHOIX_SOL){
			//if (window.confirm('Voulez-vous effacer .')	){
				this.modeSaisieLot(nomLot);
			//}
		}
	},
	
	modeSaisieTousLot:function(){
		this.modeSaisieLot('mur');
		this.modeSaisieLot('plancherHaut');
		this.modeSaisieLot('plancherBas');
		this.modeSaisieLot('menuiserie');
		this.modeSaisieLot('chauffage');
		this.modeSaisieLot('ecs');
		this.modeSaisieLot('ventilation');
	},	
	
	/********************************************************
	* tiroirs
	*********************************************************/

	initTiroir:function(){
		this.aJqTiroir={};

		jQuery('.lotContent').each(function(){
			let jqLot = jQuery(this);
			let nomLot = formB2C2.lcfirst(jqLot.attr('id').replace('lot',''));
			
			let jqTiroir = jqLot.find('.tiroirContent');
			formB2C2.aJqTiroir[nomLot] = jqTiroir;
			jqTiroir.hide(0);
			jqLot.find('.titreTiroir').on('click',()=>formB2C2.clickTiroir(nomLot));
		});
		
	},

	clickTiroir:function(nomLot){
		let jqTiroir = this.aJqTiroir[nomLot];
		if (jqTiroir.parent().hasClass('opened')){
			this.fermeTiroir(nomLot);
		} else {
			this.ouvreTiroir(nomLot);
		}
	},

	fermeTiroir:function(nomLot){
		let jqTiroir = this.aJqTiroir[nomLot];
		jqTiroir.hide(500);
		jqTiroir.attr('open',false);
		jqTiroir.parent().removeClass("opened");
	},
	
	estTiroirOuvert:function(nomLot){
		let jqTiroir = this.aJqTiroir[nomLot];		
		
		//si dejà ouvert
		return jqTiroir.parent().hasClass("opened");
	},

	ouvreTiroir:function(nomLot,bNotVerif){
		let jqTiroir = this.aJqTiroir[nomLot];		
		
		//si dejà ouvert
		if (this.estTiroirOuvert(nomLot)){
			return;
		}
		
		let nomTirPrec = this.getNomTiroirRelatif(nomLot,-1);
		if(nomTirPrec){	
			if (!bNotVerif){					
				//verif tiroir precedent						
				if (!this.verifTiroir(nomTirPrec)){					
					return;
				}	
				//valide tirroir precedent
				if (this.estTiroirOuvert(nomTirPrec)){
					if (!this.valideTiroir(nomTirPrec)){
						return;
					}
				}
			}
		}		
		
		this.fermeTousLesTiroir();
		
		//ouverture
		jqTiroir.show(500);
		jqTiroir.attr('open',true);
		jqTiroir.parent().addClass("opened");
		
		//fonction à executé à l'ouverture du tirroir 
		let fOuvreTiroir =  this.aJqTiroir[nomLot].fOuvreTiroir;
		if (fOuvreTiroir){
			return fOuvreTiroir(nomLot);
		}	
	},
	
	ouvreTiroirSuivant:function(nomLotPrec,bNotVerif){				
		let nomTirroirSuiv = this.getNomTiroirRelatif(nomLotPrec,+1);
		if (nomTirroirSuiv){
			this.ouvreTiroir(nomTirroirSuiv,bNotVerif);
		}
	},
	
	valideTiroir:function(nomLot){	
		//valide le tirroir 
		let fValideTiroir =  this.aJqTiroir[nomLot].fValideTiroir;
		if (fValideTiroir){
			return fValideTiroir(nomLot);
		}		
		return true;
	},	
	
	verifTiroir:function(nomLot){	
		//verif tous les tirroirs prec
		let nomTirPrec = this.getNomTiroirRelatif(nomLot,-1);
		if(nomTirPrec){
			if (!this.verifTiroir(nomTirPrec)){
				return false;
			}
		}
		//verif le tirroir en cours
		let fVerifTiroir =  this.aJqTiroir[nomLot].fVerifTiroir;
		if (fVerifTiroir){
			if (!fVerifTiroir(nomLot)){
				this.ouvreTiroir(nomLot,true);
				return false;
			}
		}
		
		return true;
	},

	fermeTousLesTiroir:function(){
		for(let nomLot in this.aJqTiroir){
			this.fermeTiroir(nomLot)
		}
	},
	
	getNomTiroirRelatif:function(nomLot,i){
		let lstNom = Object.keys(this.aJqTiroir);
		let pos = lstNom.indexOf(nomLot);
		if (pos<0 || (pos+i) >= (lstNom.length) ){
			return false;
		}
		return lstNom[pos+i];
	},

	
	/** Tiroir de la feuille de route*/
	
	initTirroirGroupe:function(nomPoignee){		
		jQuery('.'+nomPoignee).each(function(){
			let jqPoignee = jQuery(this);
			if (!jqPoignee.hasClass('opened')){
				formB2C2.fermeTiroirGroupe(jqPoignee);
			}			
			jqPoignee.on('click',()=>formB2C2.clickTiroirGroupe(jqPoignee));
		});
	},
	
	clickTiroirGroupe:function(jqPoignee){		
		if (jqPoignee.hasClass('opened')){
			this.fermeTiroirGroupe(jqPoignee);
		} else {
			this.ouvreTiroirGroupe(jqPoignee);
		}
	},
	
	fermeTiroirGroupe:function(jqPoignee){		
		jqPoignee.removeClass("opened");
		jqPoignee.next().hide(500);
	},
	
	ouvreTiroirGroupe:function(jqPoignee){		
		jqPoignee.addClass("opened");
		jqPoignee.next().show(500);
	},
	
	/********************************************************
	* Lecture des données Saisies
	*********************************************************/
	
	getJsonProjet:function(nomLotSeul){
		let jData = {
			projet:{
				general:this.getJsonTiroir('general'),				
				batiment:this.getJsonTiroir('batiment'),				
			},			
		};
		
		if (nomLotSeul){
			jData.projet.lstLot = {};
			jData.projet.lstLot[nomLotSeul]=this.getJsonLot(nomLotSeul);
		} else {
			jData.projet.lstLot = this.getJsonTousLot();
		}
		
		if (this.bDebug){
			jData.debug = true;
		}
		
		return jData;
	},
	
	getJsonTiroir:function(nomTiroir){
		let res = {};
		this.aJqTiroir[nomTiroir].find('.ligneSaisie :input').not('.notJson').each(function(iChamp){
					let nomChamp = jQuery(this).attr('name');
					let val = jQuery(this).val();
					res[nomChamp] = val;
		});
		return res;
	},		
		
		
	getJsonLot:function(nomLot){
		let res = [];
		var aJqLigne = this.getJqLstTypeLot(nomLot);//jqSaisie.find('.typeLot');
		
		aJqLigne.each(function(iType){
				let dataExist = {};
				let jqTypeLot = jQuery(this);
				//Etat Existant
				jqTypeLot.find('.ligneSaisie :input').each(function(iChamp){
					let nomChamp = jQuery(this).attr('name');
					let val = jQuery(this).val();
					dataExist[nomChamp]=val;					
				});
				
				//Solutions
				let aSolution = {};
				jqTypeLot.find('.solutionListe .tableSol').each(function(){
					let dataLstSol = jQuery(this).attr('data');
					aSolution = JSON.parse(dataLstSol);
				});
				
				let idSol = jqTypeLot.find('.solutionListe input:checked').attr('idSol');
				if (idSol ){
					dataExist['idSol']=idSol;
				}
								
				res[iType] = {
								existant:dataExist,
								lstSol:aSolution
							};
			});
		return res;
	},
		
	getJsonTousLot:function(){
		let res = {};
		for (let nomLot in this.aInfoChampSaisie){
			//let jLot={};
			res[nomLot]= this.getJsonLot(nomLot);
			//Object.assign(res,jLot);
		}
		return res;
	},

	/********************************************************
	* Affichage Solutions
	*********************************************************/
	
	ajaxGenereSolutionsLot:function(nomLot){
		this.effaceSolutionsLot(nomLot);
				
		let jDataLot = this.getJsonProjet(nomLot);
		
		if (nomLot == 'chauffage'){
			//pour le chauffage, on a besoin de connaitre les solution d'ECS
			let jDataEcs = this.getJsonProjet('ecs');
			jDataLot['projet']['lstLot']['ecs'] = jDataEcs['projet']['lstLot']['ecs'] ;
		}
		console.log(jDataLot);	
		dialogTool.showLoading();
		apiSolB2C2.genereSolutions(jDataLot,(d)=>{
			dialogTool.hideLoading();
			this.afficheResultatSolution(nomLot,d)
		});
			
		return;
	},
	
	afficheResultatSolution:function(nomLot,jData){		
		if (jData.solutions){
			this.afficheSolutionsLots(nomLot,jData.solutions);
			this.modeChoixSolutionLot(nomLot);
		}
	},
	
	afficheSolutionsLots:function(nomLot,jData){
		var aSolutionsLots = jData[nomLot];
		
		for (var iType = 0 ;  iType < aSolutionsLots.length ; iType++){			
			let aSolutionsLotsType = aSolutionsLots[iType].lstSol;
			this.ajouteSolutionLotsType(nomLot,iType,aSolutionsLotsType);
		}
	},
	
	ajouteSolutionLotsType:function(nomLot,iType,aSolutionsLotsType,idChoix){
		let htmlSol = this.htmlSolutionsLotsType(nomLot,iType,aSolutionsLotsType,idChoix);
		let jqTypeLot = this.getJqTypeLot(nomLot,iType).find('.solutionListe');
		jqTypeLot.html('<div class="titreSolution">Solutions proposées :</div>' + htmlSol);
		jqTypeLot.find('input[name^="solEcs_"]').on('change',()=>this.modeSaisieChauffageSiModifEcs(nomLot));
	},
	
	htmlSolutionsLotsType:function(nomLot,iType,aSolutionsLotsType,idChoix){
		//console.log(aSolutionsLotsType);
		let htmlSol =  '';
		let htmlEntete='';
		let numSol = 0;
		let sChecked  ='';
		let bTypeLotPrioritaire = true;
		
		let aKeySol = Object.keys(aSolutionsLotsType);

		if (aKeySol.length){
			for (let idSol in aSolutionsLotsType){
				let htmlLigne='';
				let bAjouteLigne = true;
				let solution = aSolutionsLotsType[idSol];
				
				if ((aKeySol.length == 1) || idChoix==idSol){
					sChecked = ' checked ';
				} else {
					sChecked = '';
				}
				
				let nbCol = Object.keys(solution).length;
				let iCol = 0;
				let bAjouteCol = true;				
				let bAjoutePerfMin = true;				
				for (let nomChamp in solution){
					
					let htmlAttribut = '';
					let valChamp = solution[nomChamp];
					let txtChamp = apiSolB2C2.getListeValChamp(nomChamp,valChamp);
					let classSup = '';
					
					if (nomChamp == 'idSol'){
						classSup = 'debug';
					}
					
					if (nomChamp == 'perfMin'){
						txtChamp = this.getTxtPerfMin(nomLot,valChamp);
						bAjouteCol = bAjoutePerfMin;
					}
					
					if (valChamp == 'nonPrioritaire'){
						bTypeLotPrioritaire = false;
						bAjouteLigne = false;
					}						
					
					if (valChamp == 'expertise'){
						txtChamp = apiSolB2C2.getValChamp('remarqueSolution',valChamp); 
						htmlAttribut += ' colspan = '+(nbCol - iCol-1);
					}
					if (valChamp == 'nonTraite'){
						txtChamp = apiSolB2C2.getValChamp('remarqueSolution',valChamp); 
						htmlAttribut += ' colspan = '+(nbCol - iCol);						
						bAjoutePerfMin = false;
						//choix automatique da la solution "non traité" si non prioritaire.
						if (!bTypeLotPrioritaire && !idChoix){
							sChecked = ' checked ';
						}
					}
									
					if (numSol==0){
						//entêtes
						htmlEntete += '<td class="champSol '+nomChamp+' '+classSup+'">'+apiSolB2C2.getValChamp('lstChamp',nomChamp)+'</td>';					
					} 
					if (bAjouteCol){
						htmlLigne += '<td class="champSol '+nomChamp+' '+classSup+'" '+htmlAttribut+'>'+txtChamp+'</td>';			
					}
					
					if (valChamp == 'nonTraite' ||
						valChamp == 'expertise'){
							bAjouteCol = false;
					}
					iCol++;
				}
				
				//entête
				if (numSol==0){				
					htmlEntete = '<td class="champSol"></td>'+htmlEntete;
					htmlSol += '<tr class="solLigne entete" >'+htmlEntete+'</tr>';
				} 
				
				if (bAjouteLigne){					
					let htmlRadioBtn = '<input ' +sChecked+' type="radio" id="solBt'+this.ucfirst(nomLot)+'_'+iType+'_'+idSol+'" name="sol'+this.ucfirst(nomLot)+'_'+iType+'" idSol="'+idSol+'">';
					htmlLigne = '<td class="champSol">'+htmlRadioBtn+'</td>'+htmlLigne;
					
					htmlSol += '<tr class="solLigne" >'+htmlLigne+'</tr>';
				}
				
				
				numSol++;
			}
		}
		
		if (htmlSol){
			let dataLstSol = this.escapeHtml(JSON.stringify(aSolutionsLotsType));
			htmlSol = '<table class="tableSol"  data="'+dataLstSol+'"><tbody>'+htmlSol+'</tbody></table>';
		}
		
		if (!htmlSol){
			htmlSol += this.htmlRemarqueSolution('sansSolution');
		}
		if (!bTypeLotPrioritaire){
			htmlSol += this.htmlRemarqueSolution('nonPrioritaire');
		}
		return htmlSol;
	},
	
	htmlRemarqueSolution:function(nomSolution){
		return '<div class="remarqueSolution nomSolution">'+apiSolB2C2.getValChamp('remarqueSolution',nomSolution)+'</div>';
	},
	
	getTxtPerfMin:function(nomLot,valChamp){
		if (nomLot=='menuiserie'){
			return 'Uw < '+valChamp+' W/(m².K)';
		} else {
			return 'R > '+valChamp+' m².K/W';
		}
	},
	
	
	effaceSolutionsLot:function(nomLot){
		this.aJqLot[nomLot].find('.solutionListe').empty();
	},
	
	/********************************************************
	* Feuille de route
	*********************************************************/
	initFeuilleDeRoute:function(){
		jQuery('#feuilleDeRoute .tiroirContent button.enregistre').on('click',()=>this.enregistreProjet());
		jQuery('#feuilleDeRoute .tiroirContent button.imprimer').on('click',()=>this.imprimerProjet());
		//jQuery('#feuilleDeRoute .tiroirContent button.imprimer').on('click',()=>CMSEffiUtils.showLogin());
		//jQuery('#feuilleDeRoute .titreTiroir').on('click',()=>this.afficheFeuilleDeRoute());
		this.aJqTiroir['feuilleDeRoute'].fOuvreTiroir = ()=>this.afficheFeuilleDeRoute();
	},
	
	afficheFeuilleDeRoute:function(){
		let jqFdr = this.aJqTiroir['feuilleDeRoute'].find('.contenuFeuille');
		let jProjet = this.getJsonProjet();		
		jProjet.getHtml = true;
		console.log(jProjet);
		jqFdr.empty();
		this.jqDebugTrace.empty();
		dialogTool.showLoading();
		apiSolB2C2.genereFeuilleDeRoute(jProjet,(d)=>{
			console.log(d);
			dialogTool.hideLoading();
			let html = '';
			this.dataFdr = d;			
			html += d.html;
			//html += this.htmlFdrParcour(d);
			jqFdr.html(html);
			this.initTirroirGroupe('titreFdr');
			if (d.trace){
				this.jqDebugTrace.html(d.trace.join('<br>'));
			}
			this.dataFdr = d;	
			delete this.dataFdr.html;//on enregistrera pas le HTML en BDD
		});
		
		//this.get console.log(1)
		/*let nomlot = 'mur';		
		let aSolLot = this.getSolutionsLot(nomlot);
		for (iType in aSolLot){
			let aSolTypeLot = aSolLot[iType];
			let ligneSol = '';
			for (idSol in aSolTypeLot){
				let sol = aSolTypeLot[idSol];
				//ligneSol += this.htmlLigneFeuilleDeRoute(sol,{nivEner:});
			}
		}*/		
	},
// tout ce qui a été mis en commentaire ci-apres est maintenant effectué coté serveur pour mutualiser le code avec la génération du PDF

	/*htmlFdrParcour:function(data){
		let html = '';
		
		//html += this.htmlFdrDescription(data);
		html += this.htmlFdrListeEtape(data);

		return html;
	},*/

	/*htmlFdrDescription:function(data){
		let desc=data.desc;
		let html = '';
		let descBat = '';

		html += '<table class="tabFdr"><tbody>';
		html += '<tr class="entete"><td class="col titre">Général</td><td class="col titre">Bâtiment</td></tr>';
		html += '<tr><td class="col">';
			for(let nomChamp in desc['general']){
				html += this.htmlFdrChampVal(nomChamp, desc['general'][nomChamp]);
			}
		html += '</td><td class="col">';	
			for(let nomChamp in desc['batiment']){
				if (nomChamp=='description'){
					descBat = desc['batiment'][nomChamp];
				} else {
					html += this.htmlFdrChampVal(nomChamp, desc['batiment'][nomChamp]);
				}
			}
		html += '</td></tr>';	
		if (descBat){
			html +='<tr class="entete"><td class="col titre" colspan=2>Description</td></tr>';
			html +='<tr><td class="col" colspan=2>'+descBat+'</td></tr>';
		}
		html += '</tbody></table>';
		
		//Consommation
		if (data.cth.etapes){
			let htmlConso = '';
			//let htmlEntete = '';
			let nbEtap =0;
			for (let nEtap in data.cth.etapes){
				let cep = data.cth.etapes[nEtap]['cep'];
				let imgCep = data.cth.etapes[nEtap]['imgCEP'];
				let ges = data.cth.etapes[nEtap]['ges'];
				let imgGes = data.cth.etapes[nEtap]['imgGES'];
				//htmlEntete += '<td class="col">Etape n°'+nEtap+'</td>';
				if (nEtap>0){
					htmlConso += '<tr><td class="col colFleche">'+
						'<div class="fleche">Etape n°'+nEtap+'</div>'+
											
					'</td></tr>';
				}
				htmlConso += '<tr><td class="col">'+
								'<div class="contDpe">'+
									'<div class="dpeCep">'+
										'<img class="imgDpe" src="data:image/png;base64,'+imgCep+'">'+'<br>'+
										"CEP : "+cep.toFixed(2)+
									'</div>'+
									'<div class="dpeGes">'+
										'<img class="imgDpe" src="data:image/png;base64,'+imgGes+'">'+'<br>'+
										"GES : "+ges.toFixed(2)+
									'</div>'+
								'</div>'+
							'</td></tr>';
				nbEtap++;
			}
			
			if (htmlConso){
				html+='<table class="tabFdr tabConso debug"><tbody>';
				html +='<tr class="entete"><td class="col titre" >Evolution des consommations</td></tr>';
				//html+='<tr class="entete" >'+htmlEntete+'</tr>';
				html+=htmlConso;
				html+='</tbody></table>';
			}
		}

		
		html = 	'<div class="titreFdr opened">Données Générales</div>'+
				'<div class="blocDesc blocCadre">'+html+'</div>';
		
		return html;
	},*/

	/*htmlFdrListeEtape:function(data){
		let parcour = data.parcour;
		let html = '';
		
		let aEtapes = parcour.etapes;
		//for (let iEtap = 0; iEtap<aEtapes.length ; iEtap++){
		if (aEtapes.length===0) aEtapes = {};//car PHP remplace {} par []
		for (let iEtap in aEtapes){
			let etape = aEtapes[iEtap];
			let titreFdr = 'Etape n°'+ (iEtap) +' des travaux';
			if (iEtap =='nonTraite'){
				titreFdr = "Lots non taités";
			}
			
			html += '<div class="blocFdr">'+
						'<div class="titreFdr">'+titreFdr+'</div>'+
						'<div class="blocEtape blocCadre">'+						
							this.htmlFdrEtape(etape.lstLot)+
							((iEtap == 1)? this.htmlRespectCritere(etape.respectB2C2) : '')+
							((iEtap == 1)? this.htmlPermea(data) : '')+
													
						'</div>'+
					'</div>';
				
			if (iEtap !='nonTraite'){	
				html += '<div class="titreFdr">Interations à l\'étape n°'+(iEtap)+'</div>'+
						'<div class="blocEtape blocCadre">'+				
							this.htmlInterEtape(etape.lstInteraction)+						
						'</div>';		
			}
		}
		return html;
	},*/
	
    /** Liste travaux Lots **/
	
	/*htmlFdrEtape:function(etape){
		let html = '';		
		
		html += this.htmlFdrEnteteEtape(true);
		
		for (let nomLot in etape){
			html+= this.htmlFdrTypeLot(nomLot, etape[nomLot].type,true);				
		}	
		
		return '<table class="tabFdr"><tbody>'+html+"</tbody></table>";
	},*/
	
	/*htmlFdrEnteteEtape:function(bPerf){
		let ligne = '';
			ligne += this.htmlFdrChampEtape('nomLot','Lots');
			ligne += this.htmlFdrChampEtape('existant','Existant');
			ligne += this.htmlFdrChampEtape('descTrav','Description des travaux');
			if (bPerf){
				ligne += this.htmlFdrChampEtape('perfMin','Performance minimum');
			}			
			ligne += this.htmlFdrChampEtape('recom','Recommandations');
			ligne ='<tr class="lgn entete">'+ligne+"</tr>";
		return ligne;
	},*/
	
	/*htmlFdrTypeLot:function(nomLot,aTypeLot,bPerf){
		let html = '';
		
		
		for (let iType = 0 ; iType < aTypeLot.length ; iType++){
			let typeLot = aTypeLot[iType];
			let ligne = '';
			if (iType == 0 ){
				let sNomLot = apiSolB2C2.getValChamp('lstLot',nomLot);
				ligne += this.htmlFdrChampEtape('nomLot',sNomLot, 'rowspan="'+aTypeLot.length+'"');
			}	
			
			//si pas colonne perfMin alors on elagi la colone précédente.
			let colSpan = ''
			let htmlPerfMin = '';
			if (bPerf && typeLot['perfMin']){				
				let txtPref = this.getTxtPerfMin(nomLot,typeLot['perfMin']);
				htmlPerfMin = this.htmlFdrChampEtape('perfMin',txtPref);
			} else {
				colSpan = ' colspan="2" ';
			}				
			
			//colonne existant
			let txtExistant = '<b>' + typeLot['designLot'] + ' :</b><br>' + typeLot['existant'];
			ligne += this.htmlFdrChampEtape('existant',txtExistant);
			
			ligne += this.htmlFdrChampEtapeListeVal('descTrav',typeLot,colSpan);
			ligne += htmlPerfMin;
			ligne += this.htmlFdrChampEtapeListeVal('recom',typeLot);
			html+='<tr class="lgn">'+ligne+"</tr>";
		}
		return html
	},*/
	
	/*htmlFdrChampEtapeListeVal:function(nomChamp,listeVal,complement){
		return this.htmlFdrChampEtape(nomChamp,listeVal[nomChamp],complement);
	},
	
	htmlFdrChampEtape:function(sClass,val,complement){
		complement = complement || '';
		return '<td class="col '+sClass+'" '+complement+'>'+ val + '</td>';
	},
	
	htmlFdrChampVal:function(nomChamp,val){
		if (!val) return '';
		let text = apiSolB2C2.getValChamp(nomChamp,val);
		let lib = apiSolB2C2.getValChamp('lstChamp',nomChamp);
		
		return this.htmlFdrChamp(lib,text,nomChamp);
	},
	
	htmlFdrChamp:function(lib,val,sClass){
		return 	'<div class="champFdr '+sClass+'">'+
					'<div class="lib">'+lib+'</div>'+
					'<div class="val">'+val+'</div>'+
				'</div>';
	},*/
	
	/** Liste interaction **/
	
	/*htmlInterEtape:function(lstLotInter){
		let html = '';		
		
		//html += this.htmlFdrEnteteEtape(true);
		for (let libInter in lstLotInter){
			let lstInter = lstLotInter[libInter];
			let htmlLot = '';
			for (let i in lstInter){
				let detailIntet  = lstInter[i];					
				let htmlLigne = '';
				if (!htmlLot){
					htmlLigne+=	'<td rowspan="'+Object.keys(lstInter).length+'" class="col lots">'+libInter+'</td>';
				}
				htmlLigne+= 	'<td class="col risques">'+detailIntet['risque']+'</td>'+
						'<td class="col traitement">'+detailIntet['solution']+'</td>';
				htmlLot+=  '<tr>'+htmlLigne+'</tr>';		
			}
			html += htmlLot;
		}

		if (html){						
			return 	'<table class="tabFdr tabInter"><tbody>'+
						'<tr class="entete"><td class="col titre" colspan="3">Traitment des interactions</td></tr>'+
						'<tr class="entete"><td class="col lots">Lots concernés</td><td class="col risques">Risques</td><td class="col traitement">Traiements à prévoir</td></tr>'+
						html+
					"</tbody></table>";
		} else {
			return '';
		}
	},*/
	
	/**Respect des critères B2c2 **/
	/*htmlRespectCritere:function(aRespect){
		let html = '';
		html += '<tr class="entete"><td class="col titre">Conformité</td></tr>';
		let conforme ='';
		let pathImg = apiSolB2C2.URI_BASE+'libraries/effinergie/sourcerer/b2c2/img/';
		if (aRespect.includes('conformeB2C2')){
			conforme = '<img class="imgConformite" src="'+pathImg+'OK.png"><div class="txtConformite">'+apiSolB2C2.getValChamp('remarqueSolution',aRespect)+'</div>';
		} else {
			conforme = '<img class="imgConformite" src="'+pathImg+'warning.png"><div class="txtConformite">'+apiSolB2C2.getListeValChamp('remarqueSolution',aRespect)+'</div>';
		}
		html += '<tr><td class="col">'+conforme+'</td></tr>';
		html = '<table class="tabFdr conformite"><tbody>'+html+'</tbody></table>'; 
		return html;
	},	*/
	
	/**etancheite à l'air **/
	/*htmlPermea:function(data){
		let html = '';
		html += '<tr class="entete"><td class="col titre">Etanchéité à l\'air</td></tr>';
		let txtPermea = apiSolB2C2.getValChamp('remarqueSolution',data.permea);		
		html += '<tr><td class="col">'+txtPermea+'</td></tr>';
		html = '<table class="tabFdr permea"><tbody>'+html+'</tbody></table>'; 
		return html;
	},*/
	
	/********************************************************
	* Génération automatique packager typologie
	*********************************************************/		
	genereTypologie:function(){
		dialogTool.showLoading();
		apiSolB2C2.genereTypologie(
				this.getJsonTiroir('general'),
				(res)=>{
					dialogTool.hideLoading();
					if (res['status']=='OK'){
						this.chargeProjet(res);
						this.ouvreTiroir('feuilleDeRoute');						
					} else {
						dialogTool.message('Un probleme est survenu pendant le traitement.');
					}
				}
			);
	},
	
	/********************************************************
	* Sauvegarde des données
	*********************************************************/	
	
	enregistreProjet:function(fCbkPdf){
		dialogTool.showLoading();
		let jProjet = this.getJsonProjet();
		jProjet.dataFdr = this.dataFdr;		
		//console.log((jProjet));
		apiSolB2C2.enregistre(jProjet,(res)=>{
			dialogTool.hideLoading();
			//let res = JSON.parse(d);
			if (!res ) {
				dialogTool.message('Une erreur est survenue lors de l\'enregistement du projet.');
				return;
			} 
			
			switch (res['status']){
				case 'OK':					
					if (fCbkPdf){
						this.aJqTiroir['general'].find('[name=id]:input').val(res['id']);//on met à jour l'id dans le champ ID
						//dialogTool.showLoading();
						//window.location.href = apiSolB2C2.URI_BASE+"?viewProj="+res['id']+'&pdf=1';
					} else {
						dialogTool.message('Le projet a bien été enregistré.');
						window.location.href = CMSEffiUtils.getUrlListeProjet();	
					}									
					break;
				case 'NO_USER_ID':
					CMSEffiUtils.showLogin();					
					break;
				default : 
					dialogTool.message('Une erreur est survenue lors de l\'enregistement du projet. : '+res['status']);
					break;
			}
			fCbkPdf(res['id']);
		});
	},
	
	chargeProjet:function(jProj){
		console.log('chargeProjet',jProj);		
		this.chargeDonneTiroir(jProj,'general');
		this.chargeDonneTiroir(jProj,'batiment');
		this.chargeListeLot(jProj);
		this.bProjetChage = true;	
		let msg = '';
		if (jProj.versionDifferent){
			msg += ' ce projet a été enregistré dans une ancienne version. Les données calculées peuvent être différents.'
			
		}
		if (jProj.solutionDifferent){
			msg += ' Certaines solutions proposées ont changées. Merci de le revalider.';
		}
		if(msg){
			dialogTool.message('Attention :'+msg);
		}
		
	},
	
	chargeDonneTiroir:function(jProj,nomTiroir){		
		//reset Fields
		this.aJqTiroir[nomTiroir].find('.ligneSaisie :input').val('');
		
		let jDataGen = jProj.projet[nomTiroir];
		for (let nomChamp in jDataGen){
			let valChamp  = jDataGen[nomChamp];
			this.aJqTiroir[nomTiroir].find('.ligneSaisie [name='+nomChamp+']:input').val(valChamp);
		}
	},
	
	chargeListeLot:function(jProj){
		this.reinitTousLot();
		let aLot = jProj.projet.lstLot;
		for (let nomLot in aLot){
			let aTypeLot = aLot[nomLot];
			this.chargeLot(nomLot,aTypeLot);
		}
	},
	
	chargeLot:function(nomLot,aTypeLot){
		
		let bSolutionsAffichees = (aTypeLot.length>0);
		
		for (let iType = 0 ; iType < aTypeLot.length ; iType++){
			let typeLot = aTypeLot[iType];			
			let jqTypeLot = this.getJqTypeLot(nomLot,iType);
		
			//ajout des types de lots supplémentaires
			if (jqTypeLot.length <=0){
				this.ajouteLotLigneSaisie(nomLot);
				jqTypeLot = this.getJqTypeLot(nomLot,iType);	
			}
			//affichage de l'existant
			for (let nomChamp in typeLot.existant){
				let val = typeLot.existant[nomChamp];
				
				if (nomChamp == 'idSol'){
					this.ajouteSolutionLotsType(nomLot,iType,typeLot.lstSol,val);
				} else {					
				jqTypeLot.find('[name='+nomChamp+']:input').val(val);
				}
			}			
			
			//affichage des solutions
			let idSol = 0;
			if (typeLot.lstSol){				
				if (typeLot.existant) {
					idSol = typeLot.existant['idSol']
				}
				this.ajouteSolutionLotsType(nomLot,iType,typeLot.lstSol,idSol);
			} else {
				bSolutionsAffichees = false;
			}
		}
		if (bSolutionsAffichees){
			this.modeChoixSolutionLot(nomLot);
		} else {
			this.modeSaisieLot(nomLot);
		}
	},
	
	imprimerProjet:function(){
		var wi = window.open('about:blank', '_blank');
		this.enregistreProjet((idProj)=>{			 			 
			// window.open(apiSolB2C2.URI_BASE+"?createPDF="+idProj, '_blank') //sur firefox, la popup est bloquée
			 if (!idProj){
				wi.close(); 
			 } else {
				wi.location.href = apiSolB2C2.URI_BASE+"?createPDF="+idProj;
			 }
		});
		return;
		/*let html = jQuery('#feuilleDeRoute .contenuFeuille').html();
		html = '<div class="b2c2Contexte"><div id="feuilleDeRoute"><div class="contenuFeuille"> '+html+'</div></div></div>';
		html =  '<link rel="stylesheet" href="'+apiSolB2C2.URI_BASE+'templates/protostar/css/template.css?'+Date.now()+'" rel="stylesheet" type="text/css" />'+ 
				'<link rel="stylesheet" href="'+apiSolB2C2.URI_BASE+'libraries/effinergie/sourcerer/b2c2/css/b2c2.css?'+Date.now()+'" type="text/css" />'+
				html;
		this.printElem(html);*/
	},
	
	/********************************************************
	* utils
	*********************************************************/
	ucfirst:function(string){
		return string.charAt(0).toUpperCase() + string.slice(1);
	},

	lcfirst:function(string){
		return string.charAt(0).toLowerCase() + string.slice(1);
	},

	getCurrentUrl:function(){
		return window.location.href.split('?')[0];
	},
	
	escapeHtml:function(text){
	  return text
		  .replace(/&/g, "&amp;")
		  .replace(/</g, "&lt;")
		  .replace(/>/g, "&gt;")
		  .replace(/"/g, "&quot;")
		  .replace(/'/g, "&#039;");
	},
	
	printElem:function(elemHtml)	{
		//var mywindow = window.open('', '', 'height=800,width=600');
		var mywindow = window.open('', '', 'height='+screen.height+',width='+screen.width);

		mywindow.document.write('<html><head><title>' + document.title  + '</title>');
		mywindow.document.write('</head><body >');
		mywindow.document.write('<h1>' + document.title  + '</h1>');
		//mywindow.document.write(document.getElementById(elem).innerHTML);
		mywindow.document.write(elemHtml);
		mywindow.document.write('</body></html>');

		mywindow.document.close(); // necessary for IE >= 10
		mywindow.focus(); // necessary for IE >= 10*/

		mywindow.print();
		/*mywindow.close();*/

		return true;
	},
}	
	
	
/* message */
var dialogTool = {
	
	initDialog:function(){
		if (!this.jqDialog){
			this.jqDialog = jQuery( '<div id="bkgDialog">'+
										'<div id="dialogWindow">'+
											'<div id="dialogText">'+												
											'</div>'+	
											'<div class="grpBouton">'+												
											'</div>'+
										'</div>'+
									'</div>');
			this.jqBtnGroup = this.jqDialog.find('.grpBouton');
			this.jqDialogText = this.jqDialog.find('#dialogText');
			jQuery("body").append(this.jqDialog);			
		}
	},
	
	dialogAddButton:function(option){
		if (!option.btnList){
			option.btnList = ['OK'];
		}
		
		this.jqBtnGroup.empty();
		for (let i = 0 ; i<option.btnList.length ; i++){
			let btnName = option.btnList[i];
			let jqButton = jQuery('<button>'+btnName+'</button>');
			jqButton.on('click',()=>{			
				if (option.callback){
					option.callback(i);
				}
				this.jqDialog.fadeOut(200);
			});
			this.jqBtnGroup.append(jqButton);
		}
	},
	
	message:function(msg,option){
		if (!option){
			option = {};
		}
		this.initDialog();
		this.jqDialogText.html(msg);
		
		this.dialogAddButton(option);
		
		this.jqDialog.fadeIn(200);		
	},
	
	/* loading */
	
	initLoading:function(){
		if (!this.jqLoading){
			this.jqLoading = jQuery('<div id="loading"></div>');
			jQuery("body").append(this.jqLoading);
		}
	},
	
	showLoading:function(){
		this.initLoading();
		this.jqLoading.fadeIn(500);		
	},
	
	hideLoading:function(){
		this.initLoading();
		this.jqLoading.fadeOut(500);
	},	

}

window.addEventListener("load", ()=>formB2C2.init());