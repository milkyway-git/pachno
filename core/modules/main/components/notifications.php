<?php

/**
 * @var \pachno\core\entities\Notification $notification
 */

?>
    <?php if ($num_unread + $num_read == 0 && ! $filter_first_notification): ?>
        <li class="onboarding">
            <div class="image-container">
                <?= image_tag('/unthemed/no-notifications.png', [], true); ?>
            </div>
            <div class="helper-text">
                <?= __('No news is good news'); ?><br>
                <?= __('You will be notified when something happens'); ?>
            </div>
        </li>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
        <?php

            if (!$notification->getTarget() instanceof \pachno\core\entities\common\Identifiable)
            {
                $notification->delete();
                continue;
            }

        ?>
        <li class="<?= ($notification->isRead()) ? 'read' : 'unread'; ?>" id="notification_<?= $notification->getID(); ?>_container" data-notification-id="<?= $notification->getID(); ?>">
            <a href="javascript:void(0);" onclick="Pachno.Main.Notifications.toggleRead(<?= $notification->getID(); ?>);" class="notification_status_toggler">
                <?= image_tag('icon_notification_read.png', array('class' => 'icon_read')); ?>
                <?= image_tag('icon_notification_unread.png', array('class' => 'icon_unread')); ?>
            </a>
            <?php

                switch ($notification->getNotificationType())
                {
                    case \pachno\core\entities\Notification::TYPE_ISSUE_CREATED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name created a new issue under %project_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTarget()->getPostedBy())), '%project_name' => link_tag(make_url('project_dashboard', array('project_key' => $notification->getTarget()->getProject()->getKey())), $notification->getTarget()->getProject()->getName()))); ?>
                        </h1>
                        <div class="notification_content"><?= link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getFormattedIssueNo())), $notification->getTarget()->getFormattedIssueNo(true)); ?> - <?= $notification->getTarget()->getTitle(); ?></div>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ISSUE_UPDATED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%issue_no was updated by %user_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%issue_no' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getFormattedIssueNo())), $notification->getTarget()->getFormattedIssueNo(true)) .' - '. $notification->getTarget()->getTitle())); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ISSUE_COMMENTED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name posted a %comment on %issue_no', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%comment' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getTarget()->getFormattedIssueNo())).'#comment_'.$notification->getTarget()->getID(), __('%username_posted_a comment %on_issue', array('%username_posted_a' => '', '%on_issue' => ''))), '%issue_no' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getTarget()->getFormattedIssueNo())), $notification->getTarget()->getTarget()->getFormattedIssueNo(true)) .' - '. $notification->getTarget()->getTarget()->getTitle())); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_COMMENT_MENTIONED:
                        if ($notification->getTarget()->getTargetType() == \pachno\core\entities\Comment::TYPE_ISSUE): ?>
                            <h1>
                                <?= image_tag($notification->getTarget()->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                                <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                                <?= __('%user_name mentioned you in a %comment on %issue_no', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%comment' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getTarget()->getFormattedIssueNo())).'#comment_'.$notification->getTarget()->getID(), __('%username_posted_a comment %on_issue', array('%username_posted_a' => '', '%on_issue' => ''))), '%issue_no' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getTarget()->getFormattedIssueNo())), $notification->getTarget()->getTarget()->getFormattedIssueNo(true)) .' - '. $notification->getTarget()->getTarget()->getTitle())); ?>
                            </h1>
                        <?php else: ?>
                            <h1>
                                <?= image_tag($notification->getTarget()->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                                <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                                <?= __('%user_name mentioned you in a %comment on %article_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%comment' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getTarget()->getName())).'#comment_'.$notification->getTarget()->getID(), __('%username_posted_a comment %on_issue', array('%username_posted_a' => '', '%on_issue' => ''))), '%article_name' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getTarget()->getName())), $notification->getTarget()->getTarget()->getName()))); ?>
                            </h1>
                        <?php endif; ?>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ARTICLE_COMMENTED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name posted a %comment on %article_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%comment' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getTarget()->getName())).'#comment_'.$notification->getTarget()->getID(), __('%username_posted_a comment %on_issue', array('%username_posted_a' => '', '%on_issue' => ''))), '%article_name' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getTarget()->getName())), $notification->getTarget()->getTarget()->getName()))); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ARTICLE_CREATED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name created a new article %article_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%article_name' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getName())), $notification->getTarget()->getName()))); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ARTICLE_UPDATED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name updated %article_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%article_name' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getName())), $notification->getTarget()->getName()))); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ISSUE_MENTIONED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name mentioned you in an issue %issue_no', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%issue_no' => link_tag(make_url('viewissue', array('project_key' => $notification->getTarget()->getProject()->getKey(), 'issue_no' => $notification->getTarget()->getFormattedIssueNo())), $notification->getTarget()->getFormattedIssueNo(true)) .' - '. $notification->getTarget()->getTitle())); ?>
                        </h1>
                        <?php
                        break;
                    case \pachno\core\entities\Notification::TYPE_ARTICLE_MENTIONED:
                        ?>
                        <h1>
                            <?= image_tag($notification->getTarget()->getProject()->getIconName(), array('class' => 'notification-project-logo'), true); ?>
                            <time><?= \pachno\core\framework\Context::getI18n()->formatTime($notification->getCreatedAt(), 20); ?></time>
                            <?= __('%user_name mentioned you in an article %article_name', array('%user_name' => get_component_html('main/userdropdown_inline', array('user' => $notification->getTriggeredByUser())), '%article_name' => link_tag(make_url('publish_article', array('article_name' => $notification->getTarget()->getName())), $notification->getTarget()->getName()))); ?>
                        </h1>
                        <?php
                        break;
                    default:
                        \pachno\core\framework\Event::createNew('core', '_notification_view', $notification)->trigger();
                }

            ?>
        </li>
            <?php
                // Replace multiple spaces with single space with regex, apply trim, decode entities to show non standard characters and strip tags to remove any left / decoded "injections" to retrieve only valid text of notification.
                if (($notification_text = strip_tags(html_entity_decode(trim(preg_replace('!\s+!', ' ', get_component_html('main/notification_text', compact('notification')))), ENT_COMPAT, \pachno\core\framework\Context::getI18n()->getCharset()))) != ''):
            ?>
                <script>
                    <?php /* require(['domReady', 'pachno/index'], function (domReady, Pachno) {
                        domReady(function () {
                            Pachno.Main.Notifications.Web.Send("<?= __('New notification'); ?>", "<?= $notification_text; ?>", '<?= $notification->getID(); ?>', '<?= $notification->getTriggeredByUser()->getAvatarURL(); ?>', function () {
                                var target_url = "<?= $notification->getTargetUrl(); ?>";
                                var desktop_notifications_new_tab = <?= $desktop_notifications_new_tab ? 'true' : 'false'; ?>;
                                if (target_url.startsWith('http')) {
                                    if (desktop_notifications_new_tab) {
                                        window.open(target_url, '_blank').focus();
                                    }
                                    else {
                                        window.location = target_url;
                                    }
                                }
                                else {
                                    Pachno.UI.Backdrop.show(target_url);
                                }
                            });
                        });
                    }); */ ?>
                </script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
