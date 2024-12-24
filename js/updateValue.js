(function($) {
    // Argument passed from InvokeCommand.
    $.fn.updateValue = function(selector, value) {
      console.log('myAjaxCallback is called.');
      // Met à jour la valeur de l'élément HTML
      $(`${selector}`).attr('value', value);
      // et envoi un evenement pour informer du changement de valeur
      $(`${selector}`).trigger("change");
    };
  })(jQuery);