<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\FieldStorageDefinitionInterface;


// Voir https://chatgpt.com/share/672be872-9bd8-8003-9728-605080eee456 //

/**
 * Provides the field widget for Symbol field.
 *
 * @FieldWidget(
 *   id = "geojsonfile_widget_test",
 *   label = @Translation("geojson File widget Test"),
 *   description = @Translation("An File field with a text field for a description"),
 *   field_types = {
 *     "geojsonfile_field_test"
 *   },
 *   multiple_values = false,
 * )
 */

//  *   multiple_values = false

class GeojsonFileWidgetTest extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element += [
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#multiple' => true,
      '#tree' => true,
      // $element['#max_delta'] = $field_state['items_count'];
      // $form['#validate'][] = [$this, "validate"];

      'select_widget' => [
        '#type' => 'select',
        '#title' => $this->t('Dummy select'),
        '#options' => ['pow' => 'Pow!', 'bam' => 'Bam!'],
        '#required' => false,
        '#ajax' => [
          'callback' => static::class . '::dummyAjaxCallback',
          'effect' => 'fade',
        ],
      ],
      // Create a list of radio boxes that will toggle the  textbox
      // below if 'other' is selected.
      /* 'colour_select' => [
        '#type' => 'radios',
        '#title' => $this->t('Pick a colour'),
        '#options' => [
          'blue' => $this->t('Blue'),
          'white' => $this->t('White'),
          'black' => $this->t('Black'),
          'other' => $this->t('Other'),
        ], */
      // We cannot give id attribute to radio buttons as it will break their functionality, making them inaccessible.
      /* '#attributes' => [
        // Define a static id so we can easier select it.
        'id' => 'field_colour_select',
      ],*/
      /*  ],
      */

      // This textfield will only be shown when the option 'Other'
      // is selected from the radios above.
      'custom_colour' => [
        '#type' => 'textfield',
        '#size' => '60',
        '#placeholder' => 'Enter favourite colour',
        '#attributes' => [
          'id' => 'custom-colour',
        ],
        '#states' => [
          // Show this textfield only if the radio 'other' is selected above.
          'visible' => [
            // Don't mistake :input for the type of field or for a css selector --
            // it's a jQuery selector. 
            // You can always use :input or any other jQuery selector here, no matter 
            // whether your source is a select, radio or checkbox element.
            // in case of radio buttons we can select them by thier name instead of id.
            // ':input[name="field_zz[0][colour_select]"]' => ['value' => 'other'],
            ':input[name="' . $this->fieldDefinition->getName() . '[' . $delta . '][colour_select]"]' => ['value' => 'other'],
          ],
        ],
      ],
      'data' => [
        '#title' => 'Data ' . $delta,
        '#type' => 'details',
        '#open' => true,
        '#weight' => 5,
        'track' => [
          '#title' => 'File ' . $delta,
          '#type' => 'details',
          '#open' => true,
          '#weight' => 10,
          '#description' => 'Select a file and give a description',
          'fichier' => [
            '#type' => 'managed_file',
            '#title' => "Fichier $delta",
            '#attributes' => [
              'id' => 'data-file-' . $delta,
            ],
            '#prefix' => '<div id="data-prefix-file-' . $delta . '">',
            '#suffix' => '</div>',
            '#upload_validators' =>  [
              'file_validate_extensions' => ['geojson', 'gpx', 'pdf'],
            ],
            '#multiple' => FALSE,
            '#ajax' => [
              'callback' => [static::class, 'dummyAjaxCallback'],
              'event' => 'change click submit',
            ],
          ],
          'description' => [
            '#type' => 'textfield',
            '#title' => t('Description'),
            '#placeholder' => 'Description de la trace',
            // '#disabled' => !$file_selected ?? true,
            // '#access' => $file_selected ?? false,
            '#attributes' => [
              'id' => 'data-description-' . $delta,
            ],
            '#ajax' => [
              'callback' => static::class . '::dummyAjaxCallback',
              'effect' => 'fade',
              'event' => 'keyup',
              'wrapper' => 'mapping-fieldset-wrapper',
            ],
            // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
          ],
        ],
        'style' => [
          '#title' => 'Global style',
          '#type' => 'details',
          '#open' => false,
          '#weight' => 19,
          /* '#states' => [
            'invisible' => [
              [
                '#data-description-' . $delta  => ['value' => ''],
              ],
            ],
          ], */
          'leaflet_style' => [
            '#title' => 'Test leaflet_style',
            '#type' => 'leaflet_style',
            '#weight' => 1,
          ],
        ],
        'mapping' => [
          '#title' => 'Mapping style',
          '#type' => 'details',
          '#open' => true,
          '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
          '#suffix' => '</div>',
          /* '#states' => [
            'invisible' => [
              [
                '#data-description-' . $delta  => ['value' => ''],
              ],
            ],
          ], */
          '#weight' => 20,
        ],
      ],
    ];

    /* 
    \Drupal::service('plugin.manager.field.field_type');
    $items->getParent()->getEntity()->get('stylemapping_field');
    $item_mapping = $entity->get($name);
        $item_mapping->filterEmptyItems();
    $elements = $this->formMultipleElements($item_mapping, $form, $form_state); */

    $e = [
      'attributes' => [
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta . ':',
        '#value_callback' => [$this, 'mappingUnserialize'],
        '#cardinality' => 2,
        '#multiple' => true,
        '#tree' => true,
      ],
    ];

    /* $e = ['attributes' => [
      '#title' => 'Style Mapping',
      '#type' => 'multivalue',
      '#cardinality' => 3,
      '#min_items' => 1,
      '#item_label' => $this->t('item test'),
      '#no_items_message' => $this->t('No items entered. Please add items below.'),
      '#empty_items' => 1,
      '#add_more' => TRUE,
      '#add_more_items' => 1,
      '#add_more_button_label' => $this->t('Add test'),
      '#add_more_input' => TRUE,
      '#add_more_input_label' => $this->t('more items test'),
      'mapping' => [
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta . ':',
        '#value_callback' => [$this, 'mappingUnserialize'],
      ],
    ],
  ]; */

    /* $e = ['attributes' => [
    '#title' => 'Style Mapping',
    '#type' => 'element_multiple',
    '#element' => [
      '#title' => 'Style Mapping',
      '#type' => 'leaflet_style_mapping',
      '#description' => 'Mapping ' . $delta . ':',
      '#value_callback' => [$this, 'mappingUnserialize'],
    ],
  ], 
]; */

    $element["data"]["mapping"][0] = $e;

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
}
