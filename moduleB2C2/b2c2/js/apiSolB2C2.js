"use strict";

var apiSolB2C2 = {
	
	initValues:function(jVal){	
		Object.assign(this,jVal);
	},
	
	getAjaxUrl:function(){
		return window.location.href.split('?')[0];
	},
	
	sendAjaxRequest(actionName,jData,fCallback){
		//jQuery.ajax(this.getAjaxUrl(),{	//GET request
			//data:actionName+'='+JSON.stringify(jData)
		
		
		let jSentData = {};
		//jSentData[actionName] = JSON.stringify(jData);
		jSentData[actionName] = jData;
		jQuery.post(this.getAjaxUrl(),{		//POST request	
			data:jSentData
		}).done((d)=>{			
			let jData = this.tryJsonParse(d);			
			fCallback(jData)
		}).fail(function() {			
			fCallback('');
		});	
	},
	
	/********************************************************
	* Calcul et recherche de solutions
	*********************************************************/		
	genereTypologie(jData,fCallback){
		this.sendAjaxRequest('genereTypologie',jData,fCallback);
	},
	
	genereSolutions(jData,fCallback){
		this.sendAjaxRequest('genereSolutions',jData,fCallback);	
	},
	
	genereFeuilleDeRoute(jData,fCallback){
		this.sendAjaxRequest('genereFeuilleDeRoute',jData,fCallback);	
	},
	
	/********************************************************
	* Gestion des projets
	*********************************************************/		
	
	enregistre:function(jData,fCallback){
		this.sendAjaxRequest('enregistreProjet',jData,fCallback);	
	},
	
	/********************************************************
	* Listes Valeurs
	*********************************************************/		
	getValChamp:function(nomLst,val){	
		val = String(val);
		let lstValChamp = this.getListeVal(nomLst);		
		if (val && lstValChamp){
			if (val.charAt(0) == '#'){
				//enleve le dièze du debut au cas ou.
				val = val.substring(1);
			}
			let res = lstValChamp[val];
			if (typeof res != 'undefined'){
				return res;
			}
		}
		return val;
	},
	
	getListeValChamp:function(nomLst,aVal,sSep){
		if (!sSep){
			sSep = ', <br>';
		}
		let aRes = [];
		for (let i = 0 ; i< aVal.length ; i++){
			aRes[i] = this.getValChamp(nomLst,aVal[i])
		}
		return aRes.join(sSep);
	},
	
	getListeVal(nomLst){
		return this.lstVal[nomLst];
	},
	
	/********************************************************
	* Utils
	*********************************************************/
	tryJsonParse:function(sJson){
		let res = '';		
		try{
			res = JSON.parse(sJson);			
		} catch (err){
			console.log('Erreur JSON',err);
		}
		return res;
	}
};
