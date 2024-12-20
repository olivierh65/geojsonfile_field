<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'leaflet_style_field' field type.
 *
 * @FieldType(
 *   id = "leaflet_style_field",
 *   label = @Translation("Leaflet Style Field"),
 *   description = @Translation("A field type to manage leaflet styles."),
 *   default_widget = "leaflet_style_widget",
 *   default_formatter = "leaflet_style_formatter"
 * )
 */
class LeafletStyleField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'styles' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['styles'] = DataDefinition::create('string')
      ->setLabel(t('Styles'))
      ->setDescription(t('JSON-encoded data of styles.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $styles = $this->get('styles')->getValue();
    return empty($styles);
  }
}
