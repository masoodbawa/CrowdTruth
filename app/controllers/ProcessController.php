<?php

use crowdwatson\MechanicalTurkService;
use crowdwatson\AMTException;
use crowdwatson\Hit;

class ProcessController extends BaseController {

	public function getIndex() {
		// if(!count(Cart::content()) > 0){
		// 	Session::flash('flashNotice', 'You have not added any items to your selection yet');
		// 	return Redirect::to('files/browse');
		// }
        return Redirect::to('process/selectfile');
	}

	public function getSelectfile() {
		return View::make('process.tabs.selectfile')->with('crowdtask', unserialize(Session::get('crowdtask')));
	}

	public function getDetails() {
		return View::make('process.tabs.details')->with('crowdtask', unserialize(Session::get('crowdtask')));
	}

	public function getPlatform() {
		$ct = unserialize(Session::get('crowdtask'));
		$turk = new MechanicalTurkService(base_path() . '/public/templates/');
		$questionids = array();
		try {
			$questionids = $turk->findQuestionIds($ct->template);
		} catch (AMTException $e) {
			Session::flash('flashError', $e->getMessage());
		} 
		
		return View::make('process.tabs.platform')
			->with('crowdtask', $ct)
			->with('questionids', $questionids);
	}


	private function iterateDirectory($path, $currenttemplate){
		$r = array();
		foreach(File::directories($path) as $dir){
			$dirname = substr($dir, strlen($path));
		   	$prettydir = ucfirst(str_replace('_', ' ', $dirname));
			$r[] = array('id' => $dirname, 'parent' => '#', 'text' => $prettydir); 
			
			foreach(File::allFiles($dir) as $file){
				$filename = $file->getFileName();
				if (substr($filename, -5) == '.html') {
		   			$filename = substr($filename, 0, -5);
		   			$prettyname = ucfirst(str_replace('_', ' ', $filename));
		   			if("$dirname/$filename" == $currenttemplate)
		   				$r[] = array('id' => $filename, 'parent' => $dirname, 'text' => $prettyname, 'state' => array('selected' => 'true'));
		   			else
		   				$r[] = array('id' => $filename, 'parent' => $dirname, 'text' => $prettyname);
		   		}	
			}
		}
		return json_encode($r);
	}

	public function getTemplate() {
		// Create array for the tree
		$crowdtask = unserialize(Session::get('crowdtask'));		
		$currenttemplate = (isset($crowdtask->template) ? $crowdtask->template : 'generic/default');
		$path = base_path() . '/public/templates/';
		$treejson = $this->iterateDirectory($path, $currenttemplate);

		return View::make('process.tabs.template')
			->with('treejson', $treejson)
			->with('currenttemplate', $currenttemplate)
			->with('crowdtask', $crowdtask);
	}

	public function getSubmit() {
		$crowdtask = unserialize(Session::get('crowdtask'));
		$turk = new MechanicalTurkService();
		$questions = array();

		try{
			$question = file_get_contents(base_path() . "/public/templates/{$crowdtask->template}.html");
			$questions = $turk->createPreviews($question, base_path() . '/public/csv/test.csv');
		} catch (AMTException $e) {
			Session::flash('flashError', $e->getMessage());
		} catch (ErrorException $e) {
			Session::flash('flashError', 'Error reading templatefile.');
		}

		return View::make('process.tabs.submit')
			->with('crowdtask', $crowdtask)
			->with('questions',  $questions);
	}

	private function newCTfromTemplate($template){
		try {
			// Currently, the HIT format is used.
			$turk = new MechanicalTurkService(base_path() . '/public/templates/');
			$hit = $turk->hitFromTemplate($template);
			$ct = CrowdTask::getFromHit($hit);
			$ct->template = $template;
			return $ct;
		} catch (AMTException $e){
			Session::flash('flashError', $e->getMessage());
			return new CrowdTask;
		}
	}

	public function getClearTask(){
		Session::forget('crowdtask');
		return Redirect::to("process/selectfile");
	}

	public function postFormPart($next){
		$ct = unserialize(Session::get('crowdtask'));

		if(Input::has('template')){
			$template = Input::get('template');
			if (empty($ct) or ($ct->template != $template))
				$ct = $this->newCTfromTemplate($template);		
		} else {
			// perhaps additional logic here depending on which tab you're on
			if (empty($ct)){
				$ct = new CrowdTask;
				Session::flash('flashWarning', 'No template selected.');
			} else {
				$ct = new CrowdTask(array_merge($ct->toArray(), Input::get()));	
				if(Input::has('qr') and is_array(Input::get('qr'))) 		$ct->addQualReq(Input::get('qr'));
				if(Input::has('answerkey') and is_array(Input::get('answerkey'))) $ct->addAssRevPol(Input::get('answerkey'), Input::get('arp'));
			}		
		}

		Session::put('crowdtask', serialize($ct));
		return Redirect::to("process/$next");

	}

	public function getAmt($template='default') {
		$hit = new Hit();
		$questionids= array();
		try {
			$turk = new MechanicalTurkService(base_path() . '/public/templates/');
			$hit = $turk->hitFromTemplate($template);
			$questionids = $turk->findQuestionIds($template);
		} catch (AMTException $e) {
			Session::flash('flashError', $e->getMessage());
		}
		
		return View::make('process.tabs.amt')
			->with('hit', $hit)
			->with('template', $template)
			->with('questionids', $questionids)
			->with('crowdtask', unserialize(Session::get('crowdtask')));
	}

	// public function getCf(){
	// 	return View::make('process.index')->with('page', 'process.cf.index');
	// }

	public function postSubmitFinal(){
		$ct = unserialize(Session::get('crowdtask'));
		$hit = $ct->toHit();
		$turk = new MechanicalTurkService(base_path() . '/public/templates/');
		$csvfilename =base_path() . '/public/csv/test.csv'; //TODO: Set this @ selectfile. Also see below!
				
		// Create HIT(s)
		try {
			$created = ($turk->createBatch($ct->template, $csvfilename, $hit));
			Session::flash('flashSuccess', 'Created ' . count($created) . ' HITs.');
		} catch (AMTException $e) {
			Session::flash('flashError', $e->getMessage());
		}

		return Redirect::to("process/submit");
	}
}