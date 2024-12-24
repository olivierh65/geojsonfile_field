<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\file\Element\ManagedFile;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormHelper;



/**
 * Geojson Managed File Element.
 *
 * @FormElement("geojson_managed_file")
 */
class GeojsonManagedFile extends ManagedFile {

    /**
     * {@inheritdoc}
     */
    public function ___getInfo() {
        $info = parent::getInfo();

        $info['#element_validate'] = [
            [$this, 'geojsonValidateManagedFile'],
        ];
        $info['#process'] = [
            [$this, 'geojsonprocessManagedFile'],
        ];

        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function geojsonUploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
        $response = ManagedFile::uploadAjaxCallback($form, $form_state, $request);
        // $response->addCommand(new AlertCommand('Hello!!!'));

        // Retourner le formulaire complet.

        return $response;
    }

    public function geojsonValidateManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
        ManagedFile::validateManagedFile($element, $form_state, $complete_form);

        $frm_base = array_slice($element['#parents'], 0, 2);
        if (count($element['#value']["fids"]) > 0) {
            $form_state->setValue(array_merge($frm_base, ['style_global', '#access']), true);
        } else {
            $form_state->setValue(array_merge($frm_base, ['style_global', '#access']), false);
        }
    }

    public function geojsonProcessManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
        $element = ManagedFile::processManagedFile($element, $form_state, $complete_form);

        $element['upload_button']['#ajax']['callback'] = [$this, 'geojsonUploadAjaxCallback'];

        return $element;
    }

    /* public static function processManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {
        $element = parent::processManagedFile($element, $form_state, $complete_form);

        // Ajouter la mise à jour du champ caché après le téléchargement du fichier.
        $fid = $element['#value']['fids'] ?? [];
        if (!empty($fid)) {
            // Trouver le champ caché correspondant.
            // field_aab[0][file_upload_status]
            $delta = $element['#attributes']['data-delta'] ?? null;
            if ($delta !== null) {
                // Mettre à jour la valeur dans $form_state.
                $hidden_field_name = ['field_aab',$delta,'file_upload_status'];
                $form_state->setValue($hidden_field_name, '1');

                // Mettre à jour également dans $element pour qu'il soit rendu avec la nouvelle valeur.
                if (isset($complete_form['field_aab']['widget'][$delta]['file_upload_status'])) {
                    $complete_form['field_aab']['widget'][$delta]['file_upload_status']['#value'] = '1';
                }
            }
        }

        return $element;
    } */

    /**
     * Overrides the uploadAjaxCallback to update additional form elements.
     */
    public static function uploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
        // Call the parent method to handle file upload logic.
        $response = parent::uploadAjaxCallback($form, $form_state, $request);
        
        $trigger= $form_state->getTriggeringElement();
        $delta = $trigger['#parents'][1];
        $field_name = $trigger['#parents'][0];

        $fids = $form_state->getValue([$field_name, $delta, 'file', 'fids']) ?? null;
        if (isset($fids) && is_array($fids) && count($fids) > 0) {
            $file_status = 1;
        } else {
            $file_status = 0;
        }

        $response->addCommand(new InvokeCommand(NULL, 'updateValue', ['#file-upload-status-id', $file_status]));

        return $response;
    }

}
