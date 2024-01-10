<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldType;


use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Geojson' field type.
 *
 * @FieldType(
 *   id = "geojsonfile_field",
 *   label = @Translation("Geojson File"),
 *   description = @Translation("This field stores Geojson file and style."),
 *   category = @Translation("Reference"),
 *   default_widget = "geojsonfile_widget",
 *   default_formatter = "geojsonfile_formatter",
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}},
 * )
 */
class GeoJsonFile extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();

    $settings['stroke'] = TRUE;
    $settings['color'] = '#F00FE8';
    $settings['weight'] = 2;
    $settings['opacity'] =  1;
    $settings['linecap'] = 'round';
    $settings['linejoin'] = 'round';
    $settings['dasharray'] = NULL;
    $settings['dashoffset'] =  0;
    $settings['fill'] = FALSE;
    $settings['fill_color'] = '#C7A8A8';
    $settings['fill_opacity'] =  0.2;
    $settings['fillrule'] = 'evenodd';
    return $settings;
    /* $def=[
    'color' => '#CC00AA',
    'fill_color' => '#BB00AA',
  ] + parent::defaultStorageSettings();
  return $def; */
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    // Set a default value for the field setting we are going to add to toggle whether the description field shows
    // These are used to determine what field settings are saved

    // Get the parent field settings
    $settings = parent::defaultFieldSettings();

    // Add our setting to show/hide the description field
    $settings['description_field'] = TRUE;

    $settings['file_extensions'] = 'geojson';
    $settings['multiple'] = true;

    $settings['stroke'] = TRUE;
    $settings['color'] = '#F00FE8';
    $settings['weight'] = 2;
    $settings['opacity'] =  1;
    $settings['linecap'] = 'round';
    $settings['linejoin'] = 'round';
    $settings['dasharray'] = NULL;
    $settings['dashoffset'] =  0;
    $settings['fill'] = FALSE;
    $settings['fill_color'] = '#C7A8A8';
    $settings['fill_opacity'] =  0.2;
    $settings['fillrule'] = 'evenodd';


    // unset($settings['description_field']);
    return $settings;
  }

  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element=parent::storageSettingsForm($form, $form_state, $has_data);
    // Hide display
    $element['display_field']['#access']=false;
    $element['display_default']['#access']=false;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['stroke'] = DataDefinition::create('boolean')->setLabel('stroke');
    $properties['color'] = DataDefinition::create('string')->setLabel('color');
    $properties['weight'] = DataDefinition::create('integer')->setLabel('weight');
    $properties['opacity'] = DataDefinition::create('float')->setLabel('opacity');
    $properties['linecap'] = DataDefinition::create('string')->setLabel('linecap');
    $properties['linejoin'] = DataDefinition::create('string')->setLabel('linejoin');
    $properties['dasharray'] = DataDefinition::create('string')->setLabel('dasharray');
    $properties['dashoffset'] = DataDefinition::create('string')->setLabel('dashoffset');
    $properties['fill'] = DataDefinition::create('boolean')->setLabel('fill');
    $properties['fill_color'] = DataDefinition::create('string')->setLabel('fill_color');
    $properties['fill_opacity'] = DataDefinition::create('float')->setLabel('fill_opacity');
    $properties['fillrule'] = DataDefinition::create('string')->setLabel('fillrule');
    $properties['mappings'] = DataDefinition::create('string')->setLabel('mapping');
    $properties['styles'] = DataDefinition::create('string')->setLabel('style');
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the file entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'description' => [
          'description' => 'A description of the file.',
          'type' => 'text',
        ],
        'stroke' => [
          'description' => 'stroke',
          'type' => 'int',
          'default' => 1,
          'size' => 'tiny',
        ],
        'color' => [
          'description' => 'color',
          'type' => 'varchar',
          'length' => 8,
        ],
        'weight' => [
          'description' => 'weight.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'opacity' => [
          'description' => 'opacity.',
          'type' => 'numeric',
        ],
        'linecap' => [
          'description' => 'linecap',
          'type' => 'varchar',
          'length' => 8,
          'not null' => FALSE,
        ],
        'linejoin' => [
          'description' => 'linejoin',
          'type' => 'varchar',
          'length' => 12,
        ],
        'dasharray' => [
          'description' => 'dasharray',
          'type' => 'varchar',
          'length' => 64,
        ],
        'dashoffset' => [
          'description' => 'dashoffset',
          'type' => 'varchar',
          'length' => 64,
        ],
        'fill' => [
          'description' => 'fill',
          'type' => 'int',
          'default' => 0,
          'size' => 'tiny',
        ],
        'fill_color' => [
          'description' => 'fill_color',
          'type' => 'varchar',
          'length' => 8,
          'default' => '#C7A8A8'
        ],
        'fill_opacity' => [
          'description' => 'fill_opacity.',
          'type' => 'numeric',
          'default' => 0.2,
        ],
        'fillrule' => [
          'description' => 'fillrule',
          'type' => 'varchar',
          'length' => 8,
          'default' => 'evenodd'
        ],
        'mappings' => [
          'type' => 'blob',
          'size' => 'big', // tiny | small | normal | medium | big
          'description' => 'store styls maps.',
          'serialize' => FALSE,
        ],
        'styles' => [
          'type' => 'blob',
          'size' => 'big', // tiny | small | normal | medium | big
          'description' => 'store styls maps.',
          'serialize' => FALSE,
        ],
      ],
    ] + parent::schema($field_definition);
    return $schema;
  }


/*    public function preSave() {
    return parent::preSave();

    foreach ($this->values['style']['leaflet_style'] as $key => $value) {
      $this->values[$key] = $value;
    }

    $this->values['mapping'] = serialize($this->values['mapping']['attribut']);
    return parent::preSave();
  }  */


}
