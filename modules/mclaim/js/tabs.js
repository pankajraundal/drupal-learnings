(function ($) {
  $(document).ready(function () {
    // Activate tabs
    $('.mclaim-tabs .tab-links a').on('click', function (e) {
      var currentAttrValue = $(this).attr('href');

      // Show/Hide Tabs
      $('.mclaim-tabs ' + currentAttrValue).show().siblings().hide();

      // Change/remove current tab to active
      $(this).parent('li').addClass('active').siblings().removeClass('active');

      e.preventDefault();
    });
  });
})(jQuery);
