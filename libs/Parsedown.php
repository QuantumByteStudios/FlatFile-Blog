<?php
/**
 * Parsedown - A Markdown Parser in PHP
 * Single-file version for our flat-file blog
 * 
 * This is a minimal version of Parsedown for basic Markdown rendering
 * For production, consider using the full Parsedown library
 */

class Parsedown
{
    protected $breaksEnabled = false;
    protected $markupEscaped = false;
    protected $urlsLinked = true;

    public function setBreaksEnabled($breaksEnabled)
    {
        $this->breaksEnabled = $breaksEnabled;
        return $this;
    }

    public function setMarkupEscaped($markupEscaped)
    {
        $this->markupEscaped = $markupEscaped;
        return $this;
    }

    public function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;
        return $this;
    }

    public function text($text)
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text, "\n");
        $text = $this->textElements($text);
        return $text;
    }

    protected function textElements($text)
    {
        $text = $this->linesElements($text);
        return $text;
    }

    protected function linesElements($text)
    {
        $text = preg_replace('/^[ ]*$/m', '', $text);
        $lines = explode("\n", $text);
        $elements = [];
        $current = null;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '') {
                if ($current !== null) {
                    $elements[] = $current;
                    $current = null;
                }
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));

            if ($line[0] === '#') {
                $elements[] = $this->headerElement($line);
            } elseif ($line[0] === '*') {
                $elements[] = $this->listElement($line);
            } elseif (preg_match('/^[0-9]+\./', $line)) {
                $elements[] = $this->listElement($line);
            } elseif ($line[0] === '>') {
                $elements[] = $this->blockquoteElement($line);
            } elseif (preg_match('/^```/', $line)) {
                $elements[] = $this->codeBlockElement($line);
            } else {
                if ($current === null) {
                    $current = $this->paragraphElement($line);
                } else {
                    $current['text'] .= "\n" . $line;
                }
            }
        }

        if ($current !== null) {
            $elements[] = $current;
        }

        return $this->elements($elements);
    }

    protected function headerElement($line)
    {
        $level = 0;
        while ($line[$level] === '#') {
            $level++;
        }

        $text = trim(substr($line, $level));
        return [
            'name' => 'h' . min($level, 6),
            'text' => $text
        ];
    }

    protected function listElement($line)
    {
        $text = trim($line, '* ');
        return [
            'name' => 'li',
            'text' => $text
        ];
    }

    protected function blockquoteElement($line)
    {
        $text = trim(substr($line, 1));
        return [
            'name' => 'blockquote',
            'text' => $text
        ];
    }

    protected function codeBlockElement($line)
    {
        return [
            'name' => 'pre',
            'text' => $line
        ];
    }

    protected function paragraphElement($line)
    {
        return [
            'name' => 'p',
            'text' => $line
        ];
    }

    protected function elements($elements)
    {
        $markup = '';
        $inList = false;

        foreach ($elements as $element) {
            if ($element['name'] === 'li') {
                if (!$inList) {
                    $markup .= '<ul>';
                    $inList = true;
                }
                $markup .= '<li>' . $this->inlineElements($element['text']) . '</li>';
            } else {
                if ($inList) {
                    $markup .= '</ul>';
                    $inList = false;
                }
                $markup .= '<' . $element['name'] . '>' . $this->inlineElements($element['text']) . '</' . $element['name'] . '>';
            }
        }

        if ($inList) {
            $markup .= '</ul>';
        }

        return $markup;
    }

    protected function inlineElements($text)
    {
        $text = $this->escapeHtml($text);
        $text = $this->inlineCode($text);
        $text = $this->inlineLink($text);
        $text = $this->inlineImage($text);
        $text = $this->inlineEmphasis($text);
        $text = $this->inlineStrong($text);
        $text = $this->inlineLineBreak($text);
        return $text;
    }

    protected function escapeHtml($text)
    {
        if ($this->markupEscaped) {
            return $text;
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    protected function inlineCode($text)
    {
        return preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    }

    protected function inlineLink($text)
    {
        if (!$this->urlsLinked) {
            return $text;
        }
        return preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
    }

    protected function inlineImage($text)
    {
        return preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $text);
    }

    protected function inlineEmphasis($text)
    {
        return preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
    }

    protected function inlineStrong($text)
    {
        return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    }

    protected function inlineLineBreak($text)
    {
        if ($this->breaksEnabled) {
            return preg_replace('/  \n/', '<br>', $text);
        }
        return $text;
    }
}
?>
