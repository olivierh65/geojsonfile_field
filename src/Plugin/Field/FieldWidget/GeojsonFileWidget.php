<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use \Drupal\file\Entity\File;
use Drupal\Core\Field\FieldStorageDefinitionInterface;


/**
 * Plugin implementation of the 'test_geojsonfile_widget'.
 *
 * @FieldWidget(
 *   id = "geojsonfile_widget",
 *   label = @Translation("GeoJSON File Widget"),
 *   field_types = {
 *     "geojsonfile"
 *   }
 * )
 */
class GeojsonFileWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    // Définir les propriétés du champ.    
    $element['#cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $element['#multiple'] = true;
    $element['#tree'] = true;

    $elements = parent::formMultipleElements($items, $form, $form_state);

    $max_geojson_file = $this->getSetting('max_geojson_file') ?? $this->getSetting('max_geojson_file');

    $elements['#title'] = 'Liste des fichiers (max : ' . $max_geojson_file . ')';
    $elements['#attributes'] = [
      'class' => ['geojsonfiled_liste_fichiers'],
    ];

    if ($elements['#max_delta'] < ($max_geojson_file - 1)) {
      // Nombre maxi de fichier.
      // #max_delta est le nombre de fichiers actuellement affichés - 1
      // (indice de tableau 0-based).
      $elements[0] = $this->formElement($items, 0, $elements[0], $form, $form_state);
    } else {
      // si le nombre de fichiers max est atteint, n'affiche plus le bouton "Add more".
      unset($elements['add_more']);
    }
    // Personnaliser le bouton "Add more".
    $elements['add_more']['#value'] = $this->t('Ajouter un fichier geojson');

    // Parcourir chaque delta et modifier le bouton "Remove".
    foreach ($elements as $key => &$element) {
      if (is_numeric($key) && isset($element['_actions']['delete'])) {
        $element['_actions']['delete']['#value'] = $this->t('Supprimer ce fichier');
      }
    }
    return $elements;
  }


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

    $file_upload_status_id = $values->file_upload_status_id ?? $form_state->getUserInput()[$items->getName()][$delta]['file_upload_status_id'] ?? null;
    if (! $file_upload_status_id) {
      // if(!$form_state->get(['file_status', $delta])) {
      $uuid = \Drupal::service('uuid')->generate();
      $form_state->set(['file_status', $uuid], 0);
    } else {
      $uuid = $file_upload_status_id;
    }


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
        $form_state->set(['file_status', $uuid], 0);
      }
      // Récupérer les attributs du fichier GeoJSON.
      if ($fid > 0) {
        $attribs = static::getGeojsonAttributs($form, $form_state, $fid);
        $form_state->set(['geojson_attributs', $delta], $attribs);
        $form_state->set(['file_status', $uuid], 1);
      }
    }

    $element['#title'] = 'Fichier N° ' . $delta + 1;

    $element['file'] = [
      '#type' => 'geojson_managed_file',
      '#title' => t(isset($value->nom) ? 'Fichier "' . $value->nom . '"' : 'GeoJSON File ' . $delta),
      '#upload_location' => 'public://geojson/', // Chemin où les fichiers seront stockés
      '#default_value' => isset($value->file) ? $value->file : NULL,
      '#weight' => 10,
      '#multiple' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['geojson'],  // Pour restreindre les extensions de fichier (optionnel)
      ],
      '#widget_class' => static::class, // Ajout d'une référence à la classe.
      '#accept' => '.geojson',
      '#attributes' => [
        'class' => ['file-upload-' . $delta],
        'data-delta' => $delta,
      ],
    ];
    $element['infos'] = [
      '#type' => 'details',
      '#title' => t('Informations'),
      '#weight' => 12,
    ];

    $element['infos']['nom'] = [
      '#type' => 'textfield',
      '#title' => t('Nom de la trace'),
      '#default_value' => $value->nom ?? '',
      '#weight' => 15,
    ];

    $element['infos']['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $value->description ?? '',
      '#weight' => 16,
    ];

    // Champ caché pour identifier les données meme apres les re-indexations.
    $element['file_upload_status_id'] = [
      '#type' => 'hidden',
      '#value' => $uuid,
      '#attributes' => [
        'class' => ['file-upload-status-id-' . $delta],
        'id' => 'file-upload-status-id-' . $uuid,
      ],
    ];

    // Champ caché pour stocker l'état du fichier.
    $element['file_upload_status'] = [
      '#type' => 'hidden',
      '#default_value' => $form_state->get(['file_status', $uuid]) ?? 0,
      '#value' => $form_state->get(['file_status', $uuid]) ?? 0,
      '#attributes' => [
        'class' => ['file-upload-status-' . $delta],
        'id' => 'file-upload-status-id',
      ],
    ];

    $element['style_global'] = [
      '#type' => 'details',
      '#title' => 'Style Global',
      '#prefix' => '<div id="mapping-style-wrapper-' . $delta . '">',
      '#suffix' => '</div>',
      '#weight' => 20,
      '#states' => [
        'visible' => [
          // Montre cet élément lorsque le champ managed_file contient un fichier.
          '[name="' . $items->getName() . '[' . $delta . '][file_upload_status]"]' => ['value' => '1'],
        ],
      ],
      // '#access' => $file_selected > 0 ? true : false,
    ];


    $element['style_global']['style'] = [
      '#type' => 'leaflet_style',
      '#title' => t('Leaflet Style'),
      '#default_value' => $value->style_global['style'] ?? $this->getSetting('leaflet_style') ?? [],
    ];

    $element['mapping'] = [
      '#type' => 'details',
      '#title' => 'Mappings',
      '#prefix' => '<div id="mapping-details-wrapper-' . $delta . '">',
      '#suffix' => '</div>',
      '#weight' => 30,
      '#states' => [
        'visible' => [
          '[name="' . $items->getName() . '[' . $delta . '][file_upload_status]"]' => ['value' => '1'],
        ],
      ],
    ];

    foreach ($geojson_mapping as $index => $attribut) {

      $trigger = $form_state->getTriggeringElement();
      if (
        isset($trigger["#attributes"]["class"]) &&
        (in_array("mapping-add-button", $trigger["#attributes"]["class"]))
      ) {
        $element['mapping']['#open'] = true;
      } else {
        $element['mapping']['#open'] = false;
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
        '#name' => 'mapping-' . $delta . '-' . $index,
        '#weight' => 30 + $index,
        '#theme' => 'geojson_mapping_item', // Thème Twig personnalisé
        '#open' => false,
        '#attributes' => [
          'class' => ['mapping-item'],
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#name' => 'remove_' . $delta . '-' . $index,
          '#submit' => [[$this, 'removeMapping']],
          '#ajax' => [
            'callback' => [static::class, 'updateMappingCallback'],
            'wrapper' => 'mapping-details-wrapper-' . $delta,
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
      '#submit' => [[$this, 'addMapping']],
      '#ajax' => [
        'callback' => [static::class, 'updateMappingCallback'],
        'wrapper' => 'mapping-details-wrapper-' . $delta,
      ],
      '#attributes' => [
        'class' => ['mapping-add-button'],
      ],
    ];

    // Définir un thème personnalisé pour cet élément.
    $element['#theme'] = 'geojsonfile_field_widget_theme';

    // Ajouter des variables pour le thème.
    $element['#attached']['library'][] = 'geojsonfile_field/update_value';
    return $element;
  }

  public function ___validateMyField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $form_state->getValue($element['#parents']);
    $form_state->setValue($element['#parents']['#value'], $form_state->getValue([$element['#parents'][0], $element['#parents'][1], 'file_upload_status']));
    // $element['#value']=$form_state->getValue([$element['#parents'][0], $element['#parents'][1], 'file_upload_status']);
    if ($value < 0) {
      $form_state->setError($element, $this->t('Value must be positive.'));
    }
  }

  public function updateStyleGlobalCallback(array &$form, FormStateInterface $form_state) {
    // Logique pour mettre à jour le champ 'style_global'
    return $form['style_global'];
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
  public function addMapping(array &$form, FormStateInterface $form_state) {
    // Ne pas utiliser une fi=onction static
    // $delta dans set et get ne semble pas etre utilisé

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
  public function removeMapping(array &$form, FormStateInterface $form_state) {
    // Ne pas utiliser une fi=onction static
    // $delta dans set et get ne semble pas etre utilisé

    // Récupérez le delta et l'index à supprimer.
    $trigger = $form_state->getTriggeringElement();
    $delta = $trigger['#parents'][1];
    $index = $trigger['#parents'][3]; // L'index dans le tableau des attributs.

    // Supprimez l'attribut correspondant.
    $geojson_mapping = $form_state->get(['geojson_mapping', $delta]) ?? [];
    unset($geojson_mapping[$index]);

    //Supprime l'element de form_state
    $fields = $form_state->getValue(array_slice($trigger['#parents'], 0, 3));
    unset($fields[$index]);
    $form_state->setValue(array_slice($trigger['#parents'], 0, 3), $fields);
    // TODO - faut-il re-indexer ???
    $form_state->set(['geojson_mapping', $delta], /* array_values */ ($geojson_mapping)); // Réindexation.

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

    $vals = $items->getGeojsonfieldValues($values);

    foreach ($vals as $delta => $value) {
      // Définir les valeurs extraites dans l'objet $items.
      $items[$delta]->setValue($value);
      if (isset($value['file']) && $value['file'] > 0) {
        $form_state->set(['file_status', $delta], 1);
      } else {
        $form_state->set(['file_status', $delta], 0);
      }
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

  /**
   * Ajax refresh callback for the "Remove" button.
   *
   * This returns the new widget element content to replace
   * the previous content made obsolete by the form submission.
   *
   * @param array $form
   *   The form array to remove elements from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  /**
   * {@inheritDoc}
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   */
  public static function deleteAjax(array &$form, FormStateInterface $form_state) {

    // !!!!! Reindex les elements !!!!
    $form = parent::deleteAjax($form, $form_state);
    $button = $form_state->getTriggeringElement();

    // Met a jour file_status en supprimant cette entrée
    $file_status = $form_state->get('file_status');
    unset($file_status[$button['#parents'][1]]);
    $form_state->set('file_status', $file_status);

    return $form;
  }

  public static function deleteSubmit(&$form, FormStateInterface $form_state) {

    // avant la suppression et reindexation, met a jour le tableau file_status
    $button = $form_state->getTriggeringElement();
    $delta = (int) $button['#delta'];
    $field_name = $button['#parents'][0];
    $id = ($form_state->getUserInput())[$field_name][$delta]['file_upload_status_id'] ?? null;
    $file_status = $form_state->get('file_status');
    unset($file_status[$id]);
    $form_state->set('file_status', $file_status);

    parent::deleteSubmit($form, $form_state);
  }


  //////////////////////////////////////////////////////////////
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_geojson_file' => '5',
      'leaflet_style' => [
        '#input' => TRUE,
       '#process' => [
          ['Drupal\Core\Render\Element\FormElement', 'processGroup'],
        ],
        '#pre_render' => [
          ['Drupal\geojsonfile_field\Element\LeafletStyle', 'preRenderCustomElement'],
        ],
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form['max_geojson_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max Geojson File'),
      '#default_value' => $this->getSetting('max_geojson_file'),
      '#description' => $this->t('Nombre maxi de fichiers Geojson.'),
    ];

    $form['style'] = [
      '#type' => 'details',
      '#title' => $this->t('Style Leaflet'),
      '#open' => false,
    ];

    $form['style']['leaflet_style'] = [
      '#type' => 'leaflet_style',
      '#title' => $this->t('Leaflet Style'),
      '#default_value' => $this->getSetting('leaflet_style'),
      '#description' => $this->t('Style de rendu Leaflet.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $default_style= \Drupal\geojsonfile_field\Element\LeafletStyle::defaultConfiguration();

    $summary = [];

    $summary[] = $this->t('Max Geojson File: @max_geojson_file', ['@max_geojson_file' => $this->getSetting('max_geojson_file')]);
    $summary[] = $this->t('Largeur trace: @weight', ['@weight' => $this->getSetting('leaflet_style')['weight'] ?? $default_style['weight']]);
    $summary[] = $this->t('Couleur trace: @color', ['@color' => $this->getSetting('leaflet_style')['color'] ?? $default_style['color']]);

    return $summary;
  }
}
