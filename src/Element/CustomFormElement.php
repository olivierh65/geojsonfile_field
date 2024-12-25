<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides a custom form element with a text field and a group of multiple fields.
 *
 * @FormElement("custom_form_element")
 */
class CustomFormElement extends FormElement {


  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#single_text' => '',
      '#groups' => [],
      '#default_value' => [],
      '#process' => [[static::class, 'processGroupedTextElement']],
      '#theme' => 'grouped_text_element',
    ];
  }

  /**
   * Process callback for the grouped text element.
   */
  public static function processGroupedTextElement(&$element, FormStateInterface $form_state, &$form) {
    $element['single_text'] = [
      '#type' => 'textfield',
      '#title' => t('Single Text'),
      '#default_value' => $element['#single_text'] ?? '',
    ];

    $wrapper_id = 'grouped-text-element-wrapper';
    $element['groups_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
    ];

    $groups = $form_state->get('grouped_text_element_groups') ?? $element['#groups'];

    if (empty($element['#groups'])) {
      $groups[] = ['text1' => '', 'text2' => '', 'text3' => ''];
    }

    foreach ($groups as $index => $group) {
      $element['groups_wrapper'][$index] = [
        '#type' => 'container',
      ];

      $element['groups_wrapper'][$index]['text1'] = [
        '#type' => 'textfield',
        '#title' => t('Group Text 1'),
        '#default_value' => $group['text1'] ?? '',
      ];
      $element['groups_wrapper'][$index]['text2'] = [
        '#type' => 'textfield',
        '#title' => t('Group Text 2'),
        '#default_value' => $group['text2'] ?? '',
      ];
      $element['groups_wrapper'][$index]['text3'] = [
        '#type' => 'textfield',
        '#title' => t('Group Text 3'),
        '#default_value' => $group['text3'] ?? '',
      ];

      $element['groups_wrapper'][$index]['remove'] = [
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#name' => "remove_group_$index",
        '#submit' => [[static::class, 'removeGroupSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'updateGroupsCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    $element['add_more'] = [
      '#type' => 'submit',
      '#value' => t('Add More'),
      '#name' => 'add_more_group',
      '#submit' => [[static::class, 'addMoreGroupSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'updateGroupsCallback'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $form_state->set('grouped_text_element_groups', $groups);

    return $element;
  }

  /**
   * AJAX callback to update the groups.
   */
  public static function updateGroupsCallback(array &$form, FormStateInterface $form_state) {
    return $form['groups_wrapper'];
  }

  /**
   * Submit handler to add a new group.
   */
  public static function addMoreGroupSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $groups = $form_state->get('grouped_text_element_groups') ?? [];
    $groups[] = ['text1' => '', 'text2' => '', 'text3' => ''];
    $form_state->set('grouped_text_element_groups', $groups);
    $form_state->setRebuild();
  }

  /**
   * Submit handler to remove a group.
   */
  public static function removeGroupSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name'];
    if (preg_match('/remove_group_(\d+)/', $name, $matches)) {
      $index = $matches[1];
      $groups = $form_state->get('grouped_text_element_groups') ?? [];
      unset($groups[$index]);
      $form_state->set('grouped_text_element_groups', array_values($groups));
    }
    $form_state->setRebuild();
  }
}
