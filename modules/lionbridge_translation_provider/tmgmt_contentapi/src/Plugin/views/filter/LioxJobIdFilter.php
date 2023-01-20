<?php

namespace Drupal\tmgmt_contentapi\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Filters by phase or status of project.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("string")
 */
class LioxJobIdFilter extends ManyToOne {

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  //  /**
  //  * Gets the values of the options.
  //  *
  //  * @return array
  //  *   Returns options.
  //  */
  // #[\ReturnTypeWillChange]
    public function getValueOptions() {
  //   $this->valueOptions = [
  //     '0' => t('Sending'),
  //     '1' => t('Sent to provider'),
  //     '2' => t('In translation'),
  //     '3' => t('Review translation'),
  //     '4' => t('Completed'),
  //     '5' => t('N/A'),
  //     '6' => t('Unprocessed'),
  //   ];
  //   return $this->valueOptions;
  // }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    // $this $filters = $this->display_handler->getOption('filters');
    $this->valueTitle = t('Filter by Status');
    // $this->valueType = 'textfield';
    // $this->definition['options callback'] = [$this, 'generateOptions'];
    // $this->currentDisplay = $view->current_display;
  }

  /**
   * Helper function that generates the options.
   *
   * @return array
   *   An array of states and their ids.
   */
  #[\ReturnTypeWillChange]
    public function generateOptions() {

    // $states = workflow_get_workflow_state_names();
    // You can add your custom code here to add custom labels for state transitions.
    // return $states;
  }

  /**
   * Helper function that builds the query.
   */
  #[\ReturnTypeWillChange]
    public function query() {
     echo "<pre>";
     print_r($this->value);
     exit;
  }

}