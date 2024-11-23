jQuery(document).ready(function($) {

    // Toggle sections
    $(document).on("click", ".exi-toggle", function (e) {
        e.preventDefault();
        e.stopPropagation();
    
        const $toggle = $(this);
        const $content = $toggle.next(".exi-content");
    
        // Prevent multiple clicks during animation
        if ($content.is(":animated")) {
          return false;
        }
    
        // Close all other sections first
        $(".exi-toggle").not($toggle).removeClass("exi-open");
        $(".exi-content").not($content).slideUp(300);
    
        // Toggle current section
        $toggle.toggleClass("exi-open");
        $content.slideToggle(300);
    
        return false;
      });

});