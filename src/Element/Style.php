<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element\File;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Element\ManagedFile;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a time element.
 *
 * @FormElement("leaflet_style")
 */
class Style extends FormElement {

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


    $element['stroke'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Stroke</em> field'),
      '#default_value' => isset($item['stroke']) ? $item['stroke'] : TRUE,
      '#description' => t('Whether to draw stroke along the path.<br>Set it to false to disable borders on polygons or circles.'),
      '#weight' => 1,
    ];
    $element['color'] = [
      '#type' => 'color',
      '#title' => t('<em>Color</em> field'),
      '#default_value' => isset($item['color']) ? $item['color'] : '#F00FE8',
      '#description' => t('Stroke color.'),
      '#weight' => 2,
    ];
    $element['weight'] = [
      '#type' => 'number',
      '#title' => t('<em>Weight</em> field'),
      '#default_value' => isset($item['weight']) ? $item['weight'] : 2,
      '#description' => t('Stroke width in pixels.'),
      '#min' => 1,
      '#step' => 1,
      '#max' => 20,
      '#weight' => 3,
    ];
    $element['opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Opacity</em> field'),
      '#default_value' => isset($item['opacity']) ? $item['opacity'] : 1,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 4,
    ];
    $element['linecap'] = [
      '#type' => 'select',
      '#title' => t('<em>LineCap</em> field'),
      '#default_value' => isset($item['linecap']) ? $item['linecap'] : 'round',
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
    $element['linejoin'] = [
      '#type' => 'select',
      '#title' => t('<em>LineJoin</em> field'),
      '#default_value' => isset($item['linejoin']) ? $item['linejoin'] : 'round',
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
    $element['dasharray'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashArray</em> field'),
      '#default_value' => isset($item['dasharray']) ? $item['dasharray'] : NULL,
      '#description' => t('A string that defines the stroke <a href="https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-linejoin>dash pattern</a>. Doesn\'t work on Canvas-powered layers in some old browsers.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)(,[0-9]+)*',
      '#weight' => 7,
    ];
    $element['dashoffset'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashOffset</em> field'),
      '#default_value' => isset($item['dashoffset']) ? $item['dashoffset'] : 0,
      '#description' => t('A string that defines the <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/stroke-dashoffset">distance into the dash</a> pattern to start the dash.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)|([0-9]+%)',
      '#weight' => 8,
    ];
    $element['fill'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Fill</em> field'),
      '#default_value' => isset($item['fill']) ? $item['fill'] : FALSE,
      '#description' => t('Whether to fill the path with color. Set it to false to disable filling on polygons or circle'),
      '#weight' => 9,
    ];
    $element['fill_color'] = [
      '#type' => 'color',
      '#title' => t('<em>Fill Color</em> field'),
      '#default_value' => isset($item['fill_color']) ? $item['fill_color'] : '#C7A8A8',
      '#description' => t('Fill Color.'),
      '#weight' => 10,
    ];
    $element['fill_opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Fill Opacity</em> field'),
      '#default_value' => isset($item['fill_opacity']) ? $item['fill_opacity'] : 0.2,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 11,
    ];
    $element['fillrule'] = [
      '#type' => 'select',
      '#title' => t('<em>Fill Rule</em> field'),
      '#default_value' => isset($item['fillrule']) ? $item['fillrule'] : 'evenodd',
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
}
