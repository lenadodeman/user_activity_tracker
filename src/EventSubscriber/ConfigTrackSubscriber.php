<?php

namespace Drupal\user_activity_tracker\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\user_activity_tracker\Service\ActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to configuration events to track changes.
 *
 * Tracked actions:
 * - config_save.
 * - config_delete.
 * - config_import.
 */
class ConfigTrackSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\user_activity_tracker\Service\ActivityTrackerService $activityTrackerService
   *   The activity tracker service.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager service.
   */
  public function __construct(
    protected ActivityTrackerService $activityTrackerService,
    protected ConfigManagerInterface $configManager,
  ) {}

  /**
   * React to configuration save events.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event to process.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    $name = $config->getName();

    // Only handle simple configurations, excluding entities.
    if ($this->configManager->getEntityTypeIdByName($name) !== NULL) {
      return;
    }

    $this->activityTrackerService->track(TrackedAction::ConfigSave, [
      'config_name' => $name,
    ]);
  }

  /**
   * React to configuration delete events.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event to process.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    $name = $config->getName();

    if ($this->configManager->getEntityTypeIdByName($name) !== NULL) {
      return;
    }

    $this->activityTrackerService->track(TrackedAction::ConfigDelete, [
      'config_name' => $name,
    ]);
  }

  /**
   * React to configuration import events.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   The event to process.
   */
  public function onConfigImport(ConfigImporterEvent $event): void {
    $importer = $event->getConfigImporter();
    $count = 0;
    foreach ($importer->getStorageComparer()->getAllCollectionNames() as $collection) {
      foreach ($importer->getProcessedConfiguration($collection) as $changes) {
        $count += count($changes);
      }
    }

    $this->activityTrackerService->track(TrackedAction::ConfigImport, [
      'operation' => 'Configuration import completed',
      'changelist_count' => $count,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => [['onConfigSave', 10]],
      ConfigEvents::DELETE => [['onConfigDelete', 10]],
      ConfigEvents::IMPORT => [['onConfigImport', 10]],
    ];
  }

}
