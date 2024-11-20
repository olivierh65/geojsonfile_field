<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\FieldStorageDefinitionInterface;



/**
 * Provides the field widget for Symbol field.
 *
 * @FieldWidget(
 *   id = "stylemapping_widget",
 *   label = @Translation("Style Mapping"),
 *   description = @Translation("Style Mapping"),
 *   field_types = {
 *     "stylemapping_field"
 *   },
 * )
 */

//  *   multiple_values = false

class StyleMappingWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Get the number of values already entered.
    $field_name = $this->fieldDefinition->getName();
    $count = $form_state->get(['field_multivalue', $field_name, 'count']);
    if (is_null($count)) {
      $count = count($items);
      $form_state->set(['field_multivalue', $field_name, 'count'], $count);
    }

    // Define a wrapper for AJAX updates.
    $element['#prefix'] = '<div id="custom-field-wrapper">';
    $element['#suffix'] = '</div>';

    // Add each item field for existing values.
    for ($i = 0; $i < $count; $i++) {
      $element[$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value') . ' ' . ($i + 1),
        '#default_value' => isset($items[$i]->value) ? $items[$i]->value : '',
      ];
    }

    // "Add more" button.
    $element['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add more'),
      '#submit' => [[get_class($this), 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => 'custom-field-wrapper',
      ],
    ];

    // "Remove" button for last item.
    if ($count > 1) {
      $element['remove_last'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove last'),
        '#submit' => [[get_class($this), 'removeLastSubmit']],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => 'custom-field-wrapper',
        ],
      ];
    }

    return $element;

    ////////////////////////////

    $element += [
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#multiple' => true,
      '#tree' => true,
      // $element['#max_delta'] = $field_state['items_count'];
      // $form['#validate'][] = [$this, "validate"];
      '#title' => 'Attribute ' . $delta,
      '#type' => 'details',
      '#open' => false,
      '#weight' => 20,
      '#multiple' => true,
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#tree' => true,
      '_nb_attribut' => [
        '#type' => 'value',
        '#description' => 'number of attributs for delta ' . $delta,
        '#value' => 0,
      ],
      'attributes' => [
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta . ':',
        '#value_callback' => [$this, 'mappingUnserialize'],
      ],
    ];

    return $element;
  }

  /**
   * Ajax callback for Dummy AJAX test.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public static function dummyAjaxCallback(array &$form, FormStateInterface $form_state) {
    return new AjaxResponse();
  }

  /////////////////////////////////////////////
  /**
   * AJAX callback for the widget.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['fields'][$form_state->getTriggeringElement()['#parents'][0]];
  }

  /**
   * Submit handler for "Add more" button.
   */
  public static function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $field_name = $form_state->getTriggeringElement()['#parents'][0];
    $count = $form_state->get(['field_multivalue', $field_name, 'count']);
    $form_state->set(['field_multivalue', $field_name, 'count'], $count + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for "Remove last" button.
   */
  public static function removeLastSubmit(array &$form, FormStateInterface $form_state) {
    $field_name = $form_state->getTriggeringElement()['#parents'][0];
    $count = $form_state->get(['field_multivalue', $field_name, 'count']);
    if ($count > 1) {
      $form_state->set(['field_multivalue', $field_name, 'count'], $count - 1);
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $element['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of the textfield'),
      '#default_value' => $this->getSetting('size'),
      '#min' => 1,
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    return $summary;
  }
}
