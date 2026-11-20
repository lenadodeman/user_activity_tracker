<?php

namespace Drupal\user_activity_tracker\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user_activity_tracker\TrackedAction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Activity Tracker settings.
 */
class UserActivityTrackerSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['activity_tracker.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'activity_tracker_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('activity_tracker.settings');
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $saved = $config->get('track_role_actions') ?? [];

    $form['roles_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Roles to track'),
    ];

    $form['roles'] = [
      '#tree' => TRUE,
    ];
    foreach ($roles as $rid => $role) {
      $form['roles'][$rid] = [
        '#type' => 'details',
        '#title' => $role->label(),
        '#group' => 'roles_tabs',
      ];
      $form['roles'][$rid]['track'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Track this role'),
        '#default_value' => isset($saved[$rid]),
      ];
      $form['roles'][$rid]['actions'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Actions to track'),
        '#description' => $this->t('These actions are tracked only when "Track this role" is enabled. Selections for disabled roles are not saved.'),
        '#options' => TrackedAction::options(),
        '#default_value' => $saved[$rid] ?? [],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rids = $this->entityTypeManager->getStorage('user_role')->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $tracked = [];

    $roles_values = $form_state->getValue('roles') ?? [];
    foreach ($rids as $rid) {
      if (!empty($roles_values[$rid]['track'])) {
        $actions = array_filter($roles_values[$rid]['actions']);
        $tracked[$rid] = $actions;
      }
    }
    $this->config('activity_tracker.settings')
      ->set('track_role_actions', $tracked)
      ->save();

    // Log the tracked roles and actions.
    $simplified_tracked = [];
    foreach ($tracked as $role => $actions) {
      $simplified_tracked[$role] = array_values($actions);
    }
    $this->logger('user_activity_tracker')->notice('[TRACKER][CONFIG] Updated tracked roles and actions: @tracked', [
      '@tracked' => json_encode($simplified_tracked),
    ]);

    parent::submitForm($form, $form_state);
  }

}
