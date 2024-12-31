<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element\File;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Element\ManagedFile;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a time element.
 *
 * @FormElement("leaflet_style")
 */
class LeafletStyle extends FormElementBase {

  public function getInfo() {

    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processStyle'],
      ],
    ];
  }

  public static function processStyle(&$element, FormStateInterface $form_state, &$complete_form) {

    $input_exists = FALSE;

    $field_element = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);
    if (!$input_exists) {
      return;
    }
    if (isset($field_element)) {
      $item = $field_element;
    } else {
      $item = [];
    }

    // Charger la configuration par défaut.
  $default_config = self::defaultConfiguration();

    $element['stroke'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Stroke</em> field'),
      '#default_value' => isset($item['stroke']) ? $item['stroke'] : $default_config['stroke'],
      '#description' => t('Whether to draw stroke along the path.<br>Set it to false to disable borders on polygons or circles.'),
      '#weight' => 1,
    ];
    $element['color'] = [
      '#type' => 'color',
      '#title' => t('<em>Color</em> field'),
      '#default_value' => isset($item['color']) ? $item['color'] : $default_config['color'],
      '#description' => t('Stroke color.'),
      '#weight' => 2,
    ];
    $element['weight'] = [
      '#type' => 'number',
      '#title' => t('<em>Weight</em> field'),
      '#default_value' => isset($item['weight']) ? $item['weight'] : $default_config['weight'],
      '#description' => t('Stroke width in pixels.'),
      '#min' => 1,
      '#step' => 1,
      '#max' => 20,
      '#weight' => 3,
    ];
    $element['opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Opacity</em> field'),
      '#default_value' => isset($item['opacity']) ? $item['opacity'] : $default_config['opacity'],
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 4,
    ];
    $element['lineCap'] = [
      '#type' => 'select',
      '#title' => t('<em>lineCap</em> field'),
      '#default_value' => isset($item['lineCap']) ? $item['lineCap'] : $default_config['lineCap'],
      '#description' => t('A string that defines shape to be used at the end of the stroke.' .
        '<br>Butt : indicates that the stroke for each subpath does not extend beyond its two endpoints.' .
        '<br>Round : indicates that at the end of each subpath the stroke will be extended by a half circle with a diameter equal to the stroke width.' .
        '<br>Square : indicates that at the end of each subpath the stroke will be extended by a rectangle with a width equal to half the width of the stroke and a height equal to the width of the stroke.'),
      '#options' => [
        'butt' => 'Butt',
        'round' => 'Round',
        'square' => 'Square',
      ],
      '#weight' => 5,
    ];
    $element['lineJoin'] = [
      '#type' => 'select',
      '#title' => t('<em>lineJoin</em> field'),
      '#default_value' => isset($item['lineJoin']) ? $item['lineJoin'] : $default_config['lineJoin'],
      '#description' => t('A string that defines shape to be used at the corners of the stroke.' .
        '<br>Arcs : indicates that an arcs corner is to be used to join path segments.' .
        '<br>Bevel : indicates that a bevelled corner is to be used to join path segments.' .
        '<br>Miter : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Miter-Clip : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Round : indicates that a round corner is to be used to join path segments.'),
      '#options' => [
        'arcs' => 'Arcs',
        'bevel' => 'Bevel',
        'miter' => 'Miter',
        'miter-clip' => 'Miter-Clip',
        'round' => 'Round',
      ],
      '#weight' => 6,
    ];
    $element['dashArray'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashArray</em> field'),
      '#default_value' => isset($item['dashArray']) ? $item['dashArray'] : $default_config['dashArray'],
      '#description' => t('A string that defines the stroke <a href="https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-lineJoin>dash pattern</a>. Doesn\'t work on Canvas-powered layers in some old browsers.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)(,[0-9]+)*',
      '#weight' => 7,
    ];
    $element['dashOffset'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashOffset</em> field'),
      '#default_value' => isset($item['dashOffset']) ? $item['dashOffset'] : $default_config['dashOffset'],
      '#description' => t('A string that defines the <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/stroke-dashOffset">distance into the dash</a> pattern to start the dash.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)|([0-9]+%)',
      '#weight' => 8,
    ];
    $element['fill'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Fill</em> field'),
      '#default_value' => isset($item['fill']) ? $item['fill'] : $default_config['fill'],
      '#description' => t('Whether to fill the path with color. Set it to false to disable filling on polygons or circle'),
      '#weight' => 9,
    ];
    $element['fillColor'] = [
      '#type' => 'color',
      '#title' => t('<em>Fill Color</em> field'),
      '#default_value' => isset($item['fillColor']) ? $item['fillColor'] : $default_config['fillColor'],
      '#description' => t('Fill Color.'),
      '#weight' => 10,
    ];
    $element['fillOpacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Fill Opacity</em> field'),
      '#default_value' => isset($item['fillOpacity']) ? $item['fillOpacity'] : $default_config['fillOpacity'],
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 11,
    ];
    $element['fillRule'] = [
      '#type' => 'select',
      '#title' => t('<em>Fill Rule</em> field'),
      '#default_value' => isset($item['fillRule']) ? $item['fillRule'] : $default_config['fillRule'],
      '#description' => t('A string that defines <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/fill-rule">how the inside of a shape</a> is determined.' .
        '<br>Nonzero : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction, and then examining the places where a segment of the shape crosses the ray' .
        '<br>Evenodd : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction and counting the number of path segments from the given shape that the ray crosses.'),
      '#options' => [
        'nonzero ' => 'Nonzero',
        'evenodd' => 'Evenodd',
      ],
      '#weight' => 12,
    ];

    return $element;
  }

  /**
   * Prétraiter l'élément pour appliquer les valeurs par défaut.
   */
  public static function preRenderCustomElement($element) {
    $a=$element;

    return $element;
  }

   /**
   * Retourne les valeurs par défaut.
   */
  public static function defaultConfiguration() {
    return [
      'stroke' => true,
      'color' => '#F00FE8',
      'weight' => 6,
      'opacity' => 1,
      'lineCap' => 'round',
      'lineJoin' => 'round',
      'dashArray' => NULL,
      'dashOffset' => 0,
      'fill' => false,
      'fillColor' => '#C7A8A8',
      'fillOpacity' => 0.2,
      'fillRule' => 'evenodd',
    ];
  }

}
