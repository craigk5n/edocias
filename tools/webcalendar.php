<?php
/**
  * EDocIAS
  *
  * Extract plain text from a WebCalendar database.  Subsequent runs will
  * only add updated/new events since last scan.
  *
  * Usage is:
  *	php webcal2txt.php webcalDirectory
  *
  * Warning:
  *	This script will get ALL events regardless of their access
  *	control or privacy settings within WebCalendar.
  *	This script will included DELETED events also.
  */

// Turn on PHP warnings.
error_reporting ( E_ALL );

include_once "../dbi4php.php";

// Config settings
$includeDeleted = false; // Include deleted events?
// End config settings

// Require command line usage only (for added security)
if ( PHP_SAPI != 'cli' ) {
  echo "Command line usage only!\n";
  exit;
}

if ( count ( $argv ) != 2 ) {
  echo "Usage is: php $argv[0] webcalendarDirectory\n";
  exit;
}

$wcDir = $argv[1];

if ( ! file_exists ( $wcDir ) ) {
  echo "Error: directory not found $wcDir\n";
  exit;
}

echo "Processing webcalendar directory: $wcDir\n";
$settingsFile = "$wcDir/includes/settings.php";

read_settings ( $settingsFile );

echo "Accessing WebCalendar version: $wcVersion\n";

// Get the server URL.
$rows = dbi_get_cached_rows ( 'SELECT cal_value FROM webcal_config WHERE cal_setting = "SERVER_URL"' );
if ( ! $rows ) {
  echo "Error getting WebCalendar server URL\n"; exit;
}
$serverUrl = $rows[0][0];
if ( empty ( $serverUrl ) ) {
  echo "Error: could not find SERVER_URL in webcal_config\n"; exit;
}
echo "Using WebCalendar URL: $serverUrl\n";

// We just get all events regardless of status.  This is the easiest
// way so we don't end up with 10 entries if there are 10 participants.
// However, we end up getting deleted events, too.
// Sort with most recently modified first.
$sql = 'SELECT cal_id, cal_date, cal_name, cal_location, cal_description, ' .
  'cal_mod_date, cal_mod_time FROM ' .
  'webcal_entry ORDER by cal_mod_date, cal_date DESC';
$rows = dbi_get_cached_rows ( $sql, array () );
$events = array ();
for ( $i = 0; $i < count ( $rows ); $i++ ) {
  $e = $rows[$i];
  $url = $serverUrl . 'view_entry.php?id=' . $e[0];
  $text = "Date: " . date_to_str ( $e[1] ) . "\n";
  $text .= "Event name: $e[2]\n";
  if ( ! empty ( $e[3] ) )
    $text .= "Location: $e[3]\n";
  if ( ! empty ( $e[4] ) )
    $text .= "Description: $e[4]\n";
  $mdate = $e[5];
  $mtime = $e[6];
  // Convert the webcalendar event modification date/time to UNIX time,
  // keeping in mind it is stored in GMT format rather than localtime.
  $modtime = gmt_datetimestr_to_unix ( $mdate . $mtime );
  //echo "modtime = $modtime, mdate=$mdate, mtime=$mtime\n";
  $ev = array (
    'url' => $url,
    'title' => $e[2],
    'text' => $text,
    'date' => datestr_to_unixtime ( $e[1] ), // date of event
    'mtime' => $modtime,
  );
  $events[] = $ev;
  //echo "URL: $url\n$text";
}

dbi_close ( $c );
echo "Done with WebCalendar.  Database connection closed.\n";

// Now open database with EDoCIAS in it (which may or may not be different
// than the webcalendar database).
// TODO: handle updated events by updating the edm_doc entry.
echo "Connecting to EDocIAS database...\n";
include_once "../config.php";

$c = dbi_connect ( $db_host, $db_login, $db_password, $db_database );
if ( ! $c ) {
  echo "Error: could not connect to database\n" . dbi_error () . "\n";
  exit;
}

//print_r ( $events ); exit;
$eventCnt = 0; $ignored = 0;
for ( $i = 0; $i < count ( $events ); $i++ ) {
  $event = $events[$i];
  $url = $event['url'];
  $sql = 'SELECT process_date FROM edm_doc WHERE filepath = ?';
  $args = array ( $url );
  $rows = dbi_get_cached_rows( $sql, $args );
  $doAdd = false;
  $oldMtime = 0;
  if ( ! empty ( $rows ) && ! empty ( $rows[0] ) )
    $oldMtime = $rows[0][0];
  if ( $oldMtime == 0 ) {
    $doAdd = true;
  } else if ( $event['mtime'] > $oldMtime ) {
     //echo 'Updated: ' . $event['title'] . "\n";
     //echo "\tOld process time: " . date("Ymd His", $oldMtime ) . "\n";
     //echo "\tEvent mod time:   " . date("Ymd His", $event['mtime'] ) . "\n";
     $doAdd = true;
     // Now delete previous entry since it is out-of-date.
     dbi_execute ( 'DELETE FROM edm_doc WHERE filepath = ?', $args );
  }
  if ( $doAdd ) {
    $eventCnt++;
    // New event.  Add it.
    $docid = generate_docid ();
    $time = time ();
    $allText = $event['text'];
    if ( ! dbi_execute (
      'INSERT INTO edm_doc ( docid, doctitle, filepath, mime, date, process_date, ocr ) ' .
      'VALUES ( ?, ?, ?, ?, ?, ?, ? )',
      array ( $docid, $event['title'], $url, 'url', $event['date'], $time, $allText ) ) ) {
      echo "ERROR: " . dbi_error () . "\n";
    }
  } else {
    $ignored++;
  }
}

echo "Added $eventCnt events.\n";
echo "Ignored $ignored previously scanned events.\n";

dbi_close ( $c );

exit;

/**
  * Convert GMT date (YYYYMMDD) and time (HHMMSS) into UNIX time format
  */
function gmt_datetimestr_to_unix ( $d ) {
  if ( $d == 0 )
    return 0;

  $dH = $di = $ds = 0;
  if ( strlen ( $d ) == 13 ) { // Hour value is single digit.
    $dH = (int)substr ( $d, 8, 1 );
    $di = (int)substr ( $d, 9, 2 );
    $ds = (int)substr ( $d, 11, 2 );
  }
  if ( strlen ( $d ) == 14 ) {
    $dH = (int)substr ( $d, 8, 2 );
    $di = (int)substr ( $d, 10, 2 );
    $ds = (int)substr ( $d, 12, 2 );
  }

  return gmmktime ( $dH, $di, $ds,
    (int)substr ( $d, 4, 2 ),
    (int)substr ( $d, 6, 2 ),
    (int)substr ( $d, 0, 4 ) );
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

function datestr_to_unixtime ( $str )
{
  $year = (int)substr ( $str, 0, 4 );
  $month = (int)substr ( $str, 4, 2 );
  $day = (int)substr ( $str, 6, 2 );
  return mktime ( 12, 0, 0, $month, $day, $year );
}

function date_to_str ( $str )
{
  $months = array ( "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December" );
  $year = (int)substr ( $str, 0, 4 );
  $month = (int)substr ( $str, 4, 2 );
  $day = (int)substr ( $str, 6, 2 );

  // Format: December 31, 2000
  return $months[$month-1] . ' ' . $day . ', ' . $year;
}

/**
  * Read the webcalendar settings.php file.
  * (Some of this code borrowed from includes/config.php's do_config function
  * in the webcalendar code. )
  */
function read_settings ( $file )
{
  global $c, $db_database, $db_host, $db_login, $db_password, $db_persistent,
    $db_type, $wcVersion;

  if( file_exists( $file ) ) {
    $fd = @fopen( $file, 'rb', true );
  } else {
    echo "Error: no such file $file\n";
    exit;
  }

  // We don't use fgets() since it seems to have problems with Mac-formatted
  // text files. Instead, we read in the entire file, and split the lines manually.
  $data = '';
  while( ! feof( $fd ) ) {
    $data .= fgets( $fd, 4096 );
  }
  fclose( $fd );

  // Replace any combination of carriage return (\r) and new line (\n)
  // with a single new line.
  $data = preg_replace( "/[\r\n]+/", "\n", $data );

  // Split the data into lines.
  $configLines = explode( "\n", $data );

  $settings = array ();

  for( $n = 0, $cnt = count( $configLines ); $n < $cnt; $n++ ) {
    $buffer = trim( $configLines[$n] );

    if( preg_match( '/^#|\/\*/', $buffer ) // comments
        || preg_match( '/^<\?/', $buffer ) // start PHP code
        || preg_match( '/^\?>/', $buffer ) // end PHP code
      ) {
      continue;
    }

    if( preg_match( '/(\S+):\s*(\S+)/', $buffer, $matches ) )
      $settings[$matches[1]] = $matches[2];
  }

  // Extract db settings into global vars.
  $db_database = $settings['db_database'];
  $db_host     = $settings['db_host'];
  $db_login    = $settings['db_login'];
  $db_password = ( empty( $settings['db_password'] )
    ? '' : $settings['db_password'] );
  $db_persistent = ( preg_match( '/(1|yes|true|on)/i',
    $settings['db_persistent'] ) ? '1' : '0' );
  $db_type = $settings['db_type'];

  // If no db settings, then user has likely started install but not yet
  // completed. So, send them back to the install script.
  if( empty( $db_type ) ) {
    echo "Invalid webcalendar settings\n"; exit;
  }

  // Use 'db_cachedir' if found, otherwise look for 'cachedir'.
  if( ! empty( $settings['db_cachedir'] ) )
    dbi_init_cache( $settings['db_cachedir'] );
  else
  if( ! empty( $settings['cachedir'] ) )
    dbi_init_cache( $settings['cachedir'] );

  // Always set this to true since this isn't for end users.
  dbi_set_debug( true );

  foreach( array( 'db_type', 'db_host', 'db_login' ) as $s ) {
    if( empty( $settings[$s] ) )
      echo ( str_replace( 'XXX', $s,
          'Could not find XXX defined in ' . $file ) );
  }

  // Allow special settings of 'none' in some settings[] values.
  // This can be used for db servers not using TCP port for connection.
  $db_host = ( $db_host == 'none' ? '' : $db_host );
  $db_password = ( empty( $db_password ) || $db_password == 'none'
    ? '' : $db_password );

  $run_mode = 'dev';

  // If SQLite, the db file is in the includes directory.
  if( $db_type == 'sqlite' || $db_type == 'sqlite3' )
    $db_database = get_full_include_path( $db_database );

  // Check the current installation version.
  $c = dbi_connect( $db_host, $db_login, $db_password, $db_database, false );
  if ( ! $c ) {
    echo "Database connection error: " . dbi_error (); exit;
  }

  $rows = dbi_get_cached_rows( 'SELECT cal_value FROM webcal_config
    WHERE cal_setting = \'WEBCAL_PROGRAM_VERSION\'' );

  if ( ! $rows ) {
    echo "Error accessing WebCalendar version in database: " .
      dbi_error () . "\n";
    exit;
  } else {
    $row = $rows[0];
    $wcVersion = $row[0];
  }
}

function translate ( $str ) { return $str; }

?>
