<?php
namespace Drupal\bulk_batchdelete\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;
/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class BulkUserDeleteCommands extends DrushCommands {
  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;
  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;
  /**
   * Constructs a new UpdateVideosStatsController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }
  /**
   * Delete Users.
   *
   * @param string $number_of_records
   *   Number user wants to delete in batch
   *   Argument provided to the drush command.
   *
   * @param string $batch_size
   *   Batch Size default 50
   *   Argument provided to the drush command.
   * 
   * @param string $batch_name
   *   Name of the batch to create log file
   *   Argument provided to the drush command.
   *
   * @param string $role
   *   Role for which user needs to delete
   *   Argument provided to the drush command.
   * 
   * @command delete:bulkuser
   * @aliases delete-bulkuser
   *
   * @usage delete:bulkuser 10000 50 aug26_b1_10000 general
   *   general is the role of the to users
   */
  public function bulkUserDelete(string $number_of_records = '10000', string $batch_size = '50', string $batch_name = 'unnamed', string $role = 'general') {
    // 1. Log the start of the script.
    $this->loggerChannelFactory->get('bulk_batchdelete')->info('Delete User batch operations start');
    // Get all user id on the basis of role and number of records needs to delete.
    $ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', $role)
      ->range(0, $number_of_records)
      ->execute();

    $num_operations = count($ids);
    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($ids)) {
      foreach (array_chunk($ids, $batch_size) as $idarray) {
        // Prepare the operation. Here we could do other operations on nodes.
        $this->output()->writeln("Preparing batch: " . $batchId);
        $operations[] = [
          '\Drupal\bulk_batchdelete\BatchService::bulk_batchdelete_op',
          [
            $idarray,
            $batch_name,
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }
    else {
      $this->logger()->warning('No user of this role @role', ['@role' => $role]);
    }
    // 4. Create the batch.
    $batch = [
      'title' => t('Updating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\bulk_batchdelete\BatchService::bulk_batchdelete_op_finished',
    ];
    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice("Batch operations end.");
    // 7. Log some information.
    $this->loggerChannelFactory->get('bulk_batchdelete')->info('Delet user batch operations end.');
  }
}