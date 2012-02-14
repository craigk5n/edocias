
CREATE TABLE edm_doc (
  /* Unique document ID (generated) */
  docid INT NOT NULL,
  /* document title (if different than filepath */
  doctitle VARCHAR(256) NULL,
  /* full path filename to image file */
  filepath VARCHAR(256) NOT NULL,
  /* mime type of document */
  mime VARCHAR(64) NULL,
  /* Modification date/time of document (Unix time = since Jan 1 1970) */
  date INT NOT NULL,
  /* date/time document was added (Unix time = since Jan 1 1970) */
  process_date INT NOT NULL,
  /* OCR'd text */
  ocr TEXT NOT NULL,
  PRIMARY KEY ( docid )
);

/* If upgrading from 1.0:
  alter table edm_doc add column doctitle VARCHAR(256) NULL;
*/
