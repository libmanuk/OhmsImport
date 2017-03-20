<nav id="section-nav" class="navigation vertical">
<?php
    $navArray = array(
        array(
            'label' => 'Import Items',
            'action' => 'index',
            'module' => 'ohms-import',
        ),
        array(
            'label' => 'Status',
            'action' => 'browse',
            'module' => 'ohms-import',
        ),
    );
    echo nav($navArray, 'admin_navigation_settings');
?>
</nav>