<?php
/**
 * OhmsImport_IndexController class - represents the Ohms Import index controller
 *
 * @copyright Copyright 2008-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package OhmsImport
 */
 
error_reporting(E_ALL);
ini_set('display_errors', 1);
 
 
class OhmsImport_IndexController extends Omeka_Controller_AbstractActionController
{
    protected $_browseRecordsPerPage = 10;
    protected $_pluginConfig = array();

    /**
     * Initialize the controller.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace('OhmsImport');
        $this->_helper->db->setDefaultModelName('OhmsImport_Import');
    }




   /**
     * Convert a new import to XML.
     */
    public function indexAction()
    {
        $form = $this->_getMainForm();
        $this->view->form = $form;

        if (!$this->getRequest()->isPost()) {
            return;
        }

        if (!$form->isValid($this->getRequest()->getPost())) {
            $this->_helper->flashMessenger(__('Invalid form input. Please see errors below and try again.'), 'error');
            return;
        }

        if (!$form->ohms_file->receive()) {
            $this->_helper->flashMessenger(__('Error uploading file. Please try again.'), 'error');
            return;
        }

        $filePath = $form->ohms_file->getFileName();
        
        $zrandom_name = str_replace("/tmp/", "", "$filePath");
        
        $plugin_path = realpath(__DIR__ . '/..');
        $zip_csv_path = "$plugin_path/zips/$zrandom_name.csv";
        $zip_extract_path = "$plugin_path/zips/$zrandom_name/";
        $zip_package_path = "$plugin_path/zips/$zrandom_name.zip";
        
        rename("$filePath", "$zip_package_path");
        
        shell_exec("unzip '".$zip_package_path."' -d '".$zip_extract_path."'");
        
        $zfiles = glob($zip_extract_path ."*.{xml}", GLOB_BRACE);
        
        $zcsv = fopen("$zip_csv_path", "w");

    foreach($zfiles as $zfile) {
    
        $zfeed = file_get_contents($zfile, true); 
    
        $zfeed = str_replace("</subject><subject>", "@", "$zfeed");
        
        $zfeed = str_replace("</keyword><keyword>", "@", "$zfeed");

        $zfeed = str_replace("</interviewer><interviewer>", "@", "$zfeed");
        
        $zfeed = str_replace("</interviewee><interviewee>", "@", "$zfeed");
    
        $zitem = simplexml_load_string($zfeed);
    
        $dctitle = $zitem->record[0]->title;
        $dcdescription = $zitem->record[0]->description;
        $accession = $zitem->record[0]->accession;
        $subject = $zitem->record[0]->subject;
        $keyword = $zitem->record[0]->keyword;
        $interviewee = $zitem->record[0]->interviewee;
        $interviewer = $zitem->record[0]->interviewer;
        $mediaurl = $zitem->record[0]->media_url;
        $date = $zitem->record[0]->date;
        $xmllocation = $zitem->record[0]->xmllocation;
        $clip_format = $zitem->record->mediafile->clip_format;
        $ohmsobjtxt = $zitem->record[0]->transcript;

        $dcdescription = str_replace("\n", "\r", "$dcdescription");
        $ohmsobjtxt = str_replace("\n", " ", "$ohmsobjtxt");
        $ohmsobjtxt = str_replace("\r", " ", "$ohmsobjtxt");    
    
        $zline = "$dctitle^$dcdescription^$accession^$subject^$keyword^$interviewee^$interviewer^$mediaurl^$date^$xmllocation^$clip_format^$ohmsobjtxt\n";
    
        $zbuild = file_put_contents($zip_csv_path, $zline.PHP_EOL , FILE_APPEND | LOCK_EX);
        
        }

        $zheader = "Dublin Core: Title^Dublin Core: Description^Item Type Metadata: Interview Accession^Item Type Metadata: Interviewer Name^Item Type Metadata: Interviewee Name^Item Type Metadata: OHMS Object^Item Type Metadata: Interview Digital File Name^Item Type Metadata: Interview Date^Item Type Metadata: Interview LC Subject^Item Type Metadata: Interview Keyword^Item Type Metadata: Interview Format^Item Type Metadata: OHMS Object Text";
        
        $zfileContent = file_get_contents($zip_csv_path);

        file_put_contents($zip_csv_path, $zheader . "\n" . $zfileContent);
  
        $ohmsfile = file_get_contents($zip_csv_path);

        $writefile="$filePath";

        file_put_contents($writefile, $ohmsfile, LOCK_EX);
        
        if (file_exists($zip_csv_path)) {
        unlink($zip_csv_path);
                } else {
            //do nothing
        }
        
        if (file_exists($zip_package_path)) {
        unlink($zip_package_path);
                } else {
            //do nothing
        }
        
        if (file_exists($zip_extract_path)) {
            $zdirfiles = glob($zip_extract_path .'*'); 

        foreach($zdirfiles as $zdirfile){
          if(is_file($zdirfile))
        unlink($zdirfile); 
        }

        rmdir($zip_extract_path);
                } else {
            //do nothing
        }

        $columnDelimiter = "^";

        $file = new OhmsImport_File($filePath, $columnDelimiter);

        if (!$file->parse()) {
            $this->_helper->flashMessenger(__('Your file is incorrectly formatted.')
                . ' ' . $file->getErrorString(), 'error');
            return;
        }

        $this->session->setExpirationHops(2);
        $this->session->originalFilename = $_FILES['ohms_file']['name'];
        $this->session->filePath = $filePath;

        $this->session->columnDelimiter = "^";
        $this->session->columnNames = $file->getColumnNames();
        $this->session->columnExamples = $file->getColumnExamples();

        $this->session->fileDelimiter = $form->getValue('file_delimiter');
        $this->session->tagDelimiter = $form->getValue('tag_delimiter');
        //$this->session->elementDelimiter = $form->getValue('element_delimiter');
        $this->session->elementDelimiter = "@";
        $this->session->itemTypeId = $form->getValue('item_type_id');
        $this->session->itemsArePublic = $form->getValue('items_are_public');
        $this->session->itemsAreFeatured = $form->getValue('items_are_featured');
        $this->session->collectionId = $form->getValue('collection_id');

        $this->session->automapColumnNamesToElements = $form->getValue('automap_columns_names_to_elements');

        $this->session->ownerId = $this->getInvokeArg('bootstrap')->currentuser->id;

        // All is valid, so we save settings.
        set_option(OhmsImport_RowIterator::COLUMN_DELIMITER_OPTION_NAME, $this->session->columnDelimiter);
        set_option(OhmsImport_ColumnMap_Element::ELEMENT_DELIMITER_OPTION_NAME, $this->session->elementDelimiter);
        set_option(OhmsImport_ColumnMap_Tag::TAG_DELIMITER_OPTION_NAME, $this->session->tagDelimiter);
        set_option(OhmsImport_ColumnMap_File::FILE_DELIMITER_OPTION_NAME, $this->session->fileDelimiter);

        if ($form->getValue('omeka_ohms_export')) {
            $this->_helper->redirector->goto('check-omeka-ohms');
        }

        $this->_helper->redirector->goto('map-columns');
    }

    /**
     * Map the columns for an import.
     */
    public function mapColumnsAction()
    {
        if (!$this->_sessionIsValid()) {
            $this->_helper->flashMessenger(__('Import settings expired. Please try again.'), 'error');
            $this->_helper->redirector->goto('index');
            return;
        }

        require_once OHMS_IMPORT_DIRECTORY . '/forms/Mapping.php';
        $form = new OhmsImport_Form_Mapping(array(
            'itemTypeId' => $this->session->itemTypeId,
            'columnNames' => $this->session->columnNames,
            'columnExamples' => $this->session->columnExamples,
            'fileDelimiter' => $this->session->fileDelimiter,
            'tagDelimiter' => $this->session->tagDelimiter,
            'elementDelimiter' => $this->session->elementDelimiter,
            'automapColumnNamesToElements' => $this->session->automapColumnNamesToElements
        ));
        $this->view->form = $form;

        if (!$this->getRequest()->isPost()) {
            return;
        }
        if (!$form->isValid($this->getRequest()->getPost())) {
            $this->_helper->flashMessenger(__('Invalid form input. Please try again.'), 'error');
            return;
        }

        $columnMaps = $form->getColumnMaps();
        if (count($columnMaps) == 0) {
            $this->_helper->flashMessenger(__('Please map at least one column to an element, file, or tag.'), 'error');
            return;
        }

        $ohmsImport = new OhmsImport_Import();
        foreach ($this->session->getIterator() as $key => $value) {
            $setMethod = 'set' . ucwords($key);
            if (method_exists($ohmsImport, $setMethod)) {
                $ohmsImport->$setMethod($value);
            }
        }
        $ohmsImport->setColumnMaps($columnMaps);
        if ($ohmsImport->queue()) {
            $this->_dispatchImportTask($ohmsImport, OhmsImport_ImportTask::METHOD_START);
            $this->_helper->flashMessenger(__('Import started. Reload this page for status updates.'), 'success');
        } else {
            $this->_helper->flashMessenger(__('Import could not be started. Please check error logs for more details.'), 'error');
        }

        $this->session->unsetAll();
        $this->_helper->redirector->goto('browse');
    }

    /**
     * For import of Omeka.net OHMS.
     * Check if all needed Elements are present.
     */
    public function checkOmekaOhmsAction()
    {
        $elementTable = get_db()->getTable('Element');
        $skipColumns = array('itemType',
                             'collection',
                             'tags',
                             'public',
                             'featured',
                             'file');

        $skipColumnsWrapped = array();
        foreach($skipColumns as $skipColumn) {
            $skipColumnsWrapped[] = "'" . $skipColumn . "'";
        }
        $skipColumnsText = '( ' . implode(',  ', $skipColumnsWrapped) . ' )';

        if (empty($this->session->columnNames)) {
            $this->_helper->redirector->goto('index');
        }

        $hasError = false;
        foreach ($this->session->columnNames as $columnName){
            if (!in_array($columnName, $skipColumns)) {
                $data = explode(':', $columnName);
                if (count($data) != 2) {
                    $this->_helper->flashMessenger(__('Invalid column names. Column names must either be one of the following %s, or have the following format: {ElementSetName}:{ElementName}', $skipColumnsText), 'error');
                    $hasError = true;
                    break;
                }
            }
        }

        if (!$hasError) {
            foreach ($this->session->columnNames as $columnName){
                if (!in_array($columnName, $skipColumns)) {
                    $data = explode(':', $columnName);
                    //$data is like array('Element Set Name', 'Element Name');
                    $elementSetName = $data[0];
                    $elementName = $data[1];
                    $element = $elementTable->findByElementSetNameAndElementName($elementSetName, $elementName);
                    if (empty($element)) {
                        $this->_helper->flashMessenger(__('Element "%s" is not found in element set "%s"', array($elementName, $elementSetName)), 'error');
                         $hasError = true;
                    }
                }
            }
        }

        if (!$hasError) {
            $this->_helper->redirector->goto('omeka-ohms');
        }
    }

    /**
     * Create and queue a new import from Omeka.net.
     */
    public function omekaOhmsAction()
    {
        // specify the export format's file and tag delimiters
        // do not allow the user to specify it
        $fileDelimiter = ',';
        $tagDelimiter = ',';

        $headings = $this->session->columnNames;
        $columnMaps = array();
        foreach ($headings as $heading) {
            switch ($heading) {
                case 'collection':
                    $columnMaps[] = new OhmsImport_ColumnMap_Collection($heading);
                    break;
                case 'itemType':
                    $columnMaps[] = new OhmsImport_ColumnMap_ItemType($heading);
                    break;
                case 'file':
                    $columnMaps[] = new OhmsImport_ColumnMap_File($heading, $fileDelimiter);
                    break;
                case 'tags':
                    $columnMaps[] = new OhmsImport_ColumnMap_Tag($heading, $tagDelimiter);
                    break;
                case 'public':
                    $columnMaps[] = new OhmsImport_ColumnMap_Public($heading);
                    break;
                case 'featured':
                    $columnMaps[] = new OhmsImport_ColumnMap_Featured($heading);
                    break;
                default:
                    $columnMaps[] = new OhmsImport_ColumnMap_ExportedElement($heading);
                    break;
            }
        }
        $ohmsImport = new OhmsImport_Import();

        //this is the clever way that mapColumns action sets the values passed along from indexAction
        //many will be irrelevant here, since OhmsImport allows variable itemTypes and Collection

        //@TODO: check if variable itemTypes and Collections breaks undo. It probably should, actually
        foreach ($this->session->getIterator() as $key => $value) {
            $setMethod = 'set' . ucwords($key);
            if (method_exists($ohmsImport, $setMethod)) {
                $ohmsImport->$setMethod($value);
            }
        }
        $ohmsImport->setColumnMaps($columnMaps);
        if ($ohmsImport->queue()) {
            $this->_dispatchImportTask($ohmsImport, OhmsImport_ImportTask::METHOD_START);
            $this->_helper->flashMessenger(__('Import started. Reload this page for status updates.'), 'success');
        } else {
            $this->_helper->flashMessenger(__('Import could not be started. Please check error logs for more details.'), 'error');
        }
        $this->session->unsetAll();
        $this->_helper->redirector->goto('browse');
    }

    /**
     * Browse the imports.
     */
    public function browseAction()
    {
        if (!$this->_getParam('sort_field')) {
            $this->_setParam('sort_field', 'added');
            $this->_setParam('sort_dir', 'd');
        }
        parent::browseAction();
    }

    /**
     * Undo the import.
     */
    public function undoImportAction()
    {
        $ohmsImport = $this->_helper->db->findById();
        if ($ohmsImport->queueUndo()) {
            $this->_dispatchImportTask($ohmsImport, OhmsImport_ImportTask::METHOD_UNDO);
            $this->_helper->flashMessenger(__('Undo import started. Reload this page for status updates.'), 'success');
        } else {
            $this->_helper->flashMessenger(__('Undo import could not be started. Please check error logs for more details.'), 'error');
        }

        $this->_helper->redirector->goto('browse');
    }

    /**
     * Clear the import history.
     */
    public function clearHistoryAction()
    {
        $ohmsImport = $this->_helper->db->findById();
        $importedItemCount = $ohmsImport->getImportedItemCount();

        if ($ohmsImport->isUndone() ||
            $ohmsImport->isUndoImportError() ||
            $ohmsImport->isOtherError() ||
            ($ohmsImport->isImportError() && $importedItemCount == 0)) {
            $ohmsImport->delete();
            $this->_helper->flashMessenger(__('Cleared import from the history.'), 'success');
        } else {
            $this->_helper->flashMessenger(__('Cannot clear import history.'), 'error');
        }
        $this->_helper->redirector->goto('browse');
    }

    /**
     * Get the main Ohms Import form.
     *
     * @return OhmsImport_Form_Main
     */
    protected function _getMainForm()
    {
        require_once OHMS_IMPORT_DIRECTORY . '/forms/Main.php';
        $ohmsConfig = $this->_getPluginConfig();
        $form = new OhmsImport_Form_Main($ohmsConfig);
        return $form;
    }

    /**
      * Returns the plugin configuration
      *
      * @return array
      */
    protected function _getPluginConfig()
    {
        if (!$this->_pluginConfig) {
            $config = $this->getInvokeArg('bootstrap')->config->plugins;
            if ($config && isset($config->OhmsImport)) {
                $this->_pluginConfig = $config->OhmsImport->toArray();
            }
            if (!array_key_exists('fileDestination', $this->_pluginConfig)) {
                $this->_pluginConfig['fileDestination'] =
                    Zend_Registry::get('storage')->getTempDir();
            }
        }
        return $this->_pluginConfig;
    }

    /**
     * Returns whether the session is valid.
     *
     * @return boolean
     */
    protected function _sessionIsValid()
    {
        $requiredKeys = array('itemsArePublic',
                              'itemsAreFeatured',
                              'collectionId',
                              'itemTypeId',
                              'ownerId');
        foreach ($requiredKeys as $key) {
            if (!isset($this->session->$key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Dispatch an import task.
     *
     * @param OhmsImport_Import $ohmsImport The import object
     * @param string $method The method name to run in the OhmsImport_Import object
     */
    protected function _dispatchImportTask($ohmsImport, $method = null)
    {
        if ($method === null) {
            $method = OhmsImport_ImportTask::METHOD_START;
        }
        $ohmsConfig = $this->_getPluginConfig();

        $jobDispatcher = Zend_Registry::get('job_dispatcher');
        $jobDispatcher->setQueueName(OhmsImport_ImportTask::QUEUE_NAME);
        try {
            $jobDispatcher->sendLongRunning('OhmsImport_ImportTask',
                array(
                    'importId' => $ohmsImport->id,
                    'memoryLimit' => @$ohmsConfig['memoryLimit'],
                    'batchSize' => @$ohmsConfig['batchSize'],
                    'method' => $method,
                )
            );
        } catch (Exception $e) {
            $ohmsImport->setStatus(OhmsImport_Import::OTHER_ERROR);
            throw $e;
        }
    }
}
