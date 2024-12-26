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
     * Overrides the uploadAjaxCallback to update additional form elements.
     */
    public static function uploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
        // Call the parent method to handle file upload logic.
        $response = parent::uploadAjaxCallback($form, $form_state, $request);
        
        $trigger= $form_state->getTriggeringElement();
        $delta = $trigger['#parents'][1];
        $field_name = $trigger['#parents'][0];

        // Check if the file field has a value.
        $fids = $form_state->getValue([$field_name, $delta, 'file', 'fids']) ?? null;
        $id = $form_state->getValue([$field_name, $delta, 'file_upload_status_id']) ?? null;
        if (isset($fids) && is_array($fids) && count($fids) > 0) {
            // Set the file status to 1.
            $file_status = 1;
            // Set the file status in the form state.
            
            // TODO : il faut choisir un autre index que les deltas
            //  car la suppression reindexe les deltas.
            $form_state->set(['file_status', $id], 1);
        } else {
            $file_status = 0;
            $form_state->set(['file_status', $id], 0);
        }

        // Add an AJAX command to update the file status.
        $response->addCommand(new InvokeCommand(NULL, 'updateValue', ['.file-upload-status-' . $delta, $file_status]));

        return $response;
    }

}
