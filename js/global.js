window.AFPG = window.AFPG || {};

jQuery(document).ready(function ($) {
    setInterval(function () {
        $.ajax({
            type: "GET",
            dataType: "JSON",
            url: AFPG.ajax_url,
            cache: true,
            data: {
                action: "auto_fetch_post",
                do_action: "fetch_random_posts"
            }
        });
    }, (parseInt(AFPG.fetch_posts_interval)) * 1000);
});

(function ($) {

})(jQuery);