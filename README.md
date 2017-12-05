# OHMSImport
Upload a valid OHMS XML file into Omeka.

# Omeka MySQL Change

In order to accomodate OHMS XML files with lengthy transcripts, the following MySQL database change is necessary.

In the 'omek_sessions' table, the field with Name 'data' should have Type set to 'mediumblob'.

# Attribution

This script is based on the CsvImport plugin which is mantained and was originally developed by the Roy Rosenzweig Center for History and New Media. Original code can be found at https://github.com/omeka/plugin-CsvImport

