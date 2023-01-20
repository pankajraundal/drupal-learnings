<?php

namespace Drupal\tmgmt_contentapi;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\contact\MessageInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\JobApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\RequestApi;

use Drupal\tmgmt_contentapi\Swagger\Client\ApiException;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\ArrayOfRequestIdsNote;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\ArrayOfRequestIds;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\Request;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\StatusCodeEnum;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Datetime;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateToken;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\TokenApi;
use Exception;
use Drupal\tmgmt_contentapi\Util\ConentApiHelper;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\ProviderApi;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\tmgmt_contentapi\Swagger\Client\Configuration;
use GuzzleHttp\Exception\RequestException;

/**
 * Freeway File translator UI.
 */
class ContentApiTranslatorUI extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $prevHost = '';
    $translator = $form_state->getFormObject()->getEntity();
    $capisettings = $translator->getSetting('capi-settings');
    $cronsettings = $translator->getSetting('cron-settings');
    $exportsettings = $translator->getSetting('export_format');
    $transfersettings = $translator->getSetting('transfer-settings');
    $tmpSettings = \Drupal::service('config.factory')->getEditable('tmgmt.translator.contentapi');
    if(array_key_exists("settings",$form_state->getUserInput()) && array_key_exists("capi-settings",$form_state->getUserInput()["settings"])){
      $capisettings["capi_username_ctt"] = $form_state->getUserInput()["settings"]["capi-settings"]["capi_username_ctt"];
      $capisettings["capi_password_ctt"] = $form_state->getUserInput()["settings"]["capi-settings"]["capi_password_ctt"];

      if($form_state->getUserInput()["settings"]["capi-settings"]["capi_username_ctt1"] != null && $form_state->getUserInput()["settings"]["capi-settings"]["capi_username_ctt1"] != ''){
          $capisettings["capi_username_ctt1"] = $form_state->getUserInput()["settings"]["capi-settings"]["capi_username_ctt1"];
          $capisettings["capi_password_ctt1"] = $form_state->getUserInput()["settings"]["capi-settings"]["capi_password_ctt1"];
      }
      
      if($form_state->getUserInput()["settings"]["capi-settings"]["capi_host"] != null && $form_state->getUserInput()["settings"]["capi-settings"]["capi_host"] != ''){

        $capisettings["capi_host"] = $form_state->getUserInput()["settings"]["capi-settings"]["capi_host"];

        if(
         
            $tmpSettings->get('settings.capi-settings') == null 
            || !array_key_exists('capi_host', $tmpSettings->get('settings.capi-settings'))
            || 
            (
              $capisettings["capi_host"] != "" 
              && 
              $tmpSettings->get('settings.capi-settings.capi_host') != $capisettings["capi_host"]
            )
        )
        {
          $prevHost = ($tmpSettings->get('settings.capi-settings.capi_host') != null ? $tmpSettings->get('settings.capi-settings.capi_host') : '');
          $tmpSettings->set('settings.capi-settings.capi_host',$capisettings["capi_host"]);
          $tmpSettings->save();
          //\Drupal::config('tmgmt.translator.contentapi')->set('settings',$tmpSettings);
        }
      }
      
    }
  
    $token = '';
    $capi = new TokenApi();
    if($capisettings['capi_username_ctt'] != '')
    {
      try{
        $token = $capi->getToken($capisettings["capi_username_ctt"], $capisettings["capi_password_ctt"]);
      }catch(Exception $ew){}
    }
    
    
    $form['export_format'] = array(
      '#type' => 'radios',
      '#title' => t('Export to'),
      '#options' => \Drupal::service('plugin.manager.tmgmt_contentapi.format')->getLabels(),
      '#default_value' => isset($exportsettings) ? $exportsettings : "contentapi_xlf",
      '#description' => t('Select the format for exporting data.'),
    );
    $form['xliff_cdata'] = array(
      '#type' => 'checkbox',
      '#title' => t('XLIFF CDATA'),
      '#description' => t('Select to use CDATA for import/export.'),
      '#default_value' => $translator->getSetting('xliff_cdata'),
    );

    $form['xliff_processing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Extended XLIFF processing'),
      '#description' => t('Select to further process content semantics and mask HTML tags instead of just escaping them.'),
      '#default_value' => $translator->getSetting('xliff_processing'),
    );

    $form['xliff_message'] = array(
      '#type' => 'container',
      '#markup' => t('By selecting CDATA option, XLIFF processing will be ignored.'),
      '#attributes' => array(
        'class' => array('messages messages--warning'),
      ),
    );

    $form['allow_override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow export-format overrides'),
      '#default_value' => $translator->getSetting('allow_override'),
    );

    $form['one_export_file'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use one export file for all items in job'),
      '#description' => t('Select to export all items to one file. Clear to export items to multiple files.'),
      '#default_value' => $translator->getSetting('one_export_file'),
    );

    // Any visible, writeable wrapper can potentially be used for the files
    // directory, including a remote file system that integrates with a CDN.
    foreach (\Drupal::service('stream_wrapper_manager')->getDescriptions(StreamWrapperInterface::WRITE_VISIBLE) as $scheme => $description) {
      $options[$scheme] = Html::escape($description);
    }

    if (!empty($options)) {
      $form['scheme'] = array(
        '#type' => 'radios',
        '#title' => t('Download method'),
        '#default_value' => $translator->getSetting('scheme'),
        '#options' => $options,
        '#description' => t('Choose where  to store exported files. Recommendation: Use a secure location to prevent unauthorized access.'),
      );
    }

    $form['capi-settings'] = array(
      '#type' => 'details',
      '#title' => t('Lionbridge Content API Settings'),
      '#open' => TRUE,
    );

    $form['capi-settings']['po_reference'] = array(
      '#type' => 'textfield',
      '#title' => t('PO Number'),
      '#required' => FALSE,
      '#description' => t('Enter your Lionbridge purchase order number.'),
      '#default_value' => $capisettings['po_reference'],
    );

    $form['capi-settings']['capi_username_ctt'] = array(
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#required' => TRUE,
      '#description' => t('Enter your Lionbridge client id.'),
      '#default_value' => $capisettings["capi_username_ctt"],
    );

    $form['capi-settings']['capi_password_ctt'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Secret ID'),
      '#required' => TRUE,
      '#description' => t('Enter your Lionbridge client secret id.'),
      '#default_value' => $capisettings["capi_password_ctt"],
    );

    $form['capi-settings']['capi_username_ctt1'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      // '#required' => TRUE,
      '#description' => t('Enter your Lionbridge username.'),
      '#default_value' => $capisettings['capi_username_ctt1'],
    );

    $form['capi-settings']['capi_password_ctt1'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      // '#required' => TRUE,
      '#description' => t('Enter your Lionbridge password.'),
      '#default_value' => $capisettings['capi_password_ctt1'],
    );

    $form['capi-settings']['capi_host'] = array(
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#required' => TRUE,
      '#description' => t('Default value: https://contentapi.lionbridge.com/v2 <br> Staging value: https://content-api.staging.lionbridge.com/v2'),
      '#default_value' => $capisettings['capi_host'],
    );

    $form['capi-settings'] += parent::addConnectButton();

    /*$form['capi-settings']['token'] = array(
      '#type' => 'hidden',
      '#value' => (isset($token) && $token != "") ? $token : NULL
    );*/


    $providers = NULL;
    if(isset($token) && $token != '') {
      try {
        $providerapi = new ProviderApi();
        $providers = $providerapi->providersGet($token);
        if($prevHost != ''){
          $tmpSettings->set('settings.capi-settings.capi_host',$prevHost);
          $tmpSettings->save();
        }
      } catch (Exception $e) {
        //$msg = 'Please verify that the Content API Host is correct Settings: ' . $e->getMessage();
        //\Drupal::messenger()->addMessage($msg, "error");
        //$form_state->setErrorByName('settings][capi-settings][capi_host', $msg);
      }
    }
    $providersarray = array();
    foreach ($providers as $provider) {
      $prid = $provider->getProviderId();
      $prname = $provider->getProviderName();
      $providersarray[$prid] = $prname;
    }
    asort($providersarray, SORT_REGULAR);
    $defaultprovidervalue = isset($capisettings['provider']) ? $capisettings['provider'] : NULL;
    $form['capi-settings']['provider'] = array(
      '#type' => 'select',
      '#title' => t('Provider configuration'),
      '#required' => FALSE,
      '#options' => $providersarray,
      '#default_value' => $defaultprovidervalue,
      '#description' => t('Please select a Provider for your project.'),
    );

    $form['capi-settings']['allow_provider_override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow provider overrides'),
      '#default_value' => $translator->getSetting('capi-settings')['allow_provider_override'],
    );

    $form['transfer-settings'] = array(
      '#type' => 'checkbox',
      '#title' => t('Transfer all files as zip'),
      '#description' => t('Select to transfer all exported files for a job as a .zip file.'),
      '#default_value' => $transfersettings,
    );

    $form['cron-settings'] = array(
      '#type' => 'details',
      '#title' => t('Scheduled Tasks'),
      '#description' => t('Specify settings for scheduled tasks.'),
      '#open' => TRUE,
    );

    $form['cron-settings']['status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Auto Import Job'),
      '#description' => t('Select to auto import job, by scheduled task. Clear to download translated jobs manually.'),
      '#default_value' => $cronsettings['status'],
    );

    /*$form['cron-settings']['exec_time'] = array(
      '#type' => 'number',
      '#title' => 'Cron execution time',
      '#default_value' => isset($cronsettings['exec_time']) ? isset($cronsettings['exec_time']) : 180,
    );*/

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {
    $valid_provider = $this->getValidProvider($form, $form_state, $job);
    $translator = $job->getTranslator();
    $capisettings = $translator->getSetting('capi-settings');
    $jobcapisettings = $job->getSetting("capi-settings");
    $exportsettingstranslator = $job->getTranslator()->getSetting('export_format');
    $exportsettings = null;
    if($job->getSetting('exports-settings') != null && array_key_exists('cpexport_format', $job->getSetting('exports-settings')))
    {
        $exportsettings = $job->getSetting('exports-settings')['cpexport_format'];
    }
    $allowprovideroverride = $capisettings['allow_provider_override'];
    $dt = DrupalDateTime::createFromTimestamp(time());
    $date = $dt->modify('+ 4 hour');
    $form['exports-settings'] = array(
      '#type' => 'details',
      '#title' => t('Export Settings'),
      '#open' => TRUE,
    );
    $form['exports-settings']['cpexport_format'] = array(
      '#type' => 'radios',
      '#title' => t('Export to'),
      '#options' => \Drupal::service('plugin.manager.tmgmt_contentapi.format')->getLabels(),
      '#default_value' => isset($exportsettings) ? $exportsettings : $exportsettingstranslator,
      '#description' => t('Select the format for exporting data.'),
    );
    $form['capi-settings'] = array(
      '#type' => 'details',
      '#title' => t('Content API Job Details'),
      '#open' => TRUE,
    );

    $form['capi-settings']['po_reference'] = array(
      '#type' => 'textfield',
      '#title' => t('PO Reference'),
      '#required' => FALSE,
      '#description' => t('Please enter your PO Reference'),
      '#default_value' => isset($jobcapisettings["po_reference"]) ? $jobcapisettings["po_reference"] : $capisettings['po_reference'],
    );


    $form['capi-settings']['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#required' => FALSE,
      '#description' => t('Please enter a description for the job.'),
      '#default_value' => isset($jobcapisettings["description"]) ? $jobcapisettings["description"] : '',
    );
    $form['capi-settings']['due_date'] = array(
      '#type' => 'datetime',
      '#title' => t('Expected Due Date'),
      '#required' => FALSE,
      '#date_date_format' => 'Y-m-d',
      '#date_time_format' => 'H:i',
      '#description' => t('Please enter the expected due date.'),
      '#default_value' => isset($jobcapisettings["due_date"]) ? $jobcapisettings["due_date"] : $date,

    );
    $form['capi-settings']['task'] = array(
      '#type' => 'select',
      '#title' => t('Task'),
      '#options' => array("trans" => "Translation"), //, "tm" => "TM Update"),
      '#default_value' => isset($jobcapisettings["task"]) ? $jobcapisettings["task"] : 'trans',
      '#description' => t('Please select a task for your project.'),
    );
    $capi = new TokenApi();
    $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
    $providers = NULL;

    try {
      $providerapi = new ProviderApi();
      $providers = $providerapi->providersGet($token);
    }
    catch (Exception $e) {
//      $linkToConfig= Link::fromTextAndUrl(t('connector configuration'), Url::fromUserInput('/admin/tmgmt/translators'));
//      $msg = \Drupal\Core\Render\Markup::create($e->getMessage().'! ' . t('Please verify that the Content API Host is correct Settings: '.$linkToConfig->toString().'!'));
//      if(strlen($msg)>200){
//            substr($msg,0,200);
//      }
//      \Drupal::messenger()->addMessage($msg,'error');
      $form_state->setErrorByName('settings][capi-settings][capi_host', $msg);
    }
    $providersarray = array();
    foreach ($providers as $provider) {
      $prid = $provider->getProviderId();
      $prname = $provider->getProviderName();
      $providersarray[$prid] = $prname;
    }
    asort($providersarray, SORT_REGULAR);
    //$defaultproviderkey = key($providersarray);
    $form['capi-settings']['provider'] = array(
      '#type' => 'select',
      '#title' => t('Provider configuration'),
      '#required' => TRUE,
      '#options' => $providersarray,
      '#default_value' => isset($valid_provider) ? $valid_provider : NULL,
      '#description' => t('Please select a Provider for your project.'),
      '#ajax' => [
        'callback' => 'ajax_tmgmt_contentapi_provider_changed',
        'wrapper' => 'quote',
      ],
    );
    if(!$allowprovideroverride){
      $form['capi-settings']['provider']['#attributes']['disabled'] = 'disabled';
    }


    $form['capi-settings']['quote'] = [
      '#type' => 'container',
      '#prefix' => '<div id="quote">',
      '#suffix' => '</div>',
    ];

    if (isset($valid_provider)) {
      $form['capi-settings']['quote']['supported_languages'] = array(
        '#type' => 'details',
        '#title' => t('Supported Languages'),
        '#open' => FALSE,
        '#description' => t('Supported language pairs by the selected provider.'),
      );
      $providerapi = new ProviderApi();
      $rows = array();
      $header = array(t('source languages'), t('target languages'));
      try {
        $selected_provider = $providerapi->providersProviderIdGet($token, $valid_provider);
        $capabilities = $selected_provider->getCapabilities();
        $supported_lang_pairs = isset($capabilities) ? $capabilities->getSupportedLanguages() : array();
        foreach ($supported_lang_pairs as $pair) {
          $rows[] = [
            join(',', isset($pair) ? $pair->getSources() : []),
            join(',', isset($pair) ? $pair->getTargets() : NULL)
          ];
        }
      }
      catch (ApiException $ex) {
        $rows[] = array($ex->getMessage());
      }
      $form['capi-settings']['quote']['supported_languages']['lang_table'] = array(
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('No language specific settings defined for the selected provider.')
      );
    }



    $form['capi-settings']['quote']['is_quote'] = array(
      '#type' => 'checkbox',
      '#title' => t('Quote'),
      '#description' => t('Check to receive a quote before translation starts. Quote has to be approved in order to start the translation'),
      '#default_value' => 0,
    );

    $provider = getProvider($token, $valid_provider);
    $capabilities = isset($provider) ? $provider->getCapabilities() : NULL;
    $supportsQuote = $capabilities != NULL ? $capabilities->getSupportQuote() : TRUE;
    $supportsQuote = isset($supportsQuote) ? $supportsQuote : TRUE;
    if (!$supportsQuote) {
      $form_values = $form_state->getValues();
      $form_user_input = $form_state->getUserInput();
      $form_values['settings']['capi-settings']['quote']['is_quote'] = 0;
      $form_user_input['settings']['capi-settings']['quote']['is_quote'] = FALSE;
      $form_state->setValues($form_values);
      $form_state->setUserInput($form_user_input);
      $form['capi-settings']['quote']['is_quote']['#attributes'] = array('disabled' => TRUE);;
    }
    else {
      unset($form['capi-settings']['quote']['is_quote']['#attributes']['disabled']);
    }

    return parent::checkoutSettingsForm($form, $form_state, $job);
  }

  /**
   * @param array $form
   * @param array $form_state
   * @param \TMGMTJob $job
   */
  #[\ReturnTypeWillChange]
    public function getValidProvider(array $form, FormStateInterface &$form_state, JobInterface $job){
    // can be triggered by translator, provider dropdown, language drop down, request translation, submit button in job overview
    $who_triggered = $form_state->getTriggeringElement();
    $values = $form_state->getValues();
    $form_user_input = $form_state->getUserInput();
    $trigger_name = isset($who_triggered) ? $who_triggered['#name'] : NULL;
    $translator = $job->getTranslator();
    // we need this, otherwise when new jobs are submitted and no translator saved, ajax causes problems when switching translator
    // TMGMT bug?
    $job->save();
    $capisettings = $translator->getSetting('capi-settings');
    $jobcapisettings = $job->getSetting("capi-settings");
    $translator_provider_id = isset($capisettings) ? $capisettings['provider'] : NULL;
    $job_provider_id = isset($values) && isset($values['settings']['capi-settings']['provider']) ?
      $values['settings']['capi-settings']['provider'] : $translator_provider_id;
    switch ($trigger_name) {
      case 'translator':
        $values['settings']['capi-settings']['provider'] = $translator_provider_id;
        $form_user_input['settings']['capi-settings']['provider'] = $translator_provider_id;
        $form_state->setValues($values);
        $form_state->setUserInput($form_user_input);
        return $translator_provider_id;

      default:
        $values['settings']['capi-settings']['provider'] = $job_provider_id;
        $form_user_input['settings']['capi-settings']['provider'] = $job_provider_id;
        $form_state->setValues($values);
        $form_state->setUserInput($form_user_input);
        return $job_provider_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function checkoutInfo(JobInterface $job) {
    $requestobjs = unserialize($job->getSetting("capi-remote"));
    $date = $job->getSetting('capi-settings')['due_date'];
    // $duedate = $date->format('Y-m-d h:i');
    $due_date = date('Y-m-d\TH:i:s.000') . 'Z';
    $translator = $job->getTranslator();
    $capisettings = $translator->getSetting('capi-settings');
    $capi = new TokenApi();
    $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
    
    $providers = NULL;
    
    $providersarray = array();


    // Check if we have any request and take from first jobid.
    $task = $job->getSetting('capi-settings')['task'];
    
    $capijobid = NULL;
    if($task == 'trans'){
      // $job->setState(Job::STATE_ACTIVE);
      $capijobid = isset($requestobjs[0]) && count($requestobjs) > 0 ? reset($requestobjs[0])->getJobId() : NULL;
    }
    else {
      $job->setState(Job::STATE_FINISHED);
      $capijobid = isset($requestobjs[0]) && count($requestobjs) > 0 ? $requestobjs[0]->getJobId() : NULL;
    }
    $form = array();
    $projectInfo = null;
    if($capisettings['capi_username_ctt1'] != '')
    {
      $tokenrequest = new CreateToken(array('username' => $capisettings['capi_username_ctt1'], 'password' => $capisettings['capi_password_ctt1']));
      $capi = new TokenApi();
      $tokenobj = $capi->oauth2TokenPost($tokenrequest);
    }
    $capiVersion = 2;
    If($job->getState() > \Drupal\tmgmt\Entity\Job::STATE_UNPROCESSED) {
 
      $jobapi = new JobApi();

      $find_job = $jobapi->FindJob($token, $capijobid);

      
  
      try {
        // echo "adsad";
        $jobapi = new JobApi();
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
          $projectInfo = $jobapi->jobsJobIdGetCtt($tokenobj['access_token'], $capijobid, "fullWithStats");
          $jobstatus = $projectInfo->getStatusCode()->getStatusCode();



          $providerapi = new ProviderApi();
          $providersCTTT = $providerapi->providersGetAllCtt($tokenobj['access_token']);
                
              foreach ($providersCTTT as $provider) {
                $prid = $provider->providerId;
                $prname = $provider->providerName;
                $providersarray[$prid] = $prname;
              }



          $capiVersion = 1;
        }
        else
        {
          $projectInfo = $jobapi->jobsJobIdGet($token, $capijobid, "fullWithStats");
        
          if($projectInfo->getStatusCode() != 'COMPLETED')
          {
            $jobstatus = $jobapi->jobsJobIdGetStatus($token, $capijobid);
          }
          else
          {
          $jobstatus = $projectInfo->getStatusCode();
          }


                
          if(isset($token) && $token != '') {
            try {
              $providerapi = new ProviderApi();
              $providers = $providerapi->providersGet($token);
              foreach ($providers as $provider) {
                $prid = $provider->getProviderId();
                $prname = $provider->getProviderName();
                $providersarray[$prid] = $prname;
              }    
            } catch (Exception $e) {

		
				$msg = $e->getMessage();
		      	if(strlen($msg) > 200){
		            $msg = substr($msg,0,200);
		      	}
		      	\Drupal::messenger()->addMessage($msg);

              //\Drupal::messenger()->addMessage($e->getMessage());
            }
          }
          
        }
        \Drupal\tmgmt_contentapi\Util\ConentApiHelper::capiJobSetStoredData($job, ['jobId' => $capijobid ,'jobStatus' => $jobstatus, 'providerId' => $prid, 'jobType' => $capiVersion]);

        //REMOVED DUE TO THE NEXT LOGIC:
        //WHEN AUTOIMPORT IS ON THAT MEANS NO USER INTERACTION IS REQUIRED TO IMPORT, SO OPENING THE JOB DETAILS
        //SHOULDN'T TRIGGER THE IMPORT, AS THAT IS AN INDIRECT USER ACTION ALREADY 
        //AND CRON IS RESPONSIBLE FOR THAT ALREADY.
        //WHEN AUTOIMPORT IS OFF, THAT MEANS THE CONNECTOR SHOULDN'T IMPORT WITHOUT A DIRECT USER INTENTION TO DO SO,
        //THAT IS BY CLICKING THE IMPORT BUTTON, BECAUSE PARTIALLY UPDATING THE JOB STATUS BASED ON AN USER 
        //OPENING THE JOB DETAILS SCREEN IS NOT A DIRECT INTENTION FOR IMPORTING AND CREATES AMBIGUITY UX AND LOGIC

        //$cronSettings = $translator->getSetting('cron-settings');
        //if (!$cronSettings['status']) {
        //  $updatedremotejob = \Drupal\tmgmt_contentapi\Util\ConentApiHelper::checkJobFinishAndApproveRemote($job);
        //  $projectInfo = $updatedremotejob != NULL ? $updatedremotejob:$projectInfo;
        //}
      }
      catch (ApiException $ex){
        $respbody = $ex->getResponseBody();
        if(strlen($respbody) > 200){
            $respbody = substr($respbody,0,200);
        }
        \Drupal::messenger()->addMessage('The API returned an error. '. $respbody,'warning');
        $projectInfo = null;
      }
      catch (Exception $ex){
        $respbody = $ex->getMessage();
        if(strlen($respbody) > 200){
            $respbody = substr($respbody,0,200);
        }
        \Drupal::messenger()->addMessage('The API returned an error. '. $respbody,'warning');
        $projectInfo = null;
      }
    }
    
    $cpodername = 'n/a';
    $cporderid = 'n/a';
    $cpstatuscode = 'n/a';
    $lateerror = 'n/a';
    $providerid = 'n/a';
    $poreference = 'n/a';
    $duedate = NULL;
    $archived = 'n/a';
    $description = 'n/a';
    $jobstats = NULL;
    if($projectInfo != null){
      $cpodername = $projectInfo->getJobName();
      $cporderid = $projectInfo->getJobId();
      $cpstatuscode = $jobstatus;
      $lateerror = '';//$projectInfo->getLatestErrorMessage();
      $providerid = $providersarray[$projectInfo->getProviderId()].'<br>( '.$projectInfo->getProviderId().' )';
      $poreference = $projectInfo->getPoReference();
      $duedate = date('Y-m-d h:i', strtotime($date));
      $archived = $projectInfo->getArchived() ? "TRUE" : "FALSE";
      $description = $projectInfo->getDescription();
      $jobstats = $projectInfo->getJobStats();

      if($capiVersion == 1){
        $projectInfo->getLatestErrorMessage();
      }else{

        $requestApi = new RequestApi();
        $allRequests = $requestApi->jobsJobIdRequestsGet($token, $capijobid);
        foreach($allRequests as $instRequest){
          if($instRequest->getLatestErrorMessage() != null && $instRequest->getLatestErrorMessage() != ''){
            $lateerror = $instRequest->getLatestErrorMessage();
          }
        }
      }
      

    }

    $this->createCpOrderForm(
      $form,
      $cpodername,
      $cporderid,
      $cpstatuscode,
      $description,
      $poreference,
      $duedate,
      $providerid,
      $lateerror,
      $archived,
      $jobstats
    );


    if($task == 'trans') {
      $form['fw-immport-palaceholder'] = [
        '#prefix' => '<div id="fw-im-placholder">',
        '#suffix' => '</div>',
      ];
      if ($projectInfo != NULL && $projectInfo->getShouldQuote()) {
        $form['fw-immport-palaceholder']['quote-info'] = [
          '#prefix' => '<div role="contentinfo" aria-label="Warning message" class="messages messages--warning">'
            . '<div role="alert"><h2 class="visually-hidden">Warning message</h2>',
          '#markup' => t('This job was submitted for a quote. To submit your job for processing, you must log into your translation provider\'s system to approve this quote.
'),
          '#suffix' => '</div></div>'
        ];
      }

      $form['fw-immport-palaceholder']['fieldset-import'] = [
        '#type' => 'fieldset',
        '#title' => t('IMPORT TRANSLATED FILE'),
        '#collapsible' => TRUE,
      ];


      $form['fw-immport-palaceholder']['fieldset-import']['automatic-import'] = [
        '#type' => 'details',
        '#title' => t('Import'),
        '#open' => TRUE,
      ];

      $form['fw-immport-palaceholder']['fieldset-import']['automatic-import']['auto-submit'] = [
        '#type' => 'submit',
        '#value' => t('Import'),
        '#submit' => ['tmgmt_contentapi_semi_import_form_submit'],
      ];

      $form['fw-immport-palaceholder']['fieldset-import']['automatic-import']['tm-update'] = [
        '#type' => 'submit',
        '#value' => t('Update TM'),
        '#submit' => ['tmgmt_contentapi_update_tm_form_submit'],
      ];

      $form['fw-immport-palaceholder']['fieldset-import']['manual-import'] = [
        '#type' => 'details',
        '#title' => t('Manual Import'),
        '#open' => TRUE,
      ];


      $form['fw-immport-palaceholder']['fieldset-import']['manual-import']['file'] = [
        '#type' => 'file',
        '#title' => t('File'),
        '#size' => 50,
        '#description' => t('Supported formats: xlf.'),
      ];
      $form['fw-immport-palaceholder']['fieldset-import']['manual-import']['submit'] = [
        '#type' => 'submit',
        '#value' => t('Manual Import'),
        '#submit' => ['tmgmt_contentapi_import_form_submit'],
        '#validate' => ['tmgmt_contentapi_check_empty_file']
      ];
    }
    return $form;
  }

  #[\ReturnTypeWillChange]
    public function createCpOrderForm(&$fieldset, $ordername, $orderid, $orderstatus, $description, $poreference, $duedate, $providerid, $errors, $archived, $jobstats){
    $fieldset['fw-table'] = array(
      '#prefix' => '<table class="views-table views-view-table cols-8"><thead>
                            <tr>
                                <th>Job Name</th>
                                <th>Job ID</th>
                                <th>Job Status</th>
                                <th>Description</th>
                                <th>PO Number</th>
                                <th>Due Date</th>
                                <th>Provider ID</th>
                                <th>Latest Error</th>
                                <th>Archived</th>
                                <th>Statistics</th>
                            </tr></thead>',
      '#suffix' => '</table>',
    );
    $fieldset['fw-table']['first-row'] = array(
      '#prefix' => '<tr>',
      '#suffix' => '</tr>'
    );
    $fieldset['fw-table']['first-row']['ordername'] = array(
      '#prefix' => '<td>',
      '#markup' => $ordername,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['id'] = array(
      '#prefix' => '<td>',
      '#markup' => $orderid,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['status'] = array(
      '#prefix' => '<td>',
      '#markup' => $orderstatus,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['description'] = array(
      '#prefix' => '<td>',
      '#markup' => $description,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['po-reference'] = array(
      '#prefix' => '<td>',
      '#markup' => $poreference,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['due-date'] = array(
      '#prefix' => '<td>',
      '#markup' => isset($duedate) && $duedate !== NULL ? $duedate : "n/a",
      '#suffix' => '</td>'
    );

    $fieldset['fw-table']['first-row']['provider-id'] = array(
      '#prefix' => '<td>',
      '#markup' => $providerid,
      '#suffix' => '</td>'
    );
    $fieldset['fw-table']['first-row']['error'] = array(
      '#prefix' => '<td>',
      '#markup' => $errors,
      '#suffix' => '</td>'
    );

    $fieldset['fw-table']['first-row']['archived'] = array(
      '#prefix' => '<td>',
      '#markup' => $archived,
      '#suffix' => '</td>'
    );

    $fieldset['fw-table']['first-row']['statistics'] = array(
      '#prefix' => '<td>',
      '#markup' => $this->createMarkupForStats($jobstats),
      '#suffix' => '</td>'
    );

  }

  #[\ReturnTypeWillChange]
    public function createMarkupForStats($stats) {
    $markup = "";
    if (isset($stats)) {
      $totalcompleted = t("total completed: ") . $stats->getTotalCompleted();
      $totalintrans = t("total in translation: ") . $stats->getTotalInTranslation();
      $totalreceived = t("total received: ") . $stats->getTotalReceived();
      $totalerrors = t("total errors: ") . $stats->getTotalError();
      $markup += "<p>" . $totalcompleted . "</p>";
      $markup += "<p>" . $totalreceived . "</p>";
      $markup += "<p>" . $totalintrans . "</p>";
      $markup += "<p>" . $totalerrors . "</p>";
    }
    return $markup;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here by default.
    $whotriggered = $form_state->getTriggeringElement();
    $typeofTrigger = $whotriggered['#type'];
    $prevHost = "";
    $providers = array();
    $tmpSettings = \Drupal::service('config.factory')->getEditable('tmgmt.translator.contentapi');
    if ($typeofTrigger != 'select') {
      try {
        $logonError = false;
        $logonError1 = false;
        $endpointError = false;
        $translator = $form_state->getFormObject()->getEntity();
        $capisettings = $translator->getSetting('capi-settings');
        $settings = $form_state->getValue('settings');
        $capisettings = $settings['capi-settings'];
        $username = $capisettings['capi_username_ctt'];
        $password = $capisettings['capi_password_ctt'];

        $username1 = $capisettings['capi_username_ctt1'];
        $password1 = $capisettings['capi_password_ctt1'];

        $tokenobj = null;
        
        $providerapi = new ProviderApi();

        $host = $capisettings['capi_host'];
        $config = new Configuration();
        $config->setHost($host);
        // $tokenrequest = new CreateToken(array('username' => $username, 'password' => $password));
        $capi = new TokenApi();



        
        if(
          !array_key_exists('capi_host', $tmpSettings->get('settings.capi-settings'))
          || 
          (
            $capisettings["capi_host"] != "" 
            && 
            $tmpSettings->get('settings.capi-settings.capi_host') != $capisettings["capi_host"]
          )
        )
        {
          $prevHost = $tmpSettings->get('settings.capi-settings.capi_host');
          $tmpSettings->set('settings.capi-settings.capi_host',$capisettings["capi_host"]);
          $tmpSettings->save();
          //\Drupal::config('tmgmt.translator.contentapi')->set('settings',$tmpSettings);
        }



        // $tokenobj = $capi->oauth2TokenPost($tokenrequest);
        try{
          $tokenobj = $capi->getToken($username,$password);
        }catch(RequestException $ex){
          $logonError = true;
          throw $ex;
        }catch(ApiException $ex){
          $logonError = true;
          throw $ex;
        }
        try{
          $providers = $providerapi->providersGet($tokenobj);
        }catch(RequestException $ex){
          $endpointError = true;
          throw $ex;
        }catch(ApiException $ex){
          $endpointError = true;
          throw $ex;
        }
        if($username1 != "" || $password1 != ""){
          try{
            $tokenrequest = new CreateToken(array('username' => $username1, 'password' => $password1));
            $tokenobj1 =  $capi->oauth2TokenPost($tokenrequest);
          }catch(RequestException $ex){
            $logonError1 = true;
            throw $ex;
          }catch(ApiException $ex){
            $logonError1 = true;
            throw $ex;
          } 
        }
        // $tokenobj = ConentApiHelper::generateToken($translator);
        //$form_state->setValue(array('settings','capi-settings','capi_password'),'');
        $form_state->setValue(array('settings','capi-settings','token'),$tokenobj);
        $capisettings['token'] = $tokenobj;
        $translator->setSetting('capi-settings',$capisettings);
 
      }
      catch (Exception $exception) {
        if($prevHost != ""){
          $tmpSettings->set('settings.capi-settings.capi_host',$prevHost);
          $tmpSettings->save();
        }
        $msgs = array();
        if($logonError == true){
          array_push($msgs, "Please check your Content API Client ID and Secret ID");
          $form_state->setErrorByName('settings][capi-settings][capi_username_ctt');
          $form_state->setErrorByName('settings][capi-settings][capi_password_ctt', 'Please check your Client ID and Secret ID Settings: ' . $exception->getMessage());
        }
        if($endpointError == true){
          array_push($msgs, "Please verify that the Content API Host is correct");
          $form_state->setErrorByName('settings][capi-settings][capi_host', 'Please verify that the Content API Host is correct');
        }
        if($logonError1 == true){
          array_push($msgs, "Please check your Legacy username and password");
          $form_state->setErrorByName('settings][capi-settings][capi_username_ctt1');
          $form_state->setErrorByName('settings][capi-settings][capi_password_ctt1', 'Please check your Legacy username and password Settings: ' . $exception->getMessage());
        }
        \Drupal::logger('TMGMT_CONTENTAPI')->error('Failed to valideate form: %message ', [
          '%message' => $exception->getMessage(),
        ]);
      }
    }

  }

  #[\ReturnTypeWillChange]
    public function reviewForm(array $form, FormStateInterface $form_state, JobItemInterface $item) {
     // TODO: Change the autogenerated stub


    return $form;
  }

  #[\ReturnTypeWillChange]
    public function reviewDataItemElement(array $form, FormStateInterface $form_state, $data_item_key, $parent_key, array $data_item, JobItemInterface $item) {
    return parent::reviewDataItemElement($form, $form_state, $data_item_key, $parent_key, $data_item, $item); // TODO: Change the autogenerated stub
  }

  #[\ReturnTypeWillChange]
    public function reviewFormValidate(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    parent::reviewFormValidate($form, $form_state, $item); // TODO: Change the autogenerated stub
  }

  #[\ReturnTypeWillChange]
    public function reviewFormSubmit(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    parent::reviewFormSubmit($form, $form_state, $item);
    $triggertby = $form_state->getTriggeringElement();
    $triggerid = $triggertby['#id'];
    // If reject button has been pressed, reject request in content api.
    if($triggerid == 'edit-reject'){
      $job = $item->getJob();
      $submittedrequestsarray = unserialize($job->getSetting('capi-remote'));
      if(isset($submittedrequestsarray) && count($submittedrequestsarray)>0){
        $arraywithrequest = $submittedrequestsarray[0];
        $itemid = $item->id();
        foreach ($arraywithrequest as $request){
          $requestSourceNativeId = explode("_",$request->getSourceNativeId())[1];
          // Check to cancel the request which belongs to the item or if all item sent in one request then all.
          if($requestSourceNativeId == $itemid || $requestSourceNativeId == 'all'){
            try {
              $translator = $job->getTranslator();
              $capisettings = $translator->getSetting('capi-settings');
              $capi = new TokenApi();
              $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
              $requestapi = new RequestApi();
              $arrayrequestid = new ArrayOfRequestIdsNote();$test = new Request();
              $arrayrequestid->setRequestIds(array($request->getRequestId()));
              $arrayrequestid->setNote('Translation has been rejected by Client using Drupal Connector. Please check the Translation.');
              $returnarray = $requestapi->jobsJobIdRequestsRejectPut($token,$request->getJobId(),$arrayrequestid);
              if(count($returnarray) == 1 && $returnarray[0] instanceof Request){
                $job->addMessage(t('Remote request rejected: '. $request->getRequestId()));
              }
            }
            catch (Exception $ex){
              $msg = t('Remote Job could not be rejected: ' . $ex->getMessage());
              if(strlen($msg) > 200){
                    $msg = substr($msg,0,200);
              }
              $job->addMessage($msg,array(),'warning');
            }
          }
        }
      }

    }
    // If Item have been saved as completed, approve request, but not all.
    if($triggerid == 'edit-accept'){
      $job = $item->getJob();
      $submittedrequestsarray = unserialize($job->getSetting('capi-remote'));
      if(isset($submittedrequestsarray) && count($submittedrequestsarray)>0){
        $arraywithrequest = $submittedrequestsarray[0];
        $itemid = $item->id();
        foreach ($arraywithrequest as $request){
          $requestSourceNativeId = explode("_",$request->getSourceNativeId())[1];
          // Check to cancel the request which belongs to the item or if all item sent in one request then all.
          if($requestSourceNativeId == $itemid){
            try {
              $translator = $job->getTranslator();
              $capisettings = $translator->getSetting('capi-settings');
              $capi = new TokenApi();
              $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
              $requestapi = new RequestApi();
              $arrayrequestid = new ArrayOfRequestIds();
              $arrayrequestid->setRequestIds(array($request->getRequestId()));
              if($capisettings['capi_username_ctt1'] != '')
              {
                $tokenrequest = new CreateToken(array('username' => $capisettings['capi_username_ctt1'], 'password' => $capisettings['capi_password_ctt1']));
                $tokenobj = $capi->oauth2TokenPost($tokenrequest);
              }
              $jobapi = new JobApi();
              $find_job = $jobapi->FindJob($token, $request->getJobId());
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
                $returnarray = $requestapi->jobsJobIdRequestsApprovePutCtt($tokenobj['access_token'],$request->getJobId(),$arrayrequestid);
                
              }
              else
              {
                $returnarray = $requestapi->jobsJobIdRequestsApprovePut($token, $request->getJobId(),$arrayrequestid);
              }
              if(count($returnarray) == 1 && $returnarray[0] instanceof Request){
                $job->addMessage(t('Remote request approved: '. $request->getRequestId()));
              }
            }
            catch (Exception $ex){
              $msg = t('Remote Job could not be approved: ' . $ex->getMessage());
              if(strlen($msg) > 200){
                    $msg = substr($msg,0,200);
              }
              $job->addMessage($msg,array(),'warning');
              //$job->addMessage(t('Remote Job could not be approved: ' . $ex->getMessage()),array(),'warning');
            }
          }
          //TODO: Not sure if this is required, as when displaying Job details, check happens if job finished and approves all. comment out to check?
          $allaccepteditems =  $job->getItems(array('state'=>JobItemInterface::STATE_ACCEPTED));
          $allitems = $job->getItems();
          // check if all all job items excluding this one, as this one has not been saved as comleted yet, are accepted.
          if(count($allitems) == (count($allaccepteditems)+1)){
            // Generate array with requestIds to approve, all will be approved.
            try {
              $translator = $job->getTranslator();
              $capisettings = $translator->getSetting('capi-settings');
              $capi = new TokenApi();
              $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
              $requestapi = new RequestApi();
              $arrayrequestid = new ArrayOfRequestIds();
              $arrayrequestid->setRequestIds(array($request->getRequestId()));
              if($capisettings['capi_username_ctt1'] != '')
              {
                $tokenrequest = new CreateToken(array('username' => $capisettings['capi_username_ctt1'], 'password' => $capisettings['capi_password_ctt1']));
                $tokenobj = $capi->oauth2TokenPost($tokenrequest);
              }
              $jobapi = new JobApi();
              $find_job = $jobapi->FindJob($token, $request->getJobId());
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
                $returnarray = $requestapi->jobsJobIdRequestsApprovePutCtt($tokenobj['access_token'],$request->getJobId(),$arrayrequestid);
              }
              else
              {
                $returnarray = $requestapi->jobsJobIdRequestsApprovePut($token, $request->getJobId(),$arrayrequestid);
              }
              if(count($returnarray) == 1 && $returnarray[0] instanceof Request){
                $job->addMessage(t('Remote request archived: '. $request->getJobId()));
              }
            }
            catch (Exception $ex){
                $msg = t('Remote Job could not be approved: ' . $ex->getMessage());
              if(strlen($msg) > 200){
                    $msg = substr($msg,0,200);
              }
              $job->addMessage($msg,array(),'warning');
              //$job->addMessage(t('Remote Job could not be approved: ' . $ex->getMessage()),array(),'warning');
            }
          }

        }

      }
    }

  }

}
