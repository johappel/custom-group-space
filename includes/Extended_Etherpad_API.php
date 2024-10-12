<?php
/**
 * Erweiterte Etherpad API Library für WordPress mit Changesets-Unterstützung
 * Basierend auf der Arbeit von Joachim Happel
 * @see https://etherpad.org/doc/v2.2.2/#_http_api
 * @see https://github.com/ether/etherpad-lite/blob/develop/doc/api/changeset_library.md
 * @see https://raw.githubusercontent.com/ether/etherpad-lite/refs/heads/develop/src/static/js/Changeset.ts
  */
use League\HTMLToMarkdown\HtmlConverter;
require_once 'Etherpad_Changeset.php';
require_once 'Etherpad_API.php';


class Extended_Etherpad_API extends Etherpad_API {
    public function setHTML($padID, $html, $append = 0, $authorId = null) {
        // Konvertiere HTML

        //$etherpadText = $this->convertHtmlToSaveHTML($html);
        $etherpadText = $html;


        if ($append === 1) {
            return parent::appendHTML($padID, "\n\n" . $etherpadText, $authorId);
        } elseif ($append === -1) {
            $currentText = $this->getText($padID);
            return parent::setHTML($padID, $etherpadText . "\n\n" . $currentText, $authorId);
        } else {
            return parent::setHTML($padID, $etherpadText, $authorId);
        }
    }
    /**
     * Konvertiere in sicheres HTML ohne \n \t \r \l und andere formatierende Zeichen
     * @param string $html Der HTML-Inhalt
     * @return string Der konvertierte HTML-Inhalt
     */
    private function convertHtmlToSaveHTML($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $etherpadText = '';
        //entferne alle Zeilenumbrüche, Tabs und Zeilenumbrüche außerhalb von tags
        $dom->normalize();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//text()');
        foreach ($nodes as $node) {
            $node->nodeValue = preg_replace('/\s+/', ' ', $node->nodeValue);
        }
        //gib den HTML-Code zurück
        $etherpadText = $dom->saveHTML();

        return trim($etherpadText);

    }

    /**
     * Setzt den Inhalt eines Pads als HTML
     * @param string $padID Die ID des Pads
     * @param string $html Der HTML-Inhalt
     * @param int $append 0 = überschreiben, 1 = anhängen, -1 = voranstellen
     * @param string $authorId Die ID des Autors
     * @return mixed
     */
    public function setPadText($padID, $html, $append = 0, $authorId = null) {
        // Konvertiere HTML in Etherpad-Formatierung
        $etherpadText = $this->convertHtmlToEtherpadFormat($html);

        if ($append === 1) {
            return $this->appendText($padID, "\n\n" . $etherpadText, $authorId);
        } elseif ($append === -1) {
            $currentText = $this->getText($padID);
            return $this->setText($padID, $etherpadText . "\n\n" . $currentText, $authorId);
        } else {
            return $this->setText($padID, $etherpadText, $authorId);
        }
    }

    private function convertHtmlToEtherpadFormat($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $etherpadText = '';
        $this->traverseNodes($dom->documentElement, $etherpadText);

        return trim($etherpadText);
    }

    private function traverseNodes($node, &$etherpadText, $parentTags = []) {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->nodeValue;
                foreach (array_reverse($parentTags) as $tag) {
                    switch ($tag) {
                        case 'strong':
                        case 'b':
                            $text = '*' . $text . '*';
                            break;
                        case 'i':
                        case 'em':
                            $text = '_' . $text . '_';
                            break;
                        case 'u':
                            $text = '+' . $text . '+';
                            break;
                    }
                }
                $etherpadText .= $text;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $newParentTags = $parentTags;
                $newParentTags[] = $child->nodeName;

                switch ($child->nodeName) {
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $level = substr($child->nodeName, 1);
                        $etherpadText .= str_repeat('=', $level) . ' ';
                        $this->traverseNodes($child, $etherpadText, $newParentTags);
                        $etherpadText .= "\n";
                        break;
                    case 'p':
                        $this->traverseNodes($child, $etherpadText, $newParentTags);
                        $etherpadText .= "\n\n";
                        break;
                    case 'br':
                        $etherpadText .= "\n";
                        break;
                    case 'ol':
                        $etherpadText .= "\n";
                        $listItems = $child->getElementsByTagName('li');
                        for ($i = 0; $i < $listItems->length; $i++) {
                            $etherpadText .= ($i + 1) . '. ';
                            $this->traverseNodes($listItems->item($i), $etherpadText, $newParentTags);
                            $etherpadText .= "\n";
                        }
                        $etherpadText .= "\n";
                        break;
                    case 'ul':
                        $etherpadText .= "\n";
                        $listItems = $child->getElementsByTagName('li');
                        foreach ($listItems as $item) {
                            $etherpadText .= '- ';
                            $this->traverseNodes($item, $etherpadText, $newParentTags);
                            $etherpadText .= "\n";
                        }
                        $etherpadText .= "\n";
                        break;
                    default:
                        $this->traverseNodes($child, $etherpadText, $newParentTags);
                }
            }
        }
    }
    public function setText($padID, $text, $authorId = null) {
        $params = array(
            'padID' => $padID,
            'text' => $text
        );

        if ($authorId) {
            $params['authorId'] = $authorId;
        }

        return $this->make_request('setText', $params);
    }

    public function appendText($padID, $text, $authorId = null) {
        $params = array(
            'padID' => $padID,
            'text' => $text
        );

        if ($authorId) {
            $params['authorId'] = $authorId;
        }

        return $this->make_request('appendText', $params);
    }
}