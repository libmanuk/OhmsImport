<?php 
    echo head(array('title' => __('OHMS Import')));
?>
<?php echo common('ohmsimport-nav'); ?>
<div id="primary">
    <h2><?php echo __('Status'); ?></h2>
    <?php echo flash(); ?>
    <div class="pagination"><?php echo pagination_links(); ?></div>
    <?php if (iterator_count(loop('OhmsImport_Import'))): ?>
    <table class="simple" cellspacing="0" cellpadding="0">
        <thead>
            <tr>                
                <?php
                $browseHeadings[__('Import Date')] = 'added';
                $browseHeadings[__('OHMS File')] = 'original_filename';
                $browseHeadings[__('Imported Items')] = null;
                $browseHeadings[__('Skipped Items')] = 'skipped_item_count';
                $browseHeadings[__('Skipped Rows')] = 'skipped_row_count';
                $browseHeadings[__('Status')] = 'status';
                $browseHeadings[__('Action')] = null;
                echo browse_sort_links($browseHeadings, array('link_tag' => 'th scope="col"', 'list_tag' => '')); 
                ?>
            </tr>
        </thead>
        <tbody>            
            <?php $key = 0; ?>
            <?php foreach (loop('OhmsImport_Import') as $ohmsImport): ?>
            <tr class="<?php if(++$key%2==1) echo 'odd'; else echo 'even'; ?>">
            
                <td><?php echo html_escape(format_date($ohmsImport->added, Zend_Date::DATETIME_SHORT)); ?></td>
                <td><?php echo html_escape($ohmsImport->original_filename); ?></td>
                
                <?php $importedItemCount = $ohmsImport->getImportedItemCount(); ?>
                <td><?php echo html_escape($importedItemCount); ?></td>
                <td><?php echo html_escape($ohmsImport->skipped_item_count); ?></td>
                <td><?php echo html_escape($ohmsImport->skipped_row_count); ?></td>
                
                <td><?php echo html_escape(__(Inflector::humanize($ohmsImport->status, 'all'))); ?></td>
                <?php
                    if ($ohmsImport->isCompleted() 
                        || $ohmsImport->isStopped()
                        || ($ohmsImport->isImportError() && $importedItemCount > 0)): ?>
                    <?php 
                    $undoImportUrl = $this->url(array('action' => 'undo-import',
                                                      'id' => $ohmsImport->id),
                                                      'default'); 
                    ?>
                    <td>
                        <a href="<?php echo html_escape($undoImportUrl);  ?>" class="ohms-undo-import delete-button"><?php echo html_escape(__('Undo Import')); ?></a>
                    </td>
                <?php elseif ($ohmsImport->isUndone() || 
                              $ohmsImport->isUndoImportError() || 
                              $ohmsImport->isOtherError() || 
                              ($ohmsImport->isImportError() && $importedItemCount == 0)): ?>
                    <?php 
                    $clearHistoryImportUrl = $this->url(array('action' => 'clear-history',
                                                              'id' => $ohmsImport->id),
                                                              'default'); 
                    ?>
                    <td>
                        <a href="<?php echo html_escape($clearHistoryImportUrl);  ?>" class="ohms-clear-history delete-button"><?php echo html_escape(__('Clear History')); ?></a>
                    </td>
                <?php else: ?>
                    <td></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p><?php echo __('You have no imports yet.'); ?></p> 
    <?php endif; ?>
    
</div>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function () {
    Omeka.OhmsImport.confirm();
});
//]]>
</script>
<?php 
    echo foot(); 
?>
