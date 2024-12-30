<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'geojsonfile' field type.
 *
 * @FieldType(
 *   id = "geojsonfile",
 *   label = @Translation("GeoJSON File"),
 *   description = @Translation("A custom field with a managed file and test leaflet style."),
 *   default_widget = "geojsonfile_widget",
 *   default_formatter = "geojsonfile_formatter",
 *   list_class = "\Drupal\geojsonfile_field\Plugin\Field\GeojsonfileItemList"
 * )
 */
class Geojsonfile extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'file' => [
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'nom' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'description' => [
          'type' => 'text',
          'size' => 'big',
        ],
        'style' => [
          'type' => 'text',
          'size' => 'big',
          'serialize' => TRUE,
        ],
        'mapping' => [
          'type' => 'text',
          'size' => 'big',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['file'] = DataDefinition::create('integer')
      ->setLabel(t('Geojson File ID'))
      ->setRequired(false);

    $properties['nom'] = DataDefinition::create('string')
      ->setLabel(t('Nom de la trace'))
      ->setDescription(t('Nom de la trace.'));

      $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Description'))
      ->setDescription(t('Description de la trace.'));
      
    $properties['style'] = DataDefinition::create('string')
      ->setLabel(t('Leaflet Style'))
      ->setDescription(t('Stores leaflet style data as JSON.'));

    $properties['mapping'] = DataDefinition::create('string')
      ->setLabel(t('Mapping'))
      ->setDescription(t('Stores mapping data as JSON.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $empty = empty($this->get('file')->getValue());
    return $empty;
  }

  public function getValue() {
    return $this->values;
  }

}
