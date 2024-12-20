<?php
namespace Drupal\geojsonfile_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a custom constraint.
 *
 * @Constraint(
 *   id = "CustomDataValidation",
 *   label = @Translation("Custom Data Validation", context = "Validation"),
 * )
 */
class CustomDataValidation extends Constraint {
  public $message = 'The value for @property is invalid.';
}

