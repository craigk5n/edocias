<?php
/*
 * Main page for EDocIAS (Electronic Document Index And Search).
 * Presents the search form and displays search results in the case of
 * an HTTP POST request.
 */

error_reporting ( E_ALL );

// config.php contains database login info, file type settings
include_once "config.php";
// dbi4php.php has database API for multiple databases (Oracle, MySQL, etc.)
include_once "dbi4php.php";
// Common functions
include_once "functions.php";
// Functions for translating into different languages
include_once "translate.php";

// Allow 'lang' to be reset with URL parameter.
// Example: index.php?q=searchterm&lang=de
$langParam = '';
if ( ! empty ( $language ) ) {
  if ( empty ( $browser_languages[$language] ) ) {
    echo "Error: '" . $language . "' is not a valid language abbreviation\n";
    exit;
  }
  reset_language ( $browser_languages[$language] );
} else {
  $newLang = getGetValue ( 'lang' );
  if ( ! empty ( $newLang ) ) {
    if ( ! empty ( $browser_languages[$newLang] ) ) {
      reset_language ( $browser_languages[$newLang] );
    }
    $langParam = '&amp;lang=' . $newLang;
  }
}

?>
<html>
<head>
<title>Document Search</title>
<?php
// Allow customization of the appearance with a custom CSS file.
// We will include a style.css if we find one.
if ( file_exists ( 'style.css' ) ) {
  include_once "style.css";
}
?>
<style type="text/css">
dt a {
  font-size: 80%;
  font-weight: normal;
}
div.textmatch {
  font-size: 70%;
}
div.pagination {
  font-size: 80%;
}
div.pagination a, b{
}
div.pagination a.page, b.page {
  border: 1px solid #c0c0c0;
  background-color: #e0e0e0;
  padding-left: 3px;
  padding-right: 3px;
}
</style>
</head>
<body>

<?php

// Allow customization of the appearance with a custom header file.
// We will include either 'header.html' or 'header.php';
if ( file_exists ( 'header.php' ) ) {
  include_once ( 'header.php' );
} else if ( file_exists ( 'header.html' ) ) {
  echo file_get_contents ( 'header.html' );
}

$q = getGetValue ( 'q' );

// Open database connection
connect_db ();

// Count how many documents
$res = dbi_execute ( 'SELECT COUNT(*) FROM edm_doc' );
if ( ! $res ) {
  echo translate("Database error") . ": " . dbi_error ();
  exit;
}
$row = dbi_fetch_row ( $res );
$numDocs = $row[0];
dbi_free_result ( $res );

$numDocsStr = preg_replace ( '/XXX/', $numDocs, 
  translate ( 'There are currently XXX documents.' ) );

echo '<p>' . $numDocsStr . '</p>' . "\n";
?>


<form action="index.php" method="get">
<input type="text" size="40" name="q" value="<?php echo htmlentities ( stripslashes ( $q ) );?>"/>
<input type="submit" value="<?php etranslate("Search");?>" />

</form>

<?php
// If we are already searching... perform the search now.
if ( ! empty ( $q ) ) {
  $klen = strlen ( $q );
  $phrasedelim = "\\\"";
  $plen = strlen ( $phrasedelim );

  if ( substr ( $q, 0, $plen ) == $phrasedelim &&
      substr ( $q, $klen - $plen ) == $phrasedelim ) {
    $phrase = substr ( $q, $plen, $klen - ( $plen * 2 ) );
    $words = array ( $phrase );
  } else {
    // original (default) behavior
    $words = explode ( ' ', $q );
    // end Phrase modification
  }

  $order = 'DESC';
  $word_cnt = count ( $words );
  $sql = 'SELECT docid, doctitle, filepath, date, mime, ocr FROM edm_doc WHERE ';
  $sql_params = array ();
  for ( $i = 0; $i < $word_cnt; $i++ ) {
    if ( $i > 0 )
      $sql .= ' AND';
    $sql .= ' UPPER(ocr) LIKE UPPER(?)';
    $sql_params[] = '%' . $words[$i] . '%';
  }

  $sql .= ' ORDER by date DESC';
  //echo "SQL: $sql<br>Params:<br/>"; print_r ( $sql_params ); echo "<br/>";
  $res = dbi_execute ( $sql, $sql_params );
  $cnt = 0;
  $outArray = array ();
  while ( $row = dbi_fetch_row ( $res ) ) {
    $cnt++;
    $docid = $row[0];
    $title = $row[1];
    $filepath = $row[2];
    $date = $row[3];
    $mime = $row[4];
    $ocr = $row[5];
    $icon = mime_to_icon ( $mime );
    $icon_url = empty ( $icon ) ?
      '&nbsp;&nbsp;' : '<img src="' . $icon . '" /> ';
    // If the filepath starts with 'http://', then this is a remote URL
    // rather than a local file.
    $thisMatch = '';
    if ( preg_match ( '/http:\/\//', $filepath ) ) {
      $thisMatch .= '<dt>' . $icon_url . '<a href="' .
        $filepath . $langParam . '">' .
        ( empty ( $title ) ? htmlentities ( $filepath ) :
        htmlentities ( $title ) ) .
        '</a></dt><dd>';
    } else {
      $thisMatch .= '<dt>' . $icon_url . '<a href="docview.php?id=' . $docid . '">' .
        htmlentities ( trim_filename ( $filepath ) ) .
        '</a></dt><dd>';
   }
    $thisMatch .= show_matching_text ( $words, $ocr );
    $thisMatch .= '</dd>' . "\n";
    $outArray[] = $thisMatch;
  }
  if ( count ( $outArray ) > $maxMatchesBeforePagination ) {
    // Use pagination
    $p = getIntValue ( 'p' ); // default is first page (1)
    if ( empty ( $p ) )
      $p = 1;
    $start = ( $p - 1 ) * $matchesPerPage;
    $last = $start + $matchesPerPage - 1;
    if ( $last >= count ( $outArray ) - 1 )
      $last = count ( $outArray ) - 1;
    $numPages = ceil ( count ( $outArray ) / $matchesPerPage );
    $nav = "<div class=\"pagination\"><p>" . translate('Page') . " ";
    for ( $i = 1; $i <= $numPages; $i++ ) {
      $nav .= " ";
      if ( $i == $p ) {
        $nav .= "<b class=\"page\">$i</b>";
      } else {
        $nav .= '<a class="page" href="index.php?q=' . htmlentities ( $q ) .
          '&amp;p=' . $i . $langParam . '">' . $i . '</a>';
      }
    }
    $nav .= "</p>\n</div>";
    $out = $nav;
    $t = translate ( 'Showing matches XXXSTARTXXX to XXXENDXXX' );
    $t = preg_replace ( '/XXXSTARTXXX/', ( $start + 1 ), $t );
    $t = preg_replace ( '/XXXENDXXX/', ( $last + 1 ), $t );
    $out .= "<p>" . $t . "</p>\n";
    $out .= "<dl>\n";
    for ( $i = $start; $i < count ( $outArray ) && $i <= $last; $i++ ) {
      $out .= $outArray[$i];
    }
    $out .= "</dl>\n" . $nav;
  } else {
    // No pagination required
    $out = "<dl>\n";
    for ( $i = 0; $i < count ( $outArray ); $i++ ) {
      $out .= $outArray[$i];
    }
    $out .= "</dl>\n";
  }
  echo $cnt . ' ' . translate('matches found') . "<br/>";
  echo $out;
  dbi_free_result ( $res );
}

function mime_to_icon ( $mime )
{
  global $fileSpecs;

  for ( $i = 0; $i < count ( $fileSpecs ); $i++ ) {
    $fs = $fileSpecs[$i];
    if ( $fs['mime'] == $mime ) {
      $icon = 'icons/' . $fs['type'] . '-icon.png';
      if ( file_exists ( $icon ) )
        return $icon;
      return false; // no such icon
    }
  }

  $icon = 'icons/' . $mime . '-icon.png';
  if ( file_exists ( $icon ) )
    return $icon;

  return false; // mime type not found (oops)
}

function trim_filename ( $filepath )
{
  global $dirs;

  for ( $i = 0; $i < count ( $dirs ); $i++ ) {
    $d = $dirs[$i];
    if ( substr ( $filepath, 0, strlen ( $d ) ) == $d ) {
      // match found
      return substr ( $filepath, strlen ( $d ) + 1 );
    }
  }
  return $filepath;
}

function show_matching_text ( $words, $ocr )
{
  $ret = '<div class="textmatch">';
  $ocrU = strtoupper ( $ocr );

  for ( $i = 0; $i < count ( $words ); $i++ ) {
    $w = str_replace ( "\n", " ", $words[$i] );
    $w = strtoupper ( $w );
    $pos = strpos ( $ocrU, $w );
    //$ret .= "i: $i, pos: $pos <br>";
    if ( $pos > 0 ) {
      // found. grab 30 chars before and 30 chars after...
      if ( $i > 0 )
        $ret .= '<br/>';
      if ( $pos > 30 ) {
        $ss = substr ( $ocr, $pos - 40, 40 ) . "<b>" .
          substr ( $ocr, $pos, strlen ( $w ) ) . "</b>" .
          substr ( $ocr, $pos + strlen ( $w ), 30 );
        #$ss = substr ( $ocr, $pos - 30, 60 );
        $ret .= $ss;
        #$ret .= htmlentities ( $ss );
      } else {
        $ss = substr ( $ocr, 0, 60 );
        $ret .= htmlentities ( $ss );
      }
    }
  }

  $ret .= '</div>' . "\n";

  return $ret;
}

dbi_close ( $c );

// Allow customization of the appearance with a custom tailer file.
// We will include either 'tailer.html' or 'tailer.php';
if ( file_exists ( 'tailer.php' ) ) {
  include_once ( 'tailer.php' );
} else if ( file_exists ( 'tailer.html' ) ) {
  echo file_get_contents ( 'tailer.html' );
}
?>

</body>
</html>
