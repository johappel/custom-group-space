<?php

/**
 * PhP Lite version of the Changeset Library from Etherpad Lite
 * https://raw.githubusercontent.com/ether/etherpad-lite/refs/heads/develop/src/static/js/Changeset.ts
 */
class Etherpad_Changeset {
    /**
     * Generates a changeset that describes a modification from $oldText to $newText
     * @param string $oldText The original text
     * @param int $startPos The starting position where changes begin
     * @param int $lengthToRemove The length of text to be removed from the original text
     * @param string $newText The new text that will be inserted
     * @return string Returns the encoded changeset string
     */
    public static function makeSplice($oldText, $startPos, $lengthToRemove, $newText) {
        // Get the length of the old and new text
        $oldLen = mb_strlen($oldText, 'UTF-8');
        $newLen = mb_strlen($newText, 'UTF-8');

        // Initialize the changeset string with the old text length
        $changeset = "Z:${oldLen}";

        // If the starting position is greater than 0, add the position to the changeset
        if ($startPos > 0) {
            $changeset .= ">" . $startPos;
        }

        // If there is text to be removed, add the length to be removed to the changeset
        if ($lengthToRemove > 0) {
            $changeset .= "-" . $lengthToRemove;
        }

        // If there is new text to be inserted, add the length of the new text to the changeset
        if ($newLen > 0) {
            $changeset .= "+" . $newLen;
        }

        // Append the new text after encoding it
        $changeset .= "$" . self::encodeText($newText);

        return $changeset;
    }

    /**
     * Encodes text by escaping special characters
     * @param string $text The text to encode
     * @return string Encoded text with special characters escaped
     */
    private static function encodeText($text) {
        return str_replace(
            ['\\', '$', '\n'],
            ['\\\\', '\\$', '\\n'],
            $text
        );
    }

    /**
     * Applies a changeset to a given text
     * @param string $changeset The changeset that describes the modifications
     * @param string $text The original text to which the changeset will be applied
     * @return string Returns the modified text after applying the changeset
     */
    public static function applyToText($changeset, $text) {
        // Extract the length of the old text and the operations from the changeset
        preg_match('/^Z:(\d+)(.*)$/', $changeset, $matches);
        $oldLen = intval($matches[1]);
        $ops = $matches[2];

        $newText = '';
        $textIndex = 0;
        $opIndex = 0;

        // Iterate through each operation in the changeset
        while ($opIndex < strlen($ops)) {
            if ($ops[$opIndex] === '>') {
                // '>' indicates text to be kept from the original text
                $opIndex++;
                $keep = 0;
                while ($opIndex < strlen($ops) && ctype_digit($ops[$opIndex])) {
                    $keep = $keep * 10 + intval($ops[$opIndex]);
                    $opIndex++;
                }
                // Append the kept text to the new text
                $newText .= mb_substr($text, $textIndex, $keep, 'UTF-8');
                $textIndex += $keep;
            } elseif ($ops[$opIndex] === '-') {
                // '-' indicates text to be removed from the original text
                $opIndex++;
                $remove = 0;
                while ($opIndex < strlen($ops) && ctype_digit($ops[$opIndex])) {
                    $remove = $remove * 10 + intval($ops[$opIndex]);
                    $opIndex++;
                }
                // Move the text index forward by the length to be removed
                $textIndex += $remove;
            } elseif ($ops[$opIndex] === '+') {
                // '+' indicates new text to be inserted
                $opIndex++;
                $insert = 0;
                while ($opIndex < strlen($ops) && ctype_digit($ops[$opIndex])) {
                    $insert = $insert * 10 + intval($ops[$opIndex]);
                    $opIndex++;
                }
                if ($ops[$opIndex] === '$') {
                    // Append the new text after decoding it
                    $opIndex++;
                    $newText .= self::decodeText(mb_substr($ops, $opIndex, $insert, 'UTF-8'));
                    $opIndex += $insert;
                }
            }
        }

        // Append any remaining text from the original text
        if ($textIndex < mb_strlen($text, 'UTF-8')) {
            $newText .= mb_substr($text, $textIndex, null, 'UTF-8');
        }

        return $newText;
    }

    /**
     * Decodes text by unescaping special characters
     * @param string $text The text to decode
     * @return string Decoded text with special characters unescaped
     */
    private static function decodeText($text) {
        return str_replace(
            ['\\\\', '\\$', '\\n'],
            ['\\', '$', "\n"],
            $text
        );
    }
}