<?php

namespace Drupal\user_activity_tracker\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user_activity_tracker\TrackedAction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to track roles activity.
 */
class UserActivityTrackerService {

  /**
   * Constructs an UserActivityTrackerService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'logger.channel.user_activity_tracker')]
    protected LoggerChannelInterface $logger,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Track an action for a user based on their roles.
   *
   * @param \Drupal\user_activity_tracker\TrackedAction $action
   *   The action being performed.
   * @param array $context
   *   Additional context for logging.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account performing the action.
   *   If NULL, the current user will be used.
   */
  public function track(TrackedAction $action, array $context = [], ?AccountInterface $account = NULL): void {
    $tracked_actions = $this->configFactory
      ->get('activity_tracker.settings')
      ->get('track_role_actions') ?? [];

    if (!$account) {
      $account = $this->currentUser;
    }

    foreach ($account->getRoles() as $rid) {
      if (empty($tracked_actions[$rid]) || !in_array($action->value, $tracked_actions[$rid], TRUE)) {
        continue;
      }

      // Log the first matching role only to avoid duplicate entries.
      $this->logger->notice('[TRACKER][@action_upper] Action: @action | ActorId: @actor_id | Context: @context', [
        '@action_upper' => strtoupper($action->value),
        '@action' => ucfirst($action->label()),
        '@actor_id' => $account->id(),
        '@context' => $this->formatContext($context),
      ]);
      break;
    }
  }

  /**
   * Formats context array into a readable string.
   *
   * @param array $context
   *   The context key-value pairs.
   *
   * @return string
   *   Formatted context string, or '(none)' if empty.
   */
  protected function formatContext(array $context): string {
    if (empty($context)) {
      return '(none)';
    }

    $pairs = [];
    foreach ($context as $key => $value) {
      $pairs[] = ltrim((string) $key, '@') . ': ' . (string) $value;
    }

    return implode(' | ', $pairs);
  }

}
