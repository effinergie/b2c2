"use strict";

var apiFdrB2C2 ={
	

	
	init:function(){
		

		
		this.jqLoading = jQuery('#loading').hide();
		this.jqInputVal = jQuery('#inputVal');
		this.jqEnumScenario = jQuery('#enumScenario');
		this.jqInputFile = jQuery('#inputFile');
		this.jqOutputVal = jQuery('#outputVal');
		this.jqBtTestApiExt = jQuery('#btTestApiExt');
		this.jqTypeRetour = jQuery('#typeRetour');
		this.jqDebugMode = jQuery('#debugMode');
		this.dataViewerIn = jQuery('#dataViewerIn');
		this.dataViewerOut = jQuery('#dataViewerOut');
		this.jqLoading = jQuery('#loading');
		this.jqBtTestApiExt.on('click',()=>this.extTestApi());
		
		this.jsonViewerOut = new JSONViewer();
		document.querySelector("#dataViewerOut").appendChild(this.jsonViewerOut.getContainer());
		
		

	
		this.jqInputVal.on('drop',function (e){
				apiFdrB2C2.dropfile(e,function(e2){
					apiFdrB2C2.jqInputVal.val(e2.target.result);
					apiFdrB2C2.dataViewerIn.empty().simpleXML({ xmlString: e2.target.result } );
				});
			});	
				
		this.jqInputFile.on('change',()=>{ 
				var file = this.jqInputFile[0].files[0];
				if (file) {
					var reader = new FileReader();
					reader.readAsText(file, "UTF-8");
					reader.onload = function (evt) {
						apiFdrB2C2.jqInputVal.val(evt.target.result);
						apiFdrB2C2.dataViewerIn.empty().simpleXML({ xmlString: evt.target.result } );
					}
				}
			})		
	},
	
	extTestApi:function(){
		this.jqOutputVal.val('');
		this.jsonViewerOut.showJSON('');
		
		if (!this.jqInputVal.val()){
			alert('Aucune donnée d\'entrée.');
			return;
		}
		
		var dataIn = {
			auditXML:this.jqInputVal.val(),
			enumScenario:this.jqEnumScenario.val(),
			typeRetour:this.jqTypeRetour.val(),
			debugMode:this.jqDebugMode.val()
		};
		
		
		let params={
			data : {
				extGenereFdr:dataIn,
			}	
		}
		
		let url = this.URI_BASE+'apiFdr.php';	
		
		this.jqLoading.show();	
		if (['auditEditeurPdf','auditPdf','auditProprietairePdf'].includes(dataIn.typeRetour)){			
			
			this.loadFileAsURL64(url,params,(dataOut)=>{
				
				this.jqLoading.hide();	
				if (dataOut.indexOf('data:application')==0){
					//display PDF in a new tab
					var win = window.open();
					//iframe controunement for Chrome
					win.document.write('<iframe src="' + dataOut  + '" frameborder="0" style="border:0; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%;" allowfullscreen></iframe>');
					win.document.close();
				} else {
					//display error
					dataOut = atob(dataOut.replace('data:text/html;base64,',''));
					this.jqOutputVal.val(dataOut/*,null,"\t"*/);
					this.jsonViewerOut.showJSON(this.tryJsonParse(dataOut), -1/*maxLvl*/, 3/*colAt*/);
				}
			
			})	
			
		} else {		

			this.sendAjaxRequest(url,params,(dataOut)=>{
				this.jqLoading.hide();	
				this.jqOutputVal.val(dataOut/*,null,"\t"*/);
				this.jsonViewerOut.showJSON(this.tryJsonParse(dataOut), -1/*maxLvl*/, 3/*colAt*/);				
			});

				
		}	
		
	},
	
	encodePostParam:function(params){
		var res = [];
		
		for( name in params ) {
			this.encodePostParam2(params[name], name ,res);
		}

		return res.join('&');
	},
	
	encodePostParam2:function(params,prefix,res){
		
		if (typeof params !== 'object'){
			res.push(encodeURIComponent(prefix)+'='+encodeURIComponent(params));
			return;
		}
		
		for( name in params ) {
			this.encodePostParam2(params[name],prefix+'[' + name + ']',res);
		}
		
	},
	
	loadFileAsURL64:function(path,params,fPromise){
		
	
		
		let urlEncodedData = this.encodePostParam(params);
		console.log(urlEncodedData);
		
		var request = new XMLHttpRequest();		
		request.open('POST', path, true);
		request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		request.responseType = 'blob';
		request.onload = function() {
			var reader = new FileReader();
			reader.readAsDataURL(request.response);
			reader.onload =  function(e){
					fPromise(e.target.result);
				};
		};
		request.send(urlEncodedData);
	},	

	
	getAjaxUrl:function(){
		return window.location.href.split('?')[0];
	},
	
	sendAjaxRequest(url,jData,fCallback){
		jQuery.post(url,jData).done((d)=>{				
			fCallback(d)
		}).fail(function() {			
			fCallback('');
		});	
	},
	
	tryJsonParse:function(sJson){
		let res = '';		
		try{
			res = JSON.parse(sJson);			
		} catch (err){
			console.log('Erreur JSON',err);
		}
		return res;
	},

	dropfile:function(e,fCallback) {
		e = e.originalEvent;
		e.preventDefault();
		var file = e.dataTransfer.files[0];		
		var reader = new FileReader();
		reader.onload = fCallback;
		reader.readAsText(file, "UTF-8");
	},



	
	
};


window.addEventListener("load", ()=>apiFdrB2C2.init());




