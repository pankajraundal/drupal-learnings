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
 * @ViewsFilter("liox_job_status_filter")
 */
class LioxJobstatusFilter extends ManyToOne {

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
    $this->valueTitle = t('Filter by Status');
    $this->definition['options callback'] = [$this, 'generateOptions'];
    $this->currentDisplay = $view->current_display;
  }

  /**
   * Helper function that generates the options.
   *
   * @return array
   *   An array of states and their ids.
   */
  #[\ReturnTypeWillChange]
    public function generateOptions() {

    $options = [
      'SENDING' => t('Sending'),
      'SENT_TO_PROVIDER' => t('Sent to provider'),
      'IN_TRANSLATION' => t('In translation'),
      'REVIEW_TRANSLATION' => t('Review translation'),
      'COMPLETED' => t('Completed'),
      'N/A' => t('N/A'),
      // '6' => t('Unprocessed'),
    ];
    return $options;
    // $states = workflow_get_workflow_state_names();
    // You can add your custom code here to add custom labels for state transitions.
    // return $states;
  }

}