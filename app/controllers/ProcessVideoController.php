<?php

Use \Entities\Unit as Unit;
Use \Entity as Entity;


class ProcessVideoController extends BaseController {


    public function getIndex()
    {
        return Redirect::to('media/search');
    }

    public function postProcess()
    {
        if (!Input::has('videofile'))
        {
            return Redirect::to('media/search');
        }
        $output = Array();
        $videofile = Input::get('videofile');
        $data = Entity::where('_id',$videofile)->first()->toArray();
        $output['videofile'] = $data;

        $videodata = Entity::where('type','downloadedvideo')->whereIn('parents',[$videofile])->first()->toArray();

        $videofileid = $videodata['_id'];
        if (count($videodata) > 0) { $output['downloaded'] = $videodata;}

        $kfdata = $videodata = Entity::where('type','keyframe')->whereIn('parents',[$videofile,$videofileid])->get()->toArray();
        if (count($kfdata) > 0) {$output['keyframes'] = $kfdata; }
        
        return View::make('media.processvideo.pages.index')->with('data',$output);
    }
    
    public function getDownloadFile()
    {

        ini_set('memory_limit','256M');
        $getunit = Input::get('videounit');

        $videounit = Entity::where('_id',$getunit)->first()->toArray();
        $videourl = $videounit['content']['url'];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $videourl,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_FOLLOWLOCATION => 1,));

        $extension = explode(".",$videourl);
        $extension = array_pop($extension);

        $targetfilename = str_replace("/",".",$getunit);
        $storagedir = 'videostorage/fullvideos/'.$targetfilename . '.' . $extension;
        $targetdownload = storage_path($storagedir);

        $curldata = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpcode != '200') $this->echoError("The file could not be downloaded.");

        $fwh = @fopen($targetdownload, "w");
        @fwrite($fwh, $curldata);
        @fclose($fwh);
        curl_close($curl);
        if (!$fwh) $this->echoError("The file could not be saved to local storage.");

        $newfile = new Unit();
        $newfile->parents = [$getunit];
        $newfile->type = "downloadedvideo";
        $newfile->downloadlocation = $storagedir;
        $newfile->project = $videounit['project'];
        $newfile->save();


        $this->echoSuccess("Successfully downloaded the file to local storage.");
    }

    public function getProcessKeyframes()
    {
        $getunit = Input::get('videounit');
        $ffmpegbinary = app_path('ffmpeg');
        $unit = Entity::where('type','downloadedvideo')->whereIn('parents',[$getunit])->first()->toArray();
        $videofile = storage_path($unit['downloadlocation']);
        $videofileunit = $unit['_id'];

        $scenetreshold = "5";

        $outdir = storage_path('videostorage/keyframes/'.$getunit.'/');

        $res = @mkdir($outdir,0777,true);
        if (!$res) $this->echoError("Could not create output directory.");

        $buildcmd = "$ffmpegbinary -i $videofile -vf select=\"gt(scene\,0.".$scenetreshold.")\" -vsync 2 ".$outdir."frame%07d.png -loglevel debug 2>&1 | grep \"select:1\" | cut -d \" \" -f 6 - >".$outdir."frametimes.out";
        $out = shell_exec($buildcmd);

        $fhtimes = fopen($outdir."frametimes.out","r");
        $count = 0;
        while (($curft = fgets($fhtimes)) !== false)
        {
            $curframename = sprintf("frame%07d.png",$count);
            $curframefileloc = $outdir . $curframename;
            $curtime = explode(":",$curft);
            $curtime = array_pop($curtime);

            $newkfunit = new Unit();
            $newkfunit->parents = [$getunit,$videofileunit];
            $newkfunit->frames = [$curframefileloc];
            $newkfunit->project = $unit['project'];
            $newkfunit->scenestart = ''.$curtime.'';
            $newkfunit->type = "keyframe";
            $newkfunit->save();
        }

        $this->echoSuccess("Keyframes successfully extracted and added to database.");

    }

    private function echoError($msg)
    {
        $output['status'] = "error";
        $output['message'] = $msg;

        echo json_encode($output);
        exit;
    }

    private function echoSuccess($msg)
    {
        $output['status'] = "success";
        $output['message'] =  $msg;

        echo json_encode($output);
    }

}