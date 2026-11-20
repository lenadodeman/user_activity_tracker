<?php

namespace Drupal\Tests\user_activity_tracker\Unit\Form;

use Drupal\user_activity_tracker\Form\ActivityTrackerSettingsForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests role tracking configuration through the form.
 */
#[Group('user_activity_tracker')]
#[CoversClass(ActivityTrackerSettingsForm::class)]
class ActivityTrackerSettingsFormTest extends TestCase {

  /**
   * Tests disabling a role without clearing its selected actions.
   */
  public function testDisableRoleWithSelectedActions(): void {
    $saved = [
      'editor' => ['login' => 'login'],
      'authenticated' => ['logout' => 'logout'],
    ];
    $config = $this->createMock(Config::class);
    $config->method('get')->with('track_role_actions')->willReturn($saved);
    $config->expects($this->once())->method('set')
      ->with('track_role_actions', ['authenticated' => ['logout' => 'logout']])
      ->willReturnSelf();
    $config->expects($this->once())->method('save')->willReturnSelf();
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('getEditable')->with('user_activity_tracker.settings')->willReturn($config);
    $typed = $this->createMock(TypedConfigManagerInterface::class);

    $roles = [];
    foreach (['editor' => 'Editor', 'authenticated' => 'Authenticated user', 'reviewer' => 'Reviewer'] as $id => $label) {
      $roles[$id] = $this->createMock(RoleInterface::class);
      $roles[$id]->method('label')->willReturn($label);
    }
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('execute')->willReturn(['editor' => 'editor', 'authenticated' => 'authenticated', 'reviewer' => 'reviewer']);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($roles);
    $storage->method('getQuery')->willReturn($query);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('user_role')->willReturn($storage);

    $translation = new TranslationManager(new LanguageDefault(['id' => 'en']));
    $container = new ContainerBuilder();
    $container->set('config.factory', $factory);
    $container->set('config.typed', $typed);
    $container->set('entity_type.manager', $manager);
    $container->set('string_translation', $translation);
    $container->setAlias(ConfigFactoryInterface::class, 'config.factory');
    $container->setAlias(TypedConfigManagerInterface::class, 'config.typed');
    \Drupal::setContainer($container);

    try {
      $form = ActivityTrackerSettingsForm::create($container);
      $form->setStringTranslation($translation);
      $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
      $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
      $form->setLoggerFactory($logger_factory);
      $form->setMessenger($this->createMock(MessengerInterface::class));

      $state = new FormState();
      $elements = $form->buildForm([], $state);
      foreach (array_keys($roles) as $id) {
        $actions = $elements['roles'][$id]['actions'];
        $this->assertArrayNotHasKey('#states', $actions);
        $this->assertEmpty($actions['#disabled'] ?? FALSE);
      }
      $this->assertTrue($elements['roles']['editor']['track']['#default_value']);
      $this->assertFalse($elements['roles']['reviewer']['track']['#default_value']);
      $this->assertSame(['login' => 'login'], $elements['roles']['editor']['actions']['#default_value']);

      $state->setValue('roles', [
        'editor' => ['track' => 0, 'actions' => ['login' => 'login']],
        'authenticated' => ['track' => 1, 'actions' => ['logout' => 'logout', 'login' => 0]],
        'reviewer' => ['track' => 0, 'actions' => ['login' => 'login']],
      ]);
      $form->validateForm($elements, $state);
      $this->assertSame([], $state->getErrors());
      $form->submitForm($elements, $state);
    }
    finally {
      \Drupal::unsetContainer();
      (new FormState())->clearErrors();
    }
  }

}
