<?php
namespace Drupal\bulk_batchdelete\Service;

use Drupal\user\Entity\User;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Class BatchService.
 */
class BatchService {

  use StringTranslationTrait;
/**
 * File to process bulk delete operations.
 *
 * Batches allow heavy processing to be spread out over several page
 * requests, ensuring that the processing does not get interrupted
 * because of a PHP timeout, while allowing the user to receive feedback
 * on the progress of the ongoing operations. It also can reduce out of memory
 * situations.
 *
 * @see batch
 */

/**
 * This function will process the query and generate Batch .
 *
 * @param int $number_of_records
 *   Fetch number of records from entity which needs to delete.
 * @param string $batch_size
 *   Create batches of small size which needs to process at a time.
 * @param object $batch_name
 *   Give name to the batch for logging and identification purpose.
 * @param Array $ids
 *   Collection of values which needs to process the batch.
 */
public function generateBatch(string $batch_size, string $batch_name, array $ids) {

  // Get all user id on the basis of role.
  // And number of records needs to delete.
  $num_operations = count($ids);
  $operations = [];
  // Breakdown your process into small batches(operations).
  // Delete 10 nodes per batch.
  foreach (array_chunk($ids, $batch_size) as $idarray) {
    // Each operation is an array consisting of
    // - The function to call.
    // - An array of arguments to that function.
    $operations[] = [
      '\Drupal\bulk_batchdelete\Service\BatchService::bulkBatchdeleteOperation',
      [
        $idarray,
        $batch_name,
      ],
    ];
  }
  $batch = [
    'title' => $this->t('Deleting records from DB @num', ['@num' => $num_operations]),
    'operations' => $operations,
    'finished' => '\Drupal\bulk_batchdelete\Service\BatchService::bulkBatchdeleteOperationFinished',
  ];
  return $batch;
}

/**
 * This is the function that is called on to generate query and return ID's.
 *
 * This creates an query results and generate ID's
 * which we can passed to batchGenerate function to chunk the data
 * and pass it to batch functions for further processing.
 *
 * @param int $number_of_records
 *   Fetch number of records from entity which needs to delete.
 * @param Array $dataToGenerateQuery
 *  Collection of values which help to generate the query and get entity ID's.
 *
 */
function generateQuery(int $number_of_records, array $dataToGenerateQuery) {

  // Get all variables.
  $entityType = $dataToGenerateQuery['entity_type'];
  $node_type_list = $dataToGenerateQuery['node_type_list'];
  $user_status = $dataToGenerateQuery['user_status'];
  $use_cancellation_method = $dataToGenerateQuery['use_cancellation_method'];

  // Generate Query.
  $ids = \Drupal::entityQuery($entityType)
    ->condition('status', $user_status)
    ->condition('roles', $node_type_list)
    ->range(0, $number_of_records)
    ->execute();

    // Return query result.
  return $ids;
}

/**
 * This is the function that is called on each operation in batch.
 *
 * This creates an operations array defining what batch should do, including
 * what it should do when it's finished. In this case, each operation is the
 * same and by chance even has the same $uid to operate on.
 * 
 * @param int $idarray
 *   Array of Id to process.
 * @param string $batch_name
 *   Batch name to create log file for current batch.
 * @param object $context
 *   Context for operations.
 *
 */
  public static function bulkBatchdeleteOperation(array $idarray, string $batch_name, &$context) {
    // Simulate long process by waiting 1/50th of a second.
    $log_file_path = DRUPAL_ROOT . '/' . PublicStream::basePath() . '/bulk_delete/';
    $log_file_name = $batch_name . '_user_deletion.txt';
    $final_file = $log_file_path . $log_file_name;
    // Process each record.
    foreach ($idarray as $id) {
      // Delete user record.
      user_cancel([], $id, 'user_cancel_reassign');
      $account = User::load($id);
      $account->delete();
      // Get current time to add in log file.
      $current_time = \Drupal::time()->getCurrentTime();
      $log_time = \Drupal::service('date.formatter')->format($current_time, 'custom', 'd-m-Y:H:i:s');
      // Write log in file.
      $txt = t('@log_time - User account @id has been deleted',
      ['@log_time' => $log_time, '@id' => $id]);
      $myfile = file_put_contents($final_file, $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // We will add logs into file.
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, bulk_batchdelete_finished()).
    $batch_size = count($idarray);
    $batch_number = count($context['results']) + 1;
    $context['message'] = t("Deleting @batch_size users per batch. Batch @batch_number",
    ['@batch_size' => $batch_size, '@batch_number' => $batch_number]);
    $context['results'][] = count($idarray);
  }
  
  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function bulkBatchdeleteOperationFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('@count batch processed.', ['@count' => count($results)]));
      $messenger->addMessage(t('The final result was "%final"', ['%final' => end($results)]));
    }
    else {
      $messenger->addMessage(t('Failed'));
    }
  }
}