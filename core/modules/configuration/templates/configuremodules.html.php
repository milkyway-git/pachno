<?php

    use pachno\core\framework\Context;
    use pachno\core\framework\Response;
    use pachno\core\framework\Settings;
    use pachno\core\entities\Module;

    /**
     * @var Response $pachno_response
     * @var Module[][] $modules
     * @var Module[] $uninstalled_modules
     * @var bool $is_default_scope
     * @var bool $writable
     */

    $pachno_response->setTitle(__('Configure modules'));

?>
<div class="content-with-sidebar">
    <?php include_component('configuration/sidebar', ['selected_section' => Settings::CONFIGURATION_SECTION_MODULES]); ?>
    <div class="configuration-container">
        <div class="configuration-content centered">
            <h1><?= __('Configure modules'); ?></h1>
            <div class="helper-text">
                <?= __('Manage existing or download and install new modules for Pachno here.'); ?>
            </div>
            <?php if ($module_error !== null): ?>
                <div class="message-box type-error" id="module_error">
                    <span class="message"><?= fa_image_tag('times') . $module_error; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($module_message !== null): ?>
                <div class="message-box type-info" id="module_message">
                    <span class="message"><?= fa_image_tag('exclamation-circle') . $module_message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (count($outdated_modules) > 0): ?>
                <div class="message-box type-warning" id="outdated_module_message">
                    <span class="message">
                        <?= fa_image_tag('exclamation-circle'); ?>
                        <?php if ($is_default_scope): ?>
                            <?= __('You have %count outdated modules. They have been disabled until you upgrade them, you can upgrade them on this page.', array('%count' => count($outdated_modules))); ?>
                        <?php else: ?>
                            <?= __('You have %count outdated modules. They have been disabled until they are updated by an administrator.', array('%count' => count($outdated_modules))); ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            <div class="fancy-tabs" id="modules_menu">
                <a id="tab_installed" class="tab selected" onclick="Pachno.UI.tabSwitcher('tab_installed', 'modules_menu');"><span><?= __('Installed modules %count', ['%count' => '<span class="count-badge">'.count($modules[Context::EXTERNAL_MODULES]).'</span>']); ?></span></a>
                <a id="tab_uninstalled" class="tab" onclick="Pachno.UI.tabSwitcher('tab_uninstalled', 'modules_menu');"><span><?= __('Installable local modules %count', ['%count' => '<span class="count-badge">'.count($uninstalled_modules).'</span>']); ?></span></a>
                <?php if ($is_default_scope): ?>
                    <a id="tab_install" class="tab" onclick="Pachno.UI.tabSwitcher('tab_install', 'modules_menu');"><?= __('Discover new modules'); ?></a>
                <?php endif; ?>
            </div>
            <div id="modules_menu_panes">
                <div id="tab_installed_pane">
                    <div class="configurable-components-list" id="installed-modules-list">
                        <?php foreach ($modules[Context::EXTERNAL_MODULES] as $module_key => $module): ?>
                            <?php include_component('modulebox', array('module' => $module, 'is_default_scope' => $is_default_scope)); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="tab_uninstalled_pane" style="padding-top: 0; width: 100%; display: none;">
                    <?php if (count($uninstalled_modules) == 0): ?>
                        <div class="faded_out" style="margin-top: 5px;"><?= __('There are no uninstalled modules available'); ?></div>
                    <?php else: ?>
                        <div class="content">
                            <p><?= __('This is a list of modules that are available in the modules folder, but not currently installed on this system.'); ?></p>
                            <?php if (!$writable && $is_default_scope): ?>
                                <div class="message-box type-warning" id="module_message_writable_failure">
                                    <span class="message"><?= fa_image_tag('folder') . __('The modules folder %modules_path seems to not be writable. You may not be able to install new modules.', array('%modules_path' => '<span class="command_box">' . PACHNO_MODULES_PATH . '</span>')); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <ul class="modules-list plugins-list installed" id="uninstalled-modules-list">
                            <?php foreach ($uninstalled_modules as $module_key => $module): ?>
                                <?php include_component('modulebox', array('module' => $module, 'is_default_scope' => $is_default_scope)); ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div id="tab_install_pane" style="padding-top: 0; width: 100%; display: none;">
                    <?php if (!$writable && $is_default_scope): ?>
                        <div class="message-box type-warning" id="module_message_writable_failure">
                            <span class="message"><?= fa_image_tag('folder') . __('The modules folder %modules_path seems to not be writable. You may not be able to install new modules.', array('%modules_path' => '<span class="command_box">' . PACHNO_MODULES_PATH . '</span>')); ?></span>
                        </div>
                    <?php endif; ?>
                    <div id="available_modules_loading_indicator"><?= image_tag('spinning_16.gif'); ?></div>
                    <div id="available_modules_container" class="available_plugins_container plugins-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($is_default_scope): ?>
    <script>
        require(['domReady', 'pachno/index'], function (domReady, Pachno) {
            domReady(function () {
                Pachno.Modules.getAvailableOnline();
                Pachno.Modules.getModuleUpdates();
            });
        });
    </script>
<?php endif; ?>
