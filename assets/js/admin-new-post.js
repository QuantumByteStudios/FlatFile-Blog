// assets/js/admin-new-post.js — slug helper, content-type hints

document.addEventListener('DOMContentLoaded', () => {
	const titleInput = document.getElementById('title');
	const slugInput = document.getElementById('slug');
	const contentTypeSelect = document.getElementById('content_type');
	const contentHelp = document.getElementById('content-help');
	const contentTextarea = document.getElementById('content');

	const slugFromTitle = (title) => title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');

	let slugManuallyEdited = false;

	const syncSlugFromTitle = () => {
		if (!titleInput || !slugInput || slugManuallyEdited) {
			return;
		}
		slugInput.value = slugFromTitle(titleInput.value);
		slugInput.dispatchEvent(new Event('input', { bubbles: true }));
	};

	const toggleContentType = () => {
		if (!contentTypeSelect || !contentHelp || !contentTextarea) {
			return;
		}

		if (contentTypeSelect.value === 'html') {
			contentHelp.innerHTML = '<strong>HTML supported:</strong> Use &lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;ul&gt;, etc.';
			contentTextarea.placeholder = 'Write your post content in HTML...';
			return;
		}

		contentHelp.innerHTML = '<strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.';
		contentTextarea.placeholder = 'Write your post content in Markdown...';
	};

	if (titleInput && slugInput) {
		titleInput.addEventListener('input', syncSlugFromTitle);
		slugInput.addEventListener('input', () => {
			slugManuallyEdited = slugInput.value.trim() !== '';
		});
	}

	syncSlugFromTitle();
	toggleContentType();
	if (contentTypeSelect) {
		contentTypeSelect.addEventListener('change', toggleContentType);
	}

	document.addEventListener('ai-post-filled', (e) => {
		if (e.detail?.slug && slugInput) {
			slugManuallyEdited = false;
		}
	});
});
