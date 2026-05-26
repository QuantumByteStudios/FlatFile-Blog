/**
 * Populate new/edit post form from AI generation response.
 */
window.fillAiGeneratedPost = (data) => {
	if (!data || !data.success) return;

	const setVal = (id, value) => {
		const el = document.getElementById(id);
		if (el && value !== undefined && value !== null) {
			el.value = value;
			el.dispatchEvent(new Event('input', { bubbles: true }));
		}
	};

	setVal('title', data.title || '');
	setVal('excerpt', data.excerpt || '');
	setVal('meta_title', data.meta_title || '');
	setVal('meta_description', data.meta_description || '');
	setVal('og_title', data.og_title || '');
	setVal('og_description', data.og_description || '');

	if (data.slug) {
		const slugEl = document.getElementById('slug');
		if (slugEl) {
			slugEl.value = data.slug;
			slugEl.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	const tagsField = document.getElementById('tags');
	if (tagsField && Array.isArray(data.tags)) {
		tagsField.value = data.tags.join(', ');
		tagsField.dispatchEvent(new Event('input', { bubbles: true }));
	}

	if (Array.isArray(data.categories)) {
		const wanted = data.categories.map((c) => String(c).trim().toLowerCase()).filter(Boolean);
		document.querySelectorAll('input[name="categories[]"]').forEach((cb) => {
			const val = cb.value.trim().toLowerCase();
			cb.checked = wanted.includes(val);
		});
		data.categories.forEach((cat) => {
			const name = String(cat).trim();
			if (!name) return;
			const exists = Array.from(document.querySelectorAll('input[name="categories[]"]'))
				.some((cb) => cb.value.trim().toLowerCase() === name.toLowerCase());
			if (!exists) {
				const newCat = document.querySelector('input[name="new_category"]');
				if (newCat && !newCat.value) {
					newCat.value = name;
				}
			}
		});
	}

	const contentTypeSelect = document.getElementById('content_type');
	if (contentTypeSelect) {
		contentTypeSelect.value = data.content_type || 'html';
		contentTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
	}

	const contentTextarea = document.getElementById('content');
	if (contentTextarea) {
		contentTextarea.value = data.content_html || '';
		contentTextarea.dispatchEvent(new Event('input', { bubbles: true }));
	}

	const faqContainer = document.getElementById('faq-pairs');
	if (faqContainer && Array.isArray(data.faq) && data.faq.length > 0) {
		faqContainer.innerHTML = '';
		data.faq.forEach((item) => {
			const div = document.createElement('div');
			div.className = 'faq-pair border rounded p-2 mb-2';
			const qInput = document.createElement('input');
			qInput.type = 'text';
			qInput.className = 'form-control form-control-sm mb-2';
			qInput.name = 'faq_question[]';
			qInput.placeholder = 'Question';
			qInput.value = item.question || '';
			const aInput = document.createElement('textarea');
			aInput.className = 'form-control form-control-sm';
			aInput.name = 'faq_answer[]';
			aInput.rows = 2;
			aInput.placeholder = 'Answer';
			aInput.value = item.answer || '';
			div.appendChild(qInput);
			div.appendChild(aInput);
			faqContainer.appendChild(div);
		});
	}

	const wc = document.getElementById('live_word_count');
	const rt = document.getElementById('live_read_time');
	if (wc && data.word_count !== undefined) wc.textContent = String(data.word_count);
	if (rt && data.read_time !== undefined) rt.textContent = String(data.read_time);

	document.dispatchEvent(new CustomEvent('ai-post-filled', { detail: data }));
};

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
		el.addEventListener('input', () => {
			updateCharCounter(el);
			updateSerpPreview();
		});
	});

	const titleEl = document.getElementById('title');
	const excerptEl = document.getElementById('excerpt');
	const metaTitleEl = document.getElementById('meta_title');
	const metaDescEl = document.getElementById('meta_description');
	const canonicalEl = document.getElementById('canonical_url');
	const ogTitleEl = document.getElementById('og_title');
	const ogDescEl = document.getElementById('og_description');
	const slugEl = document.getElementById('slug');

	const updateSerpPreview = () => {
		const serpTitle = document.getElementById('serp-preview-title');
		const serpUrl = document.getElementById('serp-preview-url');
		const serpDesc = document.getElementById('serp-preview-desc');
		if (!serpTitle || !serpUrl || !serpDesc) return;

		const h1 = (titleEl?.value || '').trim();
		const metaTitle = (metaTitleEl?.value || '').trim();
		serpTitle.textContent = metaTitle || h1 || 'Post title';

		const canonical = (canonicalEl?.value || '').trim();
		if (canonical) {
			serpUrl.textContent = canonical;
		} else if (slugEl?.value) {
			const slug = slugEl.value.trim().replace(/^\/+/, '');
			serpUrl.textContent = `${baseUrl.replace(/\/$/, '')}/${encodeURIComponent(slug)}`;
		}

		const metaDesc = (metaDescEl?.value || '').trim();
		const excerpt = (excerptEl?.value || '').trim();
		const desc = metaDesc || excerpt || 'Meta description…';
		serpDesc.textContent = desc.length > 160 ? `${desc.slice(0, 157)}…` : desc;
	};

	[titleEl, excerptEl, metaTitleEl, metaDescEl, canonicalEl, slugEl].forEach((el) => {
		if (el) el.addEventListener('input', updateSerpPreview);
	});
	updateSerpPreview();

	document.addEventListener('ai-post-filled', () => {
		updateSerpPreview();
		document.querySelectorAll('.char-limit').forEach(updateCharCounter);
	});

	document.getElementById('fill-meta-from-post')?.addEventListener('click', () => {
		if (metaTitleEl && titleEl) {
			metaTitleEl.value = titleEl.value.slice(0, 60);
			metaTitleEl.dispatchEvent(new Event('input'));
		}
		if (metaDescEl && excerptEl) {
			metaDescEl.value = excerptEl.value.slice(0, 160);
			metaDescEl.dispatchEvent(new Event('input'));
		}
	});

	document.getElementById('fill-og-from-meta')?.addEventListener('click', () => {
		if (ogTitleEl && metaTitleEl) {
			ogTitleEl.value = metaTitleEl.value;
		} else if (ogTitleEl && titleEl) {
			ogTitleEl.value = titleEl.value;
		}
		if (ogDescEl && metaDescEl) {
			ogDescEl.value = metaDescEl.value;
		} else if (ogDescEl && excerptEl) {
			ogDescEl.value = excerptEl.value;
		}
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

	// AI blog generation (new-post + edit-post when modal present)
	const aiModal = document.getElementById('aiModal');
	if (aiModal) {
		aiModal.addEventListener('hide.bs.modal', () => {
			const active = document.activeElement;
			if (active && aiModal.contains(active)) active.blur();
		});
	}

	const aiGenerateBtn = document.getElementById('ai_generate_btn');
	const aiTopic = document.getElementById('ai_topic');
	const aiSpinner = document.getElementById('ai_spinner');
	const aiBtnText = document.getElementById('ai_generate_text');
	const aiError = document.getElementById('ai_error');

	if (aiGenerateBtn && aiTopic) {
		const setAiLoading = (loading) => {
			if (aiSpinner) aiSpinner.classList.toggle('d-none', !loading);
			if (aiBtnText) aiBtnText.textContent = loading ? 'Generating...' : 'Generate';
			aiGenerateBtn.disabled = loading;
		};

		aiGenerateBtn.addEventListener('click', async () => {
			if (aiError) {
				aiError.classList.add('d-none');
				aiError.textContent = '';
			}
			const topic = (aiTopic.value || '').trim();
			if (!topic) {
				if (aiError) {
					aiError.textContent = 'Please enter a topic.';
					aiError.classList.remove('d-none');
				}
				return;
			}

			setAiLoading(true);
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
				} catch {
					throw new Error('Failed to parse AI response');
				}
				if (!data?.success) {
					throw new Error(data?.error || 'Generation failed');
				}
				window.fillAiGeneratedPost(data);
				const modal = bootstrap.Modal.getInstance(aiModal) || new bootstrap.Modal(aiModal);
				modal.hide();
			} catch (err) {
				if (aiError) {
					aiError.textContent = err.message || 'Generation error';
					aiError.classList.remove('d-none');
				}
			} finally {
				setAiLoading(false);
			}
		});
	}
});
