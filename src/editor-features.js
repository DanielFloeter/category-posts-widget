/**
 * Whether the block sidebar has the "Content" tab (WordPress 7.0, Gutenberg
 * 22.4). Set by editor_has_content_tab() in cat-posts-block.php before this
 * script loads.
 */
export const hasContentTab = !! window.cpwpBlockEditor?.hasContentTab;
