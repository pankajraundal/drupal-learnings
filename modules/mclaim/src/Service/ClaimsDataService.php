<?php

namespace Drupal\mclaim\Service;

class ClaimsDataService {

  // Define the directory path to store JSON files.
  const JSON_DIRECTORY = 'public://json_data';
  // Define the filename for the JSON file.
  const JSON_FILENAME = 'patient-data.json';

  public function getClaimsData() {
    $file_path = self::JSON_DIRECTORY . '/' . self::JSON_FILENAME;
    $json_data = file_get_contents($file_path);
    return json_decode($json_data, TRUE);
  }

  public function getAllPatientNames() {
    $data = $this->getClaimsData();
    $patient_names = [];
    foreach ($data as $claim) {
      $patient_names[] = $claim['patient_name'];
    }
    return array_unique($patient_names);
  }

  public function getAllClaimNumbers() {
    $data = $this->getClaimsData();
    $claims_numbers = [];
    foreach ($data as $claim) {
      $claims_numbers[] = $claim['claims_number'];
    }
    return array_unique($claims_numbers);
  }

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