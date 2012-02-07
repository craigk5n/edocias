#!/usr/bin/perl
# $Id$
# EDocIAS
#
# Convert a ms-office xls file into a single text file (tab-delimited)
#
# Usage:
#	perl xls2txt.pl infile.xls outfile.txt
#
# Requires the Spreadsheet::ParseExcel perl module (available on CPAN).
#
############################################################################

use Spreadsheet::ParseExcel;
my $oExcel = new Spreadsheet::ParseExcel;
my $oBook;

if ( @ARGV != 2 ) {
  print STDERR "Error: usage is $0 infile.xls outfile.txt\n";
  exit 1;
}
$infile = $ARGV[0];
$outfile = $ARGV[1];

if ( ! -f $infile ) {
  die "Error: no such input file $infile\n";
}
if ( -f $outfile ) {
  die "Error: outfile $outfile already exists\n";
}



$oBook = $oExcel->Parse($infile);


my($iR, $iC, $oWkS, $oWkC);
print "FILE: ", $oBook->{File} , "\n";
print "WORKSHEET COUNT: ", $oBook->{SheetCount} , "\n";
print "AUTHOR: ", $oBook->{Author} , "\n";

my $table = [];

open ( F, ">$outfile" ) || die "Error opening $outfile";

for(my $iSheet=0; $iSheet < $oBook->{SheetCount} ; $iSheet++) {
  $oWkS = $oBook->{Worksheet}[$iSheet];
  my $fname = $oWkS->{Name};
  if ( $fname =~ /sheet(\d+)/i ) {
    my $sheetNum = $1;
    if ( $sheetNum == 1 ) {
      $fname = $oBook->{File};
    } else {
      $fname = $oBook->{File};
    }
  }
  print "Processing worksheet '" . $oWkS->{Name} . "\n";
  for(my $iR = $oWkS->{MinRow} ; 
    defined $oWkS->{MaxRow} && $iR <= $oWkS->{MaxRow} ; $iR++) {
    for(my $iC = $oWkS->{MinCol} ;
      defined $oWkS->{MaxCol} && $iC <= $oWkS->{MaxCol} ; $iC++) {
      $oWkC = $oWkS->{Cells}[$iR][$iC];
      #print "( $iR , $iC ) =>", $oWkC->Value, "\n" if($oWkC);
      print F "\t" if ( $iC > $oWkS->{MinCol} );
      if ( $oWkC ) {
        if ( $oWkC->Value =~ /^[\-]{0,1}\d\.\d+$/ ) {
          printf F "%0.3f", $oWkC->Value;
        } else {
          print F $oWkC->Value;
        }
      }
    }
    print F "\n";
  }
  close ( F );
}

exit 0;
