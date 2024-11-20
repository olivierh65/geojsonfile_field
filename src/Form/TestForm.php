<?php

namespace Drupal\geojsonfile_field\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class TestForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'geojsonfile_test_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $form += [
            'select_widget' => [
                '#type' => 'select',
                '#title' => $this->t('Dummy select'),
                '#options' => ['pow' => 'Pow!', 'bam' => 'Bam!'],
                '#required' => TRUE,
                '#ajax' => [
                    'callback' => static::class . '::dummyAjaxCallback',
                    'effect' => 'fade',
                ],
            ],
            // Create a list of radio boxes that will toggle the  textbox
            // below if 'other' is selected.
            'colour_select' => [
                '#type' => 'radios',
                '#title' => $this->t('Pick a colour'),
                '#options' => [
                    'blue' => $this->t('Blue'),
                    'white' => $this->t('White'),
                    'black' => $this->t('Black'),
                    'other' => $this->t('Other'),
                ],
                // We cannot give id attribute to radio buttons as it will break their functionality, making them inaccessible.
                /* '#attributes' => [
          // Define a static id so we can easier select it.
          'id' => 'field_colour_select',
        ],*/
            ],

            // This textfield will only be shown when the option 'Other'
            // is selected from the radios above.
            'custom_colour' => [
                '#type' => 'textfield',
                '#size' => '60',
                '#placeholder' => 'Enter favourite colour',
                '#attributes' => [
                    'id' => 'custom-colour',
                ],
                '#states' => [
                    // Show this textfield only if the radio 'other' is selected above.
                    'visible' => [
                        // Don't mistake :input for the type of field or for a css selector --
                        // it's a jQuery selector. 
                        // You can always use :input or any other jQuery selector here, no matter 
                        // whether your source is a select, radio or checkbox element.
                        // in case of radio buttons we can select them by thier name instead of id.
                        ':input[name="colour_select"]' => ['value' => 'other'],
                    ],
                ],
            ],
            'track' => [
                '#title' => 'Data ',
                '#type' => 'details',
                '#open' => false,
                '#weight' => 10,
                'fichier' => [
                    '#type' => 'managed_file',
                    '#title' => "Fichier",
                    '#upload_validators' =>  [
                        'file_validate_extensions' => ['geojson', 'gpx', 'pdf'],
                    ],
                    '#multiple' => FALSE,
                    '#ajax' => [
                        'callback' => [static::class, 'dummyAjaxCallback'],
                        'event' => 'change click submit',
                    ],
                ],
                'description' => [
                    '#type' => 'textfield',
                    '#title' => t('Description'),
                    '#placeholder' => 'Description de la trace',
                    // '#disabled' => !$file_selected ?? true,
                    // '#access' => $file_selected ?? false,
                    '#attributes' => [
                        'id' => 'data_description_',
                    ],
                    '#ajax' => [
                        'callback' => static::class . '::dummyAjaxCallback',
                        'effect' => 'fade',
                        'event' => 'keyup',
                        'wrapper' => 'mapping-fieldset-wrapper',
                    ],
                    // '#access' => null !== $form_state->getValue(['field_data', $delta, 'data', 'fichier']) ? true : false,
                ],
            ],
            'style' => [
                '#title' => 'Global style',
                '#type' => 'details',
                '#open' => false,
                // hide until a file is selected
                // '#access' => true, // $file_selected ?? false,
                '#weight' => 19,
                'leaflet_style' => [
                    '#title' => 'Test leaflet_style',
                    '#type' => 'leaflet_style',
                    '#weight' => 1,
                    // '#value_callback' => [$this, 'styleUnserialize'],
                    // '#disabled' => !$file_selected ?? true,
                ],
            ],
            'mapping' => [
                '#title' => 'Mapping style',
                '#type' => 'details',
                '#open' => true,
                '#prefix' => '<div id="mapping-fieldset-wrapper">',
                '#suffix' => '</div>',
                // hide until a file is selected
                // '#access' => $file_selected ?? false,
                // '#disabled' => !$file_selected ?? true,
                '#states' => [
                    'visible' => [
                        [
                            '[id="data_description"]' => ['value' => 'matin'],
                        ],
                    ],
                ],
                '#weight' => 20,
                'attribut' => [
                    '#title' => 'Attribute ',
                    '#type' => 'details',
                    '#open' => false,
                    '#weight' => 20,
                    '_nb_attribut' => [
                        '#type' => 'value',
                        '#description' => 'number of attributs for delta ',
                        '#value' => 0,
                    ],
                    'attributes' => [
                        '#title' => 'Style Mapping',
                        '#type' => 'leaflet_style_mapping',
                        '#cardinality' => 3,
                        '#multiple' => true,
                        '#description' => 'Mapping :',
                        '#value_callback' => [$this, 'mappingUnserialize'],
                        '#cardinality' => -1,
                        '#tree' => true,
                    ],
                ],
            ],
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (strlen($form_state->getValue('student_rollno')) < 8) {
            $form_state->setErrorByName('student_rollno', $this->t('Please enter a valid Enrollment Number'));
        }
        if (strlen($form_state->getValue('student_phone')) < 10) {
            $form_state->setErrorByName('student_phone', $this->t('Please enter a valid Contact Number'));
        }
    }


    public function submitForm(array &$form, FormStateInterface $form_state) {
        \Drupal::messenger()->addMessage(t("Student Registration Done!! Registered Values are:"));
        foreach ($form_state->getValues() as $key => $value) {
            \Drupal::messenger()->addMessage($key . ': ' . $value);
        }
    }
}
