<?php

namespace Drupal\user_activity_tracker;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum for tracked actions.
 */
enum TrackedAction: string {

  case UserLogin = 'login';
  case UserLogout = 'logout';
  case EntityInsert = 'entity_insert';
  case EntityUpdate = 'entity_update';
  case EntityPublish = 'entity_publish';
  case EntityUnpublish = 'entity_unpublish';
  case EntityDelete = 'entity_delete';
  case RoleCreate = 'role_create';
  case RoleUpdate = 'role_update';
  case RoleDelete = 'role_delete';
  case UserRoleChange = 'user_role_change';
  case FileInsert = 'file_insert';
  case FileDelete = 'file_delete';
  case ConfigImport = 'config_import';
  case ConfigSave = 'config_save';
  case ConfigDelete = 'config_delete';

  /**
   * Get human-readable labels for each tracked action.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   An associative array of action values and their corresponding labels.
   */
  public static function options(): array {
    $options = [];
    foreach (self::cases() as $action) {
      $options[$action->value] = $action->translatableLabel();
    }
    return $options;
  }

  /**
   * Get the human-readable label for the current tracked action.
   *
   * @return string
   *   The label corresponding to the current action value.
   */
  public function label(): string {
    return (string) $this->translatableLabel();
  }

  /**
   * Returns the translatable label for this action.
   */
  private function translatableLabel(): TranslatableMarkup {
    $options = ['context' => 'tracked action'];
    return match ($this) {
      self::UserLogin => t('Login', [], $options),
      self::UserLogout => t('Logout', [], $options),
      self::EntityInsert => t('Entity Insert', [], $options),
      self::EntityUpdate => t('Entity Update', [], $options),
      self::EntityPublish => t('Entity Publish', [], $options),
      self::EntityUnpublish => t('Entity Unpublish', [], $options),
      self::EntityDelete => t('Entity Delete', [], $options),
      self::RoleCreate => t('Role Creation', [], $options),
      self::RoleUpdate => t('Role Update', [], $options),
      self::RoleDelete => t('Role Deletion', [], $options),
      self::UserRoleChange => t('User Role Change', [], $options),
      self::FileInsert => t('File Insert', [], $options),
      self::FileDelete => t('File Delete', [], $options),
      self::ConfigImport => t('Configuration Import', [], $options),
      self::ConfigSave => t('Configuration Save', [], $options),
      self::ConfigDelete => t('Configuration Delete', [], $options),
    };
  }

}
