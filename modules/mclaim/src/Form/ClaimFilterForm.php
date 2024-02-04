<?php

namespace Drupal\mclaim\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mclaim\Service\ClaimsDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClaimFilterForm extends FormBase {
  /**
   * @var ClaimsDataService
   *   The claims data service.
   */
  protected $claimsDataService;

  /**
   * Constructs a new ClaimFilterForm object.
   *
   * @param ClaimsDataService $claimsDataService
   *   The ClaimsDataService object.
   */
  public function __construct(ClaimsDataService $claimsDataService) {
     $this->claimsDataService = $claimsDataService;
  }
  /**
   * Creates an instance of the ClaimFilterForm class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   *
   * @return \Drupal\mclaim\Form\ClaimFilterForm
   *   The ClaimFilterForm instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mclaim.claims_data_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'claim_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add Patient Name dropdown
    $form['row1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-row'],
      ],
    ];

    $form['row1']['patient_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Patient Name'),
      // Add options dynamically if needed
      '#options' => ['' => $this->t('Any')] + $this->claimsDataService->getAllPatientNames(),
      // set the default value if submitted
      '#default_value' => $key = array_search (\Drupal::request()->query->get('patient_name'), $this->claimsDataService->getAllPatientNames()),
      '#attributes' => ['class' => ['form-field']],
    ];

    $form['row1']['claims_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Claims Number'),
      "#size" => "9",
      '#autocomplete_route_name' => 'mclaim.claims_number', // route name of autocomplete callback
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('claims_number'),
      '#attributes' => ['class' => ['form-field']],
    ];

    $form['row1']['service_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Service Type'),

      '#options' => [
        '' => $this->t('Any'), // Add empty option with empty value
        'medical' => $this->t('Medical'),
        'dental' => $this->t('Dental'),
      ],
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('service_type'),
      '#attributes' => ['class' => ['form-field']],
    ];

    $form['row2'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-row'],
      ],
    ];

    $form['row2']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('start_date'),
      '#attributes' => ['class' => ['form-field']],
      // Size of the date field
      '#size' => 10,
    ];

    $form['row2']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('end_date'),
      '#attributes' => ['class' => ['form-field']],
      '#size' => 10,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#submit' => ['::filterFormSubmit'],
      '#attributes' => ['class' => ['button']],
    ];

    $form['actions']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export to CSV'),
      '#submit' => ['::exportFormSubmit'],
      '#attributes' => ['class' => ['button']],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    // Validate Claims Number field
    $claims_number = $form_state->getValue('claims_number');
    if (!empty($claims_number) && !is_numeric($claims_number)) {
      $form_state->setErrorByName('claims_number', $this->t('Claims Number should be a numeric value.'));
    }
    $start_date = strtotime($form_state->getValue('start_date'));
    $end_date = strtotime($form_state->getValue('end_date'));
    // Validate start date only if its not empty
    if (!empty($start_date) || !empty($end_date)) {
        // Validate Start Date and End Date
        if ($start_date > $end_date) {
        $form_state->setErrorByName('end_date', $this->t('End Date should be greater than Start Date.'));
        }

        // Validate if dates are within the last 18 months
        $eighteen_months_ago = strtotime('-18 months');
        if ($start_date < $eighteen_months_ago || $end_date < $eighteen_months_ago) {
        $form_state->setErrorByName('start_date', $this->t('Start Date and End Date should be within the last 18 months.'));
        }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Implement submit logic if needed
  }

  /**
   * Submit handler for the filter form.
   */
  public function filterFormSubmit(array &$form, FormStateInterface $form_state) {
    // Submit all values to the controller method "claimFilterForm" for further processing.
    if($form_state->getValue('patient_name') == '') {
        $selected_patient_name = '';
    } else {
        $selected_patient_name = $form['row1']['patient_name']['#options'][$form_state->getValue('patient_name')];
    }

    //$selected_service_type = $form['service_type']['#options'][$form_state->getValue('service_type')];
    $form_state->setRedirect('mclaim.view_claims', [
        'patient_name' => $selected_patient_name,
        'claims_number' => $form_state->getValue('claims_number'),
        'service_type' => $form_state->getValue('service_type'),
        'start_date' => $form_state->getValue('start_date'),
        'end_date' => $form_state->getValue('end_date'),
        // Add more values as needed
      ]);

  }

  /**
   * Submit handler for the export form.
   */
  public function exportFormSubmit(array &$form, FormStateInterface $form_state) {
    // Submit all values to the controller method "claimFilterForm" for further processing.
    if($form_state->getValue('patient_name') == '') {
        $selected_patient_name = '';
    } else {
        $selected_patient_name = $form['patient_name']['#options'][$form_state->getValue('patient_name')];
    }

    $form_state->setRedirect('mclaim.export_claims_data', [
        'patient_name' => $selected_patient_name,
        'claims_number' => $form_state->getValue('claims_number'),
        'service_type' => $form_state->getValue('service_type'),
        'start_date' => $form_state->getValue('start_date'),
        'end_date' => $form_state->getValue('end_date'),
        // Add more values as needed
      ]);

  }
}
