document.addEventListener('DOMContentLoaded', () => {
	const baseUrl = document.body.getAttribute('data-base-url') || '/';
	const csrfInput = document.querySelector('input[name="csrf_token"]');
	const contentEl = document.getElementById('content');
	const contentTypeEl = document.getElementById('content_type');

	const updateCharCounter = (el) => {
		const counterId = el.dataset.counter;
		if (!counterId) return;
		const counter = document.getElementById(counterId);
		if (counter) counter.textContent = String(el.value.length);
	};

	document.querySelectorAll('.char-limit').forEach((el) => {
		updateCharCounter(el);
		el.addEventListener('input', () => updateCharCounter(el));
	});

	const countWords = (text) => {
		const t = text.trim();
		if (!t) return 0;
		return t.split(/\s+/).filter(Boolean).length;
	};

	const updateWordStats = () => {
		if (!contentEl) return;
		const words = countWords(contentEl.value);
		const readMins = Math.max(1, Math.ceil(words / 200));
		const wc = document.getElementById('live_word_count');
		const rt = document.getElementById('live_read_time');
		if (wc) wc.textContent = String(words);
		if (rt) rt.textContent = String(readMins);
	};

	if (contentEl) {
		contentEl.addEventListener('input', updateWordStats);
		updateWordStats();
	}

	const faqContainer = document.getElementById('faq-pairs');
	const addFaqBtn = document.getElementById('add-faq-pair');
	if (faqContainer && addFaqBtn) {
		addFaqBtn.addEventListener('click', () => {
			const div = document.createElement('div');
			div.className = 'faq-pair border rounded p-2 mb-2';
			div.innerHTML = `
				<input type="text" class="form-control form-control-sm mb-2" name="faq_question[]" placeholder="Question">
				<textarea class="form-control form-control-sm" name="faq_answer[]" rows="2" placeholder="Answer"></textarea>
			`;
			faqContainer.appendChild(div);
		});
	}

	const relatedChecks = document.querySelectorAll('input[name="related_posts[]"]');
	relatedChecks.forEach((cb) => {
		cb.addEventListener('change', () => {
			const checked = document.querySelectorAll('input[name="related_posts[]"]:checked');
			if (checked.length > 3) {
				cb.checked = false;
				alert('Select at most 3 related posts.');
			}
		});
	});

	const insertBtn = document.getElementById('content_image_insert_btn');
	const fileInput = document.getElementById('content_image_file');
	const altInput = document.getElementById('content_image_alt');
	const titleInput = document.getElementById('content_image_title');
	const captionInput = document.getElementById('content_image_caption');
	const errBox = document.getElementById('content_image_error');
	const modalEl = document.getElementById('contentImageModal');

	if (insertBtn && fileInput && contentEl) {
		insertBtn.addEventListener('click', async () => {
			if (errBox) {
				errBox.classList.add('d-none');
				errBox.textContent = '';
			}
			const file = fileInput.files && fileInput.files[0];
			const alt = (altInput?.value || '').trim();
			if (!file) {
				if (errBox) {
					errBox.textContent = 'Please choose an image file.';
					errBox.classList.remove('d-none');
				}
				return;
			}
			if (!alt) {
				if (errBox) {
					errBox.textContent = 'Alt text is required.';
					errBox.classList.remove('d-none');
				}
				return;
			}

			insertBtn.disabled = true;
			const fd = new FormData();
			fd.append('action', 'upload_content_image');
			fd.append('csrf_token', csrfInput?.value || '');
			fd.append('content_image', file);

			try {
				const res = await fetch(`${baseUrl}admin_action`, { method: 'POST', body: fd, credentials: 'same-origin' });
				const data = await res.json();
				if (!data.success) throw new Error(data.error || 'Upload failed');

				const url = data.url;
				const title = (titleInput?.value || '').trim();
				const caption = (captionInput?.value || '').trim();
				const isHtml = contentTypeEl && contentTypeEl.value === 'html';
				let snippet;

				if (isHtml) {
					const titleAttr = title ? ` title="${title.replace(/"/g, '&quot;')}"` : '';
					snippet = `<figure class="content-image-figure"><img src="${url}" alt="${alt.replace(/"/g, '&quot;')}"${titleAttr} class="img-fluid">`;
					if (caption) snippet += `<figcaption>${caption.replace(/</g, '&lt;')}</figcaption>`;
					snippet += '</figure>\n';
				} else {
					const mdTitle = title ? ` "${title.replace(/"/g, '\\"')}"` : '';
					snippet = `![${alt}](${url}${mdTitle})\n`;
					if (caption) snippet += `*${caption}*\n`;
				}

				const start = contentEl.selectionStart ?? contentEl.value.length;
				const end = contentEl.selectionEnd ?? contentEl.value.length;
				contentEl.value = contentEl.value.slice(0, start) + snippet + contentEl.value.slice(end);
				contentEl.dispatchEvent(new Event('input'));
				updateWordStats();

				if (modalEl) {
					const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
					modal.hide();
				}
				fileInput.value = '';
				if (altInput) altInput.value = '';
				if (titleInput) titleInput.value = '';
				if (captionInput) captionInput.value = '';
			} catch (e) {
				if (errBox) {
					errBox.textContent = e.message || 'Upload error';
					errBox.classList.remove('d-none');
				}
			} finally {
				insertBtn.disabled = false;
			}
		});
	}
});
