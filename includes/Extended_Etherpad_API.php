<?php
/**
 * Erweiterte Etherpad API Library für WordPress mit Changesets-Unterstützung
 * Basierend auf der Arbeit von Joachim Happel
 * @see https://etherpad.org/doc/v2.2.2/#_http_api
 * @see https://github.com/ether/etherpad-lite/blob/develop/doc/api/changeset_library.md
 * @see https://raw.githubusercontent.com/ether/etherpad-lite/refs/heads/develop/src/static/js/Changeset.ts
  */
use League\HTMLToMarkdown\HtmlConverter;
require_once 'Etherpad_API.php';

class Extended_Etherpad_API extends Etherpad_API {
    public function setHTML($padID, $html, $append = 0, $authorId = null) {

        // Todo: Implement Changeset

        if ($append === 1) {
            return parent::appendHTML($padID, "\n\n" . $html, $authorId);
        } elseif ($append === -1) {
            $currentText = $this->getText($padID);
            return parent::setHTML($padID, $html . "\n\n" . $currentText, $authorId);
        } else {
            return parent::setHTML($padID, $html, $authorId);
        }
    }

}