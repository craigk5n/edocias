<?php
/*
 * EDocIAS (Electronic Document Index And Search).
 *
 * Recursively scan the specified directories for files.
 * This tools is meant to be used from the command line using the CLI
 * verison of php:
 *   php scan.php
 *
 * See README.txt for a list of tools to convert various file formats
 * (pdf, doc, xls, jpeg, png, etc.) into simple text for use in the search
 * index.  You can also check the wiki which may have info on various
 * tools for extracting text from different file formats:
 *   https://sourceforge.net/p/edocias/wiki/
 */

error_reporting ( E_ALL ); // report all errors
putenv("PAPERSIZE=letter");

// Require command line usage only (for added security)
if ( PHP_SAPI != 'cli' ) {
  echo "Command line usage only!\n";
  exit;
}

include_once "config.php";
include_once "functions.php";
include_once "translate.php";
include_once "dbi4php.php";

$outFile = "textout.txt"; // temp file used to store plain text

$memory_limit = ini_get('memory_limit');
if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
    if ($matches[2] == 'M') {
        $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
    } else if ($matches[2] == 'K') {
        $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
    }
} else {
    $memory_limit = -1;
}

// Don't allocate more than 50% of limit
$mlimit = $memory_limit > 0 ? $memory_limit * 0.50 : 512 * 1024 * 1024;

echo "Available memory: $memory_limit\n";
echo "Won't use more than: $mlimit\n";

// Open database connection
// Database settings are found in config.php.
$c = dbi_connect ( $db_host, $db_login, $db_password, $db_database );

if ( ! $c ) {
  echo "Error connecting to database: " . dbi_error () . "\n";
  exit;
}

$total = $ignored = $prior = $processed = $unknown = 0;

$existingFiles = array ();

foreach ( $dirs as $dir ) {
  if ( empty ( $skipDirs[$dir] ) ) {
    if ( !file_exists($dir) || ! is_dir($dir) ) {
      print "Ignoring missing dir: $dir\n";
    } else {
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
        } else if ( doc_is_up_to_date ( $filename ) ) {
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
            $mtime = @filemtime ( $filename );
            if ( file_exists ( $filename ) && preg_match ( "/\.$re$/i", $cur ) ) {
              $processed++;
              $didThis = true;
              echo "File: $filename\n";
              $escCur = str_replace ( '"', '\\"', $cur );
              $cmd = str_replace ( '%FILE%', $escCur, $command );
              echo "COMMAND: $cmd\n";
              $out = "$tempDir/$outFile";
	      if ( file_exists ( "$out" ) )
                @unlink ( $out ); # remove old output
              exec ( $cmd );
	      if ( file_exists ( "$out" ) ) {
                //$text = file_get_contents ( $out );
                $fh = fopen ( $out, 'r' );
                $stat = lstat ( $out );
                $filesize = $stat['size'];
                // echo "Filesize: $filesize\n";
                $text = fread ( $fh, $filesize );
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
        $existingFiles[$filename] = 1;
      }
    }
  }
}

// Examine database for files that may have been deleted or renamed.
$res = dbi_execute (
  'SELECT filepath, docid FROM edm_doc WHERE filepath LIKE "/%"' );
$deleted = 0;
while ( $row = dbi_fetch_row ( $res ) ) {
  $filepath = $row[0];
  $docid = $row[1];
  if ( empty ( $existingFiles[$filepath] ) && ! file_exists ( $filepath ) ) {
    echo "Deleted file found: $filepath\n";
    dbi_execute ( 'DELETE FROM edm_doc WHERE filepath = ?',
      array ( $filepath ) );
    $deleted++;
  }
}
dbi_free_result ( $res );

echo "Total files: $total\n";
echo "Previously processed: $prior\n";
echo "Processed now: $processed\n";
echo "Unknown file types: $unknown\n";
echo "Ignored: $ignored\n";
echo "Deleted: $deleted\n";

dbi_close ( $c );

exit;


/**
  * Does the document specified already exist in our database (and therefore
  * has already been processed)?
  */
function doc_is_up_to_date ( $filename )
{
  global $mlimit;;
  $ret = false;

  $mtime = @filemtime ( $filename );

  $res = dbi_execute ( 'SELECT date, md5hash FROM edm_doc WHERE filepath = ?',
    array ( $filename ) );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    if ( is_array ( $row ) ) {
      $stillExists = file_exists ( $filename );
      $prevMtime = $row[0];
      $hash = $row[1];
      dbi_free_result ( $res );
      $fsize = filesize ( $filename );
      $modTimeMatches = ( $mtime == $prevMtime );
      $hashMatches = true; // until we say otherwise...
      if ( ! $stillExists || $fsize > $mlimit ) {
        # Just rely on file mod time....  too big
      } else {
        // Only do this is file mod time is different.
        // No need to keep hashing files over and over...
        if ( ! $modTimeMatches ) {
          $data = file_get_contents ( $filename );
          $newhash = hash ( "md5", $data );
          if ( $newhash != $hash ) {
            // file has been modified since last run
            // delete old entry
            $hashMatches = false;
	    print "Hash mismatch, doc updated\n";
          }
        }
      }
      $ret = $modTimeMatches && $hashMatches && $stillExists;
      if ( ! $ret ) {
        #print "modTimeMatches = $modTimeMatches, hashMatches = $hashMatches\n";
        // file has been modified since last run
        // delete old entry
        dbi_execute ( 'DELETE FROM edm_doc WHERE filepath = ?',
          array ( $filename ) );
      }
    }
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

  //$data = file_get_contents ( $filename );
  //$hash = hash ( "md5", $data );
  $hash = md5_file ( $filename );

  if ( ! dbi_execute (
    'INSERT INTO edm_doc ( docid, filepath, md5hash, mime, date, process_date, ocr ) ' .
    'VALUES ( ?, ?, ?, ?, ?, ?, ? )',
    array ( $docid, $filename, $hash, $mime, $mod_date, $time, $allText ) ) ) {
    echo "ERROR: " . dbi_error () . "\n";
  }
}

?>
