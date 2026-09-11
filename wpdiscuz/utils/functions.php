<?php

if (!defined("ABSPATH")) {
    exit();
}

function wpDiscuz() {
    return WpdiscuzCore::getInstance();
}

function wpdiscuzGetOptions() {
    $wpDiscuz   = wpDiscuz();
    $optionsObj = $wpDiscuz->getOptions();

    return $optionsObj->getOptions();
}

function wpDiscuzGetOption($key, $tab = null) {
    $wpDiscuz   = wpDiscuz();
    $optionsObj = $wpDiscuz->getOptions();

    return $optionsObj->getOption($key, $tab);
}

function wpDiscuzHelperOptimization() {
    return wpDiscuz()->helperOptimization;
}

/**
 * Prints the standalone Article Rating.
 *
 * Template tag for themes and page builder templates that need the Article
 * Rating somewhere the "Display Ratings" option doesn't offer:
 *
 *     <?php wpdiscuz_post_rating(); ?>          the post being displayed
 *     <?php wpdiscuz_post_rating(1); ?>         post 1, wherever this runs
 *
 * It prints the very same rating the built-in positions print, so uncheck the
 * positions that are not needed anymore to avoid printing it twice. "Before
 * Comment Form" is checked by default.
 *
 * Visitors can rate any post whose rating is displayed, each rating goes to its
 * own post, but only where wpDiscuz is loaded, because the script that sends
 * the rating comes with it. Elsewhere the rating is displayed read-only, and
 * only when the "Display ratings on non-singular pages" option is enabled,
 * which is what puts the rating stylesheet on those pages.
 *
 * Nothing is printed when the post's form has Article Rating disabled, or when
 * the post is a WooCommerce product, which keeps WooCommerce's own rating.
 *
 * Every rating carries the `wpd-post-rating` class, which is what custom CSS
 * for a rating this tag prints should target. One of them is meant to carry
 * the `wpd-post-rating` id, which one element on a page can have, and the
 * rating schema: they describe the page, so they go to the page's own rating,
 * the one displayed at the first checked position of "Display Ratings", in
 * the order the positions appear on the page, or to this tag and the
 * shortcode when no position is checked.
 *
 * Two placements the settings cannot choose between, and $isPageRating
 * chooses for them:
 *
 *     the page's own rating printed here more than once with no position
 *     checked, where each of them carries the id and the schema unless one is
 *     given true and the others false;
 *
 *     a checked position whose place on the page is never printed, a page
 *     builder template that doesn't run the_content for instance, where
 *     nothing carries them until a placement here is given true.
 *
 * @param int       $postId       The post to print the rating of, 0 for the
 *                                post being displayed.
 * @param bool      $canRate      Pass false to print the rating read-only,
 *                                without the rate stars.
 * @param null|bool $isPageRating Pass true to give this rating the id and the
 *                                schema, false to keep them away from it. The
 *                                default, null, leaves the choice to the
 *                                settings. Only the rating of the post the
 *                                page is on can take them, another post's
 *                                rating never describes the page it is
 *                                displayed on.
 */
function wpdiscuz_post_rating($postId = 0, $canRate = true, $isPageRating = null) {
    echo wpdiscuz_get_post_rating($postId, $canRate, $isPageRating);
}

/**
 * Returns the standalone Article Rating HTML instead of printing it.
 *
 *     <?php echo wpdiscuz_get_post_rating(); ?>
 *
 * @param int       $postId       See wpdiscuz_post_rating().
 * @param bool      $canRate      See wpdiscuz_post_rating().
 * @param null|bool $isPageRating See wpdiscuz_post_rating().
 *
 * @return string Empty string when there is nothing to display.
 */
function wpdiscuz_get_post_rating($postId = 0, $canRate = true, $isPageRating = null) {
    $wpDiscuz = wpDiscuz();
    if (empty($wpDiscuz->wpdiscuzForm)) {
        return "";
    }
    $postId = (int)$postId;
    $post   = get_post($postId ? $postId : null);
    if (empty($post->ID)) {
        return "";
    }
    // The post the rating belongs to decides which form applies, whether it was
    // named or came from the loop: in a secondary loop it is not the post the
    // page is on, and it can use a form of its own.
    $form = $wpDiscuz->wpdiscuzForm->getPostForm($post->ID);
    if (!$form) {
        return "";
    }

    return $form->getPostRatingHtml((bool)$canRate, $post, "", is_null($isPageRating) ? null : (bool)$isPageRating);
}
