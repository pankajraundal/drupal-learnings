<?php
namespace Drupal\bulk_batchdelete\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\user\Entity\User;

/**
 * Class ProcessEntity.
 */
class ProcessEntity extends EntityTypeManager
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * Constructs an ProcessEntity object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager)
  {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Function to get list of all entities.
   */
  public function listEntity()
  {
    $entity_types = $this->entityTypeManager->getDefinitions('content');
    $result = [];
    foreach ($entity_types as $name => $entity_type) {
      $group = $entity_type->getGroupLabel()->render();
      if ($group == 'Content') {
        $bundleType = $entity_type->getBundleEntityType();
        $result[$bundleType] = $name;
      }
    }
    return $result;
  }

  /**
   * Function to get list of all entities.
   * For now we have consider limited entities.
   * We have scope for enhancement by adding more entities.
   */
  public function listLimitedEntity()
  {
    // Current support entity, This can be increased in future release.
    $supportEntities = [
      'user_role' => 'user',
      'node_type' => 'node',
      'taxonomy_vocabulary' => 'taxonomy_term'
    ];
    $listContentEntities = $this->listEntity();
    // Generate array of only those entities which are supported.
    $result = array_intersect($supportEntities, $listContentEntities);
    return $result;
  }

  /**
   * @param string $entity_type
   *    get bundles for provided entity.
   *
   * @return array
   * 
   */
  public function getBundles(string $entity_type)
  {
    $types = [];
    $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
    foreach ($entities as $entitie) {
      if ($entitie->id() != 'anonymous') {
        $types[$entitie->id()] = $entitie->label();
      }
    }
    return $types;
  }

  /**
   * Function to get list of all user status.
   */
  public function getListOfUserStatus()
  {
    // Get diaplyed only when "User" entity has been selected
    $userStatus = [
      'all' => 'Both Blocked and Active',
      '0' => 'Blocked',
      '1' => 'Active',
    ];
    return $userStatus;
  }

  /**
   * Function to select option about Cancellation method.
   */
  public function getListOfCancelMethod()
  {
    // Get diaplyed only when "User" entity has been selected
    $userStatus = [
      'user_cancel_block' => 'Disable the account and keep its content.',
      'user_cancel_block_unpublish' => 'Disable the account and unpublish its content.',
      'user_cancel_reassign' => 'Delete the account and make its content belong to the Anonymous user. This action cannot be undone.',
      'user_cancel_delete' => 'Delete the account and its content. This action cannot be undone.'
    ];
    return $userStatus;
  }

  /**
   * Function to get tablename mapped against entity.
   */
  public function getEntityTableNameMapping()
  {
    // Entity name is not match with the table name for.
    // So we need to matach that using below array.
    $entityTableNameMapping = [
      'user' => 'users',
      'node' => 'node',
      'taxonomy_term' => 'taxonomy_term',
    ];
    return $entityTableNameMapping;
  }

  /**
 * This is the function that is called on each operation in batch.
 *
 * This deletes an entity from system
 * 
 * @param string $entityType
 *   Entit type which needs to delete.
 * @param string $id
 *   id of an entity which needs to delete
 *
 */
  public function deleteEntity(string $entityType, int $id)
  {
    switch ($entityType) {
      case 'user':
        $this->deleteUserEntity($id);
        break;
      case 'node':
        $this->deleteNodeEntity($id);
        break;
      case 'taxonomy_term':
        $this->deleteTaxonomyEntity($id);
        break;
    }
  }

  /**
   * Function to get tablename mapped against entity.
   */
  public function deleteUserEntity($userId)
  {
    user_cancel([], $id, 'user_cancel_reassign');
    $account = User::load($id);
    $deleteAccountConfirmation = $account->delete();
    return $deleteAccountConfirmation;
  }

  /**
   * Function to get tablename mapped against entity.
   */
  public function deleteNodeEntity($nodeId)
  {
    user_cancel([], $id, 'user_cancel_reassign');
    $account = User::load($id);
    $account->delete();
  }

  /**
   * Function to get tablename mapped against entity.
   */
  public function deleteTaxonomyEntity($vocabularyId)
  {
    user_cancel([], $id, 'user_cancel_reassign');
    $account = User::load($id);
    $account->delete();
  }
}
