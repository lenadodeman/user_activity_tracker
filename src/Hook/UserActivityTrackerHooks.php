<?php

namespace Drupal\user_activity_tracker\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\user_activity_tracker\Service\UserActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Drupal\file\FileInterface;

/**
 * Hook implementations for the Activity Tracker module.
 */
final class UserActivityTrackerHooks {

  /**
   * Constructs the hook implementations.
   */
  public function __construct(
    private readonly UserActivityTrackerService $activityTrackerService,
  ) {}

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(AccountInterface $account): void {
    $this->activityTrackerService->track(TrackedAction::UserLogin, [], $account);
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout(AccountInterface $account): void {
    $this->activityTrackerService->track(TrackedAction::UserLogout, [], $account);
  }

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $account): void {
    $this->activityTrackerService->track(TrackedAction::EntityDelete, [
      'entity_type' => 'user',
      'entity_id' => $account->id(),
      'user_name' => $account->getAccountName(),
      'user_email' => $account->getEmail(),
      'label' => $account->label(),
    ]);
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    // Exclude certain entity types from tracking to avoid redundant log entries.
    $excluded_entity_types = [
      'file',
      'user_role',
    ];

    if (in_array($entity->getEntityTypeId(), $excluded_entity_types, TRUE)) {
      return;
    }

    if ($this->isTechnicalRoleAction($entity)) {
      return;
    }

    $this->activityTrackerService->track(TrackedAction::EntityInsert, [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
    ]);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $excluded_entity_types = [
      'user_role',
    ];

    if ($entity instanceof UserInterface && $this->userRolesChanged($entity)) {
      $excluded_entity_types[] = 'user';
    }

    if (in_array($entity->getEntityTypeId(), $excluded_entity_types, TRUE)) {
      return;
    }

    // Exclude the technical update for files that transition from temporary to permanent.
    // This avoids tracking changes that are not meaningful from a user perspective.
    if ($entity instanceof FileInterface) {
      $original = $this->getOriginal($entity);
      if ($original instanceof FileInterface && $original->isTemporary() && $entity->isPermanent()) {
        $technical_update = TRUE;
        foreach ($entity->getFields(FALSE) as $name => $field) {
          if (in_array($name, ['status', 'changed'], TRUE)) {
            continue;
          }
          if (!$field->equals($original->get($name))) {
            $technical_update = FALSE;
            break;
          }
        }
        if ($technical_update) {
          return;
        }
      }
    }

    $action = TrackedAction::EntityUpdate;
    if ($entity instanceof EntityPublishedInterface) {
      $original = $this->getOriginal($entity);
      if ($entity instanceof ContentEntityInterface && $original instanceof ContentEntityInterface) {
        $langcode = $entity->language()->getId();
        $original = $original->hasTranslation($langcode) ? $original->getTranslation($langcode) : NULL;
      }
      if ($original instanceof EntityPublishedInterface && $original->isPublished() !== $entity->isPublished()) {
        $action = $entity->isPublished() ? TrackedAction::EntityPublish : TrackedAction::EntityUnpublish;
      }
    }

    $this->activityTrackerService->track($action, [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
    ]);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $excluded_entity_types = [
      'user',
      'file',
      'user_role',
    ];

    if (in_array($entity->getEntityTypeId(), $excluded_entity_types, TRUE) || $this->isTechnicalRoleAction($entity)) {
      return;
    }

    $this->activityTrackerService->track(TrackedAction::EntityDelete, [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
    ]);
  }

  /**
   * Implements hook_user_role_insert().
   */
  #[Hook('user_role_insert')]
  public function userRoleInsert(RoleInterface $role): void {
    $this->activityTrackerService->track(TrackedAction::RoleCreate, [
      'entity_type' => 'user_role',
      'entity_id' => $role->id(),
      'label' => $role->label(),
    ]);
  }

  /**
   * Implements hook_user_role_update().
   */
  #[Hook('user_role_update')]
  public function userRoleUpdate(RoleInterface $role): void {
    $context = [
      'entity_type' => 'user_role',
      'entity_id' => $role->id(),
      'label' => $role->label(),
    ];

    $original = $this->getOriginal($role);
    if ($original instanceof RoleInterface) {
      $original_permissions = $original->getPermissions();
      $current_permissions = $role->getPermissions();

      $added_permissions = array_diff($current_permissions, $original_permissions);
      $removed_permissions = array_diff($original_permissions, $current_permissions);

      // The permissions form also saves unchanged roles; keep only meaningful changes.
      if (empty($added_permissions) && empty($removed_permissions)) {
        $original_values = $original->toArray();
        $current_values = $role->toArray();
        unset($original_values['permissions'], $current_values['permissions']);

        if ($original_values == $current_values) {
          return;
        }
      }

      if (!empty($added_permissions)) {
        $context['added_permissions'] = implode(', ', $added_permissions);
      }

      if (!empty($removed_permissions)) {
        $context['removed_permissions'] = implode(', ', $removed_permissions);
      }
    }

    $this->activityTrackerService->track(TrackedAction::RoleUpdate, $context);
  }

  /**
   * Implements hook_user_role_delete().
   */
  #[Hook('user_role_delete')]
  public function userRoleDelete(RoleInterface $role): void {
    $this->activityTrackerService->track(TrackedAction::RoleDelete, [
      'entity_type' => 'user_role',
      'entity_id' => $role->id(),
      'label' => $role->label(),
    ]);
  }

  /**
   * Implements hook_user_update().
   */
  #[Hook('user_update')]
  public function userUpdate(UserInterface $account): void {
    $original = $this->getOriginal($account);
    if (!$original instanceof UserInterface) {
      return;
    }

    $old_roles = $original->getRoles();
    $new_roles = $account->getRoles();
    sort($old_roles);
    sort($new_roles);

    if ($old_roles === $new_roles) {
      return;
    }

    $this->activityTrackerService->track(TrackedAction::UserRoleChange, [
      'user_id' => $account->id(),
      'user_name' => $account->getAccountName(),
      'added_roles' => implode(', ', array_diff($new_roles, $old_roles)),
      'removed_roles' => implode(', ', array_diff($old_roles, $new_roles)),
      'current_roles' => implode(', ', $new_roles),
    ]);
  }

  /**
   * Implements hook_file_insert().
   */
  #[Hook('file_insert')]
  public function fileInsert(FileInterface $file): void {
    $this->activityTrackerService->track(TrackedAction::FileInsert, [
      'entity_type' => 'file',
      'entity_id' => $file->id(),
      'label' => $file->getFilename(),
    ]);
  }

  /**
   * Implements hook_file_delete().
   */
  #[Hook('file_delete')]
  public function fileDelete(FileInterface $file): void {
    $this->activityTrackerService->track(TrackedAction::FileDelete, [
      'entity_type' => 'file',
      'entity_id' => $file->id(),
      'label' => $file->getFilename(),
    ]);
  }

  /**
   * Checks whether user roles changed, independently of their order.
   */
  private function userRolesChanged(UserInterface $account): bool {
    $original = $this->getOriginal($account);
    if (!$original instanceof UserInterface) {
      return FALSE;
    }

    $old_roles = $original->getRoles();
    $new_roles = $account->getRoles();
    sort($old_roles);
    sort($new_roles);

    return $old_roles !== $new_roles;
  }

  /**
   * Identifies technical actions generated for bulk role operations.
   */
  private function isTechnicalRoleAction(EntityInterface $entity): bool {
    return $entity instanceof ActionConfigEntityInterface && in_array($entity->get('plugin'), [
      'user_add_role_action',
      'user_remove_role_action',
    ], TRUE);
  }

  /**
   * Gets the previous entity state across supported Drupal versions.
   */
  private function getOriginal(EntityInterface $entity): ?EntityInterface {
    if (method_exists($entity, 'getOriginal')) {
      return $entity->getOriginal();
    }

    return $entity->original ?? NULL;
  }

}
