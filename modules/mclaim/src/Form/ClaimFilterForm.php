<?php

namespace Drupal\mclaim\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mclaim\Service\ClaimsDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClaimFilterForm extends FormBase {
  protected $claimsDataService;

  public function __construct(ClaimsDataService $claimsDataService) {
     $this->claimsDataService = $claimsDataService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mclaim.claims_data_service')
    );
  }

  public function getFormId() {
    return 'claim_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add Patient Name dropdown
    $form['patient_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Patient Name'),
      // Add options dynamically if needed
      '#options' => ['' => $this->t('Any')] + $this->claimsDataService->getAllPatientNames(),
      // set the default value if submiteed
      '#default_value' => $key = array_search (\Drupal::request()->query->get('patient_name'), $this->claimsDataService->getAllPatientNames()),
    ];

    // Add Claims Number autocomplete field
    $form['claims_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Claims Number'),
      "#size" => "9",
      '#autocomplete_route_name' => 'mclaim.claims_number', // Replace with the route name for your autocomplete callback
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('claims_number'),
    ];

    // Add Service Type dropdown
    $form['service_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Service Type'),

      '#options' => [
        '' => $this->t('Any'), // Add empty option with empty value
        'medical' => $this->t('Medical'),
        'dental' => $this->t('Dental'),
      ],
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('service_type'),
    ];

    // Add Start Date field
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('start_date'),
    ];

    // Add End Date field
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      // Set default value if submitted
      '#default_value' => \Drupal::request()->query->get('end_date'),    
    ];

    // Add submit button
    $form['filter'] = [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
        '#submit' => ['::filterFormSubmit'],
        '#attributes' => ['class' => ['button']],
      ];

    // Provide search result to export as CSV
    // Add export button
    $form['export'] = [
        '#type' => 'submit',
        '#value' => $this->t('Export to CSV'),
        '#submit' => ['::exportFormSubmit'],
        '#attributes' => ['class' => ['button']],
      ];


    return $form;
  }

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

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Implement submit logic if needed
  }
  
  public function filterFormSubmit(array &$form, FormStateInterface $form_state) {
    // Submit all values to the controller method "claimFilterForm" for further processing.
    if($form_state->getValue('patient_name') == '') {
        $selected_patient_name = '';
    } else {
        $selected_patient_name = $form['patient_name']['#options'][$form_state->getValue('patient_name')];
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

  public function exportFormSubmit(array &$form, FormStateInterface $form_state) {
    // Submit all values to the controller method "claimFilterForm" for further processing.
    if($form_state->getValue('patient_name') == '') {
        $selected_patient_name = '';
    } else {
        $selected_patient_name = $form['patient_name']['#options'][$form_state->getValue('patient_name')];
    }
    
    //$selected_service_type = $form['service_type']['#options'][$form_state->getValue('service_type')];
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
