<?php

namespace Drupal\plugin\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Provides a plugin selector field widget.
 *
 * @FieldWidget(
 *   id = "plugin_selector",
 *   label = @Translation("Plugin selector"),
 *   deriver = "\Drupal\plugin\Plugin\Field\FieldWidget\PluginSelectorDeriver"
 * )
 *
 * @see plugin_field_widget_info_alter()
 */
class PluginSelector extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$parent_form, FormStateInterface $complete_form_state) {
    /** @var \Drupal\plugin\Plugin\Field\FieldType\PluginCollectionItemInterface $item */
    $item = $items[$delta];
    /** @var \Drupal\plugin\PluginType\PluginTypeInterface $plugin_type */
    $plugin_type = $item->getPluginType();

    $element = [
      '#delta' => $delta,
      '#field_definition' => $this->fieldDefinition,
      '#element_validate' => [[get_class(), 'validateFormElement']],
      '#plugin_type_id' => $plugin_type->getId(),
      '#plugin_selector_id' => $this->pluginDefinition['plugin_selector_id'],
      '#process' => [[get_class(), 'processFormElement']],
      '#selected_plugin' => $items->isEmpty() ? NULL : $items->get($delta)->getContainedPluginInstance(),
    ];

    return $element;
  }

  /**
   * Implements a #process callback.
   */
  public static function processFormElement(array &$element, FormStateInterface $complete_form_state, array &$complete_form) {
    // Store #array_parents in the form state, so we can get the elements from
    // the complete form array by using only the form state.
    $element['array_parents'] = [
      '#type' => 'value',
      '#value' => $element['#array_parents'],
    ];

    $plugin_selector = static::getPluginSelector($complete_form_state, $element);
    $plugin_selector_form = [];
    $plugin_selector_form_state = SubformState::createForSubform($plugin_selector_form, $complete_form_state->getCompleteForm(), $complete_form_state);
    $element['plugin_selector'] = $plugin_selector->buildSelectorForm($plugin_selector_form, $plugin_selector_form_state);

    return $element;
  }

  /**
   * Implements an #element_validate callback.
   */
  public static function validateFormElement(array &$element, FormStateInterface $complete_form_state, array &$complete_form) {
    $plugin_selector = static::getPluginSelector($complete_form_state, $element);
    $plugin_selector_form = $element['plugin_selector'];
    $plugin_selector_form_state = SubformState::createForSubform($plugin_selector_form, $complete_form, $complete_form_state);
    $plugin_selector->validateSelectorForm($plugin_selector_form, $plugin_selector_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $parent_form, FormStateInterface $complete_form_state) {
    $massaged_values = [];

    foreach ($values as $delta => $item_values) {
      $element = NestedArray::getValue($parent_form, array_slice($item_values['array_parents'], count($parent_form['#array_parents'])));
      $plugin_selector = static::getPluginSelector($complete_form_state, $element);
      $plugin_selector_form = $element['plugin_selector'];
      $plugin_selector_form_state = SubformState::createForSubform($plugin_selector_form, $complete_form_state->getCompleteForm(), $complete_form_state);
      $plugin_selector->submitSelectorForm($plugin_selector_form, $plugin_selector_form_state);
      $massaged_values[$delta] = [
        'plugin_instance' => $plugin_selector->getSelectedPlugin(),
      ];
    }

    return $massaged_values;
  }

  /**
   * Gets the plugin selector for a field item's elements.
   *
   * @param \Drupal\Core\Form\FormStateInterface $complete_form_state
   * @param mixed[] $element
   *   The field widget's form elements.
   *
   * @return \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface
   */
  protected static function getPluginSelector(FormStateInterface $complete_form_state, array $element) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $element['#field_definition'];
    $form_state_key = sprintf('plugin_selector:%s:%d', Crypt::hashBase64(json_encode($element['#parents'])), $element['#delta']);
    if ($complete_form_state->has($form_state_key)) {
      $plugin_selector = $complete_form_state->get($form_state_key);
    }
    else {
      /** @var \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager */
      $plugin_type_manager = \Drupal::service('plugin.plugin_type_manager');

      /** @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface $plugin_selector_manager */
      $plugin_selector_manager = \Drupal::service('plugin.manager.plugin.plugin_selector');

      $plugin_type = $plugin_type_manager->getPluginType($element['#plugin_type_id']);

      $plugin_selector = $plugin_selector_manager->createInstance($element['#plugin_selector_id']);
      $plugin_selector->setLabel($field_definition->getLabel());
      $plugin_selector->setDescription($field_definition->getDescription());
      $plugin_selector->setRequired($field_definition->isRequired());
      $plugin_selector->setSelectablePluginType($plugin_type);
      $plugin_selector->setKeepPreviouslySelectedPlugins();
      if ($element['#selected_plugin']) {
        $plugin_selector->setSelectedPlugin($element['#selected_plugin']);
      }
      $complete_form_state->set($form_state_key, $plugin_selector);
    }

    return $plugin_selector;
  }

}
