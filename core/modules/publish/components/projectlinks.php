<?php

    if (!$project->hasWikiURL()) {
        echo link_tag(\pachno\core\modules\publish\Publish::getArticleLink('Main Page', $project), fa_image_tag('book').'<span>'.__('Documentation').'</span>', array('class' => 'button secondary'));
    } else {
        echo link_tag($project->getWikiURL(), fa_image_tag('book') . '<span>'.__('Documentation').'</span>', array('target' => 'blank', 'class' => 'button secondary'));
    }
