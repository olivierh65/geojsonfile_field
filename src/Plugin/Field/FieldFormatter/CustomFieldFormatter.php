<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Defines the 'custom_field_formatter' field formatter.
 *
 * @FieldFormatter(
 *   id = "custom_field_formatter",
 *   label = @Translation("Custom Field Formatter"),
 *   field_types = {
 *     "custom_field_type"
 *   }
 * )
 */
class CustomFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $value = json_decode($item->value, TRUE);
      $elements[$delta] = [
        '#theme' => 'item_list',
        '#items' => [
          'Main Text' => $value['main_text'],
          'Groups' => $value['groups'],
        ],
      ];
    }
    return $elements;
  }
}
