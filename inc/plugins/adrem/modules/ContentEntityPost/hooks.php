<?php

namespace adrem\modules\ContentEntityPost\Hooks;

function admin_config_plugins_activate_commit(): void
{
    global $codename;

    if ($codename == 'adrem') {
        \adrem\replaceInTemplate(
            'postbit',
            '{$post[\'posturl\']}',
            '{$post[\'posturl\']}{$post[\'inspection_status\']}'
        );
        \adrem\replaceInTemplate(
            'postbit_classic',
            '{$post[\'posturl\']}',
            '{$post[\'posturl\']}{$post[\'inspection_status\']}'
        );
    }
}

function admin_config_plugins_deactivate_commit(): void
{
    global $codename;

    if ($codename == 'adrem') {
        \adrem\replaceInTemplate('postbit', '{$post[\'inspection_status\']}', '');
        \adrem\replaceInTemplate('postbit_classic', '{$post[\'inspection_status\']}', '');
    }
}

function datahandler_post_insert_thread_end(\PostDataHandler $postDataHandler): void
{
    datahandler_post_insert_post_end($postDataHandler);
}

function datahandler_post_insert_post_end(\PostDataHandler $postDataHandler): void
{
    if ($postDataHandler->method == 'insert' && $postDataHandler->return_values['visible'] == 1) {
        if (\adrem\modules\ContentEntityPost\forumIsMonitored($postDataHandler->data['fid']) && \adrem\userIsMonitored()) {
            $post = new \adrem\ContentEntity\Post();

            $post->setId($postDataHandler->return_values['pid']);
            $post->setData([
                'title' => $postDataHandler->data['subject'] ?? '',
                'content' => $postDataHandler->data['message'],
            ]);

            $inspection = \adrem\discoverContentEntity($post, 'insert');

            if ($inspection !== null) {
                $contentTypeActions = $inspection->getContentTypeActions();

                if (in_array('unapprove', $contentTypeActions)) {
                    $postDataHandler->return_values['visible'] = 0;
                }
            }
        }
    }
}

function datahandler_post_update(\PostDataHandler $postDataHandler):  void
{
    global $adremRuntimeRegistry;

    $adremRuntimeRegistry['existingPost'] = get_post($postDataHandler->pid);
}

function datahandler_post_update_end(\PostDataHandler $postDataHandler): void
{
    global $adremRuntimeRegistry;

    if ($postDataHandler->return_values['visible'] == 1) {
        if (\adrem\modules\ContentEntityPost\forumIsMonitored($postDataHandler->data['fid']) && \adrem\userIsMonitored()) {
            $post = new \adrem\ContentEntity\Post();

            $post->setId($postDataHandler->pid);
            $post->setData([
                'title' => $postDataHandler->data['subject'] ?? '',
                'content' => $postDataHandler->data['message'],
            ]);

            if (isset($adremRuntimeRegistry['existingPost'])) {
                $post->setData([
                    'title' => $adremRuntimeRegistry['existingPost']['subject'] ?? '',
                    'content' => $adremRuntimeRegistry['existingPost']['message'],
                ], 'previous');
            }

            $inspection = \adrem\discoverContentEntity($post, 'update');

            if ($inspection !== null) {
                $contentTypeActions = $inspection->getContentTypeActions();

                if (in_array('unapprove', $contentTypeActions)) {
                    $postDataHandler->return_values['visible'] = 0;
                }
            }
        }
    }
}

function datahandler_post_validate_post(\PostDataHandler $postDataHandler): void
{
    global $adremRuntimeRegistry;

    if ($postDataHandler->method != 'update') {
        $previousPost = $postDataHandler->verify_post_merge();

        if (is_array($previousPost)) {
            // fetch previous revision data to process post auto merges as post updates
            $adremRuntimeRegistry['existingPost'] = get_post($previousPost['pid']);
        }
    }
}

function datahandler_post_insert_merge(\PostDataHandler $postDataHandler): void
{
    datahandler_post_update_end($postDataHandler);
}

function postbit(array &$post): void
{
    global $mybb, $lang, $pids;

    if ($mybb->usergroup['canviewmodlogs']) {
        $lang->load('adrem');

        if (isset($pids)) {
            static $inspections = null;

            if (!$inspections) {

                $entityIds = str_replace([
                    'pid IN(',
                    ')',
                    '\'',
                ], null, $pids);
                $entityIds = explode(',', $entityIds);

                $inspections = \adrem\getCompletedInspectionEntriesByContentTypeEntityId('post', $entityIds);
            }
        } else {
            $inspections = \adrem\getCompletedInspectionEntriesByContentTypeEntityId('post', [$post['pid']]);
        }

        if (isset($inspections[$post['pid']]) && $inspections[$post['pid']] >= $post['edittime']) {
            if ($inspections[$post['pid']]['actions'] != '') {
                $statusName = 'triggered';
                $statusText = '&#9873;'; // ⚑
                $statusDescription = $lang->adrem_entity_inspection_status_triggered;
            } else {
                $statusName = 'processed';
                $statusText = '&#9872;'; // ⚐
                $statusDescription = $lang->adrem_entity_inspection_status_processed;
            }
        } else {
            $statusName = 'unprocessed';
            $statusText = '&#9872;'; // ⚐
            $statusDescription = $lang->adrem_entity_inspection_status_unprocessed;
        }

        $url = $mybb->settings['bburl'] . '/modcp.php?action=content_inspections&amp;type=post&amp;entity_id=' . (int)$post['pid'];

        eval('$post[\'inspection_status\'] = "' . \adrem\tpl('entity_inspection_status') . '";');
    } else {
        $post['inspection_status'] = null;
    }
}
