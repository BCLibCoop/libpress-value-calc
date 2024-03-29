; (function ($, window) {

  var self;

  var LibValueCalcAdmin = function (opts) {
    self = this;
    self.init(opts);
  }

  LibValueCalcAdmin.prototype = {

    init: function (opts) {
      $('#lib-value-submit').click(self.submit_form);
      return this;
    },

    submit_form: function () {

      var data = {
        action: 'lib-value-save-change',
        "lib-value-books": $('#lib-value-books').val(),
        "lib-value-magazines": $('#lib-value-magazines').val(),
        "lib-value-dvds": $('#lib-value-dvds').val(),
        "lib-value-games": $('#lib-value-games').val(),
        "lib-value-cds": $('#lib-value-cds').val(),
        "lib-value-ebooks": $('#lib-value-ebooks').val(),
        "lib-value-holds": $('#lib-value-holds').val(),
        "lib-value-computer": $('#lib-value-computer').val(),
        "lib-value-questions": $('#lib-value-questions').val(),
        "lib-value-programs": $('#lib-value-programs').val(),
        "lib-value-property-tax": $('#lib-value-property-tax').val(),
      }

      $.post(ajaxurl, data).complete(function (res) {
        alert(res.feedback);
      });
    }
  }

  $.fn.libvaluecalc = function (opts) {
    return new LibValueCalcAdmin(opts);
  }
}(jQuery, window))

jQuery().ready(function ($) {
  window.libvaluecalc = $().libvaluecalc();
});
