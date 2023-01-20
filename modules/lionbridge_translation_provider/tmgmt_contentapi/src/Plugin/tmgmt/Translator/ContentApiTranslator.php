<?php

namespace Drupal\tmgmt_contentapi\Plugin\tmgmt\Translator;

use Drupal\Component\Utility\Xss;

use Drupal\Core\Entity\Entity;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobInterface;

use Drupal\tmgmt\Translator\TranslatableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;

use Drupal\tmgmt_contentapi\Plugin\tmgmt_contentapi\Format\Xliff;
use Drupal\tmgmt_contentapi\Util\GeneralHelper;

use ZipArchive;

use \SplFileObject;

use Drupal\tmgmt_contentapi\Swagger\Client\Model\ProviderId;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateRequestUpdateTM;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateRequestFile;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateToken;

use Drupal\tmgmt_contentapi\Swagger\Client\Api\TokenApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\TranslationMemoryApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\JobApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\RequestApi;

use Drupal\tmgmt_contentapi\Swagger\Client\Api\SourceFileApi;
use Drupal\tmgmt_contentapi\Util\ConentApiHelper;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Content API Translator.
 *
 * @TranslatorPlugin(
 *   id = "contentapi",
 *   label = @Translation("Lionbridge Content API Connector"),
 *   logo = "icons/lionbridge.png",
 *   description = @Translation("Provider to send content to Lionbridge"),
 *   ui = "Drupal\tmgmt_contentapi\ContentApiTranslatorUI"
 * )
 */
class ContentApiTranslator extends TranslatorPluginBase{

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function checkTranslatable(TranslatorInterface $translator, JobInterface $job) {
    // Anything can be exported.
    return TranslatableResult::yes();
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function abortTranslation(JobInterface $job) {
    // Assume that we can abort a translation job at any time.
    /*
    try {
      $token = ConentApiHelper::generateToken($job->getTranslator());
      $jobid = GeneralHelper::getCpJobIdfromLocJob($job);
      $jobapi = new JobApi();
      $jobapi->jobsJobIdDelete($token,$jobid);
      $job->aborted();
      $job->addMessage(t("Content API Job deleted:") . $jobid);
      \Drupal::messenger()->addMessage(t("Content API Job deleted:") . $jobid);
      return TRUE;
    }
    catch (\Exception $exception) {
      $job->addMessage(t('Error occured while deleting content api job, please contact the responsible project manager: ') . $exception->getMessage(), null, 'error');
      $job->aborted();
      return TRUE;
    }
    */
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function requestTranslation(JobInterface $job) {

    $jobtranslator = $job->getTranslator();
    $jobtranslatorId = $jobtranslator->getPluginId();
    // modify operations only for contentapi translator
    if ($jobtranslatorId == 'contentapi') {
      $source = $this->requestTranslationSource($job);
    }
    
    //return $source;
  }

  private function requestTranslationSource(JobInterface $job) {
    // Defined here as they will be used in catch block.
    $createdcpjob = NULL;
    $token = NULL;
    $zipPath = NULL;
    $ziparchive = NULL;
    $jobapi = NULL;
    $allfilespath = NULL;
    try {
      // Message which will be displayed using drupa_set_message to disaplay download.
      $messageTopass = 'Exported files can be downloaded here: <br/>';
      $translator = $job->getTranslator();
      $contentapisettings = $translator->getSetting("capi-settings");
      $oneexportfile = $translator->getSetting("one_export_file");
      $capisettings = $translator->getSetting('capi-settings');
      $capi = new TokenApi();
      $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
      if ($job->getSetting('capi-settings')["task"] == "trans") {
        // Export files: gernerate paths and other variables.
        $exporter = new Xliff();
        $filesystem = \Drupal::service('file_system');
        $joblabel = GeneralHelper::getJobLabelNoSpeChars($job);
        $dirnameallfiles = $joblabel . '_' . $job->id() . "_" . $job->getRemoteSourceLanguage() . "_" . $job->getRemoteTargetLanguage();
        $zipName = 'zip_job_' . $dirnameallfiles . '.zip';
        $allfilespath = $job->getSetting('scheme') . '://tmgmt_contentapi/LioxSentFiles/' . $dirnameallfiles;
        $zipPath = $allfilespath . "/" . $zipName;
        $filearraytodelte = array();
        $filearrayexportedfiles = array();
        $transferfiles = array();
        
        // Create folder where all exported files will be stored.
        if (\Drupal::service('file_system')->prepareDirectory($allfilespath, FileSystemInterface::CREATE_DIRECTORY)) {
          // Export each item of the job in same file.
          if($oneexportfile){
            $labelname = $joblabel;
            $name = $labelname . "_" . $job->id() . "_all_"  . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
            $jobpath = $allfilespath . "/" . $name;
            $file = file_save_data($exporter->export($job), $jobpath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
            $filearraytodelte[] = $file;
            $filearrayexportedfiles['all'] = $file;
          }
          else {
            // Export each item of the job in separate file.
            foreach ($job->getItems() as $item) {
              $labelname = GeneralHelper::getStringNoSpeChars($item->label());
              $name = $labelname . "_" . $job->id() . "_" . $item->id() . "_" . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
              $itempath = $allfilespath . "/" . $name;
              $file = file_save_data($exporter->exportItem($item), $itempath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
              $filearraytodelte[] = $file;
              $filearrayexportedfiles[$item->id()] = $file;
            }
          }
          // Zip the exported files.
          $ziparchive = new ZipArchive();
          $openresult = $ziparchive->open($filesystem->realpath($zipPath), ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
          $zipcloseresult = FALSE;
          if ($openresult) {
            foreach ($filearrayexportedfiles as $tempfile) {
              $ziparchive->addFile($filesystem->realpath($tempfile->getFileUri()), $tempfile->getFilename());
            }
            $zipcloseresult = $ziparchive->close();
            if ($zipcloseresult) {
              $zipfileobj = GeneralHelper::createFileObject($zipPath);
              \Drupal::service('file.usage')->add($zipfileobj, 'tmgmt_contentapi', 'tmgmt_job', $job->id());
            }
          }
          else {

          }
          // If exported files are transfered as zip, delete org. exports as already in the zip.
          if ($job->getTranslator()->getSetting('transfer-settings')) {
            // Add zip to transfer array.
            $transferfiles['all'] = GeneralHelper::createFileObject($zipPath);
            // foreach ($filearrayexportedfiles as $tempfile) {
            //   \Drupal::service('file_system')->delete($tempfile->getFileUri());
            // }
          }
          else {
            // All exported files added to transfer array.
            $transferfiles = $filearrayexportedfiles;
          }
          // Create Job in Content API.
          $contentapibundle = array();
          $jobrequest = ConentApiHelper::genrateJobRequst($job);
          $jobapi = new JobApi();
          $createdcpjob = NULL;
          try {
            $createdcpjob = $jobapi->jobsPost($token, $jobrequest);
          }
          catch (\Exception $e) {
            $msg = $e->getMessage();
            if(strlen($msg) > 200){
                $msg = substr($msg, 0, 200);
            }
            $job->addMessage($msg, array(), 'error');
            //$job->addMessage($e->getMessage(), array(), 'error');
            return;
          }
          // Upload files from transferfiles array to CPA.
          $fileapi = new SourceFileApi();
          $transrequestapi = new RequestApi();
          // Array to store temporally translation requst and associated files.
          $contentapitrrequstfiles = array();
          foreach ($transferfiles as $id => $tmpfile) {
            $data = array();
            $data["job_id"] = $createdcpjob->getJobId();
            $data["filename"] = $tmpfile->getFilename();
            $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            $extpath = $stmrg->realpath();
            $zip = new ZipArchive;
            if ($zip->open($extpath) === TRUE) {
                $zip->extractTo($allfilespath);
                $zip->close(); 
            }
            $data["filetype"] = $tmpfile->getMimeType();
            $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            $extpath = $stmrg->realpath();
            $filrequst = new SplFileObject($extpath);
            
            // file upload to TUS CLINET
            $name = $labelname . "_" . $job->id() . "_all_"  . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
            $jobpath =$job->getTranslator()->getSetting('transfer-settings') ? $extpath :  $allfilespath . "/" . $name;
            $contentapitmpfile = $fileapi->jobsJobIdUploadPost($token, $data["job_id"], $data["filename"], $data["filetype"], $filrequst);

            // $tus_client = $fileapi->tusClient($contentapitmpfile, $jobpath);
            $uploadedFile = $fileapi->uploadFile($contentapitmpfile, $jobpath);


            $request = new CreateRequestFile(array(
              "request_name" => $job->id() . "_" . $id . "_" .  $job->getRemoteSourceLanguage() . "-" . $job->getRemoteTargetLanguage(),
              "source_native_id" => $job->id() . "_" . $id,
              "source_native_language_code" => $job->getRemoteSourceLanguage(),
              "target_native_language_codes" => array($job->getRemoteTargetLanguage()),
              "file_id" => $contentapitmpfile['fms_file_id'],
            ));
            $contentapitmprequst = $transrequestapi->jobsJobIdRequestsAddfilePost($token, $data["job_id"], $request);
            $contentapibundle[] = $contentapitmprequst;
            $filrequst = NULL;
            
          }
          try{
            $ref = $this->requestTranslationRef($job, $createdcpjob);
          }catch(Exception $exc){
          
          }
          // Submit content api job.
          $pr = $job->getSetting("capi-settings")["provider"];
          $jid = $createdcpjob->getJobId();
          $prmodel = new ProviderId(array('provider_id' => $pr));
          $jobsubmitresult = $jobapi->jobsJobIdSubmitPut($token, $jid, $prmodel);
          if ($jobsubmitresult) {
            // TODO
            // Add content api bundle to job's settings.
            GeneralHelper::addCpaSettingsToJob($job, serialize($contentapibundle));
            $messageTopass .= '<a href="' . file_create_url($zipPath) . '">' . Xss::filter(GeneralHelper::getJobLabelNoSpeChars($job)) . '</a>';
            \Drupal::messenger()->addMessage(\Drupal\Core\Render\Markup::create($messageTopass));
            if($job->getSetting('capi-settings')['quote']['is_quote']){
              $job->submitted("This job was submitted for a quote. To submit your job for processing, you must log into your translation provider's system to approve this quote.");
            }
            else{
              $job->submitted("Job sent to provider!");
            }


            // Delete files after sending to Provider.
            foreach ($filearraytodelte as $tempfile) {
              \Drupal::service('file_system')->delete($tempfile->getFileUri());
            }
            $projectInfo = $jobsubmitresult;

            \Drupal\tmgmt_contentapi\Util\ConentApiHelper::capiJobSetStoredData($job, ['jobId' => $projectInfo->getJobId() ,'jobStatus' => $projectInfo->getStatusCode(), 'providerId' => $projectInfo->getProviderId(), 'jobType' => 2]);
            
          }
        }
        else {
          throw new \Exception("Could not create directory for export: " . $allfilespath);
        }
      }
      else {
        // Export files: gernerate paths and other variables.
        $exporter = new Xliff();
        $filesystem = \Drupal::service('file_system');
        $joblabel = GeneralHelper::getJobLabelNoSpeChars($job);
        $dirnameallfiles = $joblabel . '_tmupdate' . $job->id() . "_" . $job->getRemoteSourceLanguage() . "_" . $job->getRemoteTargetLanguage();
        $zipName = 'zip_job_' . $dirnameallfiles . '.zip';
        $allfilespath = $job->getSetting('scheme') . '://tmgmt_contentapi/LioxSentFiles/' . $dirnameallfiles;
        $zipPath = $allfilespath . "/" . $zipName;
        $filearraytodelte = array();
        $filearrayexportedfiles = array();
        $transferfiles = array();
        // Create folder where all exported files will be stored.
        if (\Drupal::service('file_system')->prepareDirectory($allfilespath, FileSystemInterface::CREATE_DIRECTORY)) {
          // Export each item of the job in same file.
          if($oneexportfile){
            $labelname = $joblabel;
            $name = $labelname . "_" . $job->id() . "_all_"  . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
            $jobpath = $allfilespath . "/" . $name;
            $file = file_save_data($exporter->export($job), $jobpath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
            $filearraytodelte[] = $file;
            $filearrayexportedfiles['all'] = $file;
          }
          else {
            // Export each item of the job in separate file.
            foreach ($job->getItems() as $item) {
              $labelname = GeneralHelper::getStringNoSpeChars($item->label());
              $name = $labelname . "_" . $job->id() . "_" . $item->id() . "_" . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
              $itempath = $allfilespath . "/" . $name;
              $file = file_save_data($exporter->exportItem($item), $itempath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
              $filearraytodelte[] = $file;
              $filearrayexportedfiles[$item->id()] = $file;
            }
          }
          // Zip the exported files.
          $ziparchive = new ZipArchive();
          $openresult = $ziparchive->open($filesystem->realpath($zipPath), ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
          $zipcloseresult = FALSE;
          if ($openresult) {
            foreach ($filearrayexportedfiles as $tempfile) {
              $ziparchive->addFile($filesystem->realpath($tempfile->getFileUri()), $tempfile->getFilename());
            }
            $zipcloseresult = $ziparchive->close();
            if ($zipcloseresult) {
              $zipfileobj = GeneralHelper::createFileObject($zipPath);
              \Drupal::service('file.usage')->add($zipfileobj, 'tmgmt_contentapi', 'tmgmt_job', $job->id());
            }
          }
          else {

          }

          $transferfiles = $filearrayexportedfiles;

          // Create Job in Content API.
          $contentapibundle = array();
          $jobrequest = ConentApiHelper::genrateJobRequst($job);
          $jobapi = new JobApi();
          $createdcpjob = NULL;
          try {
            $createdcpjob = $jobapi->jobsPost($token, $jobrequest);
          }
          catch (\Exception $e) {
            $msg = $e->getMessage();
            if(strlen($msg) > 200){
                $msg = substr($msg, 0, 200);
            }
            $job->addMessage($msg, array(), 'error');
            return;
          }
          // Upload files from transferfiles array to CPA.
          $fileapi = new SourceFileApi();
          $transrequestapi = new RequestApi();
          // Array to store temporally translation requst and associated files.
          $contentapitrrequstfiles = array();
          foreach ($transferfiles as $id => $tmpfile) {
            $data = array();
            $data["job_id"] = $createdcpjob->getJobId();
            $data["filename"] = $tmpfile->getFilename();
            $data["filetype"] = $tmpfile->getMimeType();
            $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            $extpath = $stmrg->realpath();
            $filrequst = new SplFileObject($extpath);
            $contentapitmpfile = $fileapi->jobsJobIdUploadPost($token, $data["job_id"], $data["filename"], $data["filetype"], $filrequst);
            $uploadedFile = $fileapi->uploadFile($contentapitmpfile, $extpath);

            $request = new CreateRequestFile(array(
              "request_name" => $job->id() . "_" . $id . "_" .  $job->getRemoteSourceLanguage() . "-" . $job->getRemoteTargetLanguage(),
              "source_native_id" => $job->id() . "_" . $id,
              "source_native_language_code" => $job->getRemoteSourceLanguage(),
              "target_native_language_codes" => array($job->getRemoteTargetLanguage()),
              "file_id" => $contentapitmpfile['fms_file_id'],
            ));
            $contentapitmprequst = $transrequestapi->jobsJobIdRequestsAddfilePost($token, $data["job_id"], $request);
            $contentapibundle[] = $createdcpjob;
          }
          try{
            $ref = $this->requestTranslationRef($job, $createdcpjob);
          }catch(Exception $exc){
          
          }
          // Submit content api job.
          $pr = $job->getSetting("capi-settings")["provider"];
          $jid = $createdcpjob->getJobId();
          $prmodel = new ProviderId(array('provider_id' => $pr));
          $jobsubmitresult = $jobapi->jobsJobIdSubmitPut($token, $jid, $prmodel);
          if ($jobsubmitresult) {
            // TODO
            GeneralHelper::addCpaSettingsToJob($job, serialize($contentapibundle));
            $messageTopass .= '<a href="' . file_create_url($zipPath) . '">' . Xss::filter(GeneralHelper::getJobLabelNoSpeChars($job)) . '</a>';
            \Drupal::messenger()->addMessage(\Drupal\Core\Render\Markup::create($messageTopass));
            //set job to finished
            $job->setState(Job::STATE_FINISHED);
            foreach ($job->getItems() as $item){
              $item->setState(JobItem::STATE_ACCEPTED);

            }
            $job->submitted("TM update job sent to provider!");

            // Delete files after sending to Provider.
            foreach ($filearraytodelte as $tempfile) {
              \Drupal::service('file_system')->delete($tempfile->getFileUri());
            }
          }
        }
        else {
          throw new \Exception("Could not create directory for export: " . $allfilespath);
        }
      }
    }
    catch (\Exception $exception) {
      // If exception occurs, clean up everything: delete exported files, cancel job in CA if any.
      foreach ($filearraytodelte as $tempfile) {
        if (file_exists($tempfile->getFileUri())) {
          \Drupal::service('file_system')->delete($tempfile->getFileUri());
        }
      }
      if (!file_exists($zipPath) && $ziparchive != NULL) {
        $ziparchive->close();
      }
      $job->setState(Job::STATE_UNPROCESSED);
      try {
        if (isset($createdcpjob)) {
          // TODO: check why result is NULL, only at certain state job can be deleted? But seems to work!!
          $deletresult = $jobapi->jobsJobIdDelete($token, $createdcpjob->getJobId());
        }
      }
      catch (\Exception $ex) {
        $msg = t('Tried to delete Job in Content API, but failed: ' . $ex->getMessage());
        if(strlen($msg) > 200){
            $msg = substr($msg,0,200);
        }
        \Drupal::messenger()->addMessage($msg, "error");
      }
      // TODO: Check why zip cannot be deleted. But after two tests seems to work?
      $zipfileobj = GeneralHelper::createFileObject($zipPath);
      \Drupal::service('file_system')->delete($zipfileobj->getFileUri());
      \Drupal::service('file_system')->deleteRecursive($allfilespath);

      $msg = $exception->getMessage();
      if(strlen($msg) > 200){
        $msg = substr($msg, 0, 200);
      }
      \Drupal::messenger()->addMessage($msg, 'error');
      //\Drupal::messenger()->addMessage($exception->getMessage(), "error");
    }
    return $createdcpjob;
  }

  private function requestTranslationRef(JobInterface $job, $createdcpjob ) {
    // Defined here as they will be used in catch block.
    //$createdcpjob = NULL;
    $token = NULL;
    $zipPath = NULL;
    $ziparchive = NULL;
    $jobapi = NULL;
    $allfilespath = NULL;
    try {
      // Message which will be displayed using drupa_set_message to disaplay download.
      $messageTopass = 'Exported files can be downloaded here: <br/>';
      $translator = $job->getTranslator();
      $contentapisettings = $translator->getSetting("capi-settings");
      $oneexportfile = $translator->getSetting("one_export_file");
      $capisettings = $translator->getSetting('capi-settings');
      $capi = new TokenApi();
      $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
      if ($job->getSetting('capi-settings')["task"] == "trans") {
        // Export files: gernerate paths and other variables.
        $exporter = new Xliff();
        $filesystem = \Drupal::service('file_system');
        $joblabel = GeneralHelper::getJobLabelNoSpeChars($job);




        $dirnameallfiles = $joblabel . '_' . $job->id() . "_" . $job->getRemoteSourceLanguage() . "_" . $job->getRemoteTargetLanguage();
        $zipName = 'zip_ref_' . $dirnameallfiles . '.zip';
        $allfilespath = $job->getSetting('scheme') . '://tmgmt_contentapi/LioxRefFiles/' . $dirnameallfiles;
        $zipPath = $allfilespath . "/" . $zipName;
        $filearraytodelte = array();
        $filearrayexportedfiles = array();
        $transferfiles = array();
        // Create folder where all exported files will be stored.
        if (\Drupal::service('file_system')->prepareDirectory($allfilespath, FileSystemInterface::CREATE_DIRECTORY)) {
          // Export each item of the job in same file.
         
            foreach ($job->getItems() as $item) {
              $labelname = GeneralHelper::getStringNoSpeChars($item->label());
              $name = $labelname . "_" . $job->id() . "_" . $item->id() . "_" . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
              $itempath = $allfilespath . "/" . $name;

              //node id
              $nodeid = $item->getFields()["item_id"][0]->getValue()["value"];
              //to get target id
              if(\Drupal\node\Entity\Node::load($nodeid) == null){
                continue;
              }
              if(!\Drupal\node\Entity\Node::load($nodeid)->hasField("field_reference")){
                continue;
              }

              if(sizeof(\Drupal\node\Entity\Node::load($nodeid)->getFields()["field_reference"]->getValue()) == 0){
                continue;
              }
              
              //to get target id
              $targetid = \Drupal\node\Entity\Node::load($nodeid)->getFields()["field_reference"]->getValue()[0]["target_id"];
              
              if($targetid == null)
              {
                continue;
              }

              //ref url
              $refFile = \Drupal\file\Entity\File::load($targetid);

              //$file = file_save_data($exporter->exportItem($item), $itempath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
              //$filearraytodelte[] = $file;
              $filearrayexportedfiles[$item->id()] = $refFile;
            }

         if(!empty($filearrayexportedfiles))
         {
            // Zip the exported files.
            $ziparchive = new ZipArchive();
            $openresult = $ziparchive->open($filesystem->realpath($zipPath), ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
            $zipcloseresult = FALSE;
            $addedFiles = 0;
            if ($openresult) {
            foreach ($filearrayexportedfiles as $tempfile) {
                if($tempfile == null || $tempfile->getFileUri() == null || strlen($tempfile->getFileUri()) == 0)
                {
                    continue;
                }
                $ziparchive->addFile($filesystem->realpath($tempfile->getFileUri()), $tempfile->getFilename());
                $addedFiles = $addedFiles + 1;
            }
            $zipcloseresult = false;
            if($addedFiles > 0){
                $zipcloseresult = $ziparchive->close();
            }
            if ($zipcloseresult) {
                $zipfileobj = GeneralHelper::createFileObject($zipPath);
                \Drupal::service('file.usage')->add($zipfileobj, 'tmgmt_contentapi', 'tmgmt_job', $job->id());
            }
            }
         }
          // If exported files are transfered as zip, delete org. exports as already in the zip.
          if ($job->getTranslator()->getSetting('transfer-settings')) {
            // Add zip to transfer array.
            $transferfiles['all'] = GeneralHelper::createFileObject($zipPath);
            // foreach ($filearrayexportedfiles as $tempfile) {
            //     \Drupal::service('file_system')->delete($tempfile->getFileUri());
            // }
          }
          else {
            // All exported files added to transfer array.
            $transferfiles = $filearrayexportedfiles;
          }
          // Create Job in Content API.
          $contentapibundle = array();
          //$jobrequest = ConentApiHelper::genrateJobRequst($job);
          $jobapi = new JobApi();
          //$createdcpjob = NULL;
          // Upload files from transferfiles array to CPA.
          $fileapi = new SourceFileApi();
          $transrequestapi = new RequestApi();
          // Array to store temporally translation requst and associated files.
          $contentapitrrequstfiles = array();
          foreach ($transferfiles as $id => $tmpfile) {
            $data = array();
            $data["job_id"] = $createdcpjob->getJobId();
            
            $data["filename"] = $tmpfile->getFilename();
            // $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            // $extpath = $stmrg->realpath();
            // $zip = new ZipArchive;
            // if ($zip->open($extpath) === TRUE) {
            //     $zip->extractTo($allfilespath);
            //     $zip->close(); 
            // }
            $data["filetype"] = $tmpfile->getMimeType();
            $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            $extpath = $stmrg->realpath();
            $filrequst = new SplFileObject($extpath);
            
            // file upload to TUS CLINET
            
            

            //STARTS UPLOAD SOURCE
            $name =  $data["filename"];//$labelname . "_" . $job->id() . "_all_"  . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
            // $jobpath = $allfilespath . "/" . $name;
            
            $contentapitmpfile = $fileapi->jobsJobIdUploadPost($token, $data["job_id"], $data["filename"], $data["filetype"], $filrequst);
            $uploadedFile = $fileapi->uploadFile($contentapitmpfile,/* $jobpath*/$extpath);
            $request = new CreateRequestFile(array(
                "request_name" => $job->id() . "_" . $id . "_" .  $job->getRemoteSourceLanguage() . "-" . $job->getRemoteTargetLanguage(),
                "source_native_id" => $job->id() . "_" . $id,
                "source_native_language_code" => $job->getRemoteSourceLanguage(),
                "target_native_language_codes" => array($job->getRemoteTargetLanguage()),
                "file_id" => $contentapitmpfile['fms_file_id'],
            ));
            $contentapitmprequst = $transrequestapi->jobsJobIdRequestsAddfilePost($token, $data["job_id"], $request, 2);
            $contentapibundle[] = $contentapitmprequst;
            $filrequst = NULL;              
            
          }

          
            // Delete files after sending to Provider.
          foreach ($filearraytodelte as $tempfile) {
            \Drupal::service('file_system')->delete($tempfile->getFileUri());
          }
        }
        else {
          throw new \Exception("Could not create directory for export: " . $allfilespath);
        }
      }
      else {
        // Export files: gernerate paths and other variables.
        $exporter = new Xliff();
        $filesystem = \Drupal::service('file_system');
        $joblabel = GeneralHelper::getJobLabelNoSpeChars($job);
        $dirnameallfiles = $joblabel . '_tmupdate' . $job->id() . "_" . $job->getRemoteSourceLanguage() . "_" . $job->getRemoteTargetLanguage();
        $zipName = 'zip_ref_' . $dirnameallfiles . '.zip';
        $allfilespath = $job->getSetting('scheme') . '://tmgmt_contentapi/LioxRefFiles/' . $dirnameallfiles;
        $zipPath = $allfilespath . "/" . $zipName;
        $filearraytodelte = array();
        $filearrayexportedfiles = array();
        $transferfiles = array();
        // Create folder where all exported files will be stored.
        if (\Drupal::service('file_system')->prepareDirectory($allfilespath, FileSystemInterface::CREATE_DIRECTORY)) {
          // Export each item of the job in same file.
          if($oneexportfile){
            $labelname = $joblabel;
            $name = $labelname . "_" . $job->id() . "_all_"  . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
            $jobpath = $allfilespath . "/" . $name;
            $file = file_save_data($exporter->export($job), $jobpath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
            $filearraytodelte[] = $file;
            $filearrayexportedfiles['all'] = $file;
          }
          else {
            // Export each item of the job in separate file.
            foreach ($job->getItems() as $item) {
              $labelname = GeneralHelper::getStringNoSpeChars($item->label());
              $name = $labelname . "_" . $job->id() . "_" . $item->id() . "_" . $job->getRemoteSourceLanguage() . '_' . $job->getRemoteTargetLanguage() . '.xlf';
              $itempath = $allfilespath . "/" . $name;
              $file = file_save_data($exporter->exportItem($item), $itempath, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
              $filearraytodelte[] = $file;
              $filearrayexportedfiles[$item->id()] = $file;
            }
          }
          // Zip the exported files.
          $ziparchive = new ZipArchive();
          $openresult = $ziparchive->open($filesystem->realpath($zipPath), ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
          $zipcloseresult = FALSE;
          $addedFiles = 0;
          if ($openresult) {
            foreach ($filearrayexportedfiles as $tempfile) {
                if($tempfile == null || $tempfile->getFileUri() == null || strlen($tempfile->getFileUri()) == 0){
                    continue;
                }
                $ziparchive->addFile($filesystem->realpath($tempfile->getFileUri()), $tempfile->getFilename());
                $addedFiles = $addedFiles + 1;
            }
            $zipcloseresult = false;
            if($addedFiles > 0){
                $zipcloseresult = $ziparchive->close();
            }
            if ($zipcloseresult) {
              $zipfileobj = GeneralHelper::createFileObject($zipPath);
              \Drupal::service('file.usage')->add($zipfileobj, 'tmgmt_contentapi', 'tmgmt_job', $job->id());
            }
          }

          $transferfiles = $filearrayexportedfiles;

          // Create Job in Content API.
          $contentapibundle = array();
          //$jobrequest = ConentApiHelper::genrateJobRequst($job);
          $jobapi = new JobApi();
          // Upload files from transferfiles array to CPA.
          $fileapi = new SourceFileApi();
          $transrequestapi = new RequestApi();
          // Array to store temporally translation requst and associated files.
          $contentapitrrequstfiles = array();
          foreach ($transferfiles as $id => $tmpfile) {
            $data = array();
            $data["job_id"] = $createdcpjob->getJobId();
            $data["filename"] = $tmpfile->getFilename();
            $data["filetype"] = $tmpfile->getMimeType();
            $stmrg = \Drupal::service('stream_wrapper_manager')->getViaUri($tmpfile->getFileUri());
            $extpath = $stmrg->realpath();
            $filrequst = new SplFileObject($extpath);


            $contentapitmpfile = $fileapi->jobsJobIdUploadPost($token, $data["job_id"], $data["filename"], $data["filetype"], $filrequst);
            $uploadedFile = $fileapi->uploadFile($contentapitmpfile, $extpath);
            
            $request = new CreateRequestFile(array(
              "request_name" => $job->id() . "_" . $id . "_" .  $job->getRemoteSourceLanguage() . "-" . $job->getRemoteTargetLanguage(),
              "source_native_id" => $job->id() . "_" . $id,
              "source_native_language_code" => $job->getRemoteSourceLanguage(),
              "target_native_language_codes" => array($job->getRemoteTargetLanguage()),
              "file_id" => $contentapitmpfile['fms_file_id'],
            ));
            $contentapitmprequst = $transrequestapi->jobsJobIdRequestsAddfilePost($token, $data["job_id"], $request,2);
            $contentapibundle[] = $createdcpjob;
          }
          // Submit content api job.
          $pr = $job->getSetting("capi-settings")["provider"];
          $jid = $createdcpjob->getJobId();
          $prmodel = new ProviderId(array('provider_id' => $pr));
          $jobsubmitresult = $jobapi->jobsJobIdSubmitPut($token, $jid, $prmodel);
          if ($jobsubmitresult) {
            // TODO
            GeneralHelper::addCpaSettingsToJob($job, serialize($contentapibundle));
            $messageTopass .= '<a href="' . file_create_url($zipPath) . '">' . Xss::filter(GeneralHelper::getJobLabelNoSpeChars($job)) . '</a>';
            \Drupal::messenger()->addMessage(\Drupal\Core\Render\Markup::create($messageTopass));
            //set job to finished
            $job->setState(Job::STATE_FINISHED);
            foreach ($job->getItems() as $item){
              $item->setState(JobItem::STATE_ACCEPTED);

            }
            $job->submitted("TM update job sent to provider!");

            // Delete files after sending to Provider.
            foreach ($filearraytodelte as $tempfile) {
                \Drupal::service('file_system')->delete($tempfile->getFileUri());
            }
          }
        }
        else {
          throw new \Exception("Could not create directory for export: " . $allfilespath);
        }
      }
    }
    catch (\Exception $exception) {
      // If exception occurs, clean up everything: delete exported files, cancel job in CA if any.
      foreach ($filearraytodelte as $tempfile) {
        if (file_exists($tempfile->getFileUri())) {
            \Drupal::service('file_system')->delete($tempfile->getFileUri());
        }
      }
      if (!file_exists($zipPath) && $ziparchive != NULL) {
        $ziparchive->close();
      }
      $job->setState(Job::STATE_UNPROCESSED);
      try {
        if (isset($createdcpjob)) {
          // TODO: check why result is NULL, only at certain state job can be deleted? But seems to work!!
          $deletresult = $jobapi->jobsJobIdDelete($token, $createdcpjob->getJobId());
        }
      }
      catch (\Exception $ex) {
        $msg = t('Tried to delete Job in Content API, but failed: ' . $ex->getMessage());
        if(strlen($msg) > 200){
            $msg = substr($msg,0,200);
        }
        \Drupal::messenger()->addMessage($msg, "error");
        //\Drupal::messenger()->addMessage("Tried to delete Job in Content API, but failed: " . $ex->getMessage(), "error");
      }
      $zipfileobj = GeneralHelper::createFileObject($zipPath);
      \Drupal::service('file_system')->delete($zipfileobj->getFileUri());
      \Drupal::service('file_system')->deleteRecursive($allfilespath);
      $msg = $exception->getMessage();
      if(strlen($msg) > 200){
        $msg = substr($msg, 0, 200);
      }
      \Drupal::messenger()->addMessage($msg, 'error');
      //\Drupal::messenger()->addMessage($exception->getMessage(), "error");
    }
  }

  //keep this as back-up method in case we want to add all jobItems in one File
  #[\ReturnTypeWillChange]
    public function requestTranslationOrg(JobInterface $job) {
    $name = "JobID" . $job->id() . '_' . $job->getSourceLangcode() . '_' . $job->getTargetLangcode();
    //$job->settings = null;
    $export = \Drupal::service('plugin.manager.tmgmt_fwconnector.format')->createInstance($job->getSetting('export_format'));
    //$testset = $job->settings["fw_projecname"];
    //$tesvar = $job->values["settings"];
    $path = $job->getSetting('scheme') . '://tmgmt_fwconnector/' . $name . '.' .  $job->getSetting('export_format');
    $dirname = dirname($path);

    if (\Drupal::service('file_system')->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY)) {
      $file = file_save_data($export->export($job), $path);
      \Drupal::service('file.usage')->add($file, 'tmgmt_fwconnector', 'tmgmt_job', $job->id());
      $job->submitted('Exported file can be downloaded <a href="@link">here</a>.', array('@link' => file_create_url($path)));
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function hasCheckoutSettings(JobInterface $job) {
    return $job->getTranslator()->getSetting('allow_override');
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function defaultSettings() {
    return array(
      'export_format' => 'contentapi_xlf',
      'allow_override' => TRUE,
      'scheme' => 'public',
      // Making this setting TRUE by default is more appropriate, however we
      // need to make it FALSE due to backwards compatibility.
      'xliff_processing' => FALSE,
      'xliff_cdata' => FALSE,
      'capi_username_ctt' => '',
      //'capi_password_ctt' => '',
      'one_export_file' => TRUE,
      'po_reference' => '',
      'transfer-settings' => FALSE
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function requestJobItemsTranslation(array $job_items) {
    // TODO: Implement requestJobItemsTranslation() method.

  }


}
