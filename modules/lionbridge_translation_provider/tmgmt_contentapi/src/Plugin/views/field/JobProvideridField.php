<?php

namespace Drupal\tmgmt_contentapi\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\TokenApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\ProviderApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Api\JobApi;
use Drupal\tmgmt_contentapi\Swagger\Client\Model\CreateToken;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("job_providerid_field")
 */
class JobProvideridField extends FieldPluginBase {

//toremove
  
  //endtoremove
  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->currentDisplay = $view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function query() {
    if(array_key_exists('tmgmt_message_message',$this->query->fields) == true){
      return;
    }
    $sub_query = \Drupal::database()->select('tmgmt_message', 'ma');
    $sub_query->addField('ma', 'tjid');
    $sub_query->addExpression("MAX(ma.mid)", 'mid');
    $sub_query->condition('ma.type','jobInfo','=');
    $sub_query->groupBy("ma.tjid");
    

    $definition = [
      'table formula' => $sub_query,
      'field' => 'tjid',
      'left_table' => 'tmgmt_job',
      'left_field' => 'tjid',
      'type' => 'left'
    ];

    $join = Views::pluginManager('join')->createInstance('standard', $definition);
    $this->query->addRelationship('messages', $join, 'tmgmt_job');
    //$this->query->addField("messages", 'mid');
 
    

    $definition2 = [
      'table' => 'tmgmt_message',
      'field' => 'mid',
      'left_table' => 'messages',
      'left_field' => 'mid',
      'type' => 'left'
    ];

    $join2 = Views::pluginManager('join')->createInstance('standard', $definition2);
    $this->query->addRelationship('tmgmt_message', $join2, 'messages');

    $this->query->addField("tmgmt_message", 'message');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // First check whether the field should be hidden if the value(hide_alter_empty = TRUE) /the rewrite is empty (hide_alter_empty = FALSE).
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
    public function render(ResultRow $values) {
    $myMessage = $values->tmgmt_message_message;
    $node = $values->_entity;
     if($myMessage == null || !$node->hasTranslator())
     {
        return "N/A";   
     }
    
    $json_decode = json_decode($myMessage, true);
    if(array_key_exists('providerId',$json_decode) == false){
      return "N/A";   
    }
    $providerid =  $json_decode['providerId']; 
    $translator = $node->getTranslator();

    $capiV1providers = JobProvideridField::getCapiV1Providers($translator);
    $capiV2providers = JobProvideridField::getProviders($translator);
    if(array_key_exists($providerid, $capiV2providers)){
      return $capiV2providers[$providerid];
    }
    if(array_key_exists($providerid, $capiV1providers)){
      return $capiV1providers[$providerid];
    }
    return '('.$providerid.')';
  }

  private static $capiv1providers = null;
  private static function getCapiV1Providers($translator){

    
    if(self::$capiv1providers !== null){
      return self::$capiv1providers;
    }
    $capisettings = $translator->getSetting('capi-settings');
    if($capisettings['capi_username_ctt1'] == ''){
      self::$capiv1providers = array();
      return self::$capiv1providers;
    }
    
    
      $tokenrequest = new CreateToken(array('username' => $capisettings['capi_username_ctt1'], 'password' => $capisettings['capi_password_ctt1']));
        $capi = new TokenApi();
        $tokenobj = $capi->oauth2TokenPost($tokenrequest);

        if(isset($tokenobj) && strlen($tokenobj['access_token']) > 0) {
          $providersarray = array();
            try 
            {
              $providerapi = new ProviderApi();
              $providers = $providerapi->providersGetAllCtt($tokenobj['access_token']);
                
              foreach ($providers as $provider) {
                $prid = $provider->providerId;
                $prname = $provider->providerName;
                $providersarray[$prid] = $prname;
              }
              self::$capiv1providers = $providersarray;
            } catch (Exception $e) {
		        $msg = $e->getMessage();
        		if(strlen($msg) > 200){
            		$msg = substr($msg,0,200);
        		}
              \Drupal::messenger()->addMessage($msg);
            }
            
        }
    return self::$capiv1providers;
  }

  private static $providersMain = null;
  private static function getProviders($translator){
    if(self::$providersMain === null){
      $capisettings = $translator->getSetting('capi-settings');
      $capi = new TokenApi();
      $token = $capi->getToken($capisettings['capi_username_ctt'],$capisettings['capi_password_ctt']);
      
  
        if(isset($token) && $token != '') {
            $providersarray = array();
            try {
              $providerapi = new ProviderApi();
              $providers = $providerapi->providersGet($token);
              
              foreach ($providers as $provider) {
                $prid = $provider->getProviderId();
                $prname = $provider->getProviderName();
                $providersarray[$prid] = $prname;
              }
              self::$providersMain = $providersarray;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if(strlen($msg) > 200){
                    $msg = substr($msg, 0, 200);
                }
              \Drupal::messenger()->addMessage($msg);
            }
          }

          // $provider_name = asort($providersarray, SORT_REGULAR);

      //YOU READ FROM THE API ALL THE PROVIDERS AND LOAD THEM INTO JobStatusField::$providers
    }
    return self::$providersMain;
  }
}