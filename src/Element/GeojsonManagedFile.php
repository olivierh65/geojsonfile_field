<?php

namespace Drupal\geojsonfile_field\Element;

use Drupal\file\Element\ManagedFile;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Geojson Managed File Element.
 *
 * @FormElement("geojson_managed_file")
 */
class GeojsonManagedFile extends ManagedFile {

    /**
     * {@inheritdoc}
     */
    public static function uploadAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
        $response = parent::uploadAjaxCallback($form, $form_state, $request);
        // $response->addCommand(new AlertCommand('Hello!!!'));

        // Retourner le formulaire complet.

        return $response;
    }

}
