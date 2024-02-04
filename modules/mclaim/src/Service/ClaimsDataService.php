<?php

namespace Drupal\mclaim\Service;

class ClaimsDataService {

  
  /**
   * The directory path for JSON data.
   *
   * This constant represents the directory path where JSON data is stored.
   * The value is set to 'public://json_data'.
   */
  const JSON_DIRECTORY = 'public://json_data';

  
  /**
   * The name of the JSON file used for storing patient data.
   */
  const JSON_FILENAME = 'patient-data.json';

  /**
   * Retrieves the claims data.
   *
   * @return array The claims data.
   */
  public function getClaimsData() {
    $file_path = self::JSON_DIRECTORY . '/' . self::JSON_FILENAME;
    $json_data = file_get_contents($file_path);
    return json_decode($json_data, TRUE);
  }

  /**
   * Retrieves all patient names.
   *
   * @return array An array of patient names.
   */
  public function getAllPatientNames() {
    $data = $this->getClaimsData();
    $patient_names = [];
    foreach ($data as $claim) {
      $patient_names[] = $claim['patient_name'];
    }
    return array_unique($patient_names);
  }

  /**
   * Retrieves all claim numbers.
   *
   * @return array An array of claim numbers.
   */
  public function getAllClaimNumbers() {
    $data = $this->getClaimsData();
    $claims_numbers = [];
    foreach ($data as $claim) {
      $claims_numbers[] = $claim['claims_number'];
    }
    return array_unique($claims_numbers);
  }

  /**
   * Filters the claims data based on the provided parameters.
   *
   * @param string $patient_name The name of the patient.
   * @param string $claims_number The claims number.
   * @param string $service_type The type of service.
   * @param string $start_date The start date of the claims.
   * @param string $end_date The end date of the claims.
   * @return array The filtered claims data.
   */
  public function filterClaimsData($patient_name = '', $claims_number = '', $service_type = '', $start_date = '', $end_date = '') {
    $data = $this->getClaimsData();
    $filtered_data = [];
    
    foreach ($data as $claim) {
      if (
        (empty($patient_name) || $claim['patient_name'] === $patient_name) &&
        (empty($claims_number) || $claim['claims_number'] === $claims_number) &&
        (empty($service_type) || $claim['service_type'] === $service_type) &&
        (empty($start_date) || strtotime($claim['submission_date']) >= strtotime($start_date)) &&
        (empty($end_date) || strtotime($claim['submission_date']) <= strtotime($end_date))
      ) {
        $filtered_data[] = $claim;
      }
    }
    return $filtered_data;
  }

}