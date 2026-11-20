<?php

namespace Drupal\Tests\user_activity_tracker\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the schema for role-specific action selections.
 */
#[Group('user_activity_tracker')]
#[RunTestsInSeparateProcesses]
class SettingsSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'config', 'user_activity_tracker'];

  /**
   * Tests empty selections and arbitrary role and action keys.
   */
  public function testRoleActionsSchema(): void {
    $typed = $this->container->get('config.typed');
    foreach ([
      [],
      [
        'authenticated' => ['login' => 'login', 'logout' => 'logout'],
        'custom_editor' => ['entity_update' => 'entity_update'],
        'empty_role' => [],
      ],
    ] as $actions) {
      $this->assertConfigSchema($typed, 'user_activity_tracker.settings', [
        'track_role_actions' => $actions,
      ]);
    }
  }

}
