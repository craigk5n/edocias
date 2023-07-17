# EDocIAS
Electronic Document Index And Search

*Author:* craig _at_ k5n.us
*Version:* 1.1
*URL:* https://www.k5n.us/edocias/

## Purpose

The goal of this project is to create a simple way to find information in
a large pile of documents.  Old paper documents can be scanned and saved
and later found with a simple text search (assuming you can get tesseract
installed).  You can download your online bills statements, product user
manuals and various other documents to go paperless.

## Sample Use Cases

- Scan an old paper credit card bill and save as a JPEG file.
  Search for a purchase at "Costco" and find the scanned JPEG file that
  contains the purchase.
  Shred the old bill!
- Find the owners manual online to replace the one in your file cabinet
  for your microwave.
  Search for "popcorn" and find the owners manual's section on how to
  microwave popcorn.
  Throw away the paper owners manual!
- Download the online PDF statement for your bank.
  Search for check "3023" and view the PDF that contains that check.
  Tell your bank to stop sending paper statements!


## Installation

1) Copy config.php.example to config.php. Use your favorite text editor to
   customize config.php.  You must set define which document directories to
   include.  You must also configure how to handle all the various file types.
   There are some other configuration options in there, too.
2) Install and configure the various document conversion tools needed to
   extract plain text from the various file types.  See the list of possible
   tools to use for this below.  There are some tools in the included
   tools.zip file.
3) Create the MySQL database using the SQL found in tables-mysql.sql.
   Other database types should work (Oracle, PostgreSQL, etc.) but have not
   been tested (and you may have to change the 'CREATE TABLE' syntax).
4) Run the scan.php script to process all your files.  This file is meant to
   be run from the command line:
     php scan.php
   It is recommended you setup a cron job to run this once a day to
   automatically pick up new files.
5) Optionally customize the appearance to match the rest of your site.
   The UI was intentionally left bare to avoid conflicts with your website.
   The main page (index.php) will look for the following files.  If found,
   they will be included.
   - style.css: a custom style sheet
   - header.html or header.php: custom PHP or HTML that will be included
     at the top of the page before the EDocIAS content.
   - trailer.html or trailer.php: custom PHP or HTML that will be included
     after the EDocIAS content.

## Misc. Tips

- Installing and configuring the various tools for extracting text from
  the various file formats (step 2 above) will be the most time-consuming
  part of the setup.

## Extraction tools

I have personally used the tools below (on Mac OS X 10.7).

- png, tif, jpeg, gif via OCR:
  Tool: Tesseract  
  URL: http://code.google.com/p/tesseract-ocr/  
  Note: Tesseract required some hacking to get it installed on OS X,
  so google it.
- pdf:  
  Tool: pdftotext (part of XPDF)  
  URL: http://www.foolabs.com/xpdf/
- doc (MS Word):  
  Tool: antiword  
  URL: http://www.winfield.demon.nl/
- xls (MS Excel):   
  Tool: xls2txt.pl (included in tools.zip)  
  Notes: This perl script requires a few modules available from cpan.org.
    You'll get an error indicating the missing modules if you don't have them.
- html:  
  Tool: html2txt.php (included in tools.zip)  
  Notes: Small custom script that uses open source phphtmlparser tool  
  URL: http://php-html.sourceforge.net/html2text.php


The tools below look like a good fit also, but I have not tried them.


