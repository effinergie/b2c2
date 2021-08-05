"use strict";

var CMSEffiUtils = {	
	showLogin:function(){
		//let redirectUrl = 'index.php?totolala';
		//let redirectUrl = 'http://127.0.0.1/edsa-effinergie/index.php/presentation/2346?testsestes=1';
		//window.location = 'http://127.0.0.1/edsa-effinergie/connexionb2c2?return='+encodeURI(btoa(redirectUrl));  ;
		this.openPopup( apiSolB2C2.URI_BASE	+ 'connexionb2c2');
	},

	openPopup:function(url){
		jcepopup.open(url, '', '', 'iframe', {'src':'', 'width':400, 'height':400});
	},
	
	getUrlListeProjet:function(){
		return apiSolB2C2.URI_BASE+"liste-des-projets";
	}

};