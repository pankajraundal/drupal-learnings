<?php

namespace Drupal\tmgmt_contentapi\Plugin\views\field;
use Drupal\views\Views;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("job_status_field")
 */
class JobStatusField extends FieldPluginBase {

//toremove
  // private static $providers = null;
  // private static function getProviders(){
  //   if(JobStatusField::$providers == null){
  //     //YOU READ FROM THE API ALL THE PROVIDERS AND LOAD THEM INTO JobStatusField::$providers
  //   }
  //   return $JobStatusField::$providers;
  // }
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

    if($myMessage == null)
    {
      return "N/A";  
        
    }
    $json_decode = json_decode($myMessage, true);
    return $json_decode['jobStatus'];
  }
}