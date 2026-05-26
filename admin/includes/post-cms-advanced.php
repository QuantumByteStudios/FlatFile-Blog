<?php
declare(strict_types=1);
/**
 * Schema, sitemap, related posts, stats, content images (sidebar).
 * Expects: $post, $all_posts, optional $current_slug
 */
$post = $post ?? [];
$current_slug = $current_slug ?? ($post['slug'] ?? '');
$schema = $post['schema'] ?? [];
$related = $post['related_posts'] ?? [];
$word_count = post_word_count($post);
$read_time = post_read_time_minutes($post);
$sitemap_priority = $post['sitemap_priority'] ?? 0.9;
$faq_items = $schema['faq'] ?? [];
if ($faq_items === []) {
    $faq_items = [['question' => '', 'answer' => '']];
}
?>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-code-slash me-2"></i>Schema Markup</h5></div>
    <div class="card-body">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="schema_article" name="schema_article" value="1"
                <?php echo (!isset($schema['article']) || $schema['article'] !== false) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="schema_article">Article (BlogPosting)</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="schema_breadcrumb" name="schema_breadcrumb" value="1"
                <?php echo (!isset($schema['breadcrumb']) || $schema['breadcrumb'] !== false) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="schema_breadcrumb">Breadcrumb JSON-LD</label>
        </div>
        <label class="form-label small fw-medium">FAQ schema</label>
        <div id="faq-pairs">
            <?php foreach ($faq_items as $faq): ?>
                <div class="faq-pair border rounded p-2 mb-2">
                    <input type="text" class="form-control form-control-sm mb-2" name="faq_question[]"
                        placeholder="Question" value="<?php echo htmlspecialchars($faq['question'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <textarea class="form-control form-control-sm" name="faq_answer[]" rows="2"
                        placeholder="Answer"><?php echo htmlspecialchars($faq['answer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="add-faq-pair">+ Add FAQ</button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-map me-2"></i>Sitemap</h5></div>
    <div class="card-body">
        <label for="sitemap_priority" class="form-label small">Priority (0.1 – 1.0)</label>
        <input type="number" class="form-control form-control-sm" id="sitemap_priority" name="sitemap_priority"
            min="0.1" max="1" step="0.1" value="<?php echo htmlspecialchars((string) $sitemap_priority, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Related Posts</h5></div>
    <div class="card-body" style="max-height:220px;overflow-y:auto;">
        <?php
        $published = array_filter($all_posts ?? [], function ($p) use ($current_slug) {
            return ($p['status'] ?? '') === 'published' && ($p['slug'] ?? '') !== $current_slug;
        });
        foreach (array_slice($published, 0, 50) as $rp):
            $checked = in_array($rp['slug'], $related, true) ? 'checked' : '';
        ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="related_posts[]"
                    value="<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                    id="rel_<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked; ?>>
                <label class="form-check-label small" for="rel_<?php echo htmlspecialchars($rp['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($rp['title'] ?? $rp['slug'], ENT_QUOTES, 'UTF-8'); ?>
                </label>
            </div>
        <?php endforeach; ?>
        <?php if ($published === []): ?>
            <p class="text-muted small mb-0">No other published posts yet.</p>
        <?php endif; ?>
        <div class="form-text mt-2 mb-0">Select up to 3.</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Content Stats</h5></div>
    <div class="card-body py-2">
        <p class="mb-1 small"><strong>Words:</strong> <span id="live_word_count"><?php echo (int) $word_count; ?></span></p>
        <p class="mb-0 small"><strong>Read time:</strong> <span id="live_read_time"><?php echo (int) $read_time; ?></span> min</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-image me-2"></i>Content Images</h5></div>
    <div class="card-body">
        <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#contentImageModal">
            Insert with alt / caption
        </button>
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
