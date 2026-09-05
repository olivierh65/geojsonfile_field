(function ($, Drupal, once) {
    Drupal.behaviors.updateManagedFile = {
      attach: function (context, settings) {
        // Sélectionne tous les champs autocomplete une seule fois.
        const autocompleteFields = once('updateManagedFile', '[data-delta]', context);
  
        $(autocompleteFields).on('autocompleteclose', function () {
          const delta = $(this).data('delta'); // Récupère le delta.
          const hiddenInputSelector = `edit-field-aab-${delta}-selection-fichier-upload-fids`;
          const hiddenInput = $('[data-drupal-selector="'+hiddenInputSelector+'"]');
  
          if (hiddenInput.length) {
            const fid = $(this).val(); // Le fid retourné par l'autocomplete.
  
            // Met à jour la valeur du champ caché.
            hiddenInput.val(fid);
  
            // Déclenche un événement 'change' pour informer le widget.
            hiddenInput.trigger('change');
  
            // Rafraîchit les comportements du widget pour ce delta spécifique.
            // const widgetWrapper = hiddenInput.closest(`#selection_fichier-upload-${delta}`);
            const widgetWrapper = $('[name="files[field_aab_' + delta + '_selection_fichier_upload]"]')
            if (widgetWrapper.length) {
              Drupal.attachBehaviors(widgetWrapper[0], settings);
            }
          }
        });
      },
    };
  })(jQuery, Drupal, once);
  