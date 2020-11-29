<?php 

    $pachno_response->setTitle(__('Frontpage'));

/**
 * @var \pachno\core\entities\User $pachno_user
 * @var \pachno\core\helpers\Pagination $active_pagination
 * @var \pachno\core\helpers\Pagination $archived_pagination
 * @var \pachno\core\entities\Project[] $active_projects
 * @var \pachno\core\entities\Project[] $archived_projects
 * @var int $active_project_count
 * @var int $archived_project_count
 * @var bool $show_project_config_link
 * @var bool $show_project_list
 */

?>
<div class="content-with-sidebar">
    <nav class="sidebar">
        <?php include_component('main/menulinks', array('links' => $links, 'target_type' => 'main_menu', 'target_id' => 0, 'title' => __('Quick links'))); ?>
        <?php \pachno\core\framework\Event::createNew('core', 'index_left')->trigger(); ?>
        <?php if (!$pachno_user->isGuest()): ?>
            <?php include_component('main/onboarding_invite'); ?>
        <?php endif; ?>
    </nav>
    <div class="main_area frontpage">
        <?php \pachno\core\framework\Event::createNew('core', 'index_right_top')->trigger(); ?>
        <?php if ($show_project_list): ?>
            <?php include_component('main/projectlist', ['list_mode' => 'all', 'admin' => false]); ?>
        <?php endif; ?>
        <?php \pachno\core\framework\Event::createNew('core', 'index_right_bottom')->trigger(); ?>
    </div>
</div>
