<?php

namespace adrem\modules\ContentEntityPost\Hooks;

function datahandler_post_insert_thread_end(\PostDataHandler $postDataHandler): void
{
    global $mybb;

    if ($postDataHandler->method == 'insert' && $postDataHandler->return_values['visible'] == 1) {
        if (\adrem\forumIsMonitored($postDataHandler->data['fid']) && \adrem\userIsMonitored()) {
            $post = new \adrem\ContentEntity\Post();

            $post->setId($postDataHandler->return_values['pid']);
            $post->setData([
                'content' => $postDataHandler->data['message'],
            ]);

            \adrem\discoverContentEntity($post);
        }
    }
}

function datahandler_post_insert_post_end(\PostDataHandler $postDataHandler): void
{
    if ($postDataHandler->method == 'insert' && $postDataHandler->return_values['visible'] == 1) {
        if (\adrem\forumIsMonitored($postDataHandler->data['fid']) && \adrem\userIsMonitored()) {
            $post = new \adrem\ContentEntity\Post();

            $post->setId($postDataHandler->return_values['pid']);
            $post->setData([
                'content' => $postDataHandler->data['message'],
            ]);

            \adrem\discoverContentEntity($post);
        }
    }
}

function datahandler_post_update_end(\PostDataHandler $postDataHandler): void
{
    if ($postDataHandler->method == 'update' && $postDataHandler->return_values['visible'] == 1) {
        if (\adrem\forumIsMonitored($postDataHandler->data['fid']) && \adrem\userIsMonitored()) {
            $post = new \adrem\ContentEntity\Post();

            $post->setId($postDataHandler->data['pid']);
            $post->setData([
                'content' => $postDataHandler->data['message'],
            ]);

            \adrem\discoverContentEntity($post);
        }
    }
}

function postbit(array &$post): void
{
    global $mybb, $lang, $pids;

    if ($mybb->usergroup['canviewmodlogs']) {
        static $inspections = null;

        if (!$inspections) {
            $lang->load('adrem');

            $entityIds = str_replace([
                'pid IN(',
                ')',
                '\'',
            ], null, $pids);
            $entityIds = explode(',', $entityIds);

            $inspections = \adrem\getCompletedInspectionEntriesByContentTypeEntityId('post', $entityIds);
        }

        if (isset($inspections[$post['pid']]) && $inspections[$post['pid']] >= $post['edittime']) {
            if ($inspections[$post['pid']]['actions'] != '') {
                $statusName = 'triggered';
                $statusText = $lang->adrem_entity_inspection_status_triggered;
            } else {
                $statusName = 'processed';
                $statusText = $lang->adrem_entity_inspection_status_processed;
            }
        } else {
            $statusName = 'unprocessed';
            $statusText = $lang->adrem_entity_inspection_status_unprocessed;
        }

        $url = $mybb->settings['bburl'] . '/modcp.php?action=content_inspections&amp;type=post&amp;entity_id=' . (int)$post['pid'];

        eval('$post[\'inspection_status\'] = "' . \adrem\tpl('entity_inspection_status') . '";');
    } else {
        $post['inspection_status'] = null;
    }
}
