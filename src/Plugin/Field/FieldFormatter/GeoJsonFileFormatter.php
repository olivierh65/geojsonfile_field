<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use \Drupal\file\Entity\File;


/**
 * Plugin implementation of the 'test_geojsonfile_formatter'.
 *
 * @FieldFormatter(
 *   id = "geojsonfile_formatter",
 *   label = @Translation("GeoJSON File Formatter"),
 *   field_types = {
 *     "geojsonfile"
 *   }
 * )
 */
class GeoJsonFileFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $attributes = $item->attributes;
      $style = $item->style;
      $features = '';
      $name = '';
      $filename = '';
      $filesize = '';
      $fileChanged = '';
      if (isset($item->file) && is_array($item->file) && count($item->file) > 0) {
        $fid =$item->file[0];
      } 
      else { 
        $fid=0;
      }
      if ($fid > 0) {
        $file = File::Load($fid);
        $cont = file_get_contents($file->getFileUri());
        $feature = json_decode($cont, true)['features'][0];
        $features = implode(',', array_keys($feature['properties']));

        $name = json_decode($cont, true)['name'];
        $filename = $file->getFilename();
        $filesize = $file->getSize();
        $fileChanged = $file->getChangedTime();
      }
      $elements[$delta] = [
        // '#theme' => 'test_geojsonfile_formatter',
        '#file' => $item->file,
        '#style' => $style,
        '#attributes' => $attributes,
        'name' => [
          '#type' => 'markup',
          '#markup' => '<hr><strong>Name: </strong>' . $name,
        ],
        'filename' => [
          '#type' => 'markup',
          '#markup' => '<br><strong>Filename: </strong>' . $filename,
        ],
        'filesize' => [
          '#type' => 'markup',
          '#markup' => '<br><strong>Filesize: </strong>' . $filesize,
        ],
        'fileChanged' => [
          '#type' => 'markup',
          '#markup' => '<br><strong>File Changed: </strong>' . date('d/m/Y H:i:s', $fileChanged),
        ],
        'features' => [
          '#type' => 'markup',
          '#markup' => '<br><strong>Features: </strong>' . $features,
        ],
      ];
    }

    return $elements;
  }
}
