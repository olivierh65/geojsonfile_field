<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use \Drupal\file\Entity\File;


/**
 * Plugin implementation of the 'test_geojsonfile_widget'.
 *
 * @FieldWidget(
 *   id = "test_geojsonfile_widget",
 *   label = @Translation("Test GeoJSON File Widget"),
 *   field_types = {
 *     "test_geojsonfile"
 *   }
 * )
 */
class TestGeojsonFileWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta];
    $wrapper_id = "mapping-$delta";

    // Déterminez les valeurs actuelles ou récupérez-les de l'état du formulaire.
    $geojson_mapping = $form_state->get(['geojson_mapping', $delta]);
    if ($geojson_mapping === null) {
      if (isset($value->mapping)) {
        $geojson_mapping = $value->mapping;
        $form_state->set(['geojson_mapping', $delta], $geojson_mapping);
      } else {
        $geojson_mapping = [];
      }
    }
    $geojson_mapping = is_array($geojson_mapping) ? $geojson_mapping : [];

    if ((!$form_state->isRebuilding()) || (!$form_state->isSubmitted()) ||
      null === $form_state->get(['geojson_attributs', $delta])
    ) {
      // Si le fichier est deja definit, recharge les attributs
      if (isset($value->file)) {
        $temp = $value->file;
        $fid = reset($temp);
      } elseif (isset($form_state->getValue($items->getName())[$delta]['file'])) {
        $fid = reset($form_state->getValue($items->getName())[$delta]['file']);
      } else {
        $fid = -1;
      }
      if ($fid > 0) {
        $attribs = static::getGeojsonAttributs($form, $form_state, $fid);
        $form_state->set(['geojson_attributs', $delta], $attribs);
      }
    }


    $element['file'] = [
      '#type' => 'geojson_managed_file',
      '#title' => t('GeoJSON File'),
      '#upload_location' => 'public://geojson/', // Chemin où les fichiers seront stockés
      '#default_value' => isset($value->file) ? $value->file : NULL,
      '#weight' => 10,
      '#multiple' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['geojson jpg pdf'],  // Pour restreindre les extensions de fichier (optionnel)
      ],
      '#widget_class' => static::class, // Ajout d'une référence à la classe.
      '#attributes' => [
        'accept' => '.geojson,.jpeg,.pdf'
      ],
    ];

    $element['style_global'] = [
      '#type' => 'details',
      '#title' => 'Style Global',
      '#weight' => 20,
    ];

    $element['style_global']['style'] = [
      '#type' => 'leaflet_style',
      '#title' => t('Leaflet Style'),
      '#default_value' => $value->style_global['style'] ?? [],
    ];

    $element['mapping'] = [
      '#type' => 'details',
      '#title' => 'Mappings',
      '#prefix' => '<div id="mapping-details-wrapper">',
      '#suffix' => '</div>',
      '#weight' => 30,
    ];

    foreach ($geojson_mapping as $index => $attribut) {

      $trigger = $form_state->getTriggeringElement();
      if (isset($trigger["#attributes"]["class"]) &&
          (in_array("mapping-add-button", $trigger["#attributes"]["class"]))) {
            $element['mapping']['#open']=true;
      }
      else {
        $element['mapping']['#open']=false;
      }
      $attribut_options = $this->getAttributeOptions($index, $delta, $form_state);
      $attribut_default = null;
      $value_default = null;
      $value_options = [];

      if ($trigger !== null) {
        $fieldname = $trigger['#parents'][0];
        if ($form_state->getValue([$fieldname, $delta, 'mapping', $index, 'attribut']) == null) {
          // mapping pas encore cree
          // Recupere les valeurs precedement sauvées
          if (isset(($value->mapping)[$index])) {
            // mapping deja sauvegardé
            if (!empty(($value->mapping)[$index]['attribut'])) {
              $attribut_default = ($value->mapping)[$index]['attribut'];
              $value_default = ($value->mapping)[$index]['value'];
            } else {
              $attribut_default = null;
              $value_default = null;
            }
          }
        } else {
          // mapping deja cree
          $attribut_default = $form_state->getValue([$fieldname, $delta, 'mapping', $index, 'attribut']);
          if (!empty($attribut_default)) {
            $attr_saved = null;
            if (isset(($value->mapping)[$index])) {
              // mapping deja sauvegardé
              if (!empty(($value->mapping)[$index]['attribut'])) {
                $attr_saved = ($value->mapping)[$index]['attribut'];
              }
            }
            if ($attribut_default == $attr_saved) {
              // Attriut selectionné est le meme que celui precedment sauvé
              // alors, selectionne la meme valeur
              $value_default = ($value->mapping)[$index]['value'];
              $attribut_default = $attr_saved;
            } else {
              $value_default = null;
            }
          } else {
            $attribut_default = null;
            $value_default = null;
          }
        }
      } else {
        // Pas de trigger, certainement premier chargement
        if (isset(($value->mapping)[$index])) {
          // mapping deja sauvegardé
          if (!empty(($value->mapping)[$index]['attribut'])) {
            $attribut_default = ($value->mapping)[$index]['attribut'];
            $value_default = ($value->mapping)[$index]['value'];
          } else {
            $attribut_default = null;
            $value_default = null;
          }
        }
      }

      if ($attribut_default == null) {
        // Si pas d'attrubut par defaut, utilise le premier de la liste
        $attribut_default = array_key_first($attribut_options);
      }
      $value_options = $this->getValueOptions($attribut_default, $index, $delta, $form_state);
      if ($value_default == null) {
        $value_default = array_key_first($value_options);
      }


      $element['mapping'][$index] = [
        '#type' => 'details',
        '#title' => t('Mapping @attribut == @value (@index)', [
          '@attribut' => $attribut_default,
          '@value' => $value_default,
          '@index' => $index + 1,
        ]),
        '#name' => 'mapping-' . $index,
        '#weight' => 30 + $index,
        '#theme' => 'geojson_mapping_item', // Thème Twig personnalisé
        '#open' => false,
        '#attributes' => [
          'class' => ['mapping-item'],
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#name' => 'remove_' . $index,
          '#submit' => [[static::class, 'removeMapping']],
          '#ajax' => [
            'callback' => [static::class, 'updateMappingCallback'],
            'wrapper' => 'mapping-details-wrapper',
          ],
          '#attributes' => [
            'class' => ['mapping-remove-button'],
          ],
        ],
        'attribut' => [
          '#type' => 'select',
          '#title' => t('Attribut'),
          '#options' => $attribut_options,
          '#default_value' => $attribut_default ?? null,
          '#ajax' => [
            'callback' => [$this, 'updateValueOptions'],
            'disable-refocus' => FALSE, // Or TRUE to prevent re-focusing on the triggering element.
            'wrapper' => $wrapper_id . '-value-' . $index,
            'event' => 'change',
            'progress' => [
              'type' => 'throbber',
              'message' => t('Verifying entry...'),
            ],
          ],
        ],
        'value' => [
          '#type' => 'select',
          '#title' => t('Value'),
          '#options' => $value_options,
          '#default_value' => $value_default ?? null,
          '#prefix' => '<div id="' . $wrapper_id . '-value-' . $index . '">',
          '#suffix' => '</div>',
        ],
        'label' => [
          '#type' => 'textfield',
          '#title' => t('Label'),
          '#default_value' => $attribut['label'] ?? '',
        ],
        'detail_style' => [
          '#type' => 'details',
          '#open' => false,
          '#title' => t('Style pour @attribut == @value', [
            '@attribut' => $attribut_default,
            '@value' => $value_default,
          ]),
          'style' => [
            '#type' => 'leaflet_style',
            '#title' => t('Leaflet Style'),
            '#default_value' => $attribut['detail_style']['style'] ?? [],
          ],
        ],

      ];
    }

    $element['mapping']['add_more'] = [
      '#type' => 'submit',
      '#value' => t('Add Mapping'),
      '#name' => 'add_more_' . $delta,
      '#submit' => [[static::class, 'addMapping']],
      '#ajax' => [
        'callback' => [static::class, 'updateMappingCallback'],
        'wrapper' => 'mapping-details-wrapper',
      ],
      '#attributes' => [
        'class' => ['mapping-add-button'],
      ],
    ];

    // Définir un thème personnalisé pour cet élément.
    $element['#theme'] = 'geojsonfile_field_widget_theme';

    // Ajouter des variables pour le thème.
    $element['#attached']['library'][] = 'geojsonfile_field/widget_styles';

    return $element;
  }


  /**
   * Get options for the attribute select field.
   */
  protected function getAttributeOptions($index, $delta, $form_state) {
    $options = $form_state->get(['geojson_attributs', $delta]);
    // $options = $form_state->getStorage()['geojson_attributs'][$delta];
    $options_select = [];
    foreach ($options as $key => $val) {
      $options_select[$key] = $key;
    }
    return $options_select;
  }

  /**
   * Get options for the value select field.
   */
  protected function getValueOptions($attribut_default, $index, $delta, $form_state) {
    $options = $form_state->get(['geojson_attributs', $delta]);
    $options_select = [];
    if (isset($options[$attribut_default])) {
      foreach ($options[$attribut_default] as $key => $val) {
        $options_select[$val] = $val;
      }
    }
    return $options_select;
  }


  /**
   * Submit handler for adding a mapping group.
   */
  public static function addMapping(array &$form, FormStateInterface $form_state) {
    // Récupérez le delta (index du champ actuel).
    $trigger = $form_state->getTriggeringElement();
    $delta = $trigger['#parents'][1];

    // Ajoutez un nouvel attribut au conteneur existant.
    $geojson_mapping = $form_state->get(['geojson_mapping', $delta]) ?? [];
    $geojson_mapping[] = ['attribut' => '', 'value' => '', 'label' => '', 'style' => []];
    $form_state->set(['geojson_mapping', $delta], $geojson_mapping);

    // Marquez le formulaire pour reconstruction.
    $form_state->setRebuild();
  }


  /**
   * Submit handler for removing a mapping group.
   */
  public static function removeMapping(array &$form, FormStateInterface $form_state) {
    // Récupérez le delta et l'index à supprimer.
    $trigger = $form_state->getTriggeringElement();
    $delta = $trigger['#parents'][1];
    $index = $trigger['#parents'][3]; // L'index dans le tableau des attributs.

    // Supprimez l'attribut correspondant.
    $geojson_mapping = $form_state->get(['geojson_mapping', $delta]) ?? [];
    unset($geojson_mapping[$index]);
    $form_state->set(['geojson_mapping', $delta], array_values($geojson_mapping)); // Réindexation.

    // Marquez le formulaire pour reconstruction.
    $form_state->setRebuild();
  }

  /**
   * AJAX callback to update the mapping container.
   */
  public static function updateMappingCallback(array &$form, FormStateInterface $form_state) {
    // Utilise #array_parents pour cibler dynamiquement l'élément.
    $field_element = NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, 4));

    if (! empty($field_element)) {
      return $field_element;
    } else {
      return ['#markup' => t('Unable to update the mapping section.')];
    }
  }

  /**
   * AJAX callback to update value options.
   */
  public function updateValueOptions(array &$form, FormStateInterface $form_state) {
    // Récupérer les parents du champ déclencheur.
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, 5);
    $delta = $trigger['#parents'][1];
    $index = $trigger['#parents'][3];

    $values = $this->getValueOptions($trigger['#value'], $index, $delta, $form_state);

    // Ajouter 'value' pour cibler le champ à mettre à jour.
    $parents[] = 'value';

    // Récupérer l'élément dans le formulaire reconstruit.
    $field_element = NestedArray::getValue($form, $parents);
    // et le mettre a jour
    /* $field_element['#options'] = $values;
    $field_element['#default_value'] =  null;
    // $field_element['#value'] =  null;
    $field_element["#ajax_processed"] = false;
    $field_element['#validated'] = true;

    // clear errors
    $form_state->clearErrors(); */

    return $field_element ?? ['#markup' => t('Unable to update the value options.')];
  }


  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {

    $field_name = $this->fieldDefinition->getName();
    $values = $form_state->getValue($field_name);

    $vals = $items->getValues($values);

    foreach ($vals as $delta => $value) {
      // Définir les valeurs extraites dans l'objet $items.
      $items[$delta]->setValue($value);
    }
  }

  public static function GeojsonAttributs($form, FormStateInterface &$form_state) {
    $trigger = $form_state->getTriggeringElement();
    $delta = $trigger['#parents'][1];
    switch (end($trigger['#parents'])) {
      case 'remove_button':
        $form_state->set(['geojson_attributs', $delta], null);
        break;
      case 'upload_button':
        $attribs = static::getGeojsonAttributs($form, $form_state);
        $form_state->set(['geojson_attributs', $delta], $attribs);
    }
  }


  public static function getGeojsonAttributs($form, FormStateInterface &$form_state, $fid = -1) {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger !== null) {
      $delta = $trigger['#parents'][1];
      $values = $form_state->getValue(reset($trigger['#parents']));
      if (isset($values[$delta]['file']) && is_array(($values[$delta]['file']))) {
        if (isset($values[$delta]['file']['fids'])) {
          $fid = reset($values[$delta]['file']['fids']);
        } else {
          $fid = reset($values[$delta]['file']);
        }
      }
    }
    $props = [];
    if ($fid > 0) {
      $file = File::Load($fid);
      $cont = file_get_contents($file->getFileUri());
      foreach (json_decode($cont, true)['features'] as $feature) {
        if ($feature['type'] == "Feature") {
          foreach ($feature['properties'] as $key => $val) {
            $props[$key][] = $val;
          }
        }
      }
    }

    foreach ($props as $key => $value) {
      // remove duplicate entries and also item contening only null
      $ar = array_unique($props[$key]);
      if ((count($ar) == 0) || (count($ar) == 1 && $ar[0] == null)) {
        continue;
      }
      $props_uniq[$key] = $ar;
    }
    return $props_uniq;
  }
}
