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

  /**
   * @var \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver service.
   */
  protected $extensionPathResolver;

  /**
   * @var \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  protected $fileSystem;

  /**
   * @var \Drupal\mclaim\Service\ClaimsDataService
   *   The claims data service.
   */
  protected $claimsDataService;

  /**
   * Constructs a new SubmitClaimsApiController object.
   *
   * @param ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver service.
   * @param FileSystemInterface $file_system
   *   The file system service.
   * @param ClaimsDataService $claimsDataService
   *   The claims data service.
   */
  public function __construct(ExtensionPathResolver $extensionPathResolver, FileSystemInterface $file_system, ClaimsDataService $claimsDataService) {
    $this->extensionPathResolver = $extensionPathResolver;
    $this->fileSystem = $file_system;
    $this->claimsDataService = $claimsDataService;
  }

  /**
   * Creates a new instance of the SubmitClaimsApiController class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('file_system'),
      $container->get('mclaim.claims_data_service')
    );
  }

  /**
   * Submits claims.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
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

  /**
   * Saves the given data to a JSON file.
   *
   * @param array $data 
   *  The data to be saved.
   * @return string 
   *  The file path of the saved JSON file.
   */
  protected function saveToJsonFile(array $data) {

    // Uncomment below code if you want to create directory under same module.
    // $module_path = $this->extensionPathResolver->getPath('module', 'mclaim');
    // $directory = $module_path . '/json_data';

    // Create directory json_data in the public file system
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

  /**
   * Retrieves claims data.
   *
   * @param Request $request The request object.
   * @return Response The response object.
   */
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

  /**
   * Retrieves the claim numbers from the request.
   *
   * @param Request $request The HTTP request object.
   * @return Response The response containing the claim numbers.
   */
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

  /**
   * Export claims data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
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

  /**
   * Generates a CSV file from the given claims data.
   *
   * @param array $claims_data The data to be included in the CSV file.
   * @return string The CSV file content.
   */
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
