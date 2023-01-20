<?php

namespace Drupal\tmgmt_contentapi\Util;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;

use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateJob;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\ArrayOfRequestIds;

use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateToken;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\Request;

use Drupal\tmgmt_contentapi\Swagger\Client\Api\TokenApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\JobApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\RequestApi;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;


/**
 * CreateToken Class Doc Comment.
 *
 * @category Class
 * @package Drupal\tmgmt_contentapi\Util
 * @author Arben Sabani
 */
class ConentApiHelper {


  public static function capiJobGetStoredData(JobInterface $job)
  {
    $sub_query2 = \Drupal::database()->select('tmgmt_message', 'c');
    $sub_query2->addExpression("MAX(c.mid)", 'mid');
    $sub_query2->condition('c.type', 'jobinfo','=');
    $sub_query2->condition('c.tjid', $job->id(),'=');
    
    $sub_query3 = \Drupal::database()->select('tmgmt_message', 'd');
    $sub_query3->addField('d', 'message');
    $sub_query3->condition('d.mid', $sub_query2,'IN');
    $sub_query3->range(0,1);

    $qryResult2 = $sub_query3->execute()->fetchAll();

    if(count($qryResult2) == 0){
      return null;
    }
    $result = array_reverse($qryResult2);
    return json_decode(array_pop($result)->message);

  }
  public static function capiJobSetStoredData(JobInterface $job, array $elements)
  {
    $currInfo = ConentApiHelper::capiJobGetStoredData($job);
    
    if($currInfo != null){
      \Drupal::database()->update('tmgmt_message')
      ->fields([
        'message' => json_encode($elements)
      ])
      ->condition('type', 'jobInfo', '=')
      ->condition('tjid', $job->id(), '=')
      ->execute();
    }else{
      $job->addMessage(json_encode($elements), $variables = array(), $type = 'jobInfo');
    }
  }
  /**
   * Create content api objects from Job and translator.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   Job.
   *
   * @return \Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateJob
   *   Job.
   */
  public static function genrateJobRequst(JobInterface $job) {
    $source_lang = $job->getRemoteSourceLanguage();
    $target_lang = $job->getRemoteTargetLanguage();
    $shouldquote = (boolean) ($job->getSetting('capi-settings')['quote']['is_quote']);
    $capijobsettings = $job->getSetting("capi-settings");
    $jobarray = array(
      'job_name' => GeneralHelper::getJobLabelNoSpeChars($job),
      'description' => isset($capijobsettings["description"]) ? $capijobsettings["description"] : NULL,
      'po_reference' => isset($capijobsettings["po_reference"]) ? $capijobsettings["po_reference"] : NULL,
      'due_date' => isset($capijobsettings["due_date"]) ? $capijobsettings["due_date"] : NULL,
      'should_quote'=>$shouldquote,
      'source_lang_code' => $source_lang,
      'target_lang_code' => $target_lang,
      'connector_name' => 'Lionbridge Connector for Drupal 8 and 9',
      'connector_version' => 'V 2.5'
    );
    // TODO: Check with Dev why erro with costom dagta set.
    if (isset($capijobsettings["custom_data"]) && $capijobsettings["custom_data"] !== "") {
      $job['custom_data'] = $capijobsettings["custom_data"];
    }
    $jobrequest = new CreateJob($jobarray);
    return $jobrequest;
  }

  /**
   * Create content api token from translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   Job.
   *
   * @return string
   *   Token.
   */
  public static function generateToken(TranslatorInterface $translator) {
    return $translator->getSetting('capi-settings')['token'];
  }

  public static function checkJobFinishAndApproveRemote(JobInterface $job){
    $job = Job::load($job->id());
    $translator = $job->getTranslator();
    $allrequests = unserialize($job->getSetting('capi-remote'));
    if(isset($allrequests) && count($allrequests) == 1){
      $test = new Request();
      $arraywithrequests = $allrequests[0];
      if($job->getState() == JobInterface::STATE_FINISHED){
        try {
          $arrywithrequestIds = [];
          foreach ($arraywithrequests as $req) {
            $arrywithrequestIds[] = $req->getRequestId();
          }
          $capisettings = $translator->getSetting('capi-settings');
          $capi = new TokenApi();
          $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
          $jobapi = new JobApi();
          $requestapi = new RequestApi();
          $arrayruquest = new ArrayOfRequestIds();
          $arrayruquest->setRequestIds($arrywithrequestIds);
          $tokenobj = null;
          if($capisettings['capi_username_ctt1'] != '')
          {
            $tokenrequest = new CreateToken(array('username' => $capisettings['capi_username_ctt1'], 'password' => $capisettings['capi_password_ctt1']));
            try{
              $tokenobj = $capi->oauth2TokenPost($tokenrequest);
            }catch(Exception $ex){}
          }
          $jobapi = new JobApi();
          $find_job = $jobapi->FindJob($token, reset($arraywithrequests)->getJobId());
          if($find_job != 200)
          {
            if($capisettings['capi_username_ctt1'] == '')
            {
              return;
            }
            if(isset($tokenobj) != true){
              \Drupal::logger('TMGMT_CONTENTAPI')->warning("Invalid Content API V1 credentials found");
              return;
            }
            $requestapi->jobsJobIdRequestsApprovePutCtt($tokenobj['access_token'],reset($arraywithrequests)->getJobId(),$arrayruquest);
            
          }
          else
          {
            $requestapi->jobsJobIdRequestsApprovePut($token, reset($arraywithrequests)->getJobId(),$arrayruquest);
          }
          $job->addMessage(t('Remote job archived.'));
        }
        catch (\Exception $exception){
            $msg = t('Could not approve remote requests: ' . $exception->getMessage());
            if(strlen($msg) > 200){
                $msg = substr($msg,0,200);
            }
            \Drupal::messenger()->addMessage($msg, "error");
          //$job->addMessage(t('Could not approve remote requests: ' . $exception->getMessage()));
          return NULL;
        }
      }
    }
    return NULL;
  }

}
