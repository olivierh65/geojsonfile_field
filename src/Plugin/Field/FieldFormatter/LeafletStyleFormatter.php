<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'leaflet_style_formatter'.
 *
 * @FieldFormatter(
 *   id = "leaflet_style_formatter",
 *   label = @Translation("Leaflet Style Formatter"),
 *   field_types = {
 *     "leaflet_style_field"
 *   }
 * )
 */
class LeafletStyleFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $styles = json_decode($item->styles, TRUE);
      $elements[$delta] = [
        '#markup' => '<pre>' . print_r($styles, TRUE) . '</pre>',
      ];
    }
    return $elements;
  }
}
