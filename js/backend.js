jQuery(document).ready(function ($) {
    (function () {
        if ($.fn.select2) {
            $("select[data-chosen='1']").select2({
                width: "100%"
            });
        }
    })();
});