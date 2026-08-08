<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\JsonResponse;

/**
 * The terms and the privacy policy, as data the app can lay out itself.
 *
 * The provider app must not send a member to a browser for these: the portal is
 * often on a host only the office machine resolves, and even when it is not, a
 * legal page is part of the app rather than a trip out of it.
 *
 * The wording is not copied here. These read the same Blade pages the website
 * serves and turn them into headings and paragraphs, so the two can never drift
 * apart and nobody has to remember to update the app when the policy changes.
 */
class LegalController extends Controller
{
    private const DOCUMENTS = [
        'terms' => ['view' => 'portal.pages.terms', 'title' => 'Terms of Service'],
        'privacy' => ['view' => 'portal.pages.privacy', 'title' => 'Privacy Policy'],
    ];

    public function show(string $document): JsonResponse
    {
        $meta = self::DOCUMENTS[$document] ?? null;
        if (! $meta) {
            return response()->json(['error' => 'Unknown document.'], 404);
        }

        return response()->json([
            'success' => true,
            'slug' => $document,
            'title' => $meta['title'],
            'sections' => $this->sections(view($meta['view'])->render()),
        ]);
    }

    /**
     * Every `<section>` of the page body, as a heading and its blocks.
     */
    private function sections(string $html): array
    {
        $dom = new DOMDocument();
        // The pages are ordinary HTML5, which libxml grumbles about; the grumbles
        // are not errors and the tree is built regardless.
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $sections = [];

        foreach ($xpath->query("//div[contains(@class, 'legal-body')]/section") as $node) {
            $heading = null;
            $blocks = [];

            foreach ($node->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                $text = $this->text($child);

                switch (strtolower($child->tagName)) {
                    case 'h2':
                        // The first h2 titles the section; a second one would be
                        // a new heading mid-section, so it stays in the body.
                        if ($heading === null) {
                            $heading = $text;
                        } else {
                            $blocks[] = ['type' => 'heading', 'text' => $text];
                        }
                        break;

                    case 'h3':
                    case 'h4':
                        $blocks[] = ['type' => 'heading', 'text' => $text];
                        break;

                    case 'ul':
                    case 'ol':
                        $items = [];
                        foreach ($child->getElementsByTagName('li') as $item) {
                            $line = $this->text($item);
                            if ($line !== '') {
                                $items[] = $line;
                            }
                        }
                        if ($items) {
                            $blocks[] = ['type' => 'list', 'items' => $items];
                        }
                        break;

                    default:
                        if ($text !== '') {
                            $blocks[] = ['type' => 'paragraph', 'text' => $text];
                        }
                }
            }

            if ($heading !== null || $blocks) {
                $sections[] = ['title' => $heading, 'blocks' => $blocks];
            }
        }

        return $sections;
    }

    /** The element's words, with the markup's line breaks squeezed out. */
    private function text(DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? '');
    }
}
