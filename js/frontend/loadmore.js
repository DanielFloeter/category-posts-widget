/**
 * Category Posts Widget
 * https://github.com/DanielFloeter/category-posts-widget
 *
 * JS for the "load more" functionality.
 *
 * Released under the GPLv2 license or later -  http://www.gnu.org/licenses/gpl-2.0.html
 */

if (typeof jQuery !== 'undefined') {

    var php_settings_var = 'categoryPosts'; // should be identical to namespace.

    jQuery(document).ready(function() {

        // scrollbar
        jQuery('.' + php_settings_var + '-loadmore button').each(function() {
            if (jQuery(this).data('scrollto')) {
                var _ul = jQuery(this.parentElement.parentElement).find('ul');
                _ul.css({
                    height: _ul.prop('scrollHeight'),
                });
            }
        });

        // Handle the click of load more.
        jQuery(document).on('click', '.' + php_settings_var + '-loadmore button', function() {
            var _this = jQuery(this),
                id = _this.data('id'),
                number = _this.data('number'),
                start = _this.data('start'),
                context = _this.data('context'),
                url = tiptoppress[php_settings_var].json_root_url,
                container = jQuery(this).closest('.' + php_settings_var + '-loadmore').parent(),
                _ul = container.find('ul'),
                origText = _this.text(),
                postCount = _this.data('post-count'),
                loadingText = _this.data('loading'),
                loadmoreText = _this.data('placeholder'),
                widgetNumber = container.attr('id') || jQuery(this).closest("[id*='" + id + "']").attr('id'),
                scrollHeight = _ul.prop('scrollHeight'),
                useScrollTo = _this.data('scrollto');

            _this.text(loadingText);

            jQuery.getJSON(url + '/' + id + '/' + start + '/' + number + '/' + context + '/', function(data) {
                jQuery.each(data, function(key, li) {
                    _ul.append(li);
                    _ul.children().last().trigger('catposts.load_more');
                });

                if (postCount < start + number) {
                    _this.hide();
                } else {
                    loadmoreText = loadmoreText.replace('%step%', start + number - 1);
                    loadmoreText = loadmoreText.replace('%all%', postCount);
                    _this.text(loadmoreText);
                    _this.data('start', start + number);
                }
            }).done(function(data) {
                if (useScrollTo) {
                    _ul.stop().animate({
                        scrollTop: scrollHeight,
                    }, 1000, 'swing');
                }

                if (data && data.length && new RegExp('cat-post-thumbnail|cpwp-excerpt-text').test(data[0])) {
                    var widget = jQuery('#' + widgetNumber);
                    var widgetImage = jQuery(widget).find('.cat-post-item img').first();

                    if (typeof cat_posts_namespace !== 'undefined' && cat_posts_namespace.layout_wrap_text && cat_posts_namespace.layout_img_size) {
                        cat_posts_namespace.layout_wrap_text.setClass(widget);
                        if (0 !== parseInt(widgetImage.data('cat-posts-height'), 10) && 0 !== parseInt(widgetImage.data('cat-posts-width'), 10)) {
                            cat_posts_namespace.layout_img_size.setHeight(widget);
                        }
                    }
                }
            }).fail(function() {
                _this.text(origText);
            });
        });
    });
}
