<?php

namespace Drupal\geojsonfile_field\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;

class FileAutocompleteController extends ControllerBase {

    /**
     * Handle autocomplete requests.
     */
    public function handleAutocomplete(Request $request) {
        $results = [];
        $query = $request->query->get('q');

        if ($query) {
            $query_service = \Drupal::entityQuery('file');
            $query_service->condition('filename', '%' . $query . '%', 'LIKE');
            $file_ids = $query_service->accessCheck(FALSE)->execute();

            if (!empty($file_ids)) {
                $files = \Drupal::entityTypeManager()
                    ->getStorage('file')
                    ->loadMultiple($file_ids);

                foreach ($files as $file) {
                    /** @var \Drupal\file\Entity\File $file */
                    $results[] = [
                        'value' => $file->id(),
                        'label' => $file->getFilename(),
                    ];
                }
            }
        }

        return new JsonResponse($results);
    }
}
