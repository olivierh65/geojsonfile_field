(function ($, Drupal) {
    Drupal.behaviors.manageFileVisibility = {
      attach: function (context, settings) {
        $('input[type="file"]', context).on('change', function () {
          const groupWrapper = $(this).closest('.file-group-wrapper');
          const details1 = groupWrapper.find('.details-1');
          const details2 = groupWrapper.find('.details-2');
  
          if ($(this).val()) {
            details1.show();
            details2.show();
          } else {
            details1.hide();
            details2.hide();
          }
        });
      }
    };
  })(jQuery, Drupal);
  