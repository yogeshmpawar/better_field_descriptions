<?php

namespace Drupal\better_field_descriptions\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;

/**
 * Displays the better_field_descriptions_fields settings form.
 */
class BetterFieldDescriptionsFieldsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['better_field_descriptions.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormId() {
    return 'better_field_descriptions_fields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get better descriptions settings.
    $bfds = $this->config('better_field_descriptions.settings')->get('better_field_descriptions_settings');
    // Get existing descriptions for fields.
    $bfd = $this->config('better_field_descriptions.settings')->get('better_field_descriptions');
    // Use default template if not configured.
    if (isset($bfd['template']) == FALSE || empty($bfd['template'])) {
      $bfd['template'] = 'better-field-descriptions-text';
    }

    // Fetching template files from this module.
    $path = drupal_get_path('module', 'better_field_descriptions');
    $files = glob("{$path}/templates/*.html.twig", GLOB_BRACE);
    foreach ($files as $key => $value) {
      $templates[] = basename($value, ".html.twig");
    }
    $form['#templates'] = $templates;
    // Collects all templates found into array for select list.
    $better_descriptions_templates = array();

    foreach ($templates as $template) {
      $path = $template->uri;
      // Removing the '.html.twig' if exists.
      if (($pos = strpos($template->name, '.')) !== FALSE) {
        $template = substr($template->name, 0, $pos);
      }
      $better_descriptions_templates[$template] = $template;
    }

    // Possible positions for the better description.
    $positions = array(
      0 => t('Above title and input'),
      1 => t('Below title and input'),
      2 => t('Between title and input'),
    );

    $form['descriptions'] = array(
      '#type' => 'markup',
      '#markup' => t('Add/edit better descriptions to the fields below.'),
    );

    $form['bundles'] = array(
      '#type' => 'item',
      '#prefix' => '<div id="better-field-descriptions-form-id-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    );

    // Template selection.
    $form['bundles']['template'] = array(
      '#type' => 'select',
      '#title' => t('Select template for the descriptions'),
      '#options' => $better_descriptions_templates,
      '#default_value' => $bfd['template'],
      '#description' => t('Changing this value will trigger a theme registry rebuild. You can also provide your own template, consult the documentation.'),
    );

    // Setting label, default if not set.
    if (isset($bfd['default_label']) == FALSE) {
      $default_label = t('Description');
    }
    else {
      $default_label = $bfd['default_label'];
    }

    $form['bundles']['default_label'] = array(
      '#type' => 'textfield',
      '#title' => 'Default label for all field descriptions.',
      '#default_value' => $default_label,
      '#description' => t('This label will be used if not set in each of the fields below.'),
    );

    // Get info on bundles.
    $bundles = entity_get_bundles('node');

    foreach ($bfds as $bundle_machine_name => $fields) {

      // Array to hold fields in the node.
      $fields_instances = array();

      // Get info on pseudo fields, like title.
      $extra_fields = \Drupal::entityManager()->getExtraFields('node', $bundle_machine_name, 'form');
      if (isset($extra_fields['title'])) {
        $fields_instances['title'] = $extra_fields['title'];
      }

      // Get info on regular fields to the bundle.
      $entityManager = \Drupal::service('entity_field.manager');
      $fields_instances += $entityManager->getFieldDefinitions('node', $bundle_machine_name);

      // Wrapping each bundle in a collapsed fieldset.
      $form['bundles'][$bundle_machine_name] = array(
        '#type' => 'details',
        '#title' => $bundles[$bundle_machine_name]['label'],
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#prefix' => '<div id="better-field-descriptions-form-id-wrapper">',
        '#suffix' => '</div>',
      );

      foreach ($fields as $field_machine_name) {

        // Descriptions.
        $bfd_description = '';
        if (isset($bfd[$bundle_machine_name][$field_machine_name]['description'])) {
          $bfd_description = $bfd[$bundle_machine_name][$field_machine_name]['description'];
        }
        $form['bundles'][$bundle_machine_name][$field_machine_name]['description'] = array(
          '#type' => 'textarea',
          '#title' => $fields_instances[$field_machine_name]->getLabel() . ' (' . $field_machine_name . ')',
          '#default_value' => Xss::filter($bfd_description),
          '#description' => t('Add description for !machine_name.', array('!machine_name' => $field_machine_name)),
        );

        // Label.
        $bfd_label = '';
        if (isset($bfd[$bundle_machine_name][$field_machine_name]['label'])) {
          $bfd_label = $bfd[$bundle_machine_name][$field_machine_name]['label'];
        }
        $form['bundles'][$bundle_machine_name][$field_machine_name]['label'] = array(
          '#type' => 'textfield',
          '#title' => 'Label for this field description',
          '#default_value' => Xss::filter($bfd_label),
          '#description' => t('Label for this field description.'),
        );

        $position = 1;
        if (isset($bfd[$bundle_machine_name][$field_machine_name]['position'])) {
          $position = $bfd[$bundle_machine_name][$field_machine_name]['position'];
        }

        // Position of description.
        $form['bundles'][$bundle_machine_name][$field_machine_name]['position'] = array(
          '#type' => 'radios',
          '#title' => 'Position of description.',
          '#options' => $positions,
          '#default_value' => $position,
          '#description' => t('Position the description field above or below the input field. When choosing the between-option the #title of the field will be used as label (if label is left blank) and the original field #title will be set as invisible.'),
        );
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bfd = $this->config('better_field_descriptions.settings')->get('better_field_descriptions');

    $template_bundle = $form_state->getValue('bundles');
    $path = drupal_get_path('module', 'better_field_descriptions');
    $template = $template_bundle['template'];
    $template_uri = $path . '/templates/' . $template . '.html.twig';
    $form_state->setValue(array('bundles', 'template_uri'), $template_uri);

    // If the template is changed, do a theme registry rebuild.
    if (isset($bfd['template']) && $template != $bfd['template']) {
      drupal_theme_rebuild();
    }

    // Setting variables.
    $config = $this->config('better_field_descriptions.settings')->set('better_field_descriptions', $form_state->getValue('bundles'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
