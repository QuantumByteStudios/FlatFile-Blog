<?php

class OpenAIClient
{
	private $apiKey;
	private $endpoint;
	private $model;

	public function __construct($apiKey, $model = 'openai/gpt-4o-mini', $endpoint = 'https://models.github.ai/inference')
	{
		// Remove any whitespace, newlines, or hidden characters
		$this->apiKey = trim($apiKey);
		$this->apiKey = preg_replace('/\s+/', '', $this->apiKey);
		$this->model = trim($model);
		$this->endpoint = rtrim($endpoint, '/');
	}

	public function generateBlogContent($topic, $businessInfo = '')
	{
		$messages = $this->buildMessages($topic, $businessInfo);
		$payload = [
			'model' => $this->model,
			'messages' => $messages,
			'temperature' => 0.7,
			'max_tokens' => 4096
		];

		$url = $this->endpoint . '/chat/completions';
		$response = $this->postJson($url, $payload);
		if (!$response) {
			return ['success' => false, 'error' => 'No response from OpenAI service'];
		}
		if (isset($response['error'])) {
			$err = is_array($response['error']) ? ($response['error']['message'] ?? 'OpenAI error') : (string) $response['error'];
			return ['success' => false, 'error' => $err];
		}

		$text = $this->extractText($response);
		if ($text === '') {
			return ['success' => false, 'error' => 'Empty OpenAI response'];
		}

		$parsed = $this->parseResponse($text, $topic);
		if (!$parsed) {
			return ['success' => false, 'error' => 'Failed to parse OpenAI response'];
		}

		// Clean and format the HTML content
		if (isset($parsed['content_html'])) {
			$parsed['content_html'] = $this->cleanHtmlContent($parsed['content_html']);
		}

		return ['success' => true] + $parsed;
	}

	private function buildMessages($topic, $businessInfo)
	{
		$businessInfo = trim($businessInfo);
		$rules = [
			"Act as a professional SEO expert and write a blog on the topic: '" . trim($topic) . "'.",
			"The blog must be SEO-friendly and highlight the benefits of the given topic for businesses.",
			"Promote the client's company naturally to build credibility (do not overdo it).",
			"Provide STRICT JSON only (no fences) with keys: title (string), excerpt (2-3 lines), tags (array of 3-8 short tags), categories (array of 1-4 categories), content_html (string).",
			"content_html must be pure semantic HTML. Use proper HTML structure:",
			"  - Use <p> tags for paragraphs (NOT <br> tags for spacing)",
			"  - Use <h2> for main section headings and <h3> for subsections",
			"  - Use <b> for bold, <i> for italic, <u> for underline",
			"  - Use <a href='url'> for links (email links: <a href='mailto:email'>)",
			"  - Use <a href='tel:phone'> for phone numbers (make them clickable)",
			"  - Use <ul> and <li> for lists when appropriate",
			"  - NO <html>, <head>, <body>, <div>, CSS, or inline styling",
			"  - NO excessive <br> tags - use <p> tags instead",
			"  - Each paragraph should be wrapped in <p> tags",
			"  - Structure content with proper headings and paragraphs for readability and SEO",
			"Do NOT repeat the title text within content_html.",
			"Avoid em dashes, emojis, and unnecessary formatting.",
			"Length: target ~1200-1800 words with clear structure and a short conclusion."
		];
		if ($businessInfo !== '') {
			$rules[] = "Personalize the blog for this business context: " . $businessInfo;
		}
		$system = implode("\n- ", array_merge(["Follow these rules strictly:"], $rules));
		return [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => "Topic: " . trim($topic)]
		];
	}

	private function postJson($url, $payload)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);

		// Ensure API key is properly formatted - remove any whitespace
		$apiKey = trim($this->apiKey);
		$apiKey = preg_replace('/\s+/', '', $apiKey);

		// GitHub Models API authentication
		// The API expects Bearer token format
		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $apiKey
		];

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_TIMEOUT, 45);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$result = curl_exec($ch);
		$curlErrNo = curl_errno($ch);
		$curlErr = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($result === false) {
			return ['error' => 'cURL error #' . $curlErrNo . ': ' . $curlErr];
		}

		$decoded = json_decode($result, true);
		if ($httpCode < 200 || $httpCode >= 300) {
			if ($httpCode === 401) {
				// Provide more detailed error info
				$errorDetail = '';
				if (is_array($decoded) && isset($decoded['error'])) {
					$errorDetail = is_array($decoded['error']) ? ($decoded['error']['message'] ?? '') : (string) $decoded['error'];
				}

				// Get more info from raw response if available
				$rawResponse = substr((string) $result, 0, 200);

				// Check if API key looks valid (GitHub tokens usually start with ghp_ or are 40+ chars)
				$keyLength = strlen($this->apiKey);
				$keyPrefix = substr($this->apiKey, 0, 4);
				$keyPreview = substr($this->apiKey, 0, 8) . '...' . substr($this->apiKey, -4);

				$errorMsg = 'Unauthorized (401): Invalid or expired API key.';
				if ($errorDetail) {
					$errorMsg .= ' API says: ' . $errorDetail;
				} elseif ($rawResponse && $rawResponse !== 'Unauthorized') {
					$errorMsg .= ' Response: ' . $rawResponse;
				}

				$errorMsg .= "\n\nDiagnostic Info:";
				$errorMsg .= "\n- Token length: " . $keyLength . " characters";
				$errorMsg .= "\n- Token starts with: " . $keyPrefix;
				$errorMsg .= "\n- Token preview: " . $keyPreview;
				$errorMsg .= "\n- Endpoint: " . $this->endpoint . '/chat/completions';

				if ($keyLength < 20) {
					$errorMsg .= "\n- ⚠️ Warning: Token seems too short. GitHub tokens are usually 40+ characters.";
				}

				if ($keyPrefix !== 'ghp_' && $keyLength < 40) {
					$errorMsg .= "\n- ⚠️ Warning: Token doesn't start with 'ghp_' and is shorter than expected.";
				}

				$errorMsg .= "\n\nTroubleshooting Steps:";
				$errorMsg .= "\n1. Verify token is active: Go to GitHub Settings > Developer settings > Personal access tokens";
				$errorMsg .= "\n2. Check token permissions: Token needs appropriate scopes (read:packages, etc.)";
				$errorMsg .= "\n3. Regenerate token: Create a new token and update it in Settings > AI Settings";
				$errorMsg .= "\n4. Verify no extra spaces: Copy token directly without spaces";
				$errorMsg .= "\n5. Check endpoint: Should be 'https://models.github.ai/inference'";

				return ['error' => $errorMsg];
			}
			if (is_array($decoded) && isset($decoded['error'])) {
				$message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Unknown error') : (string) $decoded['error'];
				return ['error' => 'HTTP ' . $httpCode . ': ' . $message, 'raw' => $decoded];
			}
			return ['error' => 'HTTP ' . $httpCode . ': ' . substr((string) $result, 0, 500)];
		}

		return is_array($decoded) ? $decoded : ['error' => 'Invalid JSON from OpenAI service'];
	}

	private function extractText($response)
	{
		if (isset($response['choices'][0]['message']['content'])) {
			return (string) $response['choices'][0]['message']['content'];
		}
		return '';
	}

	private function parseResponse($text, $topic = '')
	{
		$trimmed = trim($text);
		// Strip common code fences if present
		if (preg_match('/```(?:json)?\\s*([\\s\\S]*?)\\s*```/i', $trimmed, $m)) {
			$trimmed = trim($m[1]);
		}

		$asJson = json_decode($trimmed, true);
		if (is_array($asJson)) {
			$title = trim((string) ($asJson['title'] ?? ''));
			$excerpt = trim((string) ($asJson['excerpt'] ?? ''));
			$tags = $asJson['tags'] ?? [];
			if (is_string($tags)) {
				$tags = array_filter(array_map('trim', explode(',', $tags)));
			}
			if (!is_array($tags))
				$tags = [];
			$categories = $asJson['categories'] ?? [];
			if (is_string($categories)) {
				$categories = array_filter(array_map('trim', explode(',', $categories)));
			}
			if (!is_array($categories))
				$categories = [];
			$contentHtml = (string) ($asJson['content_html'] ?? '');
			// Fallback: if title missing, derive from topic
			if ($title === '' && $topic !== '') {
				$title = $this->fallbackTitle($topic);
			}
			if ($title !== '' && $contentHtml !== '') {
				return [
					'title' => $title,
					'excerpt' => $excerpt,
					'tags' => $tags,
					'categories' => $categories,
					'content_html' => $contentHtml
				];
			}
		}
		return null;
	}

	private function fallbackTitle($topic)
	{
		$topic = trim($topic);
		// Simple title-case fallback
		$topic = preg_replace('/\\s+/', ' ', $topic);
		$topic = ucwords($topic);
		// Limit length sensibly
		if (mb_strlen($topic) > 120) {
			$topic = rtrim(mb_substr($topic, 0, 117)) . '...';
		}
		return $topic ?: 'Untitled';
	}

	private function cleanHtmlContent($html)
	{
		$html = trim($html);

		// Remove any DOCTYPE, html, head, body tags if present
		$html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
		$html = preg_replace('/<html[^>]*>/i', '', $html);
		$html = preg_replace('/<\/html>/i', '', $html);
		$html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);
		$html = preg_replace('/<body[^>]*>/i', '', $html);
		$html = preg_replace('/<\/body>/i', '', $html);

		// Fix phone numbers wrapped in <u> tags - make them clickable tel: links
		$html = preg_replace_callback(
			'/<u>([+\d\s\-()]{10,})<\/u>/i',
			function ($matches) {
				$phone = trim($matches[1]);
				$phoneClean = preg_replace('/[^\d+]/', '', $phone);
				if (strlen($phoneClean) >= 10) {
					return '<a href="tel:' . htmlspecialchars($phoneClean) . '">' . htmlspecialchars($phone) . '</a>';
				}
				return $matches[0];
			},
			$html
		);

		// Check if content already has proper paragraph structure
		$hasParagraphs = preg_match('/<p[^>]*>/i', $html);
		$hasHeadings = preg_match('/<h[1-6][^>]*>/i', $html);

		// Only process if content uses <br> tags excessively (not well-structured)
		if (!$hasParagraphs && !$hasHeadings && preg_match('/<br\s*\/?>/i', $html)) {
			// Convert <br> tags to proper paragraph structure
			// Replace sequences of 2+ <br> tags with paragraph breaks
			$html = preg_replace('/(<br\s*\/?>\s*){2,}/i', '</p><p>', $html);

			// Split content by remaining <br> tags to identify paragraph boundaries
			$parts = preg_split('/<br\s*\/?>/i', $html);
			$result = [];

			foreach ($parts as $part) {
				$part = trim($part);
				if (empty($part)) {
					continue;
				}

				// Check if this part is already a block-level element
				if (preg_match('/^<(h[1-6]|p|ul|ol|li)/i', $part)) {
					// It's already a block element, add as-is
					$result[] = $part;
				} else {
					// Wrap in paragraph tag
					$result[] = '<p>' . $part . '</p>';
				}
			}

			$html = implode("\n", $result);
		}

		// If content doesn't start with a block element, ensure it's wrapped
		if (!preg_match('/^<(h[1-6]|p|ul|ol)/i', $html)) {
			$html = '<p>' . $html . '</p>';
		}

		// Clean up empty paragraphs
		$html = preg_replace('/<p>\s*<\/p>/i', '', $html);
		$html = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $html);

		// Normalize whitespace between tags
		$html = preg_replace('/>\s+</', '><', $html);
		$html = preg_replace('/\s{2,}/', ' ', $html);

		return trim($html);
	}
}
