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
class GeojsonFileWidget extends WidgetBase /* FileWidget */ implements TrustedCallbackInterface {

private $geo_properties = null;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

    /**
   * The element info manager.
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->elementInfo = $element_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('element_info'));
  }

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


  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'managedFile',
    ];
  }

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
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
        $max = count($items);
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
      $elements['#file_upload_delta'] = $delta;
      $elements['#type'] = 'details';
      $elements['#open'] = TRUE;
      $elements['#theme'] = 'file_widget_multiple';
      $elements['#theme_wrappers'] = ['details'];
      $elements['#process'] = [[static::class, 'processMultiple']];
      $elements['#title'] = $title;

      $elements['#description'] = $description;
      $elements['#field_name'] = $field_name;
      $elements['#language'] = $items->getLangcode();
      // The field settings include defaults for the field type. However, this
      // widget is a base class for other widgets (e.g., ImageWidget) that may
      // act on field types without these expected settings.
      $field_settings = $this->getFieldSettings() + ['display_field' => NULL];
      $elements['#display_field'] = (bool) $field_settings['display_field'];

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


  /**
   * {@inheritdoc}
   */
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

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $defaults = [
      'fids' => [],
      'display' => (bool) $field_settings['display_default'],
      'description' => '',
    ];

    // Essentially we use the managed_file type, extended with some
    // enhancements.
    $element_info = $this->elementInfo->getInfo('managed_file');


    // $element['#multiple'] = false;
    /* $element['#upload_validators'] = [
      'file_validate_extensions' => ['gpx gepjson'],
    ];
 */
    if (isset($element['#default_value']['fids'])) {
      $file_selected = count($element['#default_value']['fids']) > 0;
    } else {
      $file_selected = false;
    }

    $element['fichmieranage'] = [
      '#type' => 'managed_file',
      '#title' => "Fichier $delta" ,
     '#upload_location' => $items[$delta]->getUploadLocation(),
      '#upload_validators' => $items[$delta]->getUploadValidators(),
      '#value_callback' => [static::class, 'value'],
      '#process' => array_merge($element_info['#process'], [[static::class, 'process']]),
      '#progress_indicator' => $this->getSetting('progress_indicator'),
      // Allows this field to return an array instead of a single value.
      // '#extended' => TRUE,
      '#entity_type' => $items->getEntity()->getEntityTypeId(),
      '#display_field' => (bool) $field_settings['display_field'],
      '#display_default' => $field_settings['display_default'],
      '#description_field' => $field_settings['description_field'],
      '#cardinality' => 1,
      '#multiple' => false,
    ];

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


    if ($file_selected && (! isset($this->geo_properties ))) {
      $props = [];
      $file = File::Load($element['#default_value']['fids'][0]);
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
    if (!$num_names && isset($element['#default_value']['mappings'])) {
      $num_names = count($element['#default_value']['mappings']) ?? 1;
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

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

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
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {

    $element['#pre_render'][] = [static::class, 'managedFile'];

    $element['replace_button'] = $element['upload_button'];
    $element['replace_button']['#value'] = t('Replace');
    $element['replace_button']['#weight'] = -5;
    $element['replace_button']['#empty_option'] = t('Select Replace File');
    unset($element['replace_button']['#attributes']);
    $element['replace_button']['#attributes']['title'] = 'Select a file, then click Replace button';
    $element['replace_button']['#attributes']['class'][] = 'button--extrasmall';
    // $element['replace_button']['#ajax']['event'] = 'fileUpload';

    return $element;
  }

  public static function managedFile($element) {

    if (!isset($element['remove_button']['#access']) || $element['remove_button']['#access'] !== false) {
      $element['upload']['#access'] = true;
      $element['replace_button']['#access'] = true;
    } else {
      // $element['upload']['#access']=false;
      $element['upload']['#description'] = '';
      $element['replace_button']['#access'] = false;
    }
    return $element;
  }

    /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entity;
    $file_uri = $file->getFileUri();

    /** @var \Drupal\file\FileInterface $replacement */
    $replacement = file_save_upload('replacement', $form['replacement']['replacement']['#upload_validators'], FALSE, 0);
    if (!$replacement) {
      $this->messenger()->addError($this->t('The replacement file was not saved'));
      return;
    }

    if (!$this->fileSystem->copy($replacement->getFileUri(), $file_uri, FileSystemInterface::EXISTS_REPLACE)) {
      $this->messenger()->addError($this->t('The file could not be replaced'));
      return;
    }

    // Recalculate file size and change date.
    $return = $file->save();

    $this->messenger()->addStatus($this->t('The file was replaced.'));
    $this->moduleHandler->invokeAll('file_replace', [$file]);

    // Clean up the temporary file.
    $replacement->delete();

    return $return;
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
}
