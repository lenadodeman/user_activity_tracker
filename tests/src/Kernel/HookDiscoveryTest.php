<?php

namespace Drupal\Tests\user_activity_tracker\Kernel;

use Drupal\user_activity_tracker\Service\ActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests discovery and invocation of object-oriented hooks.
 */
#[Group('user_activity_tracker')]
#[RunTestsInSeparateProcesses]
class HookDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'config', 'user_activity_tracker'];

  /**
   * Tests that a hook is discovered, autowired, and invoked exactly once.
   */
  public function testUserLoginHookDiscovery(): void {
    $account = $this->createMock(AccountInterface::class);
    $tracker = $this->createMock(ActivityTrackerService::class);
    $tracker->expects($this->once())
      ->method('track')
      ->with(TrackedAction::UserLogin, [], $account);
    $this->container->set(ActivityTrackerService::class, $tracker);

    $this->container->get('module_handler')
      ->invoke('user_activity_tracker', 'user_login', [$account]);
  }

}
