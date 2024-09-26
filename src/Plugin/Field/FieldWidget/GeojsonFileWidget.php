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

class GeojsonFileWidget extends WidgetBase implements WidgetInterface  {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  static protected $fieldName = null;

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function __formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $this->fieldDefinition->getFieldStorageDefinition()->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $element_manager = \Drupal::service('plugin.manager.element_info');
    $b = $element_manager->getInfo('leaflet_style_mapping');
    $c = Element::children($b);
    $d = $element_manager->getDefinition('leaflet_style_mapping');
    $e = $element_manager->getDefinitions('leaflet_style_mapping');

    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    if ($form_state->isProcessingInput()) {
      $p = true;
    } else {
      $p = false;
    }

    // Increment the items count.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (! isset($field_state['items_count'])) {
      $field_state['items_count'] = 1;
    }
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $elements = parent::formMultipleElements($items, $form, $form_state);



    return $elements;
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    if (static::$fieldName == null) {
      static::$fieldName = $this->fieldDefinition->getName();
    }

    $settings = $this->getSettings();

    // $element = [];
    $element['#cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $element['#multiple'] = true;
    $element['#tree'] = true;
    // $element['#max_delta'] = $field_state['items_count'];
    $form['#validate'][] = [$this, "validate"];


    $element['track'] = [
      '#title' => 'Data ' . $delta,
      '#type' => 'details',
      '#open' => false,
      '#weight' => 10,
    ];

    if (null != $form_state->getValue([$items->getName(), 0, 'track', 'fichier', 0])) {
      $fid = $form_state->getValue([$items->getName(), 0, 'track', 'fichier', 0]) ?? 0;
    } else {
      $fid = 0;
    }
    $file_selected = $fid > 0;

    $element['track']['fichier'] = [
      '#type' => 'managed_file',
      '#title' => "Fichier $delta",
      '#upload_validators' =>  [
        'file_validate_extensions' => [$settings['track']['file_extensions']],
        'file_validate_size' => $settings['track']['file_extensions'] === null ?
          [Environment::getUploadMaxSize()] :
          [$settings['track']['file_extensions']],
      ],
      '#default_value' => [
        'fids' => $fid,
      ],
      '#fids' => 0,
    ];

    $element['track']['description'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#default_value' => $items[$delta]->fichier ?? null,
      '#placeholder' => 'Description de la trace',
      // '#disabled' => !$file_selected ?? true,
      '#access' => $file_selected ?? false,
      '#attributes' => [
        'id' => 'data_description_' . $delta,
      ],
      '#required' => true,
      // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
    ];

    // $element['style'] = [
    //   '#title' => 'Global style',
    //   '#type' => 'details',
    //   '#open' => false,
    //   // hide until a file is selected
    //   '#access' => true, // $file_selected ?? false,
    //   '#weight' => 19,
    // ];

    // $element['style']['leaflet_style'] = [
    //   '#title' => 'Test leaflet_style',
    //   '#type' => 'leaflet_style',
    //   '#weight' => 1,
    //   // '#value_callback' => [$this, 'styleUnserialize'],
    //   '#disabled' => !$file_selected ?? true,
    // ];

    // $element['mapping'] = [
    //   '#title' => 'Mapping style',
    //   '#type' => 'details',
    //   '#open' => ((null !== $form_state->getValue("last_added")) && $delta == $form_state->getValue("last_added") ||
    //     ((null === $form_state->getValue("last_added")) && $file_selected == true)) ? true : false,
    //   '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
    //   '#suffix' => '</div>',
    //   // hide until a file is selected
    //   '#access' => true, // $file_selected ?? false,
    //   '#disabled' => !$file_selected ?? true,
    //   '#weight' => 20,
    // ];

    // $element['mapping']['attribut'] = [
    //   '#title' => 'Attribute ' . $delta + 1,
    //   '#type' => 'details',
    //   '#open' => false,
    //   '#weight' => 20,
    // ];

    // $element['mapping']['attribut']['_nb_attribut'] = [
    //   '#type' => 'value',
    //   '#description' => 'number of attributs for delta ' . $delta + 1,
    //   '#value' => 0,
    // ];
    // save number of attributs mapping
    ////// $form_state->setValue(['mapping', 'attribut', '_nb_attribut'], $element['mapping']['attribut']['_nb_attribut']['#value']);



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

    // $element['mapping']['attribut']['attributes'] /* [$i] */ = [
    //   '#title' => 'Style Mapping',
    //   '#type' => 'leaflet_style_mapping',
    //   '#cardinality' => 3,
    //   '#multiple' => true,
    //   '#description' => 'Mapping ' . $delta . ':',
    //   '#value_callback' => [$this, 'mappingUnserialize'],
    //   '#cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    //   '#tree' => true,
    // ];

    /*  // If there is more than one name, add the remove button.
    if ($num_mappings >= 1) {
        $element['mapping']['attribut']['attributes'][$i]['actions'] = [
          '#type' => 'actions',
        ];
        $element['mapping']['attribut']['attributes'][$i]['actions']['remove_name'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove last'),
          '#submit' => [static::class, 'removeLast'],
          '#description' => 'Remove ' . $delta,
          '#name' => 'remove_' . $delta, // #name must be defined and unique
          '#ajax' => [
            'callback' => [$this, 'addmoreCallback'],
            'wrapper' => 'mapping-fieldset-wrapper' . $delta,
          ],
        ];
      }
    } */

    /* if ($num_mappings > $previous_mappings) {
      // ajout d'un mapping
      $form_state->setRebuild();
    } */

    /* $element['mapping']['actions'] = [
      '#type' => 'actions',
    ];

    $element['mapping']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => [[static::class, 'addOne']],
      '#description' => 'Add ' . $delta,
      '#name' => 'add_' . $delta, // #name must be defined and unique
      '#ajax' => [
        'callback' => [$this, 'addmoreCallback'],
        'wrapper' => 'mapping-fieldset-wrapper' . $delta,
      ],
    ]; */


    /* $element['mapping']['attribut'] = [
      '#type' => 'element_multiple',
      '#title' => 'Multiple values',
      '#min_items' => 1,
      '#cardinality' => 10,
      '#element' => [
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta,
        '#cardinality' => 10,
        '#weight' => 1,
        '#value_callback' => [$this, 'mappingUnserialize'],
        '#multiple' => true,
        '#tree' => true,
        '#max_delta' => 1,
      ],
    ]; */

    // Return the updated widget
    return $element;
  }

  public function validate(&$form, &$form_state) {
    $a=1;
  }

  public static function ___processMultiple($element, FormStateInterface $form_state, $form) {
    return FileWidget::processMultiple($element, $form_state, $form);
  }


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
  public static function defaultSettings() {
    // recupere es settings definies dans le Field 
    $default_settings = \Drupal::service('plugin.manager.field.field_type')->getDefaultFieldSettings('geojsonfile_field');
    return $default_settings;
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

    $new_values = [];
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

  /**
   * After-build handler for field elements in a form.
   *
   * This stores the final location of the field within the form structure so
   * that flagErrors() can assign validation errors to the right form element.
   */
  public static function ___afterBuild(array $element, FormStateInterface $form_state) {
    /* $session = \Drupal::request()->getSession();
    $session->set('batch_form_state', $form_state); */

    return parent::afterBuild($element, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function ___flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Never flag validation errors for the remove button.
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if ($clicked_button !== 'remove_button') {
      parent::flagErrors($items, $violations, $form, $form_state);
    }
  }
}
