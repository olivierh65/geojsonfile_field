<?php

namespace Drupal\geojsonfile_field\Plugin\Field\FieldType;


use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\DataType\FieldItem;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Render\Element\File;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Component\Utility\Environment;

/**
 * Plugin implementation of the 'Geojson' field type.
 *
 * @FieldType(
 *   id = "geojsonfile_field",
 *   label = @Translation("Geojson File"),
 *   description = @Translation("This field stores Geojson file and style."),
 *   category = @Translation("Custom"),
 *   default_widget = "geojsonfile_widget",
 *   default_formatter = "geojsonfile_formatter",
 * )
 */
//default_formatter = "leaflet_edit_formatter",
class GeoJsonFile extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the file entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'description' => [
          'description' => 'A description of the file.',
          'type' => 'text',
        ],
        'stroke' => [
          'description' => 'Whether to draw stroke along the path.',
          'type' => 'int',
          'size' => 'tiny',
        ],
        'color' => [
          'description' => 'Stroke color.',
          'type' => 'varchar',
          'length' => 12,
        ],
        'weight' => [
          'description' => 'Stroke width in pixels.',
          'type' => 'int',
          'size' => 'tiny',
        ],
        'opacity' => [
          'description' => 'Stroke opacity.',
          'type' => 'float',
        ],
        'linecap' => [
          'description' => 'Linecap.',
          'type' => 'varchar',
          'length' => 12,
        ],
        'linejoin' => [
          'description' => 'Linejoin.',
          'type' => 'varchar',
          'length' => 12,
        ],
        'dasharray' => [
          'description' => 'Dasharray.',
          'type' => 'varchar',
          'length' => 64,
        ],
        'dashoffset' => [
          'description' => 'Dashoffset.',
          'type' => 'int',
          'size' => 'tiny',
        ],
        'fill' => [
          'description' => 'fill.',
          'type' => 'int',
          'size' => 'tiny',
        ],
        'fill_color' => [
          'description' => 'fill_color.',
          'type' => 'varchar',
          'length' => 12,
        ],
        'fill_opacity' => [
          'description' => 'Stroke opacity.',
          'type' => 'float',
        ],
        'fillrule' => [
          'description' => 'fillrule.',
          'type' => 'varchar',
          'length' => 64,
        ]
      ]
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['target_id'] = DataDefinition::create('integer')->setLabel('target_id');
    $properties['description'] = DataDefinition::create('string')->setLabel('description');
    $properties['stroke'] = DataDefinition::create('boolean')->setLabel('stroke');
    $properties['color'] = DataDefinition::create('string')->setLabel('color');
    $properties['color'] = DataDefinition::create('integer')->setLabel('color');
    $properties['opacity'] = DataDefinition::create('float')->setLabel('opacity');
    $properties['linecap'] = DataDefinition::create('string')->setLabel('linecap');
    $properties['linejoin'] = DataDefinition::create('string')->setLabel('linejoin');
    $properties['dasharray'] = DataDefinition::create('string')->setLabel('dasharray');
    $properties['dashoffset'] = DataDefinition::create('integer')->setLabel('dashoffset');
    $properties['fill'] = DataDefinition::create('integer')->setLabel('fill');
    $properties['fill_color'] = DataDefinition::create('string')->setLabel('fill_color');
    $properties['fill_opacity'] = DataDefinition::create('float')->setLabel('fill_opacity');
    $properties['fillrule'] = DataDefinition::create('string')->setLabel('fillrule');

    return $properties;
  }


  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    $settings = $this->getSettings();
    $element = [
      '#tree' => true,
    ];

    $element['track'] = [
      '#title' => 'Track file',
      '#type' => 'details',
      '#open' => true,
      '#weight' => 10,
      '#tree' => true,
    ];

    $element['track']['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['track']['file_directory'] ?? '',
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[FileItem::class, 'validateDirectory']],
      '#weight' => 3,
    ];

    // Make the extension list a little more human-friendly by comma-separation.
    $extensions = str_replace(' ', ', ', $settings['track']['file_extensions']);
    $element['track']['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $extensions,
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
      '#element_validate' => [[FileItem::class, 'validateExtensions']],
      '#weight' => 1,
      '#maxlength' => 256,
      // By making this field required, we prevent a potential security issue
      // that would allow files of any type to be uploaded.
      '#required' => TRUE,
    ];

    $element['track']['max_filesize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $settings['track']['max_filesize'],
      '#description' => $this->t('Enter a value like "512" (bytes), "80 KB" (kilobytes) or "50 MB" (megabytes) in order to restrict the allowed file size. If left empty the file sizes could be limited only by PHP\'s maximum post and file upload sizes (current limit <strong>%limit</strong>).', [
        '%limit' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
      ]),
      '#size' => 10,
      '#element_validate' => [[FileItem::class, 'validateMaxFilesize']],
      '#weight' => 5,
    ];

    $element['style'] = [
      '#title' => 'Leaflet style',
      '#type' => 'details',
      '#open' => true,
      '#weight' => 20,
      '#tree' => true,
    ];

    $element['style']['stroke'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Stroke</em> field'),
      '#default_value' => isset($settings['style']['stroke']) ? $settings['style']['stroke'] : TRUE,
      '#description' => t('Whether to draw stroke along the path.<br>Set it to false to disable borders on polygons or circles.'),
      '#weight' => 1,
    ];
    $element['style']['color'] = [
      '#type' => 'color',
      '#title' => t('<em>Color</em> field'),
      '#default_value' => isset($settings['style']['color']) ? $settings['style']['color'] : '#F00FE8',
      '#description' => t('Stroke color.'),
      '#weight' => 2,
    ];
    $element['style']['weight'] = [
      '#type' => 'number',
      '#title' => t('<em>Weight</em> field'),
      '#default_value' => isset($settings['style']['weight']) ? $settings['style']['weight'] : 6,
      '#description' => t('Stroke width in pixels.'),
      '#min' => 1,
      '#step' => 1,
      '#max' => 20,
      '#weight' => 3,
    ];
    $element['style']['opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Opacity</em> field'),
      '#default_value' => isset($settings['style']['opacity']) ? $settings['style']['opacity'] : 1,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 4,
    ];
    $element['style']['linecap'] = [
      '#type' => 'select',
      '#title' => t('<em>LineCap</em> field'),
      '#default_value' => isset($settings['style']['linecap']) ? $settings['style']['linecap'] : 'round',
      '#description' => t('A string that defines shape to be used at the end of the stroke.' .
        '<br>Butt : indicates that the stroke for each subpath does not extend beyond its two endpoints.' .
        '<br>Round : indicates that at the end of each subpath the stroke will be extended by a half circle with a diameter equal to the stroke width.' .
        '<br>Square : indicates that at the end of each subpath the stroke will be extended by a rectangle with a width equal to half the width of the stroke and a height equal to the width of the stroke.'),
      '#options' => [
        'butt' => 'Butt',
        'round' => 'Round',
        'square' => 'Square',
      ],
      '#weight' => 5,
    ];
    $element['style']['linejoin'] = [
      '#type' => 'select',
      '#title' => t('<em>LineJoin</em> field'),
      '#default_value' => isset($settings['style']['linejoin']) ? $settings['style']['linejoin'] : 'round',
      '#description' => t('A string that defines shape to be used at the corners of the stroke.' .
        '<br>Arcs : indicates that an arcs corner is to be used to join path segments.' .
        '<br>Bevel : indicates that a bevelled corner is to be used to join path segments.' .
        '<br>Miter : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Miter-Clip : indicates that a sharp corner is to be used to join path segments.' .
        '<br>Round : indicates that a round corner is to be used to join path segments.'),
      '#options' => [
        'arcs' => 'Arcs',
        'bevel' => 'Bevel',
        'miter' => 'Miter',
        'miter-clip' => 'Miter-Clip',
        'round' => 'Round',
      ],
      '#weight' => 6,
    ];
    $element['style']['dasharray'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashArray</em> field'),
      '#default_value' => isset($settings['style']['dasharray']) ? $settings['style']['dasharray'] : NULL,
      '#description' => t('A string that defines the stroke <a href="https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/stroke-linejoin>dash pattern</a>. Doesn\'t work on Canvas-powered layers in some old browsers.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)(,[0-9]+)*',
      '#weight' => 7,
    ];
    $element['style']['dashoffset'] = [
      '#type' => 'textfield',
      '#title' => t('<em>dashOffset</em> field'),
      '#default_value' => isset($settings['style']['dashoffset']) ? $settings['style']['dashoffset'] : 0,
      '#description' => t('A string that defines the <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/stroke-dashoffset">distance into the dash</a> pattern to start the dash.'),
      '#maxlength' => 64,
      '#pattern' => '([0-9]+)|([0-9]+%)',
      '#weight' => 8,
    ];
    $element['style']['fill'] = [
      '#type' => 'checkbox',
      '#title' => t('<em>Fill</em> field'),
      '#default_value' => isset($settings['style']['fill']) ? $settings['style']['fill'] : FALSE,
      '#description' => t('Whether to fill the path with color. Set it to false to disable filling on polygons or circle'),
      '#weight' => 9,
    ];
    $element['style']['fill_color'] = [
      '#type' => 'color',
      '#title' => t('<em>Fill Color</em> field'),
      '#default_value' => isset($settings['style']['fill_color']) ? $settings['style']['fill_color'] : '#C7A8A8',
      '#description' => t('Fill Color.'),
      '#weight' => 10,
    ];
    $element['style']['fill_opacity'] = [
      '#type' => 'range',
      '#title' => t('<em>Fill Opacity</em> field'),
      '#default_value' => isset($settings['style']['fill_opacity']) ? $settings['style']['fill_opacity'] : 0.2,
      '#description' => t('Stroke opacity.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#weight' => 11,
    ];
    $element['style']['fillrule'] = [
      '#type' => 'select',
      '#title' => t('<em>Fill Rule</em> field'),
      '#default_value' => isset($settings['style']['fillrule']) ? $settings['style']['fillrule'] : 'evenodd',
      '#description' => t('A string that defines <a href="https://developer.mozilla.org/docs/Web/SVG/Attribute/fill-rule">how the inside of a shape</a> is determined.' .
        '<br>Nonzero : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction, and then examining the places where a segment of the shape crosses the ray' .
        '<br>Evenodd : determines the "insideness" of a point in the shape by drawing a ray from that point to infinity in any direction and counting the number of path segments from the given shape that the ray crosses.'),
      '#options' => [
        'nonzero ' => 'Nonzero',
        'evenodd' => 'Evenodd',
      ],
      '#weight' => 12,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {

    // Get the parent field settings
    $settings = [
      'track' => [
        'progress_indicator' => 'throbber',
        'file_directory' => "[date:custom:Y]-[date:custom:m]",
        'max_filesize' => '10MB',
        'file_extensions' => 'geojson',
      ],
      'style' => [
        'stroke' => true,
        'color' => '#F00FE8',
        'weight' => 5,
        'opacity' => 1,
        'linecap' => 'round',
        'linejoin' => 'round',
        'dasharray' => null,
        'dashoffset' => 0,
        'fill' => false,
        'fill_color' => '#C7A8A8',
        'fill_opacity' => 0.2,
        'fillrule' => 'evenodd',
      ],
    ];
    return $settings;
  }

}
