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
class GeojsonFileWidget extends WidgetBase /* FileWidget implements TrustedCallbackInterface */ {

private $geo_properties = null;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;



  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // The formElement method returns the form for a single field widget (Used to render the form in the Admin Interface of Drupal)
    // We need to add our new field to this

    // Get the parents form elements
    // $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $field_settings = $this->getFieldSettings();

    // The field settings include defaults for the field type. However, this
    // widget is a base class for other widgets (e.g., ImageWidget) that may act
    // on field types without these expected settings.
    $field_settings += [
      'display_default' => NULL,
      'display_field' => NULL,
      'description_field' => NULL,
    ];

    $defaults = [
      'fids' => [],
      'display' => (bool) $field_settings['display_default'],
      'description' => '',
    ];


    // $element['#multiple'] = false;
    /* $element['#upload_validators'] = [
      'file_validate_extensions' => ['gpx gepjson'],
    ];
 */

    $element['fichier'] = [
      '#type' => 'managed_file',
      '#title' => "Fichier $delta" ,
      '#upload_validators' => $items[$delta]->getUploadValidators(),
      // '#process' => array_merge($element_info['#process'], [[static::class, 'process']]),
      '#progress_indicator' => $this->getSetting('progress_indicator'),
      '#display_field' => (bool) $field_settings['display_field'],
      '#display_default' => $field_settings['display_default'],
      '#description_field' => $field_settings['description_field'],
      '#display_field' => (bool) $field_settings['display_field'],
      // '#theme' => 'file_link',
      // Add some properties that will eventually be added to the file upload
      // field. These are added here so that they may be referenced easily
      // through a hook_form_alter().
      '#file_upload_title' => $this->t('Add a new file'),

      '#cardinality' => 1,
      '#multiple' => false,
      '#extended' => true,
    ];

   /*   // Field stores FID value in a single mode, so we need to transform it for
    // form element to recognize it correctly.
    if (!isset($items[$delta]->fids) && isset($items[$delta]->target_id)) {
      $items[$delta]->fids = [$items[$delta]->target_id];
    }
    // $element[$delta]['fichier']['#default_value'] = $items[$delta]->getValue() + $defaults;
    $element['fichier']['#default_value']['fids'] =  null;

    $default_fids = $element['fichier']['#extended'] ? $element['fichier']['#default_value']['fids'] : $element['fichier']['#default_value']; */
    
    if (isset($form_state->getValue($items[$delta]->getFieldDefinition()->getName())[0][0]['fichier'])) {
    // if (isset($element[$delta]['fichier']['#default_value']['fids'])) {
      // $file_selected = count($element[$delta]['fichier']['#default_value']['fids']) > 0;
      $file_selected = $form_state->getValue($items[$delta]->getFieldDefinition()->getName())[0][0]['fichier']['fids'][0] > 0;
    } else {
      $file_selected = false;
    }
    
    
    $element['description_field'] = [
      '#type' => 'textfield',
      '#title' => t('DEscription'),
    ];

    $element['style'] = [
      '#title' => 'Global style',
      '#type' => 'details',
      '#open' => false,
      // hide until a file is selected
      '#access' => $file_selected ?? false,
      '#weight' => 19,
    ];

    $element['style']['leaflet_style'] = [
      '#title' => 'Test leaflet_style',
      '#type' => 'leaflet_style',
      '#weight' => 1,
      '#value_callback' => [$this, 'styleUnserialize'],
    ];

    $element['mapping'] = [
      '#title' => 'Attribute style',
      '#type' => 'details',
      '#open' => ((null !== $form_state->getValue("last_added")) && $delta == $form_state->getValue("last_added")) ? true : false,
      '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
      '#suffix' => '</div>',
      // hide until a file is selected
      '#access' => $file_selected ?? false,
      '#weight' => 20,
    ];

    $element['_nb_attribut'] = [
      '#type' => 'value',
      '#description' => 'number of attributs for delta ' . $delta + 1,
      '#value' => $delta + 1, // attributes number is 1 indexed
    ];
    // save number of attributs mapping
    $form_state->setValue(['_nb_attribut'], $delta + 1);

    $element['#cardinality'] = 5;
    $element['#multiple'] = true;

   
    if ($file_selected && (! isset($this->geo_properties ))) {
      $props = [];
      $file = File::Load($element['fichier']['#default_value']['fids'][0]);
      $cont = file_get_contents($file->getFileUri());
      foreach (json_decode($cont, true)['features'] as $feature) {
        if ($feature['type'] == "Feature") {
          foreach ($feature['properties'] as $key => $val) {
            $props[$key][] = $val;
          }
        }
      }

      foreach($props as $key => $value) {
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
      $num_names = count($element['fichier']['mappings']) ?? 1;
      $form_state->setValue('_nb_attribut', $num_names);
    } else if (!$num_names) {
      $num_names = 1;
      $form_state->setValue('_nb_attribut', $num_names);
    }
    
    for ($i = 1; $i <= $num_names; $i++) {
      $element['mapping']['attribut'][$i] = [
        '#title' => 'Attribute ' . $i,
        '#type' => 'details',
        '#open' => false,
        '#weight' => $i,
      ];

      $element['mapping']['attribut'][$i]['leaflet_style_mapping'] = array(
        '#title' => 'Style Mapping',
        '#type' => 'leaflet_style_mapping',
        '#description' => 'Mapping ' . $delta . ':' . $i,
        '#cardinality' => 10,
        '#weight' => 1,
        '#value_callback' => [$this, 'mappingUnserialize'],
      );
    }
    
    $element['mapping']['actions'] = [
      '#type' => 'actions',
    ];
    $class = get_class($this);
    $element['mapping']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => [[$this, 'addOne']],
      '#description' => 'Add ' . $delta,
      '#name' => 'add_' . $delta, // #name must be defined and unique
      '#ajax' => [
        'callback' => $class . '::addmoreCallback',
        'wrapper' => 'mapping-fieldset-wrapper' . $delta,
      ],
    ];
    // If there is more than one name, add the remove button.
    if ($num_names > 1) {
      $element['mapping']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove last'),
        '#submit' => [[$this, 'removeLast']],
        '#description' => 'Remove ' . $delta,
      '#name' => 'remove_' . $delta, // #name must be defined and unique
        '#ajax' => [
          'callback' => $class . '::addmoreCallback',
          'wrapper' => 'mapping-fieldset-wrapper' . $delta,
        ],
      ];
    }

    // Return the updated widget
    return $element;
  }


  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public static function addmoreCallback(array &$form, FormStateInterface $form_state, Request $request) {
    // return $form['mapping'];
    $input_exists = FALSE;
    $field_element = NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, 4), $input_exists);
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
    }
    else {
      return [];
    }
  }

  
  /**
   * The element info manager.
   */
  protected ElementInfoManagerInterface $elementInfo;


 

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'progress_indicator' => 'throbber',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['progress_indicator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Progress indicator'),
      '#options' => [
        'throbber' => $this->t('Throbber'),
        'bar' => $this->t('Bar with progress meter'),
      ],
      '#default_value' => $this->getSetting('progress_indicator'),
      '#description' => $this->t('The throbber display does not show the status of uploads but takes up less space. The progress bar is helpful for monitoring progress on large uploads.'),
      '#weight' => 16,
      '#access' => extension_loaded('uploadprogress'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Progress indicator: @progress_indicator', ['@progress_indicator' => $this->getSetting('progress_indicator')]);
    return $summary;
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
    $new_values = $this->__massageFormValues($values, $form, $form_state);
    foreach ($values as &$value) {
      foreach ($value['fids'] as $fid) {
        $new_value = $value;
        $new_value['target_id'] = $fid;
        unset($new_value['fids']);
        $new_values[] = $new_value;
      }
    }

    return $new_values;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
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
   * Validates the number of uploaded files.
   *
   * This validator is used only when cardinality not set to 1 or unlimited.
   */
  public static function validateMultipleCount($element, FormStateInterface $form_state, $form) {
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    $array_parents = $element['#array_parents'];
    array_pop($array_parents);
    $previously_uploaded_count = count(Element::children(NestedArray::getValue($form, $array_parents))) - 1;

    $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($element['#entity_type']);
    $field_storage = $field_storage_definitions[$element['#field_name']];
    $newly_uploaded_count = count($values['fids']);
    $total_uploaded_count = $newly_uploaded_count + $previously_uploaded_count;
    if ($total_uploaded_count > $field_storage->getCardinality()) {
      $keep = $newly_uploaded_count - $total_uploaded_count + $field_storage->getCardinality();
      $removed_files = array_slice($values['fids'], $keep);
      $removed_names = [];
      foreach ($removed_files as $fid) {
        $file = File::load($fid);
        $removed_names[] = $file->getFilename();
      }
      $args = [
        '%field' => $field_storage->getName(),
        '@max' => $field_storage->getCardinality(),
        '@count' => $total_uploaded_count,
        '%list' => implode(', ', $removed_names),
      ];
      $message = new TranslatableMarkup('Field %field can only hold @max values but there were @count uploaded. The following files have been omitted as a result: %list.', $args);
      \Drupal::messenger()->addWarning($message);
      $values['fids'] = array_slice($values['fids'], 0, $keep);
      NestedArray::setValue($form_state->getValues(), $element['#parents'], $values);
    }
  }

  /**
   * Form API callback: Processes a file_generic field element.
   *
   * Expands the file_generic type to include the description and display
   * fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
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
      }
      else {
        $element['display']['#value'] = $element['#display_default'];
      }
    }
    else {
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
   * Form API callback: Processes a group of file_generic field elements.
   *
   * Adds the weight field to each row so it can be ordered and adds a new Ajax
   * wrapper around the entire group so it can be replaced all at once.
   *
   * This method on is assigned as a #process callback in formMultipleElements()
   * method.
   */
  public static function processMultiple($element, FormStateInterface $form_state, $form) {
    $element_children = Element::children($element, TRUE);
    $count = count($element_children);

    // Count the number of already uploaded files, in order to display new
    // items in \Drupal\file\Element\ManagedFile::uploadAjaxCallback().
    if (!$form_state->isRebuilding()) {
      $count_items_before = 0;
      foreach ($element_children as $children) {
        if (!empty($element[$children]['#default_value']['fids'])) {
          $count_items_before++;
        }
      }

      $form_state->set('file_upload_delta_initial', $count_items_before);
    }

    foreach ($element_children as $delta => $key) {
      if ($key != $element['#file_upload_delta']) {
        $description = static::getDescriptionFromElement($element[$key]);
        $element[$key]['_weight'] = [
          '#type' => 'weight',
          '#title' => $description ? new TranslatableMarkup('Weight for @title', ['@title' => $description]) : new TranslatableMarkup('Weight for new file'),
          '#title_display' => 'invisible',
          '#delta' => $count,
          '#default_value' => $delta,
        ];
      }
      else {
        // The title needs to be assigned to the upload field so that validation
        // errors include the correct widget label.
        $element[$key]['#title'] = $element['#title'];
        $element[$key]['_weight'] = [
          '#type' => 'hidden',
          '#default_value' => $delta,
        ];
      }
    }

    // Add a new wrapper around all the elements for Ajax replacement.
    $element['#prefix'] = '<div id="' . $element['#id'] . '-ajax-wrapper">';
    $element['#suffix'] = '</div>';

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
  protected static function getDescriptionFromElement($element) {
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
   * Form submission handler for upload/remove button of formElement().
   *
   * This runs in addition to and after file_managed_file_submit().
   *
   * @see file_managed_file_submit()
   */
  public static function submit($form, FormStateInterface $form_state) {
    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    $button = $form_state->getTriggeringElement();
    $parents = array_slice($button['#parents'], 0, -2);
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];

    $submitted_values = NestedArray::getValue($form_state->getValues(), array_slice($button['#parents'], 0, -2));
    foreach ($submitted_values as $delta => $submitted_value) {
      if (empty($submitted_value['fids'])) {
        unset($submitted_values[$delta]);
      }
    }

    // If there are more files uploaded via the same widget, we have to separate
    // them, as we display each file in its own widget.
    $new_values = [];
    foreach ($submitted_values as $delta => $submitted_value) {
      if (is_array($submitted_value['fids'])) {
        foreach ($submitted_value['fids'] as $fid) {
          $new_value = $submitted_value;
          $new_value['fids'] = [$fid];
          $new_values[] = $new_value;
        }
      }
      else {
        $new_value = $submitted_value;
      }
    }

    // Re-index deltas after removing empty items.
    $submitted_values = array_values($new_values);

    // Update form_state values.
    NestedArray::setValue($form_state->getValues(), array_slice($button['#parents'], 0, -2), $submitted_values);

    // Update items.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['items'] = $submitted_values;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);
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
   * Special handling to create form elements for multiple values.
   *
   * Handles generic features for multiple fields:
   * - number of widgets
   * - AHAH-'add more' button
   * - table display and drag-n-drop value reordering
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $is_multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    $is_unlimited_not_programmed = FALSE;
    $parents = $form['#parents'];

    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_unlimited_not_programmed = !$form_state->isProgrammed();
        break;

      default:
        $max = $cardinality - 1;
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();
    $id_prefix = implode('-', array_merge($parents, [$field_name]));
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');

    $elements = [];

    for ($delta = 0; $delta <= $max; $delta++) {
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $items->appendItem();
      }

      // For multiple fields, title and description are handled by the wrapping
      // table.
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)', ['@title' => $title, '@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }

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
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          ];

          // Add 'remove' button, if not working with a programmed form.
          if ($is_unlimited_not_programmed) {
            $remove_button = [
              '#delta' => $delta,
              '#name' => str_replace('-', '_', $id_prefix) . "_{$delta}_remove_button",
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#validate' => [],
              '#submit' => [[static::class, 'deleteSubmit']],
              '#limit_validation_errors' => [],
              '#ajax' => [
                'callback' => [static::class, 'deleteAjax'],
                'wrapper' => $wrapper_id,
                'effect' => 'fade',
              ],
            ];

            $element['_actions'] = [
              'delete' => $remove_button,
              '#weight' => 101,
            ];
          }
        }

        $elements = $element;
      }
    }

    if ($elements) {
      $elements += [
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $is_multiple,
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
      ];

      // Add 'add more' button, if not working with a programmed form.
      if ($is_unlimited_not_programmed) {
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';

        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => t('Add another item'),
          '#attributes' => ['class' => ['field-add-more-submit']],
          '#limit_validation_errors' => [],
          '#submit' => [[static::class, 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
        ];
      }
    }

    return $elements;
  }

      /**
   * Generates the form element for a single copy of the widget.
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#field_parents' => $form['#parents'],
      // Only the first widget should be required.
      '#required' => $delta == 0 && $this->fieldDefinition->isRequired(),
      '#delta' => $delta,
      '#weight' => $delta,
    ];

    $element = $this->formElement($items, $delta, $element, $form, $form_state);

    if ($element) {
      // Allow modules to alter the field widget form element.
      $context = [
        'form' => $form,
        'widget' => $this,
        'items' => $items,
        'delta' => $delta,
        'default' => $this->isDefaultValueWidget($form_state),
      ];
      \Drupal::moduleHandler()->alter(['field_widget_single_element_form', 'field_widget_single_element_' . $this->getPluginId() . '_form'], $element, $form_state, $context);
    }

    return $element;
  }


}
