<?php


namespace Drupal\bulk_batchdelete\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use \Drupal\bulk_batchdelete\Service\ProcessEntity;
use \Drupal\bulk_batchdelete\Service\BatchService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Form with examples on how to use cache.
 */
class BatchCreationForm extends FormBase {

  /**
   * Entity processor.
   *
   * @var \Drupal\bulk_batchdelete\Service\ProcessEntity
   * 
   */
  protected $processEntity;

  /**
   * Batch service.
   *
   * @var \Drupal\bulk_batchdelete\Service\BatchService
   * 
   */
  protected $batchService;

  /**
   * Constructs a new BatchCreationForm object.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, ProcessEntity $processEntity, BatchService $batchService) {
    $this->loggerFactory = $logger_factory;
    $this->processEntity = $processEntity;
    $this->batchService = $batchService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('process_entity'),
      $container->get('batch_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_batchdelete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Select appropriate values to delete records.'),
    ];

    // Get list of entity user can choose.
    $entityList = $this->processEntity->listLimitedEntity();
    // array_unshift($entityList, "Please select entity");
    $form['entity_list'] = [
      '#type' => 'select',
      '#title' => 'Choose entity',
      '#empty_option' => $this->t('- Select entity -'),
      '#description' => $this->t('Choose entity for which content need to delete'),
      '#options' => $entityList,
      "#ajax" => array(
        'callback' => '::getbundlesDropdown',
        'method' => 'html',
        'wrapper' => 'bundle_options',
        'event' => 'change',
        'method' => 'replace',
      ),
    ];

    // Add field which will only get displayed when selected "User" entity
    // Show list of bundles which needs to delete.
    // This will get generated from the entity which selected from Entity dropdown.
    $renderedBundle = [];
    $form['node_type_list'] = [
      '#type' => 'select',
      '#title' => 'Choose bundle',
      '#empty_option' => $this->t('- Select bundle -'),
      '#description' => $this->t('Choose node bundle for which contents need to delete'),
      '#options' => $renderedBundle,
      '#attributes' => ["id" => "bundle_options"],
      '#validated' => true
    ];

    // Filed to give option for user status.
    $userStatus = $this->processEntity->getListOfUserStatus();
    $form['user_status'] = [
      '#type' => 'select',
      '#title' => 'Choose user status',
      '#empty_option' => $this->t('- Select status -'),
      '#description' => $this->t('Choose user status which needs to delete.'),
      '#options' => $userStatus,
      '#states' => [
        'visible' => [
          ':input[name="entity_list"]' => ['value' => 'user'],
        ],
      ],
      '#prefix' => '<div id="user_status_options">',
      '#suffix' => '</div>',
    
    ];

    // Field to give option for user cancellation method.
    $userCancellationMethod = $this->processEntity->getListOfCancelMethod();
    $form['use_cancellation_method'] = [
      '#type' => 'radios',
      '#title' => 'Choose user cancel method',
      '#description' => $this->t('Choose user cancellation method.'),
      '#options' => $userCancellationMethod,
      '#states' => [
        'visible' => [
          ':input[name="entity_list"]' => ['value' => 'user'],
        ],
      ],
      '#prefix' => '<div id="user_cancellation_options">',
      '#suffix' => '</div>',
    
    ];

    // Field which collect total number of records which needs to delete.
    $form['number_of_records'] = [
      '#type' => 'number',
      '#title' => 'Number of records',
      '#description' => $this->t('Add number of records which need to process.'),
      '#maxlength' => 7,
      '#min' => 1,
      '#attributes' => array(
        ' type' => 'number', // insert space before attribute name :)
      ),
    ];

    // Field which collect btach size in which records needs to delete.
    $form['batch_size'] = [
      '#type' => 'number',
      '#title' => 'Delet users in batch size',
      '#description' => $this->t('Add number for batch size which needs to process at a time.'),
      '#maxlength' => 3,
      '#min' => 1,
    ];

    // Field which collect some unique name for batch.
    // This will helpfull for logging and identification purpose.
    $form['batch_name'] = [
      '#type' => 'textfield',
      '#title' => 'Name the batch',
      '#maxlength' => 50,
      '#description' => 'Please keep unique name for each batch, eg aug24_b1_1000
      it will create log file for same,
      aug24 - (month and day), b1 - (today batch number), number_of_records',
      '#attributes' => array(
        'alt' => 'Aug24_b1_100', // insert space before attribute name :)
      ),
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Start Deletion',
    ];
    return $form;
  }

  /**
   * Ajax callback function on entity dropdown change.
   */
  public function getbundlesDropdown(array &$form, FormStateInterface $form_state) {

    $triggeringElement = $form_state->getTriggeringElement();
    $entityValue = $triggeringElement['#value'];
    $wrapper_id = $triggeringElement["#ajax"]["wrapper"];
    $renderedField = '';
    $renderedField .= "<option value=''>--Select bundle--</option>";
    // Process only if entity is selected, nothing will happen if entity is blank.
    if(!empty($entityValue)) {
      $bundles = $this->processEntity->getBundles($entityValue);
      foreach ($bundles as $key => $value) {
        $renderedField .= "<option value='".$key."'>".$value."</option>";
      }
    }
    $response = new AjaxResponse();
    // Assign bundle option for bundle dropdown.
    $response->addCommand(new HtmlCommand("#".$wrapper_id, $renderedField));
    // Check if user entity has been selected for a entity field.
    // If option user is selected then only disaply user status option.
    if ($entityValue == 'user_role') {
      
      $response->addCommand(new ReplaceCommand('#user_status_options', $form['user_status']));
      $response->addCommand(new ReplaceCommand('#user_cancellation_options', $form['use_cancellation_method']));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Entity field validation.
    $entityName = $form_state->getValue('entity_list');
    if (empty($entityName)) {
      $form_state->setErrorByName('entity_list', $this->t('Please select entity to delete.'));
    }
    // Bundle field validation.
    $bundleName = $form_state->getValue('node_type_list');
    if (empty($bundleName)) {
      $form_state->setErrorByName('node_type_list', $this->t('Please select bundle to delete.'));
    }
    // Validate extra fields which will get add if user entity has been selected.
    if (!empty($entityName) && $entityName == 'user_role') {
      // User status selection.
      $userStatus = $form_state->getValue('user_status');
      if (empty($userStatus)) {
        $form_state->setErrorByName('user_status', $this->t('Please select user status to delete.'));
      }
      // User cancellation method.
      $userCancellationMethod = $form_state->getValue('use_cancellation_method');
      if (empty($userCancellationMethod)) {
        $form_state->setErrorByName('use_cancellation_method', $this->t('Please select user status to delete.'));
      }
    }
    // Validation for number of records.
    $numberOfRecords = $form_state->getValue('number_of_records');
    if (empty($numberOfRecords)) {
      $form_state->setErrorByName('number_of_records', $this->t('Please enter number of records which needs to delete.'));
    }
    // Number of records should only contains numbers.
    if (!empty($numberOfRecords)) {
      if (!is_numeric($numberOfRecords)) {
        $form_state->setErrorByName('number_of_records', $this->t('Please enter only integer value for number of records.'));
      }
    }
    // Validation for batch size.
    $batchSize = $form_state->getValue('batch_size');
    if (empty($batchSize)) {
      $form_state->setErrorByName('batch_size', $this->t('Please enter batch size in which records needs to delete.'));
    }
    // Batch size should only contains numbers.
    if (!empty($batchSize)) {
      if (!is_numeric($batchSize)) {
        $form_state->setErrorByName('batch_size', $this->t('Please enter only integer value for batch size.'));
      }
    }
    // Batch name validation.
    $entityName = $form_state->getValue('batch_name');
    if (empty($entityName)) {
      $form_state->setErrorByName('batch_name', $this->t('Please enter batch name.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Number of records need to delete.
    $number_of_records = $form_state->getValues()['number_of_records'];
    // Batch size.
    $batch_size = $form_state->getValues()['batch_size'];
    // Batch name.
    $batch_name = $form_state->getValues()['batch_name'];
    // entity_list
    $entity_type = $form['entity_list']['#options'][$form_state->getValue('entity_list')];
    // node_type_list
    $node_type_list = $form_state->getValues()['node_type_list'];
    // user_status
    $user_status = $form_state->getValues()['user_status'];
    // use_cancellation_method
    $use_cancellation_method = $form_state->getValues()['use_cancellation_method'];

    $dataToGenerateQuery = [
      'entity_type' => $entity_type,
      'node_type_list' => $node_type_list,
      'user_status' => $user_status,
      'use_cancellation_method' => $use_cancellation_method,
    ];

    // print_r($dataToGenerateQuery);
    // exit;

    // Set the batch, using convenience methods.
    $batch = [];
    $ids = $this->batchService->generateQuery($number_of_records, $dataToGenerateQuery);
    $batch = $this->batchService->generateBatch($batch_size, $batch_name,  $ids);
    batch_set($batch);
  }
}
