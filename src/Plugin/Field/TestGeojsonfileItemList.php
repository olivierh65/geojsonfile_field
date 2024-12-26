<?php

namespace Drupal\geojsonfile_field\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use \Drupal\file\Entity\File;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;

class TestGeojsonfileItemList extends FieldItemList {

    use TypedDataAwareValidatorTrait;

    /**
     * Appelé avant la sauvegarde des éléments du champ.
     */
    public function preSave() {
        \Drupal::logger('geojsonfile_field')->notice('preSave() appelé!');

        foreach ($this->list as $item) {
            if (
                isset($item->file) &&
                !is_array($item->file)
            ) {
                // Le champ file doit être un ID de fichier valide.
                $file = File::load($item->file);
                if ($file) {
                    if ($file->isTemporary()) {
                        // Déplacer le fichier vers permanent
                        $file->setPermanent();
                        $file->save();
                    }
                    $item->file = $file ? $file->id() : NULL;
                }
            }

            // Sérialisation des données JSON.
            $item->style = is_array($item->style) ? json_encode($item->style) : $item->style;
            $item->mapping = is_array($item->mapping) ? json_encode($item->mapping) : $item->mapping;
        }
        parent::preSave(); // Appelez la méthode parent pour effectuer la sauvegarde
    }

/**
     * Appelé après la sauvegarde des éléments du champ.
     */
    public function setValue($values, $notify = TRUE) {
        foreach ($values as $index => &$value) {
            foreach ($value as $property_name => $val) {
                switch ($property_name) {
                    case 'file':
                        $value[$property_name] = !empty($value[$property_name]) ? [(int) $value[$property_name]] : [];
                        // Retourne un tableau contenant l'ID du fichier pour le widget `managed_file`.
                        break;
                        case'nom':
                            case 'description':
                                // Récupérer les valeurs de champ `nom` et `description`.
                                // Ces champs sont des champs de texte simples, donc pas besoin de traitement
                                break;

                    case 'style':
                        if (!empty($value[$property_name]) && is_string($value[$property_name])) {
                            $decoded = json_decode($value[$property_name], TRUE);
                            $value['style_global'][$property_name] = is_array($decoded) ? $decoded : [];
                            unset($value['style']);
                        }
                        break;
                    case 'mapping':
                        if (!empty($value[$property_name]) && is_string($value[$property_name])) {
                            $decoded = json_decode($value[$property_name], TRUE);
                            $value[$property_name] = is_array($decoded) ? $decoded : [];
                        }
                        break;
                }
            }
        }
        parent::setValue($values); // Appeler la méthode parente pour gérer la valeur
    }

    /**
     * Met en forme les valeurs saisies dans le widget
     */
    public function getGeojsonfieldValues($values) {
        // $values = parent::getValue();

        $vals = [];
        foreach ($values as $delta => $value) {
            if (! is_array($value)) {
                // ne garde que les tableaux
                continue;
            }
            // Récupérer l'ID du fichier.
            $file_id = !empty($value['file']) && is_array($value['file']) ? reset($value['file']) : NULL;

            // Extraire le style depuis le conteneur `style_global`.
            $style = isset($value['style_global']['style']) ? $value['style_global']['style'] : NULL;

            // Extraire les données de mapping (tableau de tableaux).
            $mapping = [];
            if (!empty($value['mapping']) && is_array($value['mapping'])) {
                foreach ($value['mapping'] as $index => $mapping_entry) {
                    if (is_int($index)) {
                        // ignore le niveau intermediaire 
                        $mapping[$index] = $mapping_entry;
                    }
                }
            }
            $vals[$delta] = [
                'file' => $file_id ? (int) $file_id : NULL,
                'nom' => !empty($value['infos']['nom']) ? $value['infos']['nom'] : NULL,
                'description' => !empty($value['infos']['description']) ? $value['infos']['description'] : NULL,
                'style' => !empty($style) ? json_encode($style) : NULL,
                'mapping' => !empty($mapping) ? json_encode($mapping) : NULL,
            ];
        }

        return $vals;
    }

    public function __get($property_name) {
        switch ($property_name) {
            case 'file':
                // Retourne un tableau contenant l'ID du fichier pour le widget `managed_file`.
                return !empty($this->values['file']) ? [(int) $this->values['file']] : [];

            case 'nom':
            case 'description':
                return $this->values[$property_name];
                break;

            case 'style':
            case 'mapping':
                if (!empty($this->values[$property_name]) && is_string($this->values[$property_name])) {
                    $decoded = json_decode($this->values[$property_name], TRUE);
                    return is_array($decoded) ? $decoded : [];
                }
                return [];

            default:
                return parent::__get($property_name);
        }
    }
}
