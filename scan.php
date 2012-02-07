<?php
/*
 * EDocIAS (Electronic Document Index And Search).
 *
 * Recursively scan the specified directories for image and PDF files.
 * This tools is meant to be used from the command line using the CLI
 * verison of php:
 *   php scan.php
 *
 * See README.txt for a list of tools to convert various file formats
 * (pdf, doc, xls, jpeg, png, etc.) into simple text for use in the search
 * index.
 *
 * TODO: check modification time of files and reprocess if they are updated.
 */

error_reporting ( E_ALL ); // report all errors

// Require command line usage only (for added security)
if ( PHP_SAPI != 'cli' ) {
  echo "Command line usage only!\n";
  exit;
}

include_once "config.php";
include_once "dbi4php.php";

$outFile = "textout.txt"; // temp file used to store plain text

// Open database connection
// Database settings are found in config.php.
$c = dbi_connect ( $db_host, $db_login, $db_password, $db_database );

if ( ! $c ) {
  echo "Error connecting to database: " . dbi_error () . "\n";
  exit;
}

$total = $ignored = $prior = $processed = $unknown = 0;

foreach ( $dirs as $dir ) {
  $ite=new RecursiveDirectoryIterator ( $dir );
  foreach (new RecursiveIteratorIterator($ite) as $filename=>$cur) {
    chdir ( $tempDir );
    $ignore = false;
    $total++;
    foreach ( $skipFolders as $skip ) {
      if ( strpos ( $filename, $skip ) > 0 ) {
        $ignore = true;
        //echo "Ignore: found '$skip' in '$filename'\n";
      }
    }
    if ( $ignore ) {
      // do nothing :-)
      $ignored++;
    } else if ( doc_exists ( $filename ) ) {
      // Ignore
      // TODO: check timestamp in case doc was updated
      $prior++;
    } else {
      $didThis = false;
      foreach ( $fileSpecs as $fileSpec ) {
        $type = $fileSpec['type'];
        $command = $fileSpec['command'];
        $re = $fileSpec['regex'];
        $mime = $fileSpec['mime'];
        $mtime = filemtime ( $filename );
        if ( preg_match ( "/\.$re$/i", $cur ) ) {
          $processed++;
          $didThis = true;
          echo "File: $filename\n";
          $cmd = str_replace ( '%FILE%', $cur, $command );
          echo "COMMAND: $cmd\n";
          exec ( $cmd );
          $out = "$tempDir/$outFile";
	  if ( file_exists ( "$out" ) ) {
            //$text = file_get_contents ( $out );
            $fh = fopen ( $out, 'r' );
            $text = fread ( $fh, filesize ( $out ) );
            fclose ( $fh );
            //echo "\n\nTEXT:\n$text\n";
            store_file ( $filename, $mime, $mtime, $text );
            echo "Processed: $filename\n";
            echo "  Text: " . str_replace ( "\n", ' ', substr ( $text, 0, 60 ) ) . "\n";
            unlink ( "$out" );
          } else {
            echo "ERROR: no output created for input file $filename\n";
          }
          //sleep ( 3 );
        }
      }
      if ( ! $didThis ) $unknown++;
    }
  }
}

echo "Total files: $total\n";
echo "Previously processed: $prior\n";
echo "Processed now: $processed\n";
echo "Unknown file types: $unknown\n";
echo "Ignored: $ignored\n";

dbi_close ( $c );

exit;


/**
  * Does the document specified already exist in our database (and therefore
  * has already been processed)?
  */
function doc_exists ( $filename )
{
  $ret = false;

  $res = dbi_execute ( 'SELECT COUNT(*) FROM edm_doc WHERE filepath = ?',
    array ( $filename ) );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    $ret = ( $row[0] > 0 );
    dbi_free_result ( $res );
  }
  return $ret;
}



/**
  * Generate a unique document ID by a simple increment of the current
  * max ID.
  */
function generate_docid ()
{
  $ret = 1;

  $res = dbi_execute ( 'SELECT MAX(docid) FROM edm_doc', array () );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    $ret = $row[0] + 1;
    dbi_free_result ( $res );
  }

  return $ret;
}


/**
  * Store the file and associated information in the database.
  * Params:
  *   $filename - full path to the file.
  *   $mime - mime type of the file (as determined using settings in config.php)
  *   $mod_date - UNIX time (seconds since 1970) file modificaiton date
  *   $text - plain text extract from file for use in search index.
  */
function store_file ( $filename, $mime, $mod_date, $text )
{
  $docid = generate_docid ();
  $allText = str_replace ( '/', ' ', $filename ) . ' ' . $text;
  $time = time ();

  if ( ! dbi_execute (
    'INSERT INTO edm_doc ( docid, filepath, mime, date, process_date, ocr ) ' .
    'VALUES ( ?, ?, ?, ?, ?, ? )',
    array ( $docid, $filename, $mime, $mod_date, $time, $allText ) ) ) {
    echo "ERROR: " . dbi_error () . "\n";
  }
}


/**
  * No-op function required by dbi4php.php (which was copied from the
  * WebCalendar project.)  Eventually, this may be replaced with the
  * translate code from WebCalendar to support multiple languages.
  */
function translate ( $str ) {
  return $str;
}


?>
