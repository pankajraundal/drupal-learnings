<?php

namespace Drupal\mclaim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\mclaim\Service\ClaimsDataService;
use Symfony\Component\HttpFoundation\Response;


class SubmitClaimsApiController extends ControllerBase {

  protected $extensionPathResolver;

  protected $fileSystem;

  protected $claimsDataService;

  public function __construct(ExtensionPathResolver $extensionPathResolver, FileSystemInterface $file_system, ClaimsDataService $claimsDataService) {
    $this->extensionPathResolver = $extensionPathResolver;
    $this->fileSystem = $file_system;
    $this->claimsDataService = $claimsDataService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('file_system'),
      $container->get('mclaim.claims_data_service')
    );
  }

  public function submitClaims(Request $request) {
    // Get form data from the request
    $data = json_decode($request->getContent(), TRUE);

    // Validate form data

    // Save form data to a JSON file
    $file_path = $this->saveToJsonFile($data);

    // Return response
    if ($file_path) {
      return new JsonResponse(['message' => 'Form data submitted successfully', 'file_path' => $file_path]);
    }
    else {
      return new JsonResponse(['error' => 'Failed to submit form data'], 500);
    }
  }

  protected function saveToJsonFile(array $data) {

    // Get the module directory path
    //$module_path = $this->extensionPathResolver->getPath('module', 'mclaim');

    // Define the directory path to store JSON files
    //$directory = $module_path . '/json_data';
    $directory = 'public://json_data';
    // Check if the directory already exists
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      return FALSE; // Unable to create directory
    }

    // Generate a filename for the JSON file
    $filename = 'patient-data.json';
    $file_path = $directory . '/' . $filename;

    // Read existing JSON data from the file, if it exists
    $existing_data = [];
    if (file_exists($file_path)) {
      $existing_data = json_decode(file_get_contents($file_path), TRUE);
    }

    // Merge existing data with the new data
     $existing_data[] = $data;

    // Save merged data to JSON file
    if (file_put_contents($file_path, json_encode($existing_data)) === FALSE) {
      return FALSE; // Unable to save data to file
    }

    return $file_path;
  }

  public function getClaimsData(Request $request) {
    $values = json_decode($request->getContent(), TRUE);
    // Get all the form values.
    $patient_name = $values['patient_name'];
    $claims_number = $values['claims_number'];
    $service_type = $values['service_type'];
    $start_date = $values['start_date'];
    $end_date = $values['end_date'];
    // Pass all filter values to the service method.
    $claims_data = $this->claimsDataService->filterClaimsData($patient_name, $claims_number, $service_type, $start_date, $end_date);
    return new JsonResponse($claims_data);
  }

  public function getClaimNumbers(Request $request) {
    $claims_data = $this->claimsDataService->getAllClaimNumbers();

    // Get the user input from the request
    $input = $request->query->get('q');

    // Filter claim data to find matching claim numbers
    $matched_claim_numbers = array_filter($claims_data, function ($claim_number) use ($input) {
      return strpos($claim_number, $input) !== FALSE;
    });
    foreach ($matched_claim_numbers as $claim_number) {
      $matches[] = ['value' => $claim_number];
    }
    // Return the matches as JSON response
    return new JsonResponse($matches);
  }

  public function exportClaimsData(Request $request) {
    // Get all the form values.
    $patient_name = $request->query->get('patient_name');
    $claims_number = $request->query->get('claims_number');
    $service_type = $request->query->get('service_type');
    $start_date = $request->query->get('start_date');
    $end_date = $request->query->get('end_date');
    // Pass all filter values to the service method.
    $claims_data = $this->claimsDataService->filterClaimsData($patient_name, $claims_number, $service_type, $start_date, $end_date);
    // Generate the CSV file
    
    $csv = $this->generateCsv($claims_data);
    // Set headers for file download
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');
    return $response;
  }

  public function generateCsv($claims_data) {
    // Define the CSV header
    $csv = "Claims Number, Patient Name, Service Type, Provider Name, Claims Value, Submission Date\n";
    // Add the data to the CSV
    foreach ($claims_data as $claim) {
      $csv .= $claim['claims_number'] . ',';
      $csv .= $claim['patient_name'] . ',';
      $csv .= $claim['service_type'] . ',';
      $csv .= $claim['provider_name'] . ',';
      $csv .= $claim['claims_value'] . ',';
      $csv .= $claim['submission_date'] . "\n";
    }
    return $csv;
  }

}
