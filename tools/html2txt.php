<?php
/**
  * EDocIAS
  *
  * Extract plain text from an HTML file using the phphtmlparser tool
  * (which is included below).
  *
  * The phphtmlparser tools is an open source (Apache v1.1 license).
  * More info on this tool is at the following URL:
  *   http://php-html.sourceforge.net/html2text.php
  */

// Turn off warning notices since this phphtmlparser generates a lot of
// warnings.
error_reporting ( E_ALL & ~E_NOTICE );

// Require command line usage only (for added security)
if ( PHP_SAPI != 'cli' ) {
  echo "Command line usage only!\n";
  exit;
}

if ( count ( $argv ) != 2 ) {
  echo "Usage is: php $argv[0] infile.html\n";
  exit;
}

$html = file_get_contents ( $argv[1] );

$conv = new Html2Text ( $html, 15 );
$text = $conv->convert ();

echo $text;

?>



<?php // Below is the originnal htmlparser.inc and html2txt.inc files...  ?>
<?php

/*
 * Copyright (c) 2003 Jose Solorzano.  All rights reserved.
 * Redistribution of source must retain this copyright notice.
 *
 * Jose Solorzano (http://jexpert.us) is a software consultant.
 *
 * Contributions by:
 * - Leo West (performance improvements)
 */

define ("NODE_TYPE_START",0);
define ("NODE_TYPE_ELEMENT",1);
define ("NODE_TYPE_ENDELEMENT",2);
define ("NODE_TYPE_TEXT",3);
define ("NODE_TYPE_COMMENT",4);
define ("NODE_TYPE_DONE",5);

/**
 * Class HtmlParser.
 * To use, create an instance of the class passing
 * HTML text. Then invoke parse() until it's false.
 * When parse() returns true, $iNodeType, $iNodeName
 * $iNodeValue and $iNodeAttributes are updated.
 *
 * To create an HtmlParser instance you may also
 * use convenience functions HtmlParser_ForFile
 * and HtmlParser_ForURL.
 */
class HtmlParser {

    /**
     * Field iNodeType.
     * May be one of the NODE_TYPE_* constants above.
     */
    var $iNodeType;

    /**
     * Field iNodeName.
     * For elements, it's the name of the element.
     */
    var $iNodeName = "";

    /**
     * Field iNodeValue.
     * For text nodes, it's the text.
     */
    var $iNodeValue = "";

    /**
     * Field iNodeAttributes.
     * A string-indexed array containing attribute values
     * of the current node. Indexes are always lowercase.
     */
    var $iNodeAttributes;

    // The following fields should be 
    // considered private:

    var $iHtmlText;
    var $iHtmlTextLength;
    var $iHtmlTextIndex = 0;
    var $iHtmlCurrentChar;
    var $BOE_ARRAY;
    var $B_ARRAY;
    var $BOS_ARRAY;
    
    /**
     * Constructor.
     * Constructs an HtmlParser instance with
     * the HTML text given.
     */
    function HtmlParser ($aHtmlText) {
        $this->iHtmlText = $aHtmlText;
        $this->iHtmlTextLength = strlen($aHtmlText);
        $this->iNodeAttributes = array();
        $this->setTextIndex (0);

        $this->BOE_ARRAY = array (" ", "\t", "\r", "\n", "=" );
        $this->B_ARRAY = array (" ", "\t", "\r", "\n" );
        $this->BOS_ARRAY = array (" ", "\t", "\r", "\n", "/" );
    }

    /**
     * Method parse.
     * Parses the next node. Returns false only if
     * the end of the HTML text has been reached.
     * Updates values of iNode* fields.
     */
    function parse() {
        $text = $this->skipToElement();
        if ($text != "") {
            $this->iNodeType = NODE_TYPE_TEXT;
            $this->iNodeName = "Text";
            $this->iNodeValue = $text;
            return true;
        }
        return $this->readTag();
    }

    function clearAttributes() {
        $this->iNodeAttributes = array();
    }

    function readTag() {
        if ($this->iCurrentChar != "<") {
            $this->iNodeType = NODE_TYPE_DONE;
            return false;
        }
        $this->clearAttributes();
        $this->skipMaxInTag ("<", 1);
        if ($this->iCurrentChar == '/') {
            $this->moveNext();
            $name = $this->skipToBlanksInTag();
            $this->iNodeType = NODE_TYPE_ENDELEMENT;
            $this->iNodeName = $name;
            $this->iNodeValue = "";            
            $this->skipEndOfTag();
            return true;
        }
        $name = $this->skipToBlanksOrSlashInTag();
        if (!$this->isValidTagIdentifier ($name)) {
                $comment = false;
                if (strpos($name, "!--") === 0) {
                    $ppos = strpos($name, "--", 3);
                    if (strpos($name, "--", 3) === (strlen($name) - 2)) {
                        $this->iNodeType = NODE_TYPE_COMMENT;
                        $this->iNodeName = "Comment";
                        $this->iNodeValue = "<" . $name . ">";
                        $comment = true;                        
                    }
                    else {
                        $rest = $this->skipToStringInTag ("-->");    
                        if ($rest != "") {
                            $this->iNodeType = NODE_TYPE_COMMENT;
                            $this->iNodeName = "Comment";
                            $this->iNodeValue = "<" . $name . $rest;
                            $comment = true;
                            // Already skipped end of tag
                            return true;
                        }
                    }
                }
                if (!$comment) {
                    $this->iNodeType = NODE_TYPE_TEXT;
                    $this->iNodeName = "Text";
                    $this->iNodeValue = "<" . $name;
                    return true;
                }
        }
        else {
                $this->iNodeType = NODE_TYPE_ELEMENT;
                $this->iNodeValue = "";
                $this->iNodeName = $name;
                while ($this->skipBlanksInTag()) {
                    $attrName = $this->skipToBlanksOrEqualsInTag();
                    if ($attrName != "" && $attrName != "/") {
                        $this->skipBlanksInTag();
                        if ($this->iCurrentChar == "=") {
                            $this->skipEqualsInTag();
                            $this->skipBlanksInTag();
                            $value = $this->readValueInTag();
                            $this->iNodeAttributes[strtolower($attrName)] = $value;
                        }
                        else {
                            $this->iNodeAttributes[strtolower($attrName)] = "";
                        }
                    }
                }
        }
        $this->skipEndOfTag();
        return true;            
    }

    function isValidTagIdentifier ($name) {
	// cek: Changed ereg to preg_match to avoid PHP warning
        return preg_match ("/^[A-Za-z0-9_\\-]+$/", $name);
    }
    
    function skipBlanksInTag() {
        return "" != ($this->skipInTag ($this->B_ARRAY));
    }

    function skipToBlanksOrEqualsInTag() {
        return $this->skipToInTag ($this->BOE_ARRAY);
    }

    function skipToBlanksInTag() {
        return $this->skipToInTag ($this->B_ARRAY);
    }

    function skipToBlanksOrSlashInTag() {
        return $this->skipToInTag ($this->BOS_ARRAY);
    }

    function skipEqualsInTag() {
        return $this->skipMaxInTag ("=", 1);
    }

    function readValueInTag() {
        $ch = $this->iCurrentChar;
        $value = "";
        if ($ch == "\"") {
            $this->skipMaxInTag ("\"", 1);
            $value = $this->skipToInTag ("\"");
            $this->skipMaxInTag ("\"", 1);
        }
        else if ($ch == "'") {
            $this->skipMaxInTag ("'", 1);
            $value = $this->skipToInTag ("'");
            $this->skipMaxInTag ("'", 1);
        }                
        else {
            $value = $this->skipToBlanksInTag();
        }
        return $value;
    }

    function setTextIndex ($index) {
        $this->iHtmlTextIndex = $index;
        if ($index >= $this->iHtmlTextLength) {
            $this->iCurrentChar = -1;
        }
        else {
            $this->iCurrentChar = $this->iHtmlText{$index};
        }
    }

    function moveNext() {
        if ($this->iHtmlTextIndex < $this->iHtmlTextLength) {
            $this->setTextIndex ($this->iHtmlTextIndex + 1);
            return true;
        }
        else {
            return false;
        }
    }

    function skipEndOfTag() {
        while (($ch = $this->iCurrentChar) !== -1) {
            if ($ch == ">") {
                $this->moveNext();
                return;
            }
            $this->moveNext();
        }
    }

    function skipInTag ($chars) {
        $sb = "";
        while (($ch = $this->iCurrentChar) !== -1) {
            if ($ch == ">") {
                return $sb;
            } else {
                $match = false;
                for ($idx = 0; $idx < count($chars); $idx++) {
                    if ($ch == $chars[$idx]) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    return $sb;
                }
                $sb .= $ch;
                $this->moveNext();
            }
        }
        return $sb;
    }

    function skipMaxInTag ($chars, $maxChars) {
        $sb = "";
        $count = 0;
        while (($ch = $this->iCurrentChar) !== -1 && $count++ < $maxChars) {
            if ($ch == ">") {
                return $sb;
            } else {
                $match = false;
                for ($idx = 0; $idx < count($chars); $idx++) {
                    if ($ch == $chars[$idx]) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    return $sb;
                }
                $sb .= $ch;
                $this->moveNext();
            }
        }
        return $sb;
    }

    function skipToInTag ($chars) {
        $sb = "";
        while (($ch = $this->iCurrentChar) !== -1) {
            $match = $ch == ">";
            if (!$match) {
                for ($idx = 0; $idx < count($chars); $idx++) {
                    if ($ch == $chars[$idx]) {
                        $match = true;
                        break;
                    }
                }
            }
            if ($match) {
                return $sb;
            }
            $sb .= $ch;
            $this->moveNext();
        }
        return $sb;
    }

    function skipToElement() {
        $sb = "";
        while (($ch = $this->iCurrentChar) !== -1) {
            if ($ch == "<") {
                return $sb;
            }
            $sb .= $ch;
            $this->moveNext();
        }
        return $sb;             
    }

    /**
     * Returns text between current position and $needle,
     * inclusive, or "" if not found. The current index is moved to a point
     * after the location of $needle, or not moved at all
     * if nothing is found.
     */
    function skipToStringInTag ($needle) {
        $pos = strpos ($this->iHtmlText, $needle, $this->iHtmlTextIndex);
        if ($pos === false) {
            return "";
        }
        $top = $pos + strlen($needle);
        $retvalue = substr ($this->iHtmlText, $this->iHtmlTextIndex, $top - $this->iHtmlTextIndex);
        $this->setTextIndex ($top);
        return $retvalue;
    }
}

function HtmlParser_ForFile ($fileName) { 
    return HtmlParser_ForURL($fileName);
}

function HtmlParser_ForURL ($url) {
    $fp = fopen ($url, "r");
    $content = "";
    while (true) {
        $data = fread ($fp, 8192);
        if (strlen($data) == 0) {
            break;
        }
        $content .= $data;
    }
    fclose ($fp);
    return new HtmlParser ($content);
}

/*
 * Copyright (c) 2003 Jose Solorzano.  All rights reserved.
 * Redistribution of source must retain this copyright notice.
 */


/**
 * Class Html2Text. (HtmlParser example.)
 * Converts HTML to ASCII attempting to preserve
 * document structure. 
 * To use, create an instance of Html2Text passing
 * the text to convert and the desired maximum
 * number of characters per line. Then invoke 
 * convert() which returns ASCII text.
 */
class Html2Text {

    // Private fields
  
    var $iCurrentLine = "";
    var $iCurrentWord = "";
    var $iCurrentWordArray;
    var $iCurrentWordIndex;
    var $iInScript;
    var $iListLevel = 0;
    var $iHtmlText;
    var $iMaxColumns;
    var $iHtmlParser;

    // Constants

    var $TOKEN_BR       = 0;
    var $TOKEN_P        = 1;
    var $TOKEN_LI       = 2;
    var $TOKEN_AFTERLI  = 3;
    var $TOKEN_UL       = 4;
    var $TOKEN_ENDUL    = 5;
   
    function Html2Text ($aHtmlText, $aMaxColumns) {
        $this->iHtmlText = $aHtmlText;
        $this->iMaxColumns = $aMaxColumns;
    }

    function convert() {
        $this->iHtmlParser = new HtmlParser($this->iHtmlText);
        $wholeText = "";
        while (($line = $this->getLine()) !== false) {
            $wholeText .= ($line . "\r\n");
        }
        return $wholeText;
    }

    function getLine() {
        while (true) {
            if (!$this->addWordToLine($this->iCurrentWord)) {
                $retvalue = $this->iCurrentLine;
                $this->iCurrentLine = "";
                return $retvalue;
            }                
            $word = $this->getWord();
            if ($word === false) {
                if ($this->iCurrentLine == "") {
                    break;
                }
                $retvalue = $this->iCurrentLine;
                $this->iCurrentLine = "";
                $this->iInText = false;
                $this->iCurrentWord = "";
                return $retvalue;                
            }
        }
        return false;
    }

    function addWordToLine ($word) {
        if ($this->iInScript) {
            return true;
        }
        $prevLine = $this->iCurrentLine;
        if ($word === $this->TOKEN_BR) {
            $this->iCurrentWord = "";
            return false;
        }
        if ($word === $this->TOKEN_P) {
            $this->iCurrentWord = $this->TOKEN_BR;
            return false;
        }
        if ($word === $this->TOKEN_UL) {
            $this->iCurrentWord = $this->TOKEN_BR;
            return false;
        }
        if ($word === $this->TOKEN_ENDUL) {
            $this->iCurrentWord = $this->TOKEN_BR;
            return false;
        }
        if ($word === $this->TOKEN_LI) {
            $this->iCurrentWord = $this->TOKEN_AFTERLI;
            return false;
        }
        $toAdd = $word;
        if ($word === $this->TOKEN_AFTERLI) {
            $toAdd = "";
        }
        if ($prevLine != "") {
            $prevLine .= " ";
        }
        else {
            $prevLine = $this->getIndentation($word === $this->TOKEN_AFTERLI);
        }
        $candidateLine = $prevLine . $toAdd;
        if (strlen ($candidateLine) > $this->iMaxColumns && $prevLine != "") {
            return false;
        }
        $this->iCurrentLine = $candidateLine;
        return true;
    }

    function getWord() {
        while (true) {
            if ($this->iHtmlParser->iNodeType == NODE_TYPE_TEXT) {
                if (!$this->iInText) {
                    $words = $this->splitWords($this->iHtmlParser->iNodeValue);
                    $this->iCurrentWordArray = $words;
                    $this->iCurrentWordIndex = 0;
                    $this->iInText = true;
                }
                if ($this->iCurrentWordIndex < count($this->iCurrentWordArray)) {
                    $this->iCurrentWord = $this->iCurrentWordArray[$this->iCurrentWordIndex++];
                    return $this->iCurrentWord;
                }
                else {
                    $this->iInText = false;
                }
            }
            else if ($this->iHtmlParser->iNodeType == NODE_TYPE_ELEMENT) {
                if (strcasecmp ($this->iHtmlParser->iNodeName, "br") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = $this->TOKEN_BR;
                    return $this->iCurrentWord;
                }
                else if (strcasecmp ($this->iHtmlParser->iNodeName, "p") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = $this->TOKEN_P;
                    return $this->iCurrentWord;
                }
                else if (strcasecmp ($this->iHtmlParser->iNodeName, "script") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = "";
                    $this->iInScript = true;
                    return $this->iCurrentWord;
                }
                else if (strcasecmp ($this->iHtmlParser->iNodeName, "ul") == 0 || strcasecmp ($this->iHtmlParser->iNodeName, "ol") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = $this->TOKEN_UL;
                    $this->iListLevel++;
                    return $this->iCurrentWord;
                }
                else if (strcasecmp ($this->iHtmlParser->iNodeName, "li") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = $this->TOKEN_LI;
                    return $this->iCurrentWord;
                }
            }
            else if ($this->iHtmlParser->iNodeType == NODE_TYPE_ENDELEMENT) {
                if (strcasecmp ($this->iHtmlParser->iNodeName, "script") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = "";
                    $this->iInScript = false;
                    return $this->iCurrentWord;
                }
                else if (strcasecmp ($this->iHtmlParser->iNodeName, "ul") == 0 || strcasecmp ($this->iHtmlParser->iNodeName, "ol") == 0) {
                    $this->iHtmlParser->parse();
                    $this->iCurrentWord = $this->TOKEN_ENDUL;
                    if ($this->iListLevel > 0) {
                        $this->iListLevel--;
                    }
                    return $this->iCurrentWord;
                }
            }
            if (!$this->iHtmlParser->parse()) {
                break;
            }
        }
        return false;
    }

    function splitWords ($text) {
        // cek: replaced split with preg_split
        $words = preg_split ("/[ \t\r\n]+/", $text);
        for ($idx = 0; $idx < count($words); $idx++) {
            $words[$idx] = $this->htmlDecode($words[$idx]);
        }
        return $words;
    }

    function htmlDecode ($text) {
        // TBD
        return $text;
    } 

    function getIndentation ($hasLI) {
        $indent = "";
        $idx = 0;
        for ($idx = 0; $idx < ($this->iListLevel - 1); $idx++) {
            $indent .= "  ";
        }
        if ($this->iListLevel > 0) {
            $indent = $hasLI ? ($indent . "- ") : ($indent . "  ");
        }
        return $indent;
    }
}
