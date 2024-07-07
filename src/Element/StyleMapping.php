<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element\FormElement;
use Drupal\file\Entity\File;

/**
 *
 * @FormElement("leaflet_style_mapping")
 */
class StyleMapping extends FormElement {

  private $geo_properties = null;

  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [$this, 'processStyleMapping'],
      ],
      '#element_validate' => [
        [$this, 'validateMapping'],
      ],
    ];
  }

  public  function processStyleMapping(&$element, FormStateInterface $form_state, &$complete_form) {

    $input_exists = FALSE;
    $config = \Drupal::config('geojsonfile_field.settings');

    $delta_attrib = array_slice($element['#parents'], 4, 1)[0];
    $delta_geojson = array_slice($element['#parents'], 1, 1)[0];

    // retreive properties defined in geojson file
    $geo_properties = NestedArray::getValue(
      $form_state->getValues(),
      array_merge(array_slice($element['#parents'], 0, 2), ['geo_properties'])
    );
    $geojson = NestedArray::getValue($form_state->getValues(), array_slice($element['#parents'], 0, 2));
    if (isset($geojson['fids']) && (count($geojson['fids']) == 1) && (!isset($geo_properties))) {
      $props = [];
      $file = File::Load($geojson['fids'][0]);
      $cont = file_get_contents($file->getFileUri());
      $features = json_decode($cont, true);
      if (!isset($features['features'])) {
        // if just one feature, add it into array
        $features['features'] = [$features];
      }
      foreach ($features['features'] as $feature) {
        if ($feature['type'] == "Feature") {
          foreach ($feature['properties'] as $key => $val) {
            $props[$key][] = $val;
          }
        }
      }

      foreach ($props as $key => $value) {
        // remove duplicate entries and also item contening only null
        $ar = array_unique($props[$key]);
        if ((count($ar) == 0) || (count($ar) == 1 && $ar[0] == null)) {
          continue;
        }
        $props_uniq[$key] = $ar;
      }
      $geo_properties = $props_uniq;
      NestedArray::setValue(
        $form_state->getValues(),
        array_merge(array_slice($element['#parents'], 0, 2), ['geo_properties']),
        $props_uniq
      );
    }

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
      '#type' => 'select',
      '#title' => t('Attribut Name'),
      '#default_value' => (isset($item['Attribute']['attribut']) && ($item['Attribute']['attribut'] != ''))
        ? $item['Attribute']['attribut'] : ($geo_properties != null ? array_keys($geo_properties)[0] : ''),
      '#description' => t('Attribute name '),
      '#options' => $geo_properties != null ?
        array_combine(array_keys($geo_properties), array_keys($geo_properties)) :
        [],
      '#maxlength' => 64,
      '#size' => 1,
      '#weight' => 1,
      '#ajax' => [
        'callback' => [$this, 'updatePropValues'],
        'disable-refocus' => FALSE, // Or TRUE to prevent re-focusing on the triggering element.
        'event' => 'change',
        'wrapper' => 'properties_value_' . $delta_geojson . '_' . $delta_attrib, // This element is updated with this AJAX callback.
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ]
    ];
    $element['Attribute']['value'] = [
      '#type' => 'select',
      '#title' => t('Attribut Value'),
      '#default_value' => (isset($item['Attribute']['value']) && ($item['Attribute']['value'] != '') &&
        in_array($item['Attribute']['value'], ($geo_properties)[$element['Attribute']['attribut']['#default_value']]))
        ? $item['Attribute']['value'] :  null,
      '#description' => t('Attibute value '),
      '#maxlength' => 64,
      '#size' => 1,
      '#weight' => 2,
      '#prefix' => '<div id="properties_value_' . $delta_geojson . '_' . $delta_attrib . '">',
      '#suffix' => '</div>',
      '#options' => $geo_properties != null ?
        array_combine(($geo_properties)[$element['Attribute']['attribut']['#default_value']],
          ($geo_properties)[$element['Attribute']['attribut']['#default_value']]
        ) :
        [],
    ];

    $element['Attribute']['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#default_value' => $item['Attribute']['label'] ?? NULL,
      '#description' => t('Text displayed for this attribute '),
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

  // Update property values
  public function updatePropValues(array &$form, FormStateInterface $form_state) {
    // Return the prepared textfield.
    $trigElement = $form_state->getTriggeringElement();

    $geo_properties = NestedArray::getValue(
      $form_state->getValues(),
      array_merge(array_slice($trigElement['#parents'], 0, 2), ['geo_properties'])
    );
    $elem_path = array_slice($trigElement['#array_parents'], 0, -1);
    $elem_path[] = 'value';
    $element = NestedArray::getValue($form, $elem_path);

    if (isset($trigElement['#value'])) {
      $element['#options'] = array_combine($geo_properties[$trigElement['#value']], $geo_properties[$trigElement['#value']]);
      $element['#default_value'] = (($trigElement['#value'] != '') &&
        array_key_exists($element['#value'], ($geo_properties)[$trigElement['#default_value']]))
        ? $element['#value'] :  null;
    } else {
      $element['#options'] = [];
      $element['#default_value'] = null;
    }

    // clear errors
    $form_state->clearErrors();
    return $element;
  }

  public function validateMapping(&$element, FormStateInterface $form_state, &$complete_form) {
    $a = $element;
    $trigElement = $form_state->getTriggeringElement();

    if ($trigElement["#type"] == "submit") {
      return;
    } else {
      // clear error when select have been updates
      $form_state->clearErrors();
    }
  }
}
