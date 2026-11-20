<?php

namespace Drupal\Tests\user_activity_tracker\Unit\EventSubscriber;

use Drupal\user_activity_tracker\EventSubscriber\ConfigTrackSubscriber;
use Drupal\user_activity_tracker\Service\UserActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageComparerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests configuration tracking context.
 */
#[Group('user_activity_tracker')]
#[CoversClass(ConfigTrackSubscriber::class)]
class ConfigTrackSubscriberTest extends TestCase {

  /**
   * Tests that saves do not report an unreliable creation flag.
   */
  public function testSaveContext(): void {
    $tracker = $this->createMock(UserActivityTrackerService::class);
    $manager = $this->createMock(ConfigManagerInterface::class);
    $manager->method('getEntityTypeIdByName')
      ->with('system.site')
      ->willReturn(NULL);
    $config = $this->createMock(StorableConfigBase::class);
    $config->method('getName')->willReturn('system.site');
    $config->expects($this->never())->method('isNew');

    $tracker->expects($this->once())
      ->method('track')
      ->with(TrackedAction::ConfigSave, ['config_name' => 'system.site']);

    $subscriber = new ConfigTrackSubscriber($tracker, $manager);
    $subscriber->onConfigSave(new ConfigCrudEvent($config));
  }

  /**
   * Tests that simple configuration deletions are tracked.
   */
  public function testDeleteContext(): void {
    $tracker = $this->createMock(UserActivityTrackerService::class);
    $manager = $this->createMock(ConfigManagerInterface::class);
    $manager->method('getEntityTypeIdByName')
      ->with('system.site')
      ->willReturn(NULL);
    $config = $this->createMock(StorableConfigBase::class);
    $config->method('getName')->willReturn('system.site');

    $tracker->expects($this->once())
      ->method('track')
      ->with(TrackedAction::ConfigDelete, ['config_name' => 'system.site']);

    $subscriber = new ConfigTrackSubscriber($tracker, $manager);
    $subscriber->onConfigDelete(new ConfigCrudEvent($config));
  }

  /**
   * Tests that configuration entities are excluded from save and delete logs.
   */
  public function testEntityConfigurationIsNotTracked(): void {
    $tracker = $this->createMock(UserActivityTrackerService::class);
    $tracker->expects($this->never())->method('track');
    $manager = $this->createMock(ConfigManagerInterface::class);
    $manager->expects($this->exactly(2))
      ->method('getEntityTypeIdByName')
      ->with('core.entity_view_display.node.article.default')
      ->willReturn('entity_view_display');
    $config = $this->createMock(StorableConfigBase::class);
    $config->method('getName')
      ->willReturn('core.entity_view_display.node.article.default');

    $subscriber = new ConfigTrackSubscriber($tracker, $manager);
    $event = new ConfigCrudEvent($config);
    $subscriber->onConfigSave($event);
    $subscriber->onConfigDelete($event);
  }

  /**
   * Tests that import totals include processed operations in all collections.
   */
  public function testImportCountsProcessedOperationsAcrossCollections(): void {
    $tracker = $this->createMock(UserActivityTrackerService::class);
    $manager = $this->createMock(ConfigManagerInterface::class);
    $comparer = $this->createMock(StorageComparerInterface::class);
    $comparer->method('getAllCollectionNames')->willReturn(['', 'language.fr']);
    $comparer->expects($this->never())->method('getChangelist');
    $importer = $this->createMock(ConfigImporter::class);
    $importer->method('getStorageComparer')->willReturn($comparer);
    $importer->expects($this->exactly(2))
      ->method('getProcessedConfiguration')
      ->willReturnMap([
        ['', [
          'create' => ['example.first', 'example.second'],
          'update' => ['system.site'],
          'delete' => ['example.old'],
          'rename' => ['example.before::example.after'],
        ]],
        ['language.fr', [
          'create' => [],
          'update' => ['system.site'],
          'delete' => [],
          'rename' => [],
        ]],
      ]);

    $tracker->expects($this->once())
      ->method('track')
      ->with(TrackedAction::ConfigImport, [
        'operation' => 'Configuration import completed',
        'changelist_count' => 6,
      ]);

    $subscriber = new ConfigTrackSubscriber($tracker, $manager);
    $subscriber->onConfigImport(new ConfigImporterEvent($importer));
  }

  /**
   * Tests that an import without processed configurations reports zero.
   */
  public function testImportWithoutProcessedOperations(): void {
    $tracker = $this->createMock(UserActivityTrackerService::class);
    $manager = $this->createMock(ConfigManagerInterface::class);
    $comparer = $this->createMock(StorageComparerInterface::class);
    $comparer->method('getAllCollectionNames')->willReturn(['']);
    $importer = $this->createMock(ConfigImporter::class);
    $importer->method('getStorageComparer')->willReturn($comparer);
    $importer->method('getProcessedConfiguration')->with('')->willReturn([
      'create' => [],
      'update' => [],
      'delete' => [],
      'rename' => [],
    ]);

    $tracker->expects($this->once())
      ->method('track')
      ->with(TrackedAction::ConfigImport, [
        'operation' => 'Configuration import completed',
        'changelist_count' => 0,
      ]);

    $subscriber = new ConfigTrackSubscriber($tracker, $manager);
    $subscriber->onConfigImport(new ConfigImporterEvent($importer));
  }

}
