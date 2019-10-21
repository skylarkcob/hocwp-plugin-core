window.AFP = window.AFP || {};

if (window.addEventListener) {
    addEvent = function (ob, type, fn) {
        ob.addEventListener(type, fn, false);
    };
} else if (document.attachEvent) {
    addEvent = function (ob, type, fn) {
        var eProp = type + fn;
        ob["e" + eProp] = fn;

        ob[eProp] = function () {
            ob["e" + eProp](window.event);
        };

        ob.attachEvent("on" + type, ob[eProp]);
    };
}

var timer;

timerReset = function (timer) {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(function () {
        location.reload(true);
    }, (parseInt(AFP.reload_interval) * 1000));
};

addEvent(window, "mousedown", timerReset);
addEvent(window, "mousemove", timerReset);
addEvent(window, "keydown", timerReset);

(function ($) {
    var currentPostInput = $("input[name='current_post_id']"),
        postUpdating = false;

    // Fix ads and empty paragraph
    (function () {
        var ads = $(".entry-content .mobile.layerads"),
            emptyP = $(".entry-content p");

        emptyP.each(function () {
            var element = $(this),
                html = $.trim(element.html());

            if ("" === html || " " === html || "&nbsp;" === html) {
                element.addClass("empty-paragraph");
            }
        });

        var emptyParagraph = $(".entry-content .empty-paragraph"),
            postContent = null;

        if (ads && ads.length) {
            postContent = ads.closest(".entry-content");
            ads.remove();
        }

        if (emptyParagraph && emptyParagraph.length) {
            postContent = emptyParagraph.closest(".entry-content");
            emptyParagraph.remove();
        }

        if (postContent && postContent.length) {
            postUpdating = true;

            $.ajax({
                type: "POST",
                dataType: "JSON",
                url: AFP.ajax_url,
                cache: true,
                data: {
                    action: "auto_fetch_post",
                    do_action: "update_post_content",
                    post_id: currentPostInput.val(),
                    post_content: postContent.html()
                },
                complete: function () {
                    postUpdating = false;
                }
            });
        }
    })();

    // Fix custom HTML before entry content.
    (function () {
        $(".entry-content p .custom-before-content").each(function () {
            var element = $(this),
                parent = element.parent();

            if ("P" == parent.prop("tagName")) {
                parent.addClass("custom-before-content");
            }
        });

        var articleDetail = $(".entry-content .fetched-content div.article-detail-hd"),
            customContent = $(".entry-content > .custom-before-content"),
            moved = false;

        if (articleDetail && articleDetail.length) {
            if (customContent && customContent.length) {
                articleDetail.prepend(customContent.detach());
                moved = true;
            }
        }

        var lbContainer = $(".entry-content .bbWrapper > div:first-child > .lbContainer");

        if (lbContainer && lbContainer.length) {
            if (customContent && customContent.length) {
                lbContainer.parent().next().prepend(customContent.detach());
                moved = true;
            }
        }

        if (!moved) {
            var firstStrong = $(".entry-content > .custom-before-content + div.fetched-content > strong:first-child");

            if (firstStrong && firstStrong.length) {
                if (customContent && customContent.length) {
                    firstStrong.prepend(customContent.detach());
                    moved = true;
                }
            }
        }
    })();

    // Update all link from post content related with source domain
    (function () {
        if (currentPostInput && currentPostInput.length && false) {
            var domain = currentPostInput.data("source-domain");

            if ($.trim(domain)) {
                var links = $(".entry-content a[href*='" + domain + "']"),
                    urls = [];

                if (links && links.length) {
                    postUpdating = true;

                    $.each(links, function (index, element) {
                        urls[index] = $(element).attr("href");
                    });

                    $.ajax({
                        type: "POST",
                        dataType: "JSON",
                        url: AFP.ajax_url,
                        cache: true,
                        data: {
                            action: "auto_fetch_post",
                            do_action: "update_post_content_links",
                            post_id: currentPostInput.val(),
                            links: urls,
                            domain: domain
                        },
                        complete: function () {
                            postUpdating = false;
                        }
                    });
                } else {
                    links = $(".entry-content a[href*='" + "p=" + "']");

                    if (links && links.length) {
                        postUpdating = true;
                        console.log(links.length);

                        $.each(links, function (index, element) {
                            urls[index] = $(element).attr("href");
                        });

                        $.ajax({
                            type: "POST",
                            dataType: "JSON",
                            url: AFP.ajax_url,
                            cache: true,
                            data: {
                                action: "auto_fetch_post",
                                do_action: "update_post_content_links_pending",
                                post_id: currentPostInput.val(),
                                links: urls,
                                domain: domain
                            },
                            complete: function () {
                                postUpdating = false;
                            }
                        });
                    }
                }
            }
        }
    })();
})(jQuery);

jQuery(document).ready(function ($) {
    (function () {
        $(".VCSortableInPreviewMode").each(function () {
            var element = $(this),
                video = element.attr("data-vid");

            if ($.trim(video) && -1 != video.indexOf(".mp4")) {
                var videoPlayer = document.createElement("video");

                if (videoPlayer.canPlayType("video/mp4")) {
                    if (-1 == video.indexOf("http")) {
                        video = "http://" + video;
                    }

                    videoPlayer.setAttribute("src", video);
                }

                videoPlayer.setAttribute("width", "100%");
                videoPlayer.setAttribute("height", "280");
                videoPlayer.setAttribute("controls", "controls");
                element.prepend(videoPlayer);
            }
        });
    })();
});