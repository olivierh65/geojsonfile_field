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

        /* $class_name = $form['#widget_class'];
        $method_name = 'getGeojsonAttributs';
        if (class_exists($class_name) && method_exists($class_name, $method_name)) {
            // $result = $class_name::$method_name(); // Appel direct.
            // ou
            if (isset($form['#value']['fids'])) {
                // si un fichier est deja chargé, recupere le fid
                $fid = reset($form['#value']['fids']) == false ? -1 : reset($form['#value']['fids']);
            } else {
                $fid = -1;
            }
            $delta = $form_state->getTriggeringElement()['#parents'][1];
            $result = call_user_func([$class_name, $method_name], $form, $form_state, $fid); // Appel dynamique
            $storage=$form_state->getStorage();
            $storage['geojson_attributs'] = [$delta => $result];
            $form_state->setStorage($storage);
        } */
        return $response;
    }
}
