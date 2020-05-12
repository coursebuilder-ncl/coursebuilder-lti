<?php
class StudentPage extends LTIPage {
	public function render(){
		$ok = false;
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
		}
		if (!$ok){
			$indexContent = str_replace('{base}/','',$this->module->root_url);
			$indexContent = str_replace('{code}',$this->module->code,$indexContent);
			$indexContent = str_replace('{year}',$this->module->year,$indexContent);
			$indexContent = str_replace('{theme}',$this->module->selected_theme->path,$indexContent);
			$indexContent = ltrim($indexContent,'/');
			$this->requestContentForModule($indexContent);
			$ok = $this->renderRequestedContent();
		}
		if (!$ok){
			parent::render();
		}
	}
}
?>
