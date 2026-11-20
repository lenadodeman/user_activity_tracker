<?php

namespace Drupal\Tests\user_activity_tracker\Unit\Service;

use Drupal\user_activity_tracker\Service\ActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests role-based activity tracking.
 */
#[Group('user_activity_tracker')]
#[CoversClass(ActivityTrackerService::class)]
class ActivityTrackerServiceTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set(
      'string_translation',
      new TranslationManager(new LanguageDefault(['id' => 'en'])),
    );
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();

    parent::tearDown();
  }

  /**
   * Tests that the current user's first matching role produces one log entry.
   */
  public function testTrackLogsOnceForMatchingRoles(): void {
    $current_user = $this->createMock(AccountInterface::class);
    $current_user->method('getRoles')
      ->willReturn(['viewer', 'editor', 'administrator']);
    $current_user->method('id')->willReturn(42);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('notice')
      ->with(
        '[TRACKER][@action_upper] Action: @action | '
        . 'ActorId: @actor_id | Context: @context',
        [
          '@action_upper' => 'CONFIG_SAVE',
          '@action' => 'Configuration Save',
          '@actor_id' => 42,
          '@context' => 'config_name: system.site | label: Main site',
        ],
      );

    $service = $this->createService([
      'viewer' => [],
      'editor' => [TrackedAction::ConfigSave->value],
      'administrator' => [TrackedAction::ConfigSave->value],
    ], $logger, $current_user);

    $service->track(TrackedAction::ConfigSave, [
      'config_name' => 'system.site',
      '@label' => 'Main site',
    ]);
  }

  /**
   * Tests that an explicit account is used instead of the current user.
   */
  public function testTrackUsesExplicitAccount(): void {
    $current_user = $this->createMock(AccountInterface::class);
    $current_user->expects($this->never())->method('getRoles');
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn(['auditor']);
    $account->method('id')->willReturn(7);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('notice')
      ->with(
        '[TRACKER][@action_upper] Action: @action | '
        . 'ActorId: @actor_id | Context: @context',
        [
          '@action_upper' => 'LOGIN',
          '@action' => 'Login',
          '@actor_id' => 7,
          '@context' => '(none)',
        ],
      );

    $service = $this->createService([
      'auditor' => [TrackedAction::UserLogin->value],
    ], $logger, $current_user);

    $service->track(TrackedAction::UserLogin, [], $account);
  }

  /**
   * Tests that an action not enabled for any account role is not logged.
   */
  public function testTrackDoesNotLogUnselectedAction(): void {
    $current_user = $this->createMock(AccountInterface::class);
    $current_user->method('getRoles')->willReturn(['editor']);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->never())->method('notice');

    $service = $this->createService([
      'editor' => [TrackedAction::UserLogin->value],
    ], $logger, $current_user);

    $service->track(TrackedAction::ConfigSave);
  }

  /**
   * Creates the tracker with role-action settings.
   *
   * @param array $tracked_actions
   *   The configured actions indexed by role ID.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to use.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user account.
   *
   * @return \Drupal\user_activity_tracker\Service\ActivityTrackerService
   *   The activity tracker service.
   */
  private function createService(
    array $tracked_actions,
    LoggerChannelInterface $logger,
    AccountInterface $current_user,
  ): ActivityTrackerService {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('track_role_actions')
      ->willReturn($tracked_actions);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('user_activity_tracker.settings')
      ->willReturn($config);

    return new ActivityTrackerService($config_factory, $logger, $current_user);
  }

}
