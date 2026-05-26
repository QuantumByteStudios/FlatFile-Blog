// assets/js/admin-new-post.js — slug helper, content-type hints, AI generate, modal a11y

document.addEventListener('DOMContentLoaded', () => {
	const aiModal = document.getElementById('aiModal');
	if (aiModal) {
		aiModal.addEventListener('hide.bs.modal', () => {
			const active = document.activeElement;
			if (active && aiModal.contains(active)) {
				active.blur();
			}
		});
	}

	const titleInput = document.getElementById('title');
	const slugInput = document.getElementById('slug');
	const contentTypeSelect = document.getElementById('content_type');
	const contentHelp = document.getElementById('content-help');
	const contentTextarea = document.getElementById('content');
	const baseUrl = document.body.getAttribute('data-base-url') || '/';

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
	};

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

	const aiGenerateBtn = document.getElementById('ai_generate_btn');
	const spinner = document.getElementById('ai_spinner');
	const btnText = document.getElementById('ai_generate_text');
	const errorBox = document.getElementById('ai_error');
	const aiTopic = document.getElementById('ai_topic');

	if (!aiGenerateBtn || !spinner || !btnText || !errorBox || !aiTopic) {
		return;
	}

	const setLoading = (isLoading) => {
		if (isLoading) {
			spinner.classList.remove('d-none');
			btnText.textContent = 'Generating...';
			aiGenerateBtn.disabled = true;
			return;
		}

		spinner.classList.add('d-none');
		btnText.textContent = 'Generate';
		aiGenerateBtn.disabled = false;
	};

	aiGenerateBtn.addEventListener('click', async () => {
		errorBox.classList.add('d-none');
		errorBox.textContent = '';

		const topic = (aiTopic.value || '').trim();
		if (!topic) {
			errorBox.textContent = 'Please enter a topic.';
			errorBox.classList.remove('d-none');
			return;
		}

		setLoading(true);

		try {
			const url = `${baseUrl}admin_action?action=generate_ai_post&topic=${encodeURIComponent(topic)}`;
			const response = await fetch(url, {
				method: 'GET',
				headers: { Accept: 'application/json' },
				credentials: 'same-origin'
			});

			const rawText = await response.text();
			let data = null;

			try {
				data = rawText ? JSON.parse(rawText) : null;
			} catch (parseError) {
				console.error('AI generate: Failed to parse JSON.', { url, status: response.status, body: rawText });
				throw new Error('Failed to parse AI response');
			}

			if (!data || !data.success) {
				console.error('AI generate: Server returned error payload:', data);
				throw new Error((data && data.error) || 'Generation failed');
			}

			if (titleInput) {
				titleInput.value = data.title || '';
			}

			const excerptField = document.getElementById('excerpt');
			if (excerptField) {
				excerptField.value = data.excerpt || '';
			}

			const tagsField = document.getElementById('tags');
			if (tagsField && Array.isArray(data.tags)) {
				tagsField.value = data.tags.join(', ');
			}

			const categoriesField = document.getElementById('categories');
			if (categoriesField && Array.isArray(data.categories)) {
				categoriesField.value = data.categories.join(', ');
			}

			if (slugInput && data.slug) {
				slugInput.value = data.slug;
			} else {
				syncSlugFromTitle();
			}

			if (contentTypeSelect) {
				contentTypeSelect.value = 'html';
			}
			toggleContentType();

			if (contentTextarea) {
				contentTextarea.value = data.content_html || '';
			}

			const modalElement = document.getElementById('aiModal');
			if (modalElement) {
				const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
				modal.hide();
			}
		} catch (error) {
			console.error('AI generate: Request failed.', error);
			errorBox.textContent = error.message || 'Generation error';
			errorBox.classList.remove('d-none');
		} finally {
			setLoading(false);
		}
	});
});
