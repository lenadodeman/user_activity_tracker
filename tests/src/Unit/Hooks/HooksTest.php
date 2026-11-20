<?php

namespace Drupal\Tests\user_activity_tracker\Unit\Hooks;

use Drupal\user_activity_tracker\Hook\UserActivityTrackerHooks;
use Drupal\user_activity_tracker\Service\UserActivityTrackerService;
use Drupal\user_activity_tracker\TrackedAction;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Hooks class.
 */
#[Group('user_activity_tracker')]
#[CoversClass(UserActivityTrackerHooks::class)]
class HooksTest extends TestCase {

  /**
   * The UserActivityTrackerService mock.
   *
   * @var \Drupal\user_activity_tracker\Service\UserActivityTrackerService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $activityTrackerService;

  /**
   * The Hooks class instance.
   *
   * @var \Drupal\user_activity_tracker\Hook\UserActivityTrackerHooks
   */
  protected $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->activityTrackerService = $this->createMock(UserActivityTrackerService::class);
    $this->hooks = new UserActivityTrackerHooks($this->activityTrackerService);
  }

  /**
   * Test userLogin hook.
   */
  public function testUserLogin(): void {
    $account = $this->createMock(AccountInterface::class);

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(TrackedAction::UserLogin, [], $account);

    $this->hooks->userLogin($account);
  }

  /**
   * Test userLogout hook.
   */
  public function testUserLogout(): void {
    $account = $this->createMock(AccountInterface::class);

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(TrackedAction::UserLogout, [], $account);

    $this->hooks->userLogout($account);
  }

  /**
   * Test entityInsert hook with excluded entity type.
   */
  public function testEntityInsertExcluded(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('file');

    $this->activityTrackerService
      ->expects($this->never())
      ->method('track');

    $this->hooks->entityInsert($entity);
  }

  /**
   * Test userRoleInsert hook.
   */
  public function testUserRoleInsert(): void {
    $role = $this->createMock(RoleInterface::class);
    $role->method('id')->willReturn('admin');
    $role->method('label')->willReturn('Administrator');

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(
        TrackedAction::RoleCreate,
        [
          'entity_type' => 'user_role',
          'entity_id' => 'admin',
          'label' => 'Administrator',
        ]
      );

    $this->hooks->userRoleInsert($role);
  }

  /**
   * Test fileInsert hook.
   */
  public function testFileInsert(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn(1);
    $file->method('getFilename')->willReturn('test.pdf');

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(
        TrackedAction::FileInsert,
        [
          'entity_type' => 'file',
          'entity_id' => 1,
          'label' => 'test.pdf',
        ]
      );

    $this->hooks->fileInsert($file);
  }

  /**
   * Test userDelete hook.
   */
  public function testUserDelete(): void {
    $user = $this->createMock(UserInterface::class);
    $user->method('getEntityTypeId')->willReturn('user');
    $user->method('id')->willReturn(123);
    $user->method('getAccountName')->willReturn('testuser');
    $user->method('getEmail')->willReturn('test@example.com');
    $user->method('label')->willReturn('Test User');

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(
        TrackedAction::EntityDelete,
        [
          'entity_type' => 'user',
          'entity_id' => 123,
          'user_name' => 'testuser',
          'user_email' => 'test@example.com',
          'label' => 'Test User',
        ]
      );

    $this->hooks->userDelete($user);
    $this->hooks->entityDelete($user);
  }

  /**
   * Tests generic entity deletion context.
   */
  #[DataProvider('entityDeletionProvider')]
  public function testEntityDelete(string $entity_type, string $bundle): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entity_type);
    $entity->method('id')->willReturn(1);
    $entity->method('bundle')->willReturn($bundle);
    $entity->method('label')->willReturn('Deleted entity');

    $this->activityTrackerService
      ->expects($this->once())
      ->method('track')
      ->with(
        TrackedAction::EntityDelete,
        [
          'entity_type' => $entity_type,
          'entity_id' => 1,
          'bundle' => $bundle,
          'label' => 'Deleted entity',
        ]
      );

    $this->hooks->entityDelete($entity);
  }

  /**
   * Provides content and configuration entities tracked by generic deletion.
   */
  public static function entityDeletionProvider(): array {
    return [
      'node' => ['node', 'article'],
      'media' => ['media', 'image'],
      'comment' => ['comment', 'comment'],
      'taxonomy term' => ['taxonomy_term', 'tags'],
      'configuration entity' => ['view', 'view'],
    ];
  }

  /**
   * Test entityDelete hook excludes users tracked by user_delete.
   */
  public function testEntityDeleteUser(): void {
    $user = $this->createMock(UserInterface::class);
    $user->method('getEntityTypeId')->willReturn('user');
    $this->activityTrackerService
      ->expects($this->never())
      ->method('track');

    $this->hooks->entityDelete($user);
  }

  /**
   * Tests exclusions for entities with dedicated deletion hooks.
   */
  #[DataProvider('excludedDeletionProvider')]
  public function testEntityDeleteExcluded(string $entity_type): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entity_type);

    $this->activityTrackerService
      ->expects($this->never())
      ->method('track');

    $this->hooks->entityDelete($entity);
  }

  /**
   * Provides entities whose deletions are tracked separately.
   */
  public static function excludedDeletionProvider(): array {
    return [
      'file' => ['file'],
      'role' => ['user_role'],
    ];
  }

  /**
   * Tests technical role action exclusions without excluding other actions.
   */
  #[DataProvider('actionDeletionProvider')]
  public function testActionDeletion(string $plugin, bool $tracked): void {
    $action = $this->createMock(ActionConfigEntityInterface::class);
    $action->method('getEntityTypeId')->willReturn('action');
    $action->method('get')->with('plugin')->willReturn($plugin);
    $action->method('id')->willReturn('example_action');
    $action->method('bundle')->willReturn('action');
    $action->method('label')->willReturn('Example action');

    $this->activityTrackerService->expects($tracked ? $this->once() : $this->never())
      ->method('track')
      ->with(TrackedAction::EntityDelete, [
        'entity_type' => 'action',
        'entity_id' => 'example_action',
        'bundle' => 'action',
        'label' => 'Example action',
      ]);

    $this->hooks->entityDelete($action);
  }

  /**
   * Provides excluded technical plugins and a regular action plugin.
   */
  public static function actionDeletionProvider(): array {
    return [
      'add role' => ['user_add_role_action', FALSE],
      'remove role' => ['user_remove_role_action', FALSE],
      'regular action' => ['node_publish_action', TRUE],
    ];
  }

  /**
   * Tests publication comparisons against the same translation.
   */
  #[DataProvider('publicationTranslationProvider')]
  public function testPublicationTranslation(bool $default_status, bool $previous_status, bool $current_status, bool $has_translation, TrackedAction $expected_action): void {
    $node = $this->getMockBuilder(Node::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getEntityTypeId', 'language', 'hasTranslation', 'getTranslation', 'isPublished', 'id', 'bundle', 'label', 'getFieldDefinitions'])
      ->getMock();
    $node->method('getFieldDefinitions')->willReturn([]);
    $original = clone $node;
    $previous_translation = clone $node;
    $previous_translation->method('isPublished')->willReturn($previous_status);
    $original->method('isPublished')->willReturn($default_status);
    $original->method('hasTranslation')->with('fr')->willReturn($has_translation);
    $original->expects($has_translation ? $this->once() : $this->never())
      ->method('getTranslation')->with('fr')->willReturn($previous_translation);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('language')->willReturn(new Language(['id' => 'fr']));
    $node->method('isPublished')->willReturn($current_status);
    $node->method('id')->willReturn(1);
    $node->method('bundle')->willReturn('article');
    $node->method('label')->willReturn('French article');
    $this->setOriginal($node, $original);

    $this->activityTrackerService->expects($this->once())
      ->method('track')
      ->with($expected_action, [
        'entity_type' => 'node',
        'entity_id' => 1,
        'bundle' => 'article',
        'label' => 'French article',
      ]);

    $this->hooks->entityUpdate($node);
  }

  /**
   * Provides existing and newly added translation publication states.
   */
  public static function publicationTranslationProvider(): array {
    return [
      'unchanged unpublished translation' => [TRUE, FALSE, FALSE, TRUE, TrackedAction::EntityUpdate],
      'unchanged published translation' => [FALSE, TRUE, TRUE, TRUE, TrackedAction::EntityUpdate],
      'translation published' => [FALSE, FALSE, TRUE, TRUE, TrackedAction::EntityPublish],
      'translation unpublished' => [TRUE, TRUE, FALSE, TRUE, TrackedAction::EntityUnpublish],
      'new published translation' => [FALSE, FALSE, TRUE, FALSE, TrackedAction::EntityUpdate],
      'new unpublished translation' => [TRUE, FALSE, FALSE, FALSE, TrackedAction::EntityUpdate],
    ];
  }

  /**
   * Tests permissions tracking using the original entity API.
   */
  public function testRolePermissionsUpdate(): void {
    $role = $this->getMockBuilder(Role::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['id', 'label', 'getPermissions'])
      ->getMock();
    $original = clone $role;
    $original->method('getPermissions')->willReturn(['access content']);
    $role->method('id')->willReturn('editor');
    $role->method('label')->willReturn('Editor');
    $role->method('getPermissions')->willReturn(['access content', 'administer nodes']);
    $this->setOriginal($role, $original);

    $this->activityTrackerService->expects($this->once())
      ->method('track')
      ->with(TrackedAction::RoleUpdate, [
        'entity_type' => 'user_role',
        'entity_id' => 'editor',
        'label' => 'Editor',
        'added_permissions' => 'administer nodes',
      ]);

    $this->hooks->userRoleUpdate($role);
  }

  /**
   * Tests role changes and duplicate suppression using the original user.
   */
  public function testUserRolesUpdate(): void {
    $account = $this->getMockBuilder(User::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getEntityTypeId', 'id', 'getAccountName', 'getRoles', 'getFieldDefinitions'])
      ->getMock();
    $account->method('getFieldDefinitions')->willReturn([]);
    $original = clone $account;
    $original->method('getRoles')->willReturn(['authenticated', 'editor']);
    $account->method('getEntityTypeId')->willReturn('user');
    $account->method('id')->willReturn(123);
    $account->method('getAccountName')->willReturn('testuser');
    $account->method('getRoles')->willReturn(['reviewer', 'authenticated']);
    $this->setOriginal($account, $original);

    $this->activityTrackerService->expects($this->once())
      ->method('track')
      ->with(TrackedAction::UserRoleChange, [
        'user_id' => 123,
        'user_name' => 'testuser',
        'added_roles' => 'reviewer',
        'removed_roles' => 'editor',
        'current_roles' => 'authenticated, reviewer',
      ]);

    $this->hooks->userUpdate($account);
    $this->hooks->entityUpdate($account);
  }

  /**
   * Tests that technical file updates still use the original file state.
   */
  public function testTechnicalFileUpdate(): void {
    $file = $this->getMockBuilder(File::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getEntityTypeId', 'isTemporary', 'isPermanent', 'getFields', 'getFieldDefinitions'])
      ->getMock();
    $file->method('getFieldDefinitions')->willReturn([]);
    $original = clone $file;
    $original->method('isTemporary')->willReturn(TRUE);
    $file->method('getEntityTypeId')->willReturn('file');
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getFields')->with(FALSE)->willReturn([]);
    $this->setOriginal($file, $original);

    $this->activityTrackerService->expects($this->never())->method('track');

    $this->hooks->entityUpdate($file);
  }

  /**
   * Sets the previous state using the API available in the tested core version.
   */
  private function setOriginal(EntityInterface $entity, EntityInterface $original): void {
    if (method_exists($entity, 'setOriginal')) {
      $entity->setOriginal($original);
    }
    else {
      $entity->original = $original;
    }
  }

}
