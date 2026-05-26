<?php
declare(strict_types=1);
/**
 * SEO + Open Graph fields (main column).
 * Expects: $post, optional $current_slug
 */
$post = $post ?? [];
$current_slug = $current_slug ?? ($post['slug'] ?? '');
$seo = $post['seo'] ?? [];
$og = $post['og'] ?? [];
$preview_meta_title = $seo['meta_title'] ?? ($post['title'] ?? '');
$preview_meta_desc = $seo['meta_description'] ?? ($post['excerpt'] ?? '');
$preview_canonical = !empty($seo['canonical_url'])
    ? $seo['canonical_url']
    : (!empty($current_slug) ? post_canonical_url(['slug' => $current_slug, 'seo' => []]) : '');
$effective_og_image = post_og_image($post);
$has_custom_og = !empty($og['image_custom']) || (!empty($og['image']) && str_contains((string) ($og['image'] ?? ''), '/uploads/og/'));
?>

<div class="card mb-4 border-primary border-opacity-25">
    <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-search me-2"></i>SEO &amp; Meta Tags</h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" id="fill-meta-from-post" title="Copy H1 title and excerpt into meta fields">
                <i class="bi bi-arrow-down-circle"></i> Fill from post
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-light border small mb-4 py-2" id="serp-preview" aria-live="polite">
            <div class="text-muted text-uppercase mb-1" style="font-size:0.7rem;">Search preview</div>
            <div class="text-primary text-truncate" id="serp-preview-title" style="font-size:1rem;"><?php echo htmlspecialchars($preview_meta_title ?: 'Post title', ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="text-success text-truncate small" id="serp-preview-url"><?php echo htmlspecialchars($preview_canonical ?: absolute_url('your-slug'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="text-muted small" id="serp-preview-desc"><?php echo htmlspecialchars(mb_substr($preview_meta_desc ?: 'Meta description…', 0, 160), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="meta_title" class="form-label fw-medium">Meta Title <span class="text-muted fw-normal">(separate from H1)</span></label>
                <input type="text" class="form-control char-limit" id="meta_title" name="meta_title" maxlength="60"
                    data-max="60" data-counter="meta_title_counter" data-serp="title"
                    value="<?php echo htmlspecialchars($seo['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Search engine title — blank = blog title">
                <div class="form-text"><span id="meta_title_counter"><?php echo strlen($seo['meta_title'] ?? ''); ?></span>/60</div>
            </div>
            <div class="col-md-6">
                <label for="canonical_url" class="form-label fw-medium">Canonical URL</label>
                <input type="url" class="form-control" id="canonical_url" name="canonical_url" data-serp="url"
                    value="<?php echo htmlspecialchars($seo['canonical_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="<?php echo htmlspecialchars(!empty($current_slug) ? post_canonical_url(['slug' => $current_slug, 'seo' => []]) : absolute_url('your-post-slug'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-text">Leave empty for live URL + slug (no duplicate domain).</div>
            </div>
            <div class="col-12">
                <label for="meta_description" class="form-label fw-medium">Meta Description</label>
                <textarea class="form-control char-limit" id="meta_description" name="meta_description" rows="2" maxlength="160"
                    data-max="160" data-counter="meta_description_counter" data-serp="desc"><?php echo htmlspecialchars($seo['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="form-text"><span id="meta_description_counter"><?php echo strlen($seo['meta_description'] ?? ''); ?></span>/160</div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="robots_index" name="robots_index" value="1"
                        <?php echo (($seo['robots'] ?? 'index') !== 'noindex') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="robots_index">Allow indexing (index) — turn off for <code>noindex</code></label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-share me-2"></i>Open Graph / Social Sharing</h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" id="fill-og-from-meta" title="Copy meta title and description into OG fields">
                <i class="bi bi-share"></i> Fill from SEO &amp; Meta Tags
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="og_title" class="form-label fw-medium">OG Title</label>
                <input type="text" class="form-control" id="og_title" name="og_title"
                    value="<?php echo htmlspecialchars($og['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Facebook, LinkedIn — blank = meta title">
            </div>
            <div class="col-md-6">
                <label for="og_image" class="form-label fw-medium">OG Image <span class="text-muted fw-normal">(1200×630px)</span></label>
                <input type="file" class="form-control" id="og_image" name="og_image" accept="image/*">
                <?php if ($has_custom_og && !empty($og['image'])): ?>
                    <div class="form-text mt-1">Custom: <a href="<?php echo htmlspecialchars($og['image'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View</a></div>
                <?php elseif ($effective_og_image !== ''): ?>
                    <div class="form-text mt-1">Using featured image: <a href="<?php echo htmlspecialchars($effective_og_image, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Preview</a></div>
                <?php else: ?>
                    <div class="form-text mt-1">Upload here or use featured image from sidebar.</div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label for="og_description" class="form-label fw-medium">OG Description</label>
                <textarea class="form-control" id="og_description" name="og_description" rows="2"
                    placeholder="Blank = meta description"><?php echo htmlspecialchars($og['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="twitter_card" name="twitter_card" value="1"
                        <?php echo post_use_twitter_card($post) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="twitter_card">Enable Twitter / X Card (<code>summary_large_image</code>)</label>
                </div>
                <div class="form-text mb-0"><code>og:url</code> uses the canonical URL on the live site.</div>
            </div>
        </div>
    </div>
</div>
