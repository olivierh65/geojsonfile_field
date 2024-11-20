<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\file\Entity\File;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Element\FormElement;

use Drupal\Core\Security\TrustedCallbackInterface;

// https://chatgpt.com/share/672d36f3-e4c8-8003-8a4b-80ada5a13640 //
/**
 *
 * @FormElement("leaflet_style_mapping")
 */
class StyleMapping extends FormElementBase implements TrustedCallbackInterface {

  const CARDINALITY_UNLIMITED = -1;

  /**
   * Returns the properties for the custom form element.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The render array for the form element.
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#cardinality' => -1, // Allow unlimited values.
      '#process' => [
        [self::class, 'processCustomMultiFieldElement'],
        // [$this, 'processStyleMapping'],
        // [$this, 'processMultiValue'],
        // [$this, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$this, 'prerenderStyleMapping'],
      ],
      '#element_validate' => [
        [self::class, 'validateCustomMultiFieldElement'],
      ],
      '#theme_wrappers' => ['fieldset'],
      '#theme' => 'custom_multi_field_element',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['prerenderStyleMapping'];
  }

  public static function prerenderStyleMapping($element) {
    return $element;
  }

  public function processStyleMapping(&$element, FormStateInterface $form_state, &$complete_form) {

    $input_exists = FALSE;
    $config = \Drupal::config('geojsonfile_field.settings');

    $delta_attrib = array_slice($element['#parents'], 4, 1)[0] ?? 0;
    $delta_geojson = array_slice($element['#parents'], 1, 1)[0] ?? 0;

    // retreive properties defined in geojson file
    $geo_properties = NestedArray::getValue(
      $form_state->getValues(),
      array_merge(array_slice($element['#parents'], 0, 2), ['geo_properties'])
    );

    $geojson = NestedArray::getValue($form_state->getValues(), array_slice($element['#parents'], 0, 2));
    if (isset($geojson['track']['fichier']['fids']) && (isset($geojson['track']['fichier']['fids'][0])) && (!isset($geo_properties))) {
      $props = [];
      $file = File::Load($geojson['track']['fichier']['fids'][0]);
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

    if ($geo_properties == null) {
      // TEST
      $a = 1;
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
      '#default_value' => (isset($item['Attribute']['value']) && ($item['Attribute']['value'] != '' && (isset($geo_properties))) &&
        in_array($item['Attribute']['value'], ($geo_properties)[$element['Attribute']['attribut']['#default_value']]))
        ? $item['Attribute']['value'] :  null,
      '#value' => null,
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

  /**
   * Processes a multi-value form element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public function __processMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element_name = end($element['#array_parents']);
    $parents = $element['#parents'];
    $cardinality = $element['#cardinality'] ?? 1;

    $element['#tree'] = TRUE;
    $element['#field_name'] = $element_name;

    $element_state = static::getElementState($parents, $element_name, $form_state);
    if ($element_state === NULL) {
      $element_state = [
        // The initial count is always based on the default value. The default
        // value should always have numeric keys.
        'items_count' => count($element['#default_value'] ?? []),
      ];
      static::setElementState($parents, $element_name, $form_state, $element_state);
    }

    // Determine the number of elements to display.
    $max = $cardinality === self::CARDINALITY_UNLIMITED ? $element_state['items_count'] : ($cardinality - 1);

    // Extract the elements that will have to be repeated for each delta.
    $children = [];
    foreach (Element::children($element) as $child) {
      $children[$child] = $element[$child];
      unset($element[$child]);
    }

    $value = is_array($element['#value']) ? $element['#value'] : [];
    // Re-key the elements so that deltas are consecutive.
    $value = array_values($value);

    for ($i = 0; $i <= $max; $i++) {
      $element[$i] = $children;
      // $element[$i] = $this->processStyleMapping($element, $form_state, $complete_form);

      if (isset($value[$i])) {
        static::setDefaultValue($element[$i], $value[$i]);
      }

      // static::setRequiredProperty($element[$i], $i, $element['#required']);

      $element[$i]['_weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for row @number', ['@number' => $i + 1]),
        '#title_display' => 'invisible',
        '#delta' => $max,
        '#default_value' => $i,
        '#weight' => 100,
      ];
    }

    if ($cardinality === self::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
      $id_prefix = implode('-', $parents);
      $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
      $element['#prefix'] = '<div id="' . $wrapper_id . '">';
      $element['#suffix'] = '</div>';
      $element['add_more'] = [
        '#type' => 'submit',
        '#name' => strtr($id_prefix, '-', '_') . '_add_more',
        '#value' => $element['#add_more_label'],
        '#attributes' => ['class' => ['multivalue-add-more-submit']],
        '#limit_validation_errors' => [$element['#array_parents']],
        '#submit' => [[static::class, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'addMoreAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
    }

    return $element;
  }

  /**
   * Validates a multi-value form element.
   *
   * Used to clean and sort the submitted values in the form state.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateMultiValue(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input_exists = FALSE;
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);

    if (!$input_exists) {
      return;
    }

    // Remove the 'value' of the 'add more' button.
    unset($values['add_more']);

    // Sort the values based on the weight.
    usort($values, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });

    foreach ($values as $delta => &$delta_values) {
      // Remove the weight element value from the submitted data.
      unset($delta_values['_weight']);

      // Determine if all the elements of this delta are empty.
      $is_empty_delta = array_reduce($delta_values, function (bool $carry, $value): bool {
        if (is_array($value)) {
          return $carry && empty(array_filter($value));
        } else {
          return $carry && ($value === NULL || $value === '');
        }
      }, TRUE);

      // If all the elements are empty, drop this delta.
      if ($is_empty_delta) {
        unset($values[$delta]);
      }
    }

    // Re-key the elements so that deltas are consecutive.
    $values = array_values($values);

    // Set the value back to the form state.
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      return $input;
    }

    $value = [];
    $element += ['#default_value' => []];

    $children_keys = Element::children($element, FALSE);
    $first_child = reset($children_keys);
    $children_count = count($children_keys);

    foreach ($element['#default_value'] as $delta => $default_value) {
      // Enforce numeric deltas.
      if (!is_numeric($delta)) {
        continue;
      }

      // Allow to omit the child element name when one single child exists and
      // the values are simple literals. This allows to pass
      // [0 => 'value 1', 1 => 'value 2'] instead of
      // [0 => ['element_name' => 'value 1', 1 => ['element_name' => ...]].
      if ($children_count === 1 && !is_array($default_value)) {
        $value[$delta] = [$first_child => $default_value];
      } else {
        $value[$delta] = $default_value;
      }
    }

    return $value;
  }

  /**
   * Handles the "Add another item" button AJAX request.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreSubmit()
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $element_name = $element['#field_name'];
    $parents = $element['#parents'];

    // Increment the items count.
    $element_state = static::getElementState($parents, $element_name, $form_state);
    $element_state['items_count']++;
    static::setElementState($parents, $element_name, $form_state, $element_state);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|null
   *   The element.
   *
   * @see \Drupal\Core\Field\WidgetBase::addMoreAjax()
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): ?array {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return NULL;
    }

    return $element;
  }

  /**
   * Sets the default value for the child elements.
   *
   * @param array $elements
   *   The elements array.
   * @param array $value
   *   An array of values, keyed by the children element name.
   */
  public static function setDefaultValue(array &$elements, array $value): void {
    // @todo Handle nested elements.
    foreach (Element::children($elements, FALSE) as $child) {
      if (isset($value[$child])) {
        $elements[$child]['#default_value'] = $value[$child];
      }
    }
  }

  /**
   * Sets the required property for the delta being processed.
   *
   * @param array $elements
   *   The array containing the child elements.
   * @param int $delta
   *   The delta currently being processed.
   * @param bool $required
   *   If the main element is required or not.
   */
  protected static function setRequiredProperty(array &$elements, int $delta, bool $required): void {
    if ($delta === 0 && $required) {
      // If any of the children is set as required, the first delta is already
      // set correctly.
      foreach ($elements as $element) {
        if (isset($element['#required']) && $element['#required'] === TRUE) {
          return;
        }
      }

      // Set all children as required otherwise.
      foreach ($elements as &$element) {
        $element['#required'] = TRUE;
      }

      return;
    }

    // For every other delta or when the main element is marked as not required,
    // none of the children should be required neither.
    foreach ($elements as &$element) {
      $element['#required'] = FALSE;
    }
  }

  /**
   * Retrieves processing information about the element from $form_state.
   *
   * This method is static so that it can be used in static Form API callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The field name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array with the following key/value pairs:
   *   - items_count: The number of sub-elements to display for the element.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetState()
   */
  public static function getElementState(array $parents, string $element_name, FormStateInterface $form_state): ?array {
    return NestedArray::getValue($form_state->getStorage(), static::getElementStateParents($parents, $element_name));
  }

  /**
   * Stores processing information about the element in $form_state.
   *
   * This method is static so that it can be used in static Form API #callbacks.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_state
   *   The array of data to store. See getElementState() for the structure and
   *   content of the array.
   *
   * @see \Drupal\Core\Field\WidgetBase::setWidgetState()
   */
  public static function setElementState(array $parents, string $element_name, FormStateInterface $form_state, array $field_state): void {
    NestedArray::setValue($form_state->getStorage(), static::getElementStateParents($parents, $element_name), $field_state);
  }

  /**
   * Returns the location of processing information within $form_state.
   *
   * @param array $parents
   *   The array of #parents where the element lives in the form.
   * @param string $element_name
   *   The element name.
   *
   * @return array
   *   The location of processing information within $form_state.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetStateParents()
   */
  protected static function getElementStateParents(array $parents, string $element_name): array {
    // phpcs:disable
    // Element processing data is placed at
    // $form_state->get(['multivalue_form_element_storage', '#parents', ...$parents..., '#elements', $element_name]),
    // to avoid clashes between field names and $parents parts.
    // phpcs:enable
    return array_merge(
      ['multivalue_form_element_storage', '#parents'],
      $parents,
      ['#elements', $element_name],
    );
  }

  //////////////////////////
    /**
   * Process callback for the custom multi-field element.
   */
  public static function processCustomMultiFieldElement(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $cardinality = isset($element['#cardinality']) ? $element['#cardinality'] : 1;

    // Set the multiple cardinality wrapper.
    $element['#tree'] = TRUE;
    $values = $form_state->getValue($element['#parents'], []);

    // Loop through the items based on cardinality.
    for ($delta = 0; $delta < ($cardinality == -1 ? count($values) + 1 : $cardinality); $delta++) {
      $element[$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['custom-multi-field']],
      ];

      // Add text fields.
      $element[$delta]['textfield_1'] = [
        '#type' => 'textfield',
        '#title' => t('Textfield 1'),
      ];
      $element[$delta]['textfield_2'] = [
        '#type' => 'textfield',
        '#title' => t('Textfield 2'),
      ];
      $element[$delta]['textfield_3'] = [
        '#type' => 'textfield',
        '#title' => t('Textfield 3'),
      ];

      // Add checkboxes.
      $element[$delta]['checkbox_1'] = [
        '#type' => 'checkbox',
        '#title' => t('Checkbox 1'),
      ];
      $element[$delta]['checkbox_2'] = [
        '#type' => 'checkbox',
        '#title' => t('Checkbox 2'),
      ];

      // Add "Remove" button for extra items if cardinality is unlimited.
      if ($cardinality == -1 && $delta > 0) {
        $element[$delta]['remove'] = [
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#submit' => [[self::class, 'removeFieldSubmit']],
          '#ajax' => [
            'callback' => [self::class, 'ajaxRemoveField'],
            'wrapper' => $element['#id'],
          ],
          '#name' => implode('_', array_merge($element['#parents'], ['remove', $delta])),
        ];
      }
    }

    // "Add More" button.
    if ($cardinality == -1) {
      $element['add_more'] = [
        '#type' => 'submit',
        '#value' => t('Add another'),
        '#submit' => [[self::class, 'addFieldSubmit']],
        '#ajax' => [
          'callback' => [self::class, 'ajaxAddField'],
          'wrapper' => $element['#id'],
        ],
      ];
    }

    return $element;
  }

  /**
   * Submit handler for adding a field.
   */
  public static function addFieldSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $values = $form_state->getValue($parents);
    $values[] = [];
    $form_state->setValue($parents, $values);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a field.
   */
  public static function removeFieldSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $index = end($parents);
    array_pop($parents);
    $values = $form_state->getValue($parents);
    unset($values[$index]);
    $form_state->setValue($parents, $values);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for adding a field.
   */
  public static function ajaxAddField(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * AJAX callback for removing a field.
   */
  public static function ajaxRemoveField(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Validation callback for the custom multi-field element.
   */
  public static function validateCustomMultiFieldElement(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Add validation if needed.
  }
}
