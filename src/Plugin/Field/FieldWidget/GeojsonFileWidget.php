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
use Drupal\Component\Render\PlainTextOutput;





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

class GeojsonFileWidget extends FileWidget implements WidgetInterface /*, TrustedCallbackInterface*/ {

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

  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $el = WidgetBase::formMultipleElements($items,  $form, $form_state);
    return $el;
    
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Load the items for form rebuilds from the field state as they might not
    // be in $form_state->getValues() because of validation limitations. Also,
    // they are only passed in as $items when editing existing entities.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (isset($field_state['items'])) {
      $items->setValue($field_state['items']);
    }

    // Determine the number of widgets to display.
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $max = count($items) + 5;
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $elements = [];

    $delta = 0;
    // Add an element for every existing item.
    foreach ($items as $item) {
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $item->_weight ?: $delta,
            '#weight' => 100,
          ];
        }

        $elements[$delta] = $element;
        $delta++;
      }
    }

    $empty_single_allowed = ($cardinality == 1 && $delta == 0);
    $empty_multiple_allowed = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $delta < $cardinality) && !$form_state->isProgrammed();

    // Add one more empty row for new uploads except when this is a programmed
    // multiple form as it is not necessary.
    if ($empty_single_allowed || $empty_multiple_allowed) {
      // Create a new empty item.
      $items->appendItem();
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      if ($element) {
        $element['#required'] = ($element['#required'] && $delta == 0);
        $elements[$delta] = $element;
      }
    }

    if ($is_multiple) {
      // The group of elements all-together need some extra functionality after
      // building up the full list (like draggable table rows).
    
      $elements['#type'] = 'details';
      $elements['#open'] = TRUE;
      $elements['#theme'] = 'file_widget_multiple';
      $elements['#theme_wrappers'] = ['details'];
      $elements['#process'] = [[static::class, 'processMultiple']];
      $elements['#title'] = $title;

      // Add some properties that will eventually be added to the file upload
      // field. These are added here so that they may be referenced easily
      // through a hook_form_alter().
      $elements['#file_upload_title'] = $this->t('Add a new file');
      $elements['#file_upload_description'] = [
        '#theme' => 'file_upload_help',
        '#description' => '',
        '#upload_validators' => $elements[0]['#upload_validators'],
        '#cardinality' => $cardinality,
      ];
    }

    return $elements;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $settings = $this->getSettings();

    $fichier = parent::formElement($items, $delta, $element, $form, $form_state);


    $data = [];
    $destination = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($settings['track']['file_directory'], $data));
    $bb = 'public' /* $settings['uri_scheme'] */ . '://' . $destination;
    
    $fichier['#upload_validators'] =  [
        'file_validate_extensions' => [$settings['track']['file_extensions']],
        'file_validate_size' => $settings['track']['max_filesize'] === null ?
          [Environment::getUploadMaxSize()] :
          [Bytes::toNumber($settings['track']['max_filesize'])],
    ];
    $fichier['#upload_location'] = $bb; //$settings['track']['file_directory'];
    $fichier['#cardinality'] = 1;
    $fichier['#multiple'] = false;


    $element['#cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $element['#multiple'] = true;
    $element['#tree'] = true;
    // $element['#max_delta'] = $field_state['items_count'];
   
    $element['track'] = [
      '#title' => 'Data ' . $delta,
      '#type' => 'details',
      '#open' => false,
      '#weight' => 10,
    ];

    $element['track']['fichier'] = $fichier;

    if (null != $form_state->getValue([$items->getName(), 0, 'track', 'fichier', 0])) {
      $fid = $form_state->getValue([$items->getName(), 0, 'track', 'fichier', 0]) ?? 0;
    } else {
      $fid = 0;
    }
    $file_selected = $fid > 0;

    $element['track']['fid'] = [
      '#type' => 'hidden',
      '#value' => $fid,
      '#name' => 'track_fid_' . $delta,
      '#attributes' => [
        'id' => 'track_fid_' . $delta,
      ],
    ];

    $element['track']['description'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#default_value' => $items[$delta]->fichier ?? null,
      '#placeholder' => 'Description de la trace',
      // '#disabled' => !$file_selected ?? true,
      // '#access' => $file_selected ?? false,
      '#attributes' => [
        'id' => 'data_description_' . $delta,
      ],
      '#states' => [
        'visible' => [
          ':input[id="track_fid_' . $delta . '"]' =>
          ['!value' => 0],
        ],
      ],
      // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
    ];

    $element['style'] = [
      '#title' => 'Global style',
      '#type' => 'details',
      '#open' => false,
      // hide until a file is selected
      // '#access' => true, // $file_selected ?? false,
      '#states' => [
        'visible' => [
          [
            '[name="field_leaflet_edit_geojsonfile_' . $delta . '_track_fichier_remove_button"]' => ['valid' => true],
            'or',
            '[id="data_description_' . $delta . '"]' => ['value' => 'matin'],
          ],
        ],
      ],
      '#weight' => 19,
    ];

    $element['style']['leaflet_style'] = [
      '#title' => 'Test leaflet_style',
      '#type' => 'leaflet_style',
      '#weight' => 1,
      // '#value_callback' => [$this, 'styleUnserialize'],
      // '#disabled' => !$file_selected ?? true,
    ];

    $element['mapping'] = [
      '#title' => 'Mapping style',
      '#type' => 'details',
      '#open' => ((null !== $form_state->getValue("last_added")) && $delta == $form_state->getValue("last_added") ||
        ((null === $form_state->getValue("last_added")) && $file_selected == true)) ? true : false,
      '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
      '#suffix' => '</div>',
      // hide until a file is selected
      '#access' => true, //$file_selected ?? false,
      // '#disabled' => !$file_selected ?? true,
      /* '#states' => [
        'visible' => [
          [
            '[name="field_leaflet_edit_geojsonfile_' . $delta . '_track_fichier_remove_button"]' => ['valid' => true],
            'or',
            '[id="data_description_' . $delta . '"]' => ['value' => 'matin'],
          ],
        ],
      ], */
      '#weight' => 20,
    ];

    $element['mapping']['attribut'] = [
      '#title' => 'Attribute ' . $delta + 1,
      '#type' => 'details',
      '#open' => false,
      '#weight' => 20,
    ];

    $element['mapping']['attribut']['_nb_attribut'] = [
      '#type' => 'value',
      '#description' => 'number of attributs for delta ' . $delta + 1,
      '#value' => 0,
    ];
    // save number of attributs mapping
    $form_state->setValue(['mapping', 'attribut', '_nb_attribut'], $element['mapping']['attribut']['_nb_attribut']['#value']);



    if ($file_selected && (! isset($geo_properties))) {
      $props = [];
      $file = File::Load($fid);
      if ($file != null) {
        $cont = file_get_contents($file->getFileUri());
        foreach (json_decode($cont, true)['features'] as $feature) {
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
      }
    } else {
      // TODO : Erreur fid existant mais fichier pas chargé
      $geo_properties = null;
    }

    $attribs = $element['mapping']['attribut']['attributes'] ?? [];
    // $previous_mappings = $form_state->getValue(['mapping', 'attribut', '_nb_attribut']);
    // if ($file_selected) {
    //   $num_mappings = 0;
    // } else {
    //   $num_mappings = -1;
    // }
    // foreach ($attribs as $attrib) {
    //   if (is_array($attrib)) {
    //     $num_mappings++;
    //   }
    // }

    /*  // $num_mappings = $form_state->getValue(['mapping', 'attribut', '_nb_attribut']);
    if (!$num_mappings && isset($element['mapping']['attribut']['attributes'])) {
      // $num_mappings = count($element['mapping']['attribut']['attributes']) ?? 0;
      $form_state->setValue(['mapping', 'attribut', '_nb_attribut'], $num_mappings);
    } else if (!$num_mappings) {
      $num_mappings = 0;
      $form_state->setValue(['mapping', 'attribut', '_nb_attribut'], $num_mappings);
    }

    for ($i = 0; $i <= $num_mappings; $i++) { */

    $element['mapping']['attribut']['attributes'] /* [$i] */ = [
      '#title' => 'Style Mapping',
      '#type' => 'leaflet_style_mapping',
      '#cardinality' => 3,
      '#multiple' => true,
      '#description' => 'Mapping ' . $delta . ':',
      '#value_callback' => [$this, 'mappingUnserialize'],
      '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      '#tree' => true,
    ];

    // Return the updated widget
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  /* public static function trustedCallbacks() {
    return ['preRenderManagedFile'];
  } */


  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public static function addOne(array &$form, FormStateInterface $form_state) {

    $input_exists = FALSE;
    // Use getTriggeringElement() to determine delta
    $parent = array_slice($form_state->getTriggeringElement()['#parents'], 0, 3);
    $nb_attribut_array = $parent;
    $nb_attribut_array[] = '_nb_attribut';

    $name_field = $form_state->getValue($nb_attribut_array) ?? 0;

    // $name_field = count($field_element['attribut']);
    $add_button = $name_field + 1;
    $form_state->setValue($nb_attribut_array, $add_button);

    $form_state->setValue("last_added", $parent[1]);

    // Since our buildForm() method relies on the value of 'num_mappings' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm(). */
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public static function removeLast(array &$form, FormStateInterface $form_state) {
    $parent = array_slice($form_state->getTriggeringElement()['#parents'], 0, 3);
    $nb_attribut_array = $parent;
    $nb_attribut_array[] = '_nb_attribut';

    $name_field = $form_state->getValue($nb_attribut_array) ?? 0;

    if ($name_field > 0) {
      $remove_button = $name_field - 1;
      $form_state->setValue($nb_attribut_array, $remove_button);
    }
    // Since our buildForm() method relies on the value of 'num_mappings' to
    // generate 'name' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild();
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

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $new_values = parent::massageFormValues($values, $form, $form_state);

    foreach ($values as $key => $value) {

      if (isset($value['track'])) {
        foreach ($value['track']['fichier'] as $fid) {
          $new_values[$key]['target_id'] = (int)$fid;
          $form_state->setRebuild(true);
        }
        $new_values[$key]['description'] = $value['track']['description'];
      }

      if ((isset($value['style'])) && (isset($new_values[$key]['target_id']))) {
        // $new_values[$key]['styles'] = serialize($value['style']);

        $new_values[$key]['stroke'] = $value['style']['leaflet_style']['stroke'] != 0 ? true : false;
        $new_values[$key]['color'] = $value['style']['leaflet_style']['color'];
        $new_values[$key]['weight'] = (int)$value['style']['leaflet_style']['weight'];
        $new_values[$key]['opacity'] = (float)$value['style']['leaflet_style']['opacity'];
        $new_values[$key]['linecap'] = $value['style']['leaflet_style']['linecap'];
        $new_values[$key]['linejoin'] = $value['style']['leaflet_style']['linejoin'];
        $new_values[$key]['dasharray'] = $value['style']['leaflet_style']['dasharray'];
        $new_values[$key]['dashoffset'] = (int)$value['style']['leaflet_style']['dashoffset'];
        $new_values[$key]['fill'] = (int)$value['style']['leaflet_style']['fill'];
        $new_values[$key]['fill_color'] = $value['style']['leaflet_style']['fill_color'];
        $new_values[$key]['fill_opacity'] = (float)$value['style']['leaflet_style']['fill_opacity'];
        $new_values[$key]['fillrule'] = $value['style']['leaflet_style']['fillrule'];
      }
      if (isset($value['mapping']) && (isset($new_values[$key]['target_id']))) {
        $new_values[$key]['mappings'] = serialize($value['mapping']);
      }
    }
    return $new_values;
  }

}
