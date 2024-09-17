<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\file\Plugin\Field\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\geojsonfile_field\Plugin\Field\FieldType\GeoJsonFile;


/**
 * Plugin implementation of the 'image' formatter.
 *
 * @FieldFormatter(
 *   id = "geojsonfile_formatter",
 *   label = @Translation("Geojson Formatter"),
 *   field_types = {
 *     "geojsonfile_field"
 *   },
 * )
 */
class GeojsonFileFormatter extends FormatterBase {

    public function viewElements(FieldItemListInterface $items, $langcode) {

        $elements = [];
        // Get parent elements
        // $elements = parent::viewElements($items, $langcode);
        // $files = $this->getEntitiesToView($items, $langcode);

        /* foreach ($elements as $delta => $entity) {
            $elements[$delta]['#theme'] = 'geojsonfile';
        } */

        return $elements;

    }
}
