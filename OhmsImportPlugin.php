<?php
/**
* OhmsImportPlugin class - represents the Ohms Import plugin
*
* Configuring the plugin:  Set the proper settings in config.ini
* like so:
*
* <code>
* plugins.OhmsImport.columnDelimiter = ","
* plugins.OhmsImport.memoryLimit = "128M"
* plugins.OhmsImport.requiredExtension = "txt"
* plugins.OhmsImport.requiredMimeType = "text/csv"
* plugins.OhmsImport.maxFileSize = "10M"
* plugins.OhmsImport.fileDestination = "/tmp"
* plugins.OhmsImport.batchSize = "1000"
* </code>
*
* All of the above settings are optional.  If not given, OhmsImport uses the
* following default values:
*
* memoryLimit = current script limit
* requiredExtension = "txt" or "csv"
* requiredMimeType = "text/csv"
* maxFileSize = current system upload limit
* fileDestination = current system temporary dir (via sys_get_temp_dir())
* batchSize = 0 (no batching)
*
*
* Set a high memory limit to avoid memory allocation issues with imports.
* Examples include 128M, 1G, and -1.  This will set PHP's memory_limit setting
* directly, see PHP's documentation for more info on formatting this number.
* Be advised that many web hosts set a maximum memory limit, so this setting
* may be ignored if it exceeds the maximum allowable limit. Check with your web
* host for more information.
*
* Note that 'maxFileSize' will not affect post_max_size or upload_max_filesize
* as is set in php.ini.  Having a maxFileSize that exceeds either
* will still result in errors that prevent the file upload.
*
* batchSize: Setting for advanced users.  If you find that your long-running
* imports are using too much memory or otherwise hogging system resources,
* set this value to split your import into multiple jobs based on the
* number of CSV rows to process per job.
*
* For example, if you have a CSV with 150000 rows, setting a batchSize
* of 5000 would cause the import to be split up over 30 separate jobs.
* Note that these jobs run sequentially based on the results of prior
* jobs, meaning that the import cannot be parallelized.  The first job
* will import 5000 rows and then spawn the next job, and so on until
* the import is completed.
*
*
* @copyright Copyright 2017 Eric C. Weig
* @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
* @package OhmsImport
*/
/**
 *
 * Based on the CSVImport plugin by Roy Rosenzweig Center for History and New Media.
 * 
 */

defined('OHMS_IMPORT_DIRECTORY') or define('OHMS_IMPORT_DIRECTORY', dirname(__FILE__));

/**
 * Ohms Import plugin.
 */
class OhmsImportPlugin extends Omeka_Plugin_AbstractPlugin
{
    const MEMORY_LIMIT_OPTION_NAME = 'ohms_import_memory_limit';
    const PHP_PATH_OPTION_NAME = 'ohms_import_php_path';

    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'upgrade',
        'initialize',
        'admin_head',
        'define_acl',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_navigation_main',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        self::MEMORY_LIMIT_OPTION_NAME => '',
        self::PHP_PATH_OPTION_NAME => '',
        OhmsImport_RowIterator::COLUMN_DELIMITER_OPTION_NAME => OhmsImport_RowIterator::DEFAULT_COLUMN_DELIMITER,
        OhmsImport_ColumnMap_Element::ELEMENT_DELIMITER_OPTION_NAME => OhmsImport_ColumnMap_Element::DEFAULT_ELEMENT_DELIMITER,
        OhmsImport_ColumnMap_Tag::TAG_DELIMITER_OPTION_NAME => OhmsImport_ColumnMap_Tag::DEFAULT_TAG_DELIMITER,
        OhmsImport_ColumnMap_File::FILE_DELIMITER_OPTION_NAME => OhmsImport_ColumnMap_File::DEFAULT_FILE_DELIMITER,
    );

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $db = $this->_db;

        // create ohms imports table
        $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}ohms_import_imports` (
           `id` int(10) unsigned NOT NULL auto_increment,
           `item_type_id` int(10) unsigned NULL,
           `collection_id` int(10) unsigned NULL,
           `owner_id` int unsigned NOT NULL,
           `delimiter` varchar(1) collate utf8_unicode_ci NOT NULL,
           `original_filename` text collate utf8_unicode_ci NOT NULL,
           `file_path` text collate utf8_unicode_ci NOT NULL,
           `file_position` bigint unsigned NOT NULL,
           `status` varchar(255) collate utf8_unicode_ci,
           `skipped_row_count` int(10) unsigned NOT NULL,
           `skipped_item_count` int(10) unsigned NOT NULL,
           `is_public` tinyint(1) default '0',
           `is_featured` tinyint(1) default '0',
           `serialized_column_maps` text collate utf8_unicode_ci NOT NULL,
           `added` timestamp NOT NULL default '2000-01-01 00:00:00',
           PRIMARY KEY  (`id`)
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        // create ohms imported items table
        $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}ohms_import_imported_items` (
          `id` int(10) unsigned NOT NULL auto_increment,
          `item_id` int(10) unsigned NOT NULL,
          `import_id` int(10) unsigned NOT NULL,
          PRIMARY KEY  (`id`),
          KEY (`import_id`),
          UNIQUE (`item_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        $this->_installOptions();
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $db = $this->_db;

        // drop the tables
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}ohms_import_imports`";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}ohms_import_imported_items`";
        $db->query($sql);

        $this->_uninstallOptions();
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        // Do this first because MySQL will complain about any ALTERs to a table with an
        // invalid default if we don't fix it first
        if (version_compare($oldVersion, '2.0.3', '<=')) {
            $sql = "ALTER TABLE `{$db->prefix}ohms_import_imports` MODIFY `added` timestamp NOT NULL default '2000-01-01 00:00:00'";
            $db->query($sql);
        }

        if (version_compare($oldVersion, '2.0-dev', '<=')) {
            $sql = "UPDATE `{$db->prefix}ohms_import_imports` SET `status` = ? WHERE `status` = ?";
            $db->query($sql, array('other_error', 'error'));
        }

        if (version_compare($oldVersion, '2.0', '<=')) {
            set_option(OhmsImport_RowIterator::COLUMN_DELIMITER_OPTION_NAME, OhmsImport_RowIterator::DEFAULT_COLUMN_DELIMITER);
            set_option(OhmsImport_ColumnMap_Element::ELEMENT_DELIMITER_OPTION_NAME, OhmsImport_ColumnMap_Element::DEFAULT_ELEMENT_DELIMITER);
            set_option(OhmsImport_ColumnMap_Tag::TAG_DELIMITER_OPTION_NAME, OhmsImport_ColumnMap_Tag::DEFAULT_TAG_DELIMITER);
            set_option(OhmsImport_ColumnMap_File::FILE_DELIMITER_OPTION_NAME, OhmsImport_ColumnMap_File::DEFAULT_FILE_DELIMITER);
        }   
        
        if(version_compare($oldVersion, '2.0.1', '<=')) {
            $sql = "ALTER TABLE `{$db->prefix}ohms_import_imports` CHANGE `item_type_id` `item_type_id` INT( 10 ) UNSIGNED NULL ,
                    CHANGE `collection_id` `collection_id` INT( 10 ) UNSIGNED NULL
            ";
            $db->query($sql);
        }
    }

    /**
     * Add the translations.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    /**
     * Define the ACL.
     *
     * @param array $args
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl']; // get the Zend_Acl

        $acl->addResource('OhmsImport_Index');

        // Hack to disable CRUD actions.
        $acl->deny(null, 'OhmsImport_Index', array('show', 'add', 'edit', 'delete'));
        $acl->deny('admin', 'OhmsImport_Index');
    }

    /**
     * Configure admin theme header.
     *
     * @param array $args
     */
    public function hookAdminHead($args)
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if ($request->getModuleName() == 'ohms-import') {
            queue_css_file('ohms-import-main');
            queue_js_file('ohms-import');
        }
    }

    /**
     * Add the Simple Pages link to the admin main navigation.
     *
     * @param array Navigation array.
     * @return array Filtered navigation array.
     */
    public function filterAdminNavigationMain($nav)
    {
        $nav[] = array(
            'label' => __('OHMS Import'),
            'uri' => url('ohms-import'),
            'resource' => 'OhmsImport_Index',
            'privilege' => 'index',
        );
        return $nav;
    }
}
