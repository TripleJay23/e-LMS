/**
 * Modern Blue Theme JS
 */
define(["jquery", "core/modal_factory", "core/modal_events"], function (
  $,
  ModalFactory,
  ModalEvents,
) {
  return {
    init: function () {
      // Smooth scrolling for anchor links
      $('a[href*="#"]')
        .not('[href="#"]')
        .not('[href="#0"]')
        .click(function (event) {
          if (
            location.pathname.replace(/^\//, "") ==
              this.pathname.replace(/^\//, "") &&
            location.hostname == this.hostname
          ) {
            var target = $(this.hash);
            target = target.length
              ? target
              : $("[name=" + this.hash.slice(1) + "]");
            if (target.length) {
              event.preventDefault();
              $("html, body").animate(
                {
                  scrollTop: target.offset().top,
                },
                1000,
                function () {
                  var $target = $(target);
                  $target.focus();
                  if ($target.is(":focus")) {
                    return false;
                  } else {
                    $target.attr("tabindex", "-1");
                    $target.focus();
                  }
                },
              );
            }
          }
        });

      // Example: Add a class to body when user scrolls down (for sticky header effects)
      $(window).scroll(function () {
        var scroll = $(window).scrollTop();
        if (scroll >= 50) {
          $("body").addClass("scrolled");
        } else {
          $("body").removeClass("scrolled");
        }
      });

      console.log("Modern Blue Theme JS Initialized");
    },
  };
});
