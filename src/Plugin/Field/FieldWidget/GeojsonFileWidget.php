<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\NestedArray;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\file\Entity\File;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\file\Element\ManagedFile;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Component\Utility\Bytes;




/**
 * Provides the field widget for Symbol field.
 *
 * @FieldWidget(
 *   id = "geojsonfile_widget",
 *   label = @Translation("geojson File widget"),
 *   description = @Translation("An File field with a text field for a description"),
 *   field_types = {
 *     "geojsonfile_field"
 *   },
 * )
 */

//  *   multiple_values = false

class GeojsonFileWidget extends WidgetBase implements WidgetInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    // recupere es settings definies dans le Field 
    $default_settings = \Drupal::service('plugin.manager.field.field_type')->getDefaultFieldSettings('geojsonfile_field');

    $set_file = [
      'progress_indicator' => 'throbber',
    ] + parent::defaultSettings();

    return $default_settings;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $settings = $this->getSettings();

    $file_selected = false;

    $element += [
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#multiple' => true,
      '#tree' => true,
      // $element['#max_delta'] = $field_state['items_count'];
      // $form['#validate'][] = [$this, "validate"];

      // Create a list of radio boxes that will toggle the  textbox
      // below if 'other' is selected.
      'colour_select' => [
        '#type' => 'radios',
        '#title' => $this->t('Pick a colour'),
        '#options' => [
          'blue' => $this->t('Blue'),
          'white' => $this->t('White'),
          'black' => $this->t('Black'),
          'other' => $this->t('Other'),
        ],
        // We cannot give id attribute to radio buttons as it will break their functionality, making them inaccessible.
        /* '#attributes' => [
        // Define a static id so we can easier select it.
        'id' => 'field_colour_select',
      ],*/
      ],

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
            ':input[name="colour_select"]' => ['value' => 'other'],
          ],
        ],
      ],
      'track' => [
        '#title' => 'Data ' . $delta,
        '#type' => 'details',
        '#open' => false,
        '#weight' => 10,
        'fichier' => [
          '#type' => 'managed_file',
          '#title' => "Fichier $delta",
          '#upload_validators' =>  [
            'file_validate_extensions' => [$settings['track']['file_extensions']],
            'file_validate_size' => $settings['track']['max_filesize'] === null ?
              [Environment::getUploadMaxSize()] :
              [Bytes::toNumber($settings['track']['max_filesize'])],
          ],
          '#multiple' => FALSE,
          '#ajax' => [
            'callback' => [$this, 'updateMandatoryValidationCallback'],
            'event' => 'change click submit',
          ],
        ],
        'description' => [
          '#type' => 'textfield',
          '#title' => t('Description'),
          '#default_value' => $items[$delta]->fichier ?? null,
          '#placeholder' => 'Description de la trace',
          // '#disabled' => !$file_selected ?? true,
          // '#access' => $file_selected ?? false,
          '#attributes' => [
            'id' => 'data_description_' . $delta,
          ],
          '#ajax' => [
            'callback' => '::updateMandatoryValidationCallback',
            'event' => 'change',
            'wrapper' => 'mapping-fieldset-wrapper',
          ],
          // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
        ],
      ],
      'style' => [
        '#title' => 'Global style',
        '#type' => 'details',
        '#open' => false,
        // hide until a file is selected
        // '#access' => true, // $file_selected ?? false,
        '#weight' => 19,
        'leaflet_style' => [
          '#title' => 'Test leaflet_style',
          '#type' => 'leaflet_style',
          '#weight' => 1,
          // '#value_callback' => [$this, 'styleUnserialize'],
          // '#disabled' => !$file_selected ?? true,
        ],
      ],
      'mapping' => [
        '#title' => 'Mapping style',
        '#type' => 'details',
        '#open' => ((null !== $form_state->getValue("last_added")) && $delta == $form_state->getValue("last_added") ||
          ((null === $form_state->getValue("last_added")) && $file_selected == true)) ? true : false,
        '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
        '#suffix' => '</div>',
        // hide until a file is selected
        // '#access' => $file_selected ?? false,
        // '#disabled' => !$file_selected ?? true,
        '#states' => [
          'visible' => [
            [
              '[name="field_leaflet_edit_geojsonfile_' . $delta . '_track_fichier_remove_button"]' => ['valid' => true],
              'or',
              '[id="data_description_' . $delta . '"]' => ['value' => 'matin'],
            ],
          ],
        ],
        '#weight' => 20,
        'attribut' => [
          '#title' => 'Attribute ' . $delta + 1,
          '#type' => 'details',
          '#open' => false,
          '#weight' => 20,
          '_nb_attribut' => [
            '#type' => 'value',
            '#description' => 'number of attributs for delta ' . $delta + 1,
            '#value' => 0,
          ],
          'attributes' => [
            '#title' => 'Style Mapping',
            '#type' => 'leaflet_style_mapping',
            '#cardinality' => 3,
            '#multiple' => true,
            '#description' => 'Mapping ' . $delta . ':',
            '#value_callback' => [$this, 'mappingUnserialize'],
            '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
            '#tree' => true,
          ],
        ],
      ],
    ];

    // save number of attributs mapping
    $form_state->setValue(['mapping', 'attribut', '_nb_attribut'], $element['mapping']['attribut']['_nb_attribut']['#value']);

    // Return the updated widget
    return ['value' => $element];
  }

  public function updateMandatoryValidationCallback(array &$form, FormStateInterface $form_state) {

    $form_state->setRebuild(true);
  }


  public  function mappingUnserialize($element, $input, $form_state) {
    if ($input) {
      return $input;
    }

    $data = array_slice($element['#parents'], 0, 2);

    $data[] = 'mappings';
    if ($form_state->getValue($data) && is_string($form_state->getValue($data))) {
      $a = unserialize($form_state->getValue($data));
      return $a['attribut'][array_slice($element['#parents'], 4, 1)[0]]['leaflet_style_mapping'];
    } else {
      return [];
    }
  }

  public static function styleUnserialize($element, $input, $form_state) {
    if ($input) {
      return $input;
    }

    $a = $element;
    $data = array_slice($element['#parents'], 0, 2);

    $data[] = 'styles';
    $a = unserialize($form_state->getValue($data) ?? '');
    if ($a) {
      return $a['leaflet_style'];
    } else {
      return [];
    }
  }



  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    // recupere es settings definies dans le Field 
    $default_settings = \Drupal::service('plugin.manager.field.field_type')->getDefaultFieldSettings('geojsonfile_field');

    $element = [
      '#tree' => true,
    ];

    $element['track'] = [
      '#title' => 'Track file',
      '#type' => 'details',
      '#open' => true,
      '#weight' => 10,
      '#tree' => true,
    ];

    $element['track']['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['track']['file_directory'] ?? $default_settings['track']['file_directory'] ?? 'tracks',
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[FileItem::class, 'validateDirectory']],
      '#weight' => 3,
    ];

    // Make the extension list a little more human-friendly by comma-separation.
    $extensions = str_replace(' ', ', ', ($settings['track']['file_extensions'] ?? $default_settings['track']['file_extensions'] ?? '[geojson]'));
    $element['track']['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $extensions,
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
      '#element_validate' => [[FileItem::class, 'validateExtensions']],
      '#weight' => 1,
      '#maxlength' => 256,
      // By making this field required, we prevent a potential security issue
      // that would allow files of any type to be uploaded.
      '#required' => TRUE,
    ];

    $element['track']['max_filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $settings['track']['max_filesize'] ?? $default_settings['track']['max_filesize'] ?? '5MB',
      '#description' => $this->t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes could be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', [
        '%limit' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#size' => 10,
      '#element_validate' => [[FileItem::class, 'validateMaxFilesize']],
      '#weight' => 5,
    ];

    $element['style'] = [
      '#title' => 'Leaflet style',
      '#type' => 'details',
      '#open' => true,
      '#weight' => 20,
      '#tree' => true,
    ];

    $element['style']['stroke'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Stroke</em> field'),
      '#default_value' => $settings['style']['stroke'] ?? $default_settings['style']['stroke'] ?? TRUE,
      '#description' => t('Whether to draw stroke along the path.<br>Set it to false to disable borders on polygons or circles.'),
      '#weight' => 1,
    ];
    $element['style']['color'] = [
      '#type' => 'color',
      '#title' => t('<em>Color</em> field'),
      '#default_value' => $settings['style']['color'] ?? $default_settings['style']['color'] ?? '#F00FE8',
      '#description' => t('Stroke color.'),
      '#weight' => 2,
    ];
    $element['style']['weight'] = [
      '#type' => 'number',
      '#title' => t('<em>Weight</em> field'),
      '#default_value' => $settings['style']['weight'] ?? $default_settings['style']['weight'] ?? 6,
      '#description' => t('Stroke width in pixels.'),
      '#min' => 1,
      '#step' => 1,
      '#max' => 20,
      '#weight' => 3,
    ];
    $element['style']['opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Opacity</em> field'),
      '#default_value' => $settings['style']['opacity'] ?? $dafault_settings['style']['opacity'] ?? 1,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 4,
    ];
    $element['style']['linecap'] = [
      '#type' => 'select',
      '#title' => t('<em>LineCap</em> field'),
      '#default_value' => $settings['style']['linecap'] ?? $default_settings['style']['linecap'] ?? 'round',
      '#description' => t('A string that defines shape to be used at the end of the stroke.' .
        '<br>Butt : indicates that the stroke for each subpath does not extend beyond its two endpoints.' .
        '<br>Round : indicates that at the end of each subpath the stroke will be extended by a half circle with a diameter equal to the stroke width.' .
        '<br>Square : indicates that at the end of each subpath the stroke will be extended by a rectangle with a width equal to half the width of the stroke and a height equal to the width of the stroke.'),
      '#options' => [
        'butt' => 'Butt',
        'round' => 'Round',
        'square' => 'Square',
      ],
      '#weight' => 5,
    ];
    $element['style']['linejoin'] = [
      '#type' => 'select',
      '#title' => t('<em>LineJoin</em> field'),
      '#default_value' => $settings['style']['linejoin'] ?? $default_settings['style']['linejoin'] ?? 'round',
      '#description' => t('A string that defines shape to be used at the corners of the stroke.' .
        '<br>Arcs : indicates that an arcs corner is to be used to join path segments.' .
        '<br>Bevel : indicates that a bevelled corner is to be used to join path segments.' .
        '<br>Miter : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Miter-Clip : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Round : indicates that a round corner is to be used to join path segments.'),
      '#options' => [
        'arcs' => 'Arcs',
        'bevel' => 'Bevel',
        'miter' => 'Miter',
        'miter-clip' => 'Miter-Clip',
        'round' => 'Round',
      ],
      '#weight' => 6,
    ];
    $element['style']['dasharray'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashArray</em> field'),
      '#default_value' => $settings['style']['dasharray'] ?? $default_settings['style']['dasharray'] ?? NULL,
      '#description' => t('A string that defines the stroke <a href="https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-linejoin>dash pattern</a>. Doesn\'t work on Canvas-powered layers in some old browsers.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)(,[0-9]+)*',
      '#weight' => 7,
    ];
    $element['style']['dashoffset'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashOffset</em> field'),
      '#default_value' => $settings['style']['dashoffset'] ?? $default_settings['style']['dashoffset'] ?? 0,
      '#description' => t('A string that defines the <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/stroke-dashoffset">distance into the dash</a> pattern to start the dash.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)|([0-9]+%)',
      '#weight' => 8,
    ];
    $element['style']['fill'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Fill</em> field'),
      '#default_value' => $settings['style']['fill'] ?? $default_settings['style']['fill'] ?? FALSE,
      '#description' => t('Whether to fill the path with color. Set it to false to disable filling on polygons or circle'),
      '#weight' => 9,
    ];
    $element['style']['fill_color'] = [
      '#type' => 'color',
      '#title' => t('<em>Fill Color</em> field'),
      '#default_value' => $settings['style']['fill_color'] ?? $default_settings['style']['fill_color'] ?? '#C7A8A8',
      '#description' => t('Fill Color.'),
      '#weight' => 10,
    ];
    $element['style']['fill_opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Fill Opacity</em> field'),
      '#default_value' => $settings['style']['fill_opacity'] ?? $default_settings['style']['fill_opacity'] ?? 0.2,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 11,
    ];
    $element['style']['fillrule'] = [
      '#type' => 'select',
      '#title' => t('<em>Fill Rule</em> field'),
      '#default_value' => $settings['style']['fillrule'] ?? $default_settings['style']['fillrule'] ?? 'evenodd',
      '#description' => t('A string that defines <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/fill-rule">how the inside of a shape</a> is determined.' .
        '<br>Nonzero : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction, and then examining the places where a segment of the shape crosses the ray' .
        '<br>Evenodd : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction and counting the number of path segments from the given shape that the ray crosses.'),
      '#options' => [
        'nonzero ' => 'Nonzero',
        'evenodd' => 'Evenodd',
      ],
      '#weight' => 12,
    ];

    return $element;
  }
}
