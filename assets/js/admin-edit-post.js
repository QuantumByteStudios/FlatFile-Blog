// assets/js/admin-edit-post.js — slug helper and content-type hints

document.addEventListener('DOMContentLoaded', () => {
	const titleInput = document.getElementById('title');
	const slugInput = document.getElementById('slug');
	const contentTypeSelect = document.getElementById('content_type');
	const contentHelp = document.getElementById('content-help');
	const contentTextarea = document.getElementById('content');

	if (titleInput && slugInput) {
		titleInput.addEventListener('input', () => {
			const slug = titleInput.value
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '');
			slugInput.value = slug;
		});
	}

	const toggleContentType = () => {
		if (!contentTypeSelect || !contentHelp || !contentTextarea) {
			return;
		}

		if (contentTypeSelect.value === 'html') {
			contentHelp.innerHTML = '<strong>HTML supported:</strong> Use only &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;br&gt;.';
			contentTextarea.placeholder = 'Write your post content in HTML...';
			return;
		}

		contentHelp.innerHTML = '<strong>Markdown supported:</strong> Use **bold**, *italic*, `code`, [links](url), # headers, etc.';
		contentTextarea.placeholder = 'Write your post content in Markdown...';
	};

	toggleContentType();
	if (contentTypeSelect) {
		contentTypeSelect.addEventListener('change', toggleContentType);
	}
});
