<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'leaflet_style_widget'.
 *
 * @FieldWidget(
 *   id = "leaflet_style_widget",
 *   label = @Translation("Leaflet Style Widget"),
 *   field_types = {
 *     "leaflet_style_field"
 *   }
 * )
 */
class LeafletStyleWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $wrapper_id = 'leaflet-style-wrapper-' . $delta;

    // Decode JSON-stored styles or initialize an empty array.
    $styles = isset($items[$delta]->styles) ? json_decode($items[$delta]->styles, TRUE) : [];
    if (empty($styles)) {
      $styles = [[]]; // Initialize with one style group if empty.
    }

    $element['styles_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    foreach ($styles as $index => $style) {
      $element['styles_wrapper'][$index] = [
        '#type' => 'fieldset',
        '#title' => t('Style Group @index', ['@index' => $index + 1]),
      ];

      $element['styles_wrapper'][$index]['leaflet_style'] = [
        '#type' => 'leaflet_style_field',
        '#title' => t('Leaflet Style'),
        '#default_value' => $style,
      ];

      $element['styles_wrapper'][$index]['remove'] = [
        '#type' => 'submit',
        '#name' => "remove_style_{$index}",
        '#value' => t('Remove'),
        '#submit' => [[static::class, 'removeStyleSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'updateStylesCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    $element['add_more'] = [
      '#type' => 'submit',
      '#value' => t('Add More'),
      '#submit' => [[static::class, 'addStyleSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'updateStylesCallback'],
        'wrapper' => $wrapper_id,
      ],
    ];

    return $element;
  }

  /**
   * Massage form values to save them as JSON.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      if (isset($item['styles_wrapper'])) {
        $styles = [];
        foreach ($item['styles_wrapper'] as $style) {
          if (is_array($style)) {
            $styles[] = $style['leaflet_style'];
          }
        }
        $item['styles'] = json_encode($styles);
      }
    }
    return $values;
  }

  /**
   * AJAX callback to update styles.
   */
  public static function updateStylesCallback(array &$form, FormStateInterface $form_state) {
    return $form['styles_wrapper'];
  }

  /**
   * Submit handler to add a new style group.
   */
  public static function addStyleSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#parents'], 0, -1);
    $state = $form_state->getValue($parents) ?? [];
    $state[] = [];
    $form_state->setValue($parents, $state);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to remove a style group.
   */
  public static function removeStyleSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name'];
    if (preg_match('/remove_style_(\d+)/', $name, $matches)) {
      $index = $matches[1];
      $parents = array_slice($trigger['#parents'], 0, -2);
      $state = $form_state->getValue($parents) ?? [];
      unset($state[$index]);
      $form_state->setValue($parents, array_values($state));
      $form_state->setRebuild();
    }
  }
}
