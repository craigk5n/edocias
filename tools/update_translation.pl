#!/usr/bin/perl
# $Id$
#
# This tool will update a translation file by doing the following:
# - Phrases are organized by the page on which they first appear.
# - When a missing translation is found, the phrase can optionally have
#   << MISSING >>
#   right above it. And, when the "phrase" is an abbreviation of the
#   full English text, show the English text (in a comment) below.
#
# Example:
#   << MISSING >>
#   custom-script-help:
#   English text: Allows entry of custom Javascript or stylesheet text that will be inserted into the HTML "head" section of every page.
#
# Note: you will lose any comments you put in the translation file
# when using this tool (except for the comments at the very beginning).
#
# Note #2: This will overwrite the existing translation file, so a backup
# of the original can optionally be saved with a timestamp file extension.
#
# Usage:
# update_translation.pl [-p plugin] languagefile
#
# Example:
# update_translation.pl French.txt
#    or
# update_translation.pl French
#
# Note: this utility should be run from this directory (tools).
# Note #2: you can use perltidy to format this perl script nicely:
#  http://perltidy.sourceforge.net/
# Usage:
#  perltidy -i=2 update_translation.pl
#  (which will create update_translation.pl.tdy, the new version)
#
####################################################################
use File::Copy;
use File::Find;

sub find_pgm_files {
# Skipping non WebCalendar plugins,
# if the filename ends in .class or .php, add it to @files.
  push( @files, "$File::Find::name" )
    if ( $_ =~ /\.php$/i
    && $File::Find::dir !~ /(fckeditor|htmlarea|phpmailer)/i );
}

$base_dir  = '..';
$trans_dir = '../translations';

$base_trans_file = "$trans_dir/English-US.txt";

$save_backup  = 0; # set to 1 to create backups
$show_dups    = 0; # set to 0 to minimize translation file.
$show_missing = 1; # set to 0 to minimize translation file.
$verbose      = 0;

( $this ) = reverse split( /\//, $0 );

for ( $i = 0; $i < @ARGV; $i++ ) {
  if ( $ARGV[ $i ] eq '-b' ) {
    $save_backup++;
  }
  elsif ( $ARGV[ $i ] eq '-d' ) {
    $show_dups++;
  }
  elsif ( $ARGV[ $i ] eq '-m' ) {
    $show_missing--;
  }
  elsif ( $ARGV[ $i ] eq '-v' ) {
    $verbose++;
  }
  else {
    $infile = $ARGV[ $i ];
  }
}

die "Usage: $this language\n" if ( $infile eq '' );


$p_trans_dir       = $trans_dir;
$p_base_trans_file = $base_trans_file;
$p_base_dir        = $base_dir;

$infile .= '.txt' if ( $infile !~ /txt$/ );

if ( -f "$trans_dir/$infile" || -f "$p_trans_dir/$infile" ) {
  $b_infile = "$trans_dir/$infile";
  $infile   = "$p_trans_dir/$infile";
}

#print "infile: $infile\nb_infile: $b_infile\ntrans_dir: $trans_dir\n";

die "Usage: $this [-p plugin] language\n" if ( !-f $infile );

print "Translation file: $infile\n" if ( $verbose );

#
# Save a backup copy of old translation file before we mess with it.
#
if ( $save_backup ) {
  $bak = $infile;
  $bak =~ s/txt$//;
  print "Attempting to backup file $infile. ";
  if ( copy( $infile, $bak . ( stat( $infile ) )[9] ) ) {
    print "Success!\n";
  }
  else {
    warn "Failure!:\n$! ";
  }
}

# Now load the base translation file (English) so that we can include
# the English text, below the untranslated phrase, in a comment.
open( F, $base_trans_file ) || die "Error opening $base_trans_file";
print "Reading base translation file: $base_trans_file\n" if ( $verbose );
while ( <F> ) {
  chop;
  s/\r*$//g; # remove annoying CR
  next if ( /^#/ );
  if ( /\s*:\s*/ ) {
    $abbrev = $`;
    $base_trans{ $abbrev } = $';
  }
}
close( F );

#
# Now load the translation file we are going to update.
#
if ( -f $infile ) {
  print "Reading current translations from $infile\n" if ( $verbose );
  open( F, $infile ) || die "Error opening $infile";
  $in_header = 1;
  while ( <F> ) {
    chop;
    s/\r*$//g; # remove annoying CR
    if ( $in_header && /^#/ ) {
      if ( /Translation last (pagified|updated)/ ) {
# Ignore since we will replace this with current date below.
      }
      else {
        $header .= $_ . "\n";
      }
    }
    next if ( /^#/ );
    $in_header = 0;
    if ( /\s*:\s*/ ) {
      $abbrev = $`;
      $temp   = $';
      $temp   = '='
        if ( $infile !~ /english-us/i && $base_trans{ $abbrev } eq $temp );
      $trans{ $abbrev } = $temp;
    }
  }
}

$trans{ 'charset' }   = '=' if ( !defined( $trans{ 'charset' } ) );
$trans{ 'direction' } = '=' if ( !defined( $trans{ 'direction' } ) );

( $day, $mon, $year ) = ( localtime( time() ) )[ 3, 4, 5 ];
$header .=
  '# Translation last updated on '
  . sprintf( "%02d-%02d-%04d", $mon + 1, $day, $year + 1900 ) . "\n";

print "\nFinding php files.\n\n" if ( $verbose );
find \&find_pgm_files, $base_dir;

#
# Write new translation file.
#
$notfound = 0;
open( OUT, ">$infile" ) || die "Error writing $infile: ";
print OUT $header;

foreach $f ( @files ) {
  open( F, $f ) || die "Error reading $f";
  $f =~ s,^\.\.\/,,;
  $pageHeader = "\n" . ( '#' x 40 ) . "\n# Page: $f\n#\n";
  print "Searching $f\n" if ( $verbose );
  %thispage = ();
  while ( <F> ) {
    $data = $_;
    while ( $data =~ /(translate|tooltip)\s*\(\s*['"]/ ) {
      $data = $';
      if ( $data =~ /['"]\s*[,\)]/ ) {
        $text = $`;
        if ( defined( $thispage{ $text } ) || $text eq 'charset' ) {
# already found
        }
        elsif ( defined( $text{ $text } ) ) {
          if ( $show_dups ) {
            print OUT $pageHeader
              . "# \"$text\" previously defined (in $foundin{$text})\n";
            $pageHeader = '';
          }
          $thispage{ $text } = 1;
        }
        else {
          if ( !length( $trans{ $text } ) ) {
            if ( $show_missing ) {
              print OUT $pageHeader;
              $pageHeader = '';
              if ( length( $webcaltrans{ $text } ) ) {
                print OUT "# \"$text\" defined in translation\n";
              }
              else {
                print OUT "#\n# << MISSING >>\n# $text:\n";
                print OUT "# English text: $base_trans{$text}\n#\n"
                  if ( length( $base_trans{ $text } )
                  && $base_trans{ $text } ne $text );
              }
            }
            $notfound++ if ( !length( $webcaltrans{ $text } ) );
          }
          else {
            print OUT $pageHeader;
            $pageHeader = '';
            printf OUT ( "%s: %s\n", $text, $trans{ $text } );
          }
          $foundin{ $text } = $f;
          $text{ $text } = $thispage{ $text } = 1;
        }
        $data = $';
      }
    }
  }
  close( F );
}

print STDERR (
  !$notfound
  ? "All text was found in $infile.  Good job :-)\n"
  : "$notfound translation(s) missing.\n"
);

exit 0;
