<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'test_geojsonfile_formatter'.
 *
 * @FieldFormatter(
 *   id = "test_geojsonfile_formatter",
 *   label = @Translation("Test GeoJSON File Formatter"),
 *   field_types = {
 *     "test_geojsonfile"
 *   }
 * )
 */
class TestGeoJsonFileFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      // $attributes = json_decode($item->attributes, TRUE);
      // $style = json_decode($item->style, TRUE);
      $attributes = $item->attributes;
      $style = $item->style;
      $elements[$delta] = [
        '#theme' => 'test_geojsonfile_formatter',
        '#file' => $item->file,
        '#style' => $style,
        '#attributes' => $attributes,
      ];
    }

    return $elements;
  }
}
