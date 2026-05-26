<?php
declare(strict_types=1);

/**
 * Shared CMS fields for new/edit post forms.
 * Expects: $post (array), $all_posts (array), optional $current_slug (string)
 */

$post = $post ?? [];
$current_slug = $current_slug ?? ($post['slug'] ?? '');
$seo = $post['seo'] ?? [];
$og = $post['og'] ?? [];
$schema = $post['schema'] ?? [];
$related = $post['related_posts'] ?? [];
$all_categories = collect_all_categories();
$selected_categories = $post['categories'] ?? [];
$word_count = post_word_count($post);
$read_time = post_read_time_minutes($post);
$sitemap_priority = $post['sitemap_priority'] ?? 0.9;
$featured_alt = $post['meta']['image_alt'] ?? '';
$faq_items = $schema['faq'] ?? [];
if ($faq_items === []) {
    $faq_items = [['question' => '', 'answer' => '']];
}
?>

<!-- SEO -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">SEO</h5></div>
    <div class="card-body">
        <div class="mb-3">
            <label for="meta_title" class="form-label">Meta Title</label>
            <input type="text" class="form-control char-limit" id="meta_title" name="meta_title" maxlength="60"
                data-max="60" data-counter="meta_title_counter"
                value="<?php echo htmlspecialchars($seo['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Leave blank to use blog title">
            <div class="form-text"><span id="meta_title_counter">0</span>/60 characters</div>
        </div>
        <div class="mb-3">
            <label for="meta_description" class="form-label">Meta Description</label>
            <textarea class="form-control char-limit" id="meta_description" name="meta_description" rows="2" maxlength="160"
                data-max="160" data-counter="meta_description_counter"><?php echo htmlspecialchars($seo['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text"><span id="meta_description_counter">0</span>/160 characters</div>
        </div>
        <div class="mb-3">
            <label for="canonical_url" class="form-label">Canonical URL</label>
            <input type="url" class="form-control" id="canonical_url" name="canonical_url"
                value="<?php echo htmlspecialchars($seo['canonical_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Auto-generated from slug if empty">
        </div>
        <div class="mb-0">
            <label class="form-label">Robots</label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="robots_index" name="robots_index" value="1"
                    <?php echo (($seo['robots'] ?? 'index') !== 'noindex') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="robots_index">Allow search engines to index (index)</label>
            </div>
            <div class="form-text">Turn off for noindex.</div>
        </div>
    </div>
</div>

<!-- Open Graph -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Open Graph / Social</h5></div>
    <div class="card-body">
        <div class="mb-3">
            <label for="og_title" class="form-label">OG Title</label>
            <input type="text" class="form-control" id="og_title" name="og_title"
                value="<?php echo htmlspecialchars($og['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label for="og_description" class="form-label">OG Description</label>
            <textarea class="form-control" id="og_description" name="og_description" rows="2"><?php echo htmlspecialchars($og['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="og_image" class="form-label">OG Image (1200×630 recommended)</label>
            <input type="file" class="form-control" id="og_image" name="og_image" accept="image/*">
            <?php
            $effective_og_image = post_og_image($post);
            $has_custom_og = !empty($og['image_custom']) || (!empty($og['image']) && str_contains((string) $og['image'], '/uploads/og/'));
            ?>
            <?php if ($has_custom_og && !empty($og['image'])): ?>
                <div class="form-text mt-1">Custom OG image: <a href="<?php echo htmlspecialchars($og['image'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View</a></div>
            <?php elseif ($effective_og_image !== ''): ?>
                <div class="form-text mt-1">Using featured image as OG: <a href="<?php echo htmlspecialchars($effective_og_image, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View</a></div>
            <?php else: ?>
                <div class="form-text mt-1">Upload an OG image or set a featured image — featured image is used for social sharing by default.</div>
            <?php endif; ?>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="twitter_card" name="twitter_card" value="1"
                <?php echo post_use_twitter_card($post) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="twitter_card">Enable Twitter Card</label>
        </div>
    </div>
</div>

<!-- Schema -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Schema Markup</h5></div>
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="schema_article" name="schema_article" value="1"
                <?php echo (!isset($schema['article']) || $schema['article'] !== false) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="schema_article">Article schema (BlogPosting)</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="schema_breadcrumb" name="schema_breadcrumb" value="1"
                <?php echo (!isset($schema['breadcrumb']) || $schema['breadcrumb'] !== false) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="schema_breadcrumb">Breadcrumb schema (auto from URL)</label>
        </div>
        <label class="form-label">FAQ schema</label>
        <div id="faq-pairs">
            <?php foreach ($faq_items as $i => $faq): ?>
                <div class="faq-pair border rounded p-2 mb-2">
                    <input type="text" class="form-control form-control-sm mb-2" name="faq_question[]"
                        placeholder="Question" value="<?php echo htmlspecialchars($faq['question'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <textarea class="form-control form-control-sm" name="faq_answer[]" rows="2"
                        placeholder="Answer"><?php echo htmlspecialchars($faq['answer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-faq-pair">Add FAQ pair</button>
    </div>
</div>

<!-- Sitemap -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Sitemap</h5></div>
    <div class="card-body">
        <label for="sitemap_priority" class="form-label">Priority (0.1 – 1.0)</label>
        <input type="number" class="form-control" id="sitemap_priority" name="sitemap_priority"
            min="0.1" max="1" step="0.1" value="<?php echo htmlspecialchars((string) $sitemap_priority, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-text">Published posts are included in sitemap.xml automatically.</div>
    </div>
</div>

<!-- Related posts -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Related Posts</h5></div>
    <div class="card-body">
        <label class="form-label">Select 2–3 related posts</label>
        <?php
        $published = array_filter($all_posts ?? [], function ($p) use ($current_slug) {
            return ($p['status'] ?? '') === 'published' && ($p['slug'] ?? '') !== $current_slug;
        });
        foreach (array_slice($published, 0, 50) as $rp):
            $checked = in_array($rp['slug'], $related, true) ? 'checked' : '';
        ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="related_posts[]"
                    value="<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>" id="rel_<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked; ?>>
                <label class="form-check-label" for="rel_<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($rp['title'] ?? $rp['slug'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>
        <?php endforeach; ?>
        <?php if ($published === []): ?>
            <p class="text-muted small mb-0">No other published posts yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Writer stats -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Content Stats</h5></div>
    <div class="card-body">
        <p class="mb-1"><strong>Word count:</strong> <span id="live_word_count"><?php echo (int) $word_count; ?></span></p>
        <p class="mb-0"><strong>Est. read time:</strong> <span id="live_read_time"><?php echo (int) $read_time; ?></span> min</p>
        <div class="form-text">Updated as you type in the content field.</div>
    </div>
</div>

<!-- Content image upload -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Content Images</h5></div>
    <div class="card-body">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contentImageModal">
            <i class="bi bi-image"></i> Insert image with metadata
        </button>
        <div class="form-text mt-2">Adds alt text, title, and optional caption to images in your post body.</div>
    </div>
</div>

<div class="modal fade" id="contentImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert content image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Image file *</label>
                    <input type="file" class="form-control" id="content_image_file" accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alt text *</label>
                    <input type="text" class="form-control" id="content_image_alt">
                </div>
                <div class="mb-3">
                    <label class="form-label">Image title</label>
                    <input type="text" class="form-control" id="content_image_title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Caption</label>
                    <input type="text" class="form-control" id="content_image_caption">
                </div>
                <div id="content_image_error" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="content_image_insert_btn">Upload &amp; insert</button>
            </div>
        </div>
    </div>
</div>
