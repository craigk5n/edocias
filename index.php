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

?>
<html>
<head>
<title>Document Search</title>
<style type="text/css">
dt a {
  font-size: 80%;
  font-weight: normal;
}
div.textmatch {
  font-size: 70%;
}
</style>
</head>
<body>

<?php

$q = getGetValue ( 'q' );

// Open database connection
connect_db ();

// Count how many documents
$res = dbi_execute ( 'SELECT COUNT(*) FROM edm_doc' );
if ( ! $res ) {
  echo "Database error: " . dbi_error ();
  exit;
}
$row = dbi_fetch_row ( $res );
$numDocs = $row[0];
dbi_free_result ( $res );

?>

<p>There are currently <b><?php echo $numDocs;?></b> documents.</p>

<form action="index.php" method="get">
<input type="text" size="40" name="q" value="<?php echo htmlentities ( stripslashes ( $q ) );?>"/>
<input type="submit" value="Search" />

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
  $sql = 'SELECT docid, filepath, date, mime, ocr FROM edm_doc WHERE ';
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
  $out = "<dl>\n";
  $cnt = 0;
  while ( $row = dbi_fetch_row ( $res ) ) {
    $cnt++;
    $docid = $row[0];
    $filepath = $row[1];
    $date = $row[2];
    $mime = $row[3];
    $ocr = $row[4];
    $icon = mime_to_icon ( $mime );
    $icon_url = empty ( $icon ) ?
      '&nbsp;&nbsp;' : '<img src="' . $icon . '" /> ';
    $out .= '<dt>' . $icon_url . '<a href="docview.php?id=' . $docid . '">' .
       htmlentities ( trim_filename ( $filepath ) ) .
       '</a></dt><dd>';
    $out .= show_matching_text ( $words, $ocr );
    $out .= '</dd>' . "\n";
  }
  $out .= "</dl>\n";
  echo "$cnt matching documents found<br/>";
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

?>

</body>
</html>
