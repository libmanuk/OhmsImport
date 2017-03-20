<?php 
    echo head(array('title' => __('OHMS Import')));
?>
<?php echo common('ohmsimport-nav'); ?>
<div id="primary">
    <h2><?php echo __('Step 2: Map Elements'); ?></h2>
    <?php echo flash(); ?>
    <?php echo $this->form; ?>
</div>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function () {
    Omeka.OhmsImport.enableElementMapping();
});
//]]>
</script>
<?php 
    echo foot(); 
?>
