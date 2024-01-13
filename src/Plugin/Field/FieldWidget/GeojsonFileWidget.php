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
class GeojsonFileWidget extends FileWidget implements TrustedCallbackInterface {


  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'managedFile',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // The formElement method returns the form for a single field widget (Used to render the form in the Admin Interface of Drupal)
    // We need to add our new field to this

    // Get the parents form elements
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#multiple'] = false;
    /* $element['#upload_validators'] = [
      'file_validate_extensions' => ['gpx gepjson'],
    ];
 */
    if (isset($element['#default_value']['fids'])) {
      $file_selected = count($element['#default_value']['fids']) > 0;
    } else {
      $file_selected = false;
    }

    $num_names = $form_state->getValue([$element['#field_name'], $delta, 'mapping', '_nb_attribut']);
    if (!$num_names && isset($element['#default_value']['mappings'])) {
      $num_names = unserialize($element['#default_value']['mappings'])['_nb_attribut'] ?? 1;
      $form_state->setValue([$element['#field_name'], $delta, 'mapping', '_nb_attribut'], $num_names);
    } else if (!$num_names) {
      $num_names = 1;
      $form_state->setValue([$element['#field_name'], $delta, 'mapping', '_nb_attribut'], $num_names);
    }

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
      '#open' => false,
      '#prefix' => '<div id="mapping-fieldset-wrapper' . $delta . '">',
      '#suffix' => '</div>',
      // hide until a file is selected
      '#access' => $file_selected ?? false,
      '#weight' => 20,
    ];


    for ($i = 0; $i < $num_names; $i++) {
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
    $element['mapping']['_nb_attribut'] = [
      '#type' => 'value',
      '#description' => 'number of attributs for delta ' . $delta,
      '#value' => $i,
    ];
    // save number of attributs mapping
    $form_state->setValue([$element['#field_name'], $element['#delta'], 'mapping', '_nb_attribut'], $i);

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
        '#value' => $this->t('Remove one'),
        '#submit' => [$class . '::removeCallback'],
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

    $name_field = $form_state->getValue($nb_attribut_array) ?? 1;

    // $name_field = count($field_element['attribut']);
    $add_button = $name_field + 1;
    $form_state->setValue($nb_attribut_array, $add_button);

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
  public static function removeCallback(array &$form, FormStateInterface $form_state) {
    $parent = array_slice($form_state->getTriggeringElement()['#parents'], 0, 3);
    $nb_attribut_array = $parent;
    $nb_attribut_array[] = '_nb_attribut';

    $name_field = $form_state->getValue($nb_attribut_array) ?? 1;

    if ($name_field > 1) {
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


  public static function mappingUnserialize($element, $input, $form_state) {
    $a = $element;
    if ($input) {
      return $input;
    }

    $a = $element;
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
    $a = unserialize($form_state->getValue($data));
    return $a['leaflet_style'];
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



    return parent::process($element, $form_state, $form);
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
}
