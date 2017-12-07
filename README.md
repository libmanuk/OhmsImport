# OHMSImport
Upload a valid .zip file containing valid OHMS XML files into Omeka.

# Omeka MySQL Change

In order to accommodate OHMS XML files with lengthy transcripts, the following MySQL database change is necessary.

In the 'omek_sessions' table, the field with Name 'data' should have Type set to 'mediumblob'.

# Possible Issues

Be sure to set background.php.path correctly in the Omeka application config.  This path will vary depending on your server setup.

Look in: /public_html/<omeka_install>/application/config/config.ini

Example line: background.php.path = "/usr/local/bin/php"

# Attribution

This script is based on the CsvImport plugin which is mantained and was originally developed by the Roy Rosenzweig Center for History and New Media. Original code can be found at https://github.com/omeka/plugin-CsvImport

