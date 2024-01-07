<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element\FormElement;

/**
 *
 * @FormElement("leaflet_style_mapping")
 */
class StyleMapping extends FormElement {

  public function getInfo() {

    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processStyleMapping'],
      ],
    ];
  }

  public static function processStyleMapping(&$element, FormStateInterface $form_state, &$complete_form) {

    $input_exists = FALSE;
    $config = \Drupal::config('geojsonfile_field.settings');

    $field_element = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);
    if (!$input_exists) {
      return;
    }

    if (isset($field_element)) {
      $item = $field_element;
    } else {
      $item = [];
    }

      $element['Attribute'] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'style' => [
            'display: inline;'
          ]
        ]
      ];
      $element['Attribute']['attribut'] = [
        '#type' => 'textfield',
        '#title' => t('Attribut Name'),
        '#default_value' => $item['Attribute']['attribut'] ?? NULL,
        '#description' => t('Parameter '),
        '#maxlength' => 64,
        '#size' => 12,
        '#weight' => 1,
      ];
      $element['Attribute']['value'] = [
        '#type' => 'textfield',
        '#title' => t('Attribut Value'),
        '#default_value' => $item['Attribute']['value'] ?? NULL,
        '#description' => t('Parameter '),
        '#maxlength' => 64,
        '#size' => 12,
        '#weight' => 2,
      ];

      $element['Style'] = [
        '#type' => 'leaflet_style',
        '#title' => t('Style Mapping'),
        '#weight' => 2,
      ];

    return $element;
  }
}
