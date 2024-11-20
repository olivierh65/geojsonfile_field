<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
    use Drupal\Core\Field\FieldItemListInterface;
    use Drupal\Core\Field\FormatterBase;
    use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * @FieldFormatter(
 *   id = "geojsonfile_formatter_test",
 *   label = @Translation("Geojson Formatter Test"),
 *   field_types = {
 *     "geojsonfile_field_test"
 *   },
 * )
 */
class GeojsonFileFormatterTest extends FormatterBase {
    
      /**
       * {@inheritdoc}
       */
      public function settingsSummary() {
        $summary = [];
        $summary[] = $this->t('Renders nothing');
        return $summary;
      }
    
      /**
       * {@inheritdoc}
       */
      public function viewElements(FieldItemListInterface $items, $langcode) {
        $element = [];
        return $element;
      }
}
