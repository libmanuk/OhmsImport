<?php
    echo head(array('title' => __('OHMS Import')));
?>
<?php echo common('ohmsimport-nav'); ?>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo __('Step 1: Select File and Item Settings'); ?></h2>
    <?php echo $this->form; ?>
</div>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function () {
    jQuery('#omeka_ohms_export').click(Omeka.OhmsImport.updateImportOptions);
    Omeka.OhmsImport.updateImportOptions(); // need this to reset invalid forms
});
//]]>
</script>
<?php
    echo foot();
?>
