<?php
/*
 * EDocIAS (Electronic Document Index And Search).
 *
 * Downloads a specific file by its docid.
 */

error_reporting ( E_ALL ); // Report all errors

include_once "config.php";
include_once "dbi4php.php";
include_once "functions.php";

$id = (int)getIntValue ( 'id' );
if ( empty ( $id ) ) {
  echo "Invalid doc id! (1)";
  exit;
}

// Open database connection
connect_db ();

// Get doc info
$res = dbi_execute ( 'SELECT filepath, mime FROM edm_doc WHERE docid = ?',
  array ( $id ) );
if ( ! $res ) {
  echo "Database error: " . dbi_error ();
  exit;
}
$row = dbi_fetch_row ( $res );
if ( empty ( $row ) ) {
  echo "Invalid doc id! (2)";
  exit;
}
$filepath = $row[0];
$mime = $row[1];
$length = filesize ( $filepath );

if ( empty ( $filepath ) || empty ( $length ) ) {
  echo "Invalid doc id! (3)";
  exit;
}

// Spit out the HTTP header, including the all-important mime type so
// that the browser knows how to view the document.
Header ( 'Content-Length: ' . filesize ( $filepath ) );
Header ( 'Content-Type: ' . $mime );
Header ( 'Content-Disposition: inline; filename=' . basename ( $filepath ) );

$fh = fopen ( $filepath, 'r' );
$data = fread ( $fh, filesize ( $filepath ) );
fclose ( $fh );

echo $data;

// open file and send result

dbi_free_result ( $res );

dbi_close ( $c );
?>
