<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'custom_field_widget' field widget.
 *
 * @FieldWidget(
 *   id = "custom_field_widget",
 *   label = @Translation("Custom Field Widget"),
 *   field_types = {
 *     "custom_field_type"
 *   }
 * )
 */
class CustomFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta]->value ? json_decode($items[$delta]->value, TRUE) : ['main_text' => '', 'groups' => []];

    $element['value'] = [
      '#type' => 'custom_form_element',
      '#text' => $value['main_text'],
      '#groups' => $value['groups'],
    ];

    return $element;
  }
}
