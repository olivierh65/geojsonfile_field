<?php

namespace Drupal\geojsonfile_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CustomDataValidationValidator extends ConstraintValidator {
  public function validate($value, Constraint $constraint) {
    // Exemple : Vérifier et corriger les données.
    if (is_array($value__)) {
      // Si nécessaire, corriger les données.
      $this->context->setNode($value); // Met à jour la valeur.
    }
    $a = $this->context->getValue();
    $b = $this->context->getRoot();
  }
}
