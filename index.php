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
// Load bootstrap & jquery (managed by composer)
include_once "load_assets.php";

$simple = getValue ( 'simple' );
$simple = ! empty ( $simple );
$target = getValue ( 'target' );

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
$style = getValue ( 'style' );
if ( file_exists ( 'style.css' ) && ! $simple && empty ( $style ) ) {
  include_once "style.css";
} else if ( ! empty ( $style ) ) {
  if ( file_exists ( $style ) ) {
    include_once $style;
  }
}
// Include Bootstrap and jqyuery
echo $ASSETS;
?>
</head>
<body>
<div class="container-fluid"> 

<?php

// Allow customization of the appearance with a custom header file.
// We will include either 'header.html' or 'header.php';
if ( file_exists ( 'header.php' ) && ! $simple ) {
  include_once ( 'header.php' );
} else if ( file_exists ( 'header.html' ) ) {
  echo file_get_contents ( 'header.html' );
}

?>
<div class="container">
    <div class="row">
      <div class="col-12">
<?php

$q = getGetValue ( 'q' );
$filter = getGetValue ( 'f' );

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


<form class="form-inline" action="index.php" method="get"
<?php
  if ( ! empty ( $target ) ) {
    echo "target=\"" . htmlentities ( $target ) . "\"";
}
?>>
<div class="form-group mr-2">

<input type="text" class="form-control" size="40" name="q" value="<?php echo htmlentities ( stripslashes ( $q ) );?>"/>
<?php
$filterPath = '';
if ( ! $simple && is_array ( $searchFilters ) && count ( $searchFilters ) > 0 ) {
  echo '<select class="form-control" name="f" id="f"><option value="">All Documents</option>';
  for ( $i = 0; $i < count ( $searchFilters ); $i++ ) { 
    $f = $searchFilters[$i];
    $selected = ( ! empty ( $filter ) && $filter == $f['id'] );
    if ( $selected )
      $filterPath = $f['path'];
    echo '<option value="' . $f['id'] . '"' . ( $selected ? ' SELECTED="SELECTED"' : '' ) .
       '>' . htmlentities ( $f['name'] ) . "</option>\n";
  }
  echo "</select>";
}
?>
<input class="btn btn-primary" type="submit" value="<?php etranslate("Search");?>" />

</form>
</div>

<?php

$sql = '';
$sql_params = array ();
$words = array ();
$mostRecent = false;

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

  if ( ! empty ( $filterPath ) ) {
    $sql .= ' AND filepath LIKE(?)';
    $sql_params[] = '%' . $filterPath . '%';
  }

  $sql .= ' ORDER by date DESC';
  //echo "SQL: $sql<br>Params:<br/>"; print_r ( $sql_params ); echo "<br/>";
}
if ( empty ( $sql ) && $showMostRecent && $showMostRecentCount > 0 ) {
  $sql = 'SELECT docid, doctitle, filepath, date, mime, ocr FROM edm_doc ' .
    'ORDER BY process_date DESC, filepath LIMIT ' . $showMostRecentCount;
  $mostRecent = true;
}

if ( ! empty ( $sql ) ) {
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
    echo "<!-- icon for $mime is $icon -->\n";
    $icon_url = empty ( $icon ) ?
      '&nbsp;&nbsp;' : '<img src="' . $icon . '" /> ';
    $fmt_date = date_to_str ( date ( 'Ymd', $date ),
      $date_format, true, true );
    if ( preg_match ( '/([12][90]\d\d[01]\d[0-3]\d)/', $filepath, $match ) ) {
      // found a date in the filepath
      $file_date = $match[1];
      $fmt_date = date_to_str ( $file_date, $date_format, false, true );
    }
    // If the filepath starts with 'http://', then this is a remote URL
    // rather than a local file.
    $thisMatch = '';
    if ( preg_match ( '/http:\/\//', $filepath ) ) {
      $thisMatch .= '<dt>' . $icon_url . '<a href="' .
        $filepath . $langParam . '"' .
        ( empty ( $target ) ? '' : " target=\"" .
        urlencode ( $target ) . "\"" ) . '>' .
        ( empty ( $title ) ? htmlentities ( $filepath ) :
        htmlentities ( $title ) ) .
        '</a> -- ' . $fmt_date . '</dt><dd>';
    } else {
      $thisMatch .= '<dt>' . $icon_url . '<a href="docview.php/' .
        urlencode ( basename ( $filepath ) ) . '?id=' .
        $docid . '"' .
        ( empty ( $target ) ? '' : " target=\"" .
        urlencode ( $target ) . "\"" ) . '">' .
        htmlentities ( trim_filename ( $filepath ) ) .
        '</a> -- ' . $fmt_date . '</dt><dd><small>';
    }
    $thisMatch .= show_matching_text ( $words, $ocr );
    $thisMatch .= '</small></dd>' . "\n";
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
  if ( $mostRecent )
    echo $cnt . ' ' . translate('most recent documents') . "<br/>";
  else
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
      if (!empty($fs['icon'])) {
        $icon = 'icons/' . $fs['icon'];
      } else {
        $icon = 'icons/' . $fs['type'] . '.svg';
      }
      if ( file_exists ( $icon ) )
        return $icon;
      return false; // no such icon
    }
  }

  $icon = 'icons/filetype-' . $mime . '.svg';
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

// Allow customization of the appearance with a custom trailer file.
// We will include either 'trailer.html' or 'trailer.php';
if ( file_exists ( 'trailer.php' ) && ! $simple ) {
  include_once ( 'trailer.php' );
} else if ( file_exists ( 'trailer.html' ) ) {
  echo file_get_contents ( 'trailer.html' );
}
?>

</div>
</div>
</div>
</div>
</body>
</html>
