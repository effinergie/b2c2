<?PHP
require_once __DIR__.'/../../../lib/tcpdf/tcpdf.php';

class TcpdfB2C2 extends TCPDF{
	
	protected $project_data;
	protected $css;
	
	public function newLine(){
		$this->setY($this->GetY()+5);
	}
	
	/********************************************************************************/
	function setProject_data($project_data){
		$this->project_data = $project_data;
		$this->css='
			<style>
				*{
					font-size:9px;
				}
				
				.right{
					text-align:right
				}
			</style>
		';
	}
	
	/********************************************************************************/
	public function Header() {
		$this->SetTopMargin(12);
		$y=4;
		$html='<div>Projet BBC par étapes</div>';	
		$this->writeHTMLcell('','',"",$y,$this->css.$html,'',1);		
		$html =	'<div class="right">'.$this->project_data['projName'].'</div>';
		$this->writeHTMLcell('','',"",$y,$this->css.$html,'',1);	
	}
	
	/********************************************************************************/
	public function Footer() {
		// Position at 15 mm from bottom
		$this->SetX(0);
		$this->SetY(-10);
		$y = $this->GetY();
		
		// Page number
		$html = '<div>Page : <b>'.$this->getAliasNumPage().'/'.$this->getAliasNbPages().'</b></div>';
		$this->writeHTMLcell('','',"",$y,$this->css.$html,'',1);
		$html =	'<div class="right">V'.$this->project_data['version'].'</div>';
		$this->writeHTMLcell('','',"",$y,$this->css.$html,'',1);
	}		
}
?>