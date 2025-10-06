<?php

class OpenAIClient
{
	private $apiKey;
	private $endpoint;
	private $model;

	public function __construct($apiKey, $model = 'openai/gpt-4o-mini', $endpoint = 'https://models.github.ai/inference')
	{
		$this->apiKey = trim($apiKey);
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
			$err = is_array($response['error']) ? ($response['error']['message'] ?? 'OpenAI error') : (string)$response['error'];
			return ['success' => false, 'error' => $err];
		}

		$text = $this->extractText($response);
		if ($text === '') {
			return ['success' => false, 'error' => 'Empty OpenAI response'];
		}

		$parsed = $this->parseResponse($text);
		if (!$parsed) {
			return ['success' => false, 'error' => 'Failed to parse OpenAI response'];
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
			"content_html must be pure HTML using only <h1>, <h2>, <h3>, <p>. No <html>, <head>, <body>, CSS, or styling.",
			"Do NOT repeat the title text within content_html.",
			"Avoid em dashes, emojis, and unnecessary formatting.",
			"Length: target ~1200-1800 words with clear structure and a short conclusion."
		];
		if ($businessInfo !== '') {
			$rules[] = "Personalize the blog for this business context: " . $businessInfo;
		}
		$system = implode("\n- ", array_merge(["Follow these rules strictly:"], $rules));
		return [
			['role' => 'system', 'content' => $system]
		];
	}

	private function postJson($url, $payload)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->apiKey
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_TIMEOUT, 45);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);

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
			if (is_array($decoded) && isset($decoded['error'])) {
				$message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Unknown error') : (string)$decoded['error'];
				return ['error' => 'HTTP ' . $httpCode . ': ' . $message, 'raw' => $decoded];
			}
			return ['error' => 'HTTP ' . $httpCode . ': ' . substr((string)$result, 0, 500)];
		}

		return is_array($decoded) ? $decoded : ['error' => 'Invalid JSON from OpenAI service'];
	}

	private function extractText($response)
	{
		if (isset($response['choices'][0]['message']['content'])) {
			return (string)$response['choices'][0]['message']['content'];
		}
		return '';
	}

	private function parseResponse($text)
	{
		$trimmed = trim($text);
		$asJson = json_decode($trimmed, true);
		if (is_array($asJson)) {
			$title = trim((string)($asJson['title'] ?? ''));
			$excerpt = trim((string)($asJson['excerpt'] ?? ''));
			$tags = $asJson['tags'] ?? [];
			if (is_string($tags)) {
				$tags = array_filter(array_map('trim', explode(',', $tags)));
			}
			if (!is_array($tags)) $tags = [];
			$categories = $asJson['categories'] ?? [];
			if (is_string($categories)) {
				$categories = array_filter(array_map('trim', explode(',', $categories)));
			}
			if (!is_array($categories)) $categories = [];
			$contentHtml = (string)($asJson['content_html'] ?? '');
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
}


