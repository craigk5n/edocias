#!/bin/bash
# Convert a PDF to text.
# Usage:
#   scan-pdf.sh infile.pdf [outfile]
# Requires pdftotext, which is part of the poppler-utils package on most Linux distributions.
# For Unbuntu:
#   sudo dnf install poppler-utils
# For CentOS/RHEL:
#   sudo yum install poppler-utils

convert='pdftotext -q "%FILE%" - | head -1000 > textout.txt'
infile=''
outfile='-'

if [ $# -eq 2 ]; then
  infile="$1"
  outfile="$2"
elif [ $# -eq 1 ]; then
  infile="$1"
else
  echo "Usage is: $0 infile [outfile]"
  exit 1
fi

tmp="/tmp/textout.$$"

pdftotext -q "$infile" - | strings| head -1000 > "$tmp"
lines=$(wc -l < "$tmp")

if [ $lines -lt 10 ]; then
  tmpDir="/tmp/images.$$"
  mkdir "$tmpDir"
  
  pdfimages -j "$infile" "$tmpDir/pdf-image"
  
  for path in "$tmpDir"/*; do
    tesseract "$path" - >> "$tmp"
    rm "$path"
  done

  rmdir "$tmpDir"
fi

if [ "$outfile" == "-" ]; then
  cat "$tmp"
  rm "$tmp"
else
  mv "$tmp" "$outfile"
fi

exit 0
