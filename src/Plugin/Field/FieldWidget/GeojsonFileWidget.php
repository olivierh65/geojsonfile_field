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


/**
 * Provides the field widget for Symbol field.
 *
 * @FieldWidget(
 *   id = "geojsonfile_widget",
 *   label = @Translation("geojson File widget"),
 *   description = @Translation("An File field with a text field for a description"),
 *   field_types = {
 *     "geojsonfile_field"
 *   }
 * )
 */
class GeojsonFileWidget extends WidgetBase implements WidgetInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  static protected $fieldName = null;

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    if (static::$fieldName == null) {
      static::$fieldName=$this->fieldDefinition->getName();
    }
    
    $element = [];
    $element['#cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $element['#multiple'] = true;
    $element['#tree'] = true;
    $element['#max_delta'] = 0;


    $element['data'] = [
        '#title' => 'Data ' . $delta,
        '#type' => 'details',
        '#open' => true,
        '#weight' => 10,
      ];
    $element['data']['fichier'] = [
      '#type' => 'managed_file',
      '#title' => "Fichier $delta",
      '#upload_validators' =>  [
        'file_validate_extensions' => ['geojson'],
        'file_validate_size' => [Environment::getUploadMaxSize()],
      ],
      '#default_value' => [
        'fids' => $items[$delta]->get('target_id')->getCastedValue() ?? 0
      ],
      '#fids' => 0,
      '#multiple' => false,
    ];

    $element['data']['description'] = [
      '#type' => 'textfield',
      '#title' => t('Description'),
      '#default_value' => $items[$delta]->fichier,
      '#placeholder' => 'Description de la trace',
      '#attributes' => [
        'id' => 'data_description_' . $delta,
      ],
      // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
    ];

    if (isset($form_state->getValue($items[$delta]->getFieldDefinition()->getName())[0]['fichier'])) {
      $file_selected = $form_state->getValue($items[$delta]->getFieldDefinition()->getName())[0]['fichier'][0] > 0;
    } else {
      $file_selected = false;
    }
   

    $element['fichier']['style'] = [
      '#title' => 'Global style',
      '#type' => 'details',
      '#open' => false,
      // hide until a file is selected
      '#access' => $file_selected ?? false,
      '#weight' => 19,
    ];

    $element['fichier']['style']['leaflet_style'] = [
      '#title' => 'Test leaflet_style',
      '#type' => 'leaflet_style',
      '#weight' => 1,
      '#value_callback' => [$this, 'styleUnserialize'],
    ];

    $element['fichier']['mapping'] = [
      '#title' => 'Attribute style',
      '#type' => 'details',
      '#open' => ((null !== $form_state->getValue("last_added")) && $delta == $form_state->getValue("last_added")) ? true : false,
      '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
      '#suffix' => '</div>',
      // hide until a file is selected
      '#access' => $file_selected ?? false,
      '#weight' => 20,
    ];

    $element['fichier']['_nb_attribut'] = [
      '#type' => 'value',
      '#description' => 'number of attributs for delta ' . $delta + 1,
      '#value' => 0,
    ];
    // save number of attributs mapping
    $form_state->setValue(['_nb_attribut'], $element['fichier']['_nb_attribut']['#value']);



    if ($file_selected && (! isset($this->geo_properties))) {
      $props = [];
      $file = File::Load($form_state->getValue($items[$delta]->getFieldDefinition()->getName())[0]['fichier'][0]);
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
      $this->geo_properties = $props_uniq;
    }

    $num_names = $form_state->getValue('_nb_attribut');
    if (!$num_names && isset($element['fichier']['mappings'])) {
      $num_names = count($element['fichier']['mappings']) ?? 0;
      $form_state->setValue('_nb_attribut', $num_names);
    } else if (!$num_names) {
      $num_names = 0;
      $form_state->setValue('_nb_attribut', $num_names);
    }

    for ($i = 0; $i < $num_names; $i++) {
      $element['fichier']['mapping']['attribut'][$i] = [
        '#title' => 'Attribute ' . $i,
        '#type' => 'details',
        '#open' => false,
        '#weight' => $i,
      ];

      $element['fichier']['mapping']['attribut'][$i]['leaflet_style_mapping'] = array(
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta . ':' . $i,
        '#cardinality' => 10,
        '#weight' => 1,
        '#value_callback' => [$this, 'mappingUnserialize'],
      );

      // If there is more than one name, add the remove button.
    if ($num_names >= 1) {
      $element['fichier']['mapping']['attribut'][$i]['actions'] = [
        '#type' => 'actions',
      ];
      $element['fichier']['mapping']['attribut'][$i]['actions']['remove_name'] = [
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
    }

    $element['fichier']['mapping']['actions'] = [
      '#type' => 'actions',
    ];

    $element['fichier']['mapping']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => [ [static::class, 'addOne'] ],
      '#description' => 'Add ' . $delta,
      '#name' => 'add_' . $delta, // #name must be defined and unique
      '#ajax' => [
        'callback' => [$this, 'addmoreCallback'],
        'wrapper' => 'mapping-fieldset-wrapper' . $delta,
      ],
    ];
    

    // Return the updated widget
    return $element;
  }

  public static function processMultiple($element, FormStateInterface $form_state, $form) {
    return FileWidget::processMultiple($element, $form_state, $form);
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public static function addmoreCallback(array &$form, FormStateInterface $form_state, Request $request) {
    // return $form['mapping'];
    $input_exists = FALSE;
    $field_element = NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, 5), $input_exists);
    return $field_element;
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

    // Since our buildForm() method relies on the value of 'num_names' to
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
    // Since our buildForm() method relies on the value of 'num_names' to
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
    if ($form_state->getValue($data)) {
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
    return [
      'progress_indicator' => 'throbber',
      'file_directory' => '',
      'max_filesize' => '10MB',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function ___fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[static::class, 'validateDirectory']],
      '#weight' => 3,
    ];

    // Make the extension list a little more human-friendly by comma-separation.
    $extensions = str_replace(' ', ', ', $settings['file_extensions']);
    $element['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $extensions,
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
      '#element_validate' => [[static::class, 'validateExtensions']],
      '#weight' => 1,
      '#maxlength' => 256,
      // By making this field required, we prevent a potential security issue
      // that would allow files of any type to be uploaded.
      '#required' => TRUE,
    ];

    $element['max_filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $settings['max_filesize'],
      '#description' => $this->t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes could be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', [
        '%limit' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#size' => 10,
      '#element_validate' => [[static::class, 'validateMaxFilesize']],
      '#weight' => 5,
    ];

    $element['description_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable <em>Description</em> field'),
      '#default_value' => $settings['description_field'] ?? '',
      '#description' => $this->t('The description field allows users to enter a description about the uploaded file.'),
      '#weight' => 11,
    ];

    return $element;
  }


  public function __massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $v = parent::massageFormValues($values, $form, $form_state);

    foreach ($v as $key => $value) {
      if (isset($value['style'])) {
        $v[$key]['styles'] = serialize($value['style']);
      }
      if (isset($value['mapping'])) {
        $v[$key]['mappings'] = serialize($value['mapping']);
      }
    }

    return $v;
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Since file upload widget now supports uploads of more than one file at a
    // time it always returns an array of fids. We have to translate this to a
    // single fid, as field expects single value.

    // $new_values = $this->__massageFormValues($values, $form, $form_state);
    $new_values = $values;
    foreach ($new_values as $k1 => &$value) {
      foreach ($value['fichier'] as $key => $fid) {
        $value['fichier']['#required'] = true;
        $value['fichier']['target_id'] = $fid;
        unset($value['fichier'][$key]);
      }
    }

    return $new_values;
  }

  /**
   * {@inheritdoc}
   */
  public function ___extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    // Update reference to 'items' stored during upload to take into account
    // changes to values like 'alt' etc.
    // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::submit()
    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $field_state['items'] = $items->getValue();
    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   */
  public static function value($element, $input, FormStateInterface $form_state) {
    if ($input) {
      if (empty($input['display'])) {
        // Updates the display field with the default value because
        // #display_field is invisible.
        if (empty($input['fids'])) {
          $input['display'] = $element['#display_default'];
        }
        // Checkboxes lose their value when empty.
        // If the display field is present, make sure its unchecked value is
        // saved.
        else {
          $input['display'] = $element['#display_field'] ? 0 : 1;
        }
      }
    }

    // We depend on the managed file element to handle uploads.
    $return = ManagedFile::valueCallback($element, $input, $form_state);

    // Ensure that all the required properties are returned even if empty.
    $return += [
      'fids' => [],
      'display' => 1,
      'description' => '',
    ];

    return $return;
  }


  /**
   * Form API callback: Processes a file_generic field element.
   *
   * Expands the file_generic type to include the description and display
   * fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function __process($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // Add the display field if enabled.
    if ($element['#display_field']) {
      $element['display'] = [
        '#type' => empty($item['fids']) ? 'hidden' : 'checkbox',
        '#title' => new TranslatableMarkup('Include file in display'),
        '#attributes' => ['class' => ['file-display']],
      ];
      if (isset($item['display'])) {
        $element['display']['#value'] = $item['display'] ? '1' : '';
      } else {
        $element['display']['#value'] = $element['#display_default'];
      }
    } else {
      $element['display'] = [
        '#type' => 'hidden',
        '#value' => '1',
      ];
    }

    // Add the description field if enabled.
    if ($element['#description_field'] && $item['fids']) {
      $config = \Drupal::config('file.settings');
      $element['description'] = [
        '#type' => $config->get('description.type'),
        '#title' => new TranslatableMarkup('Description'),
        '#value' => $item['description'] ?? '',
        '#maxlength' => $config->get('description.length'),
        '#description' => new TranslatableMarkup('The description may be used as the label of the link to the file.'),
      ];
    }

    // Adjust the Ajax settings so that on upload and remove of any individual
    // file, the entire group of file fields is updated together.
    if ($element['#cardinality'] != 1) {
      $parents = array_slice($element['#array_parents'], 0, -1);
      $new_options = [
        'query' => [
          'element_parents' => implode('/', $parents),
        ],
      ];
      $field_element = NestedArray::getValue($form, $parents);
      $new_wrapper = $field_element['#id'] . '-ajax-wrapper';
      foreach (Element::children($element) as $key) {
        if (isset($element[$key]['#ajax'])) {
          $element[$key]['#ajax']['options'] = $new_options;
          $element[$key]['#ajax']['wrapper'] = $new_wrapper;
        }
      }
      unset($element['#prefix'], $element['#suffix']);
    }

    // Add another submit handler to the upload and remove buttons, to implement
    // functionality needed by the field widget. This submit handler, along with
    // the rebuild logic in file_field_widget_form() requires the entire field,
    // not just the individual item, to be valid.
    foreach (['upload_button', 'remove_button'] as $key) {
      $element[$key]['#submit'][] = [static::class, 'submit'];
      $element[$key]['#limit_validation_errors'] = [array_slice($element['#parents'], 0, -1)];
    }

    return $element;
  }


  /**
   * Retrieves the file description from a field element.
   *
   * This helper static method is used by processMultiple() method.
   *
   * @param array $element
   *   An associative array with the element being processed.
   *
   * @return array|false
   *   A description of the file suitable for use in the administrative
   *   interface.
   */
  protected static function ___getDescriptionFromElement($element) {
    // Use the actual file description, if it's available.
    if (!empty($element['#default_value']['description'])) {
      return $element['#default_value']['description'];
    }
    // Otherwise, fall back to the filename.
    if (!empty($element['#default_value']['filename'])) {
      return $element['#default_value']['filename'];
    }
    // This is probably a newly uploaded file; no description is available.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Never flag validation errors for the remove button.
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if ($clicked_button !== 'remove_button') {
      parent::flagErrors($items, $violations, $form, $form_state);
    }
  }

  
  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    
    $this->fieldDefinition->getFieldStorageDefinition()->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $elements = parent::formMultipleElements($items, $form, $form_state);

    return $elements;
  }


}
