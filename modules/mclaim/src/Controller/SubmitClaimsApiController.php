<?php

namespace Drupal\mclaim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ExtensionPathResolver;

class SubmitClaimsApiController extends ControllerBase {

  protected $extensionPathResolver;

  protected $fileSystem;

  public function __construct(ExtensionPathResolver $extensionPathResolver, FileSystemInterface $file_system) {
    $this->extensionPathResolver = $extensionPathResolver;
    $this->fileSystem = $file_system;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.path.resolver'),
      $container->get('file_system')
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
    $module_path = $this->extensionPathResolver->getPath('module', 'mclaim');

    // Define the directory path to store JSON files
    $directory = $module_path . '/json_data';
    // Check if the directory already exists
    if (!file_exists($directory)) {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
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

}
