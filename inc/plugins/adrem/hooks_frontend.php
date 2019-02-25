<?php

namespace adrem\Hooks;

function global_start(): void
{
    global $mybb;

    \adrem\loadTemplates([
        'entity_inspection_status',
    ], 'adrem_');

    switch (\THIS_SCRIPT) {
        case 'modcp.php':
            \adrem\loadTemplates([
                'modcp_nav_inspections',
            ], 'adrem_');

            if ($mybb->get_input('action') == 'content_inspections') {
                \adrem\loadTemplates([
                    'modcp_inspections',
                    'modcp_inspections_inspection',
                    'modcp_inspections_inspection_filter',
                    'modcp_inspections_none',
                    'modcp_inspections_request',
                ], 'adrem_');
            } elseif ($mybb->get_input('action') == 'content_inspection') {
                \adrem\loadTemplates([
                    'modcp_inspection',
                    'modcp_inspection_assessments',
                    'modcp_inspection_assessments_item',
                    'modcp_inspection_attribute_value',
                    'modcp_inspection_content_entity_data',
                    'modcp_inspection_content_entity_data_item',
                ], 'adrem_');
            }

            break;
    }
}

function modcp_nav()
{
    global $nav_modlogs, $lang;

    $lang->load('adrem');

    eval('$nav_modlogs .= "' . \adrem\tpl('modcp_nav_inspections') . '";');
}

function modcp_start(): void
{
    global $mybb, $db, $lang,
    $header, $headerinclude, $footer, $theme, $modcp_nav;

    if ($mybb->get_input('action') == 'content_inspections') {
        if ($mybb->usergroup['canviewmodlogs']) {
            if ($mybb->request_method == 'post' && \verify_post_check($mybb->get_input('my_post_key'))) {
                $contentEntity = \adrem\getContentEntity(
                    $mybb->get_input('type'),
                    $mybb->get_input('entity_id', \MyBB::INPUT_INT)
                );

                if ($contentEntity && $contentEntity->accessibleForCurrentUser()) {
                    \adrem\inspectContentEntity($contentEntity);
                    \redirect(
                        'modcp.php?action=content_inspections&amp;type=' . \htmlspecialchars_uni($mybb->get_input('type')) . '&amp;entity_id=' . $mybb->get_input('entity_id', \MyBB::INPUT_INT),
                        $lang->adrem_inspection_requested
                    );
                }
            }

            if (
                $mybb->get_input('type') &&
                $mybb->get_input('entity_id') &&
                \adrem\contentTypeExists($mybb->get_input('type'))
            ) {
                $title = $lang->sprintf(
                    $lang->adrem_entity_inspection_logs,
                    \htmlspecialchars_uni($mybb->get_input('type')),
                    \htmlspecialchars_uni($mybb->get_input('entity_id', \MyBB::INPUT_INT))
                );
                $contentType = $mybb->get_input('type');
                $contentEntityId = $mybb->get_input('entity_id', \MyBB::INPUT_INT);
                $baseurl = 'modcp.php?action=content_inspections&amp;type=' . $contentType . '&amp;entity_id=' . $contentEntityId;

                eval('$request .= "' . \adrem\tpl('modcp_inspections_request') . '";');
            } else {
                $title = $lang->adrem_inspection_logs;
                $contentType = null;
                $contentEntityId = null;
                $baseurl = 'modcp.php?action=content_inspections';
                $request = null;
            }

            $itemsNum = \adrem\getCompletedInspectionEntriesCount($contentType, $contentEntityId);

            $listManager = new \adrem\ListManager([
                'mybb' => $mybb,
                'baseurl' => $baseurl,
                'order_columns' => ['date_completed'],
                'order_dir' => 'desc',
                'items_num' => $itemsNum,
                'per_page' => $mybb->settings['threadsperpage'],
            ]);

            if ($itemsNum > 0) {
                $results = null;

                $query = \adrem\getCompletedInspectionEntries($contentType, $contentEntityId, $listManager->sql());

                while ($inspectionData = $db->fetch_array($query)) {
                    if (\adrem\contentTypeExists($inspectionData['content_type'])) {
                        $contentType = \htmlspecialchars_uni($inspectionData['content_type']);
                        $entityId = (int)$inspectionData['content_entity_id'];
                        $entityUrl = \adrem\getContentEntityUrl($inspectionData['content_type'], $entityId);
                        $date = \my_date('relative', $inspectionData['date_completed']);
                        $entityInspectionUrl = 'modcp.php?action=content_inspection&amp;id=' . $inspectionData['id'];

                        if (!$contentEntityId) {
                            $filterUrl = 'modcp.php?action=content_inspections&amp;type=' . $inspectionData['content_type'] . '&amp;entity_id=' . $inspectionData['content_entity_id'];
                            eval('$controls = "' . \adrem\tpl('modcp_inspections_inspection_filter') . '";');
                        } else {
                            $controls = null;
                        }

                        if (empty($inspectionData['actions'])) {
                            $actions = '-';
                        } else {
                            $actions = '<code>' . \htmlspecialchars_uni(
                                str_replace(';', '</code>, <code>', $inspectionData['actions'])
                            ) . '</code>';
                        }

                        eval('$results .= "' . \adrem\tpl('modcp_inspections_inspection') . '";');
                    }
                }
            } else {
                eval('$results .= "' . \adrem\tpl('modcp_inspections_none') . '";');
            }

            $resultspages = $listManager->pagination();

            eval('$page = "' . \adrem\tpl('modcp_inspections') . '";');

            \output_page($page);
        } else {
            \error_no_permission();
        }
    } elseif ($mybb->get_input('action') == 'content_inspection') {
        if ($mybb->usergroup['canviewmodlogs']) {
            $inspectionData = \adrem\getInspectionEntry($mybb->get_input('id', \MyBB::INPUT_INT));

            if ($inspectionData) {
                if (\adrem\contentEntityAccessibleForCurrentUser(
                    $inspectionData['content_type'],
                    $inspectionData['content_entity_id']
                )) {
                    $contentType = \htmlspecialchars_uni($inspectionData['content_type']);
                    $entityId = (int)$inspectionData['content_entity_id'];
                    $entityUrl = \adrem\getContentEntityUrl($inspectionData['content_type'], $inspectionData['content_entity_id']);
                    $dateCompleted = \my_date('relative', $inspectionData['date_completed']);

                    if (empty($actions)) {
                        $actions = '-';
                    } else {
                        $actions = \htmlspecialchars_uni(
                            str_replace(';', ', ', $inspectionData['actions'])
                        );
                    }

                    if ($inspectionData['content_entity_data']) {
                        $contentEntityDataItems = null;

                        $items = json_decode($inspectionData['content_entity_data']);

                        foreach ($items as $name => $value) {
                            $name = \htmlspecialchars_uni($name);
                            $value = \htmlspecialchars_uni($value);

                            eval('$contentEntityDataItems .= "' . \adrem\tpl('modcp_inspection_content_entity_data_item') . '";');
                        }

                        eval('$contentEntityData .= "' . \adrem\tpl('modcp_inspection_content_entity_data') . '";');
                    } else {
                        $contentEntityData = null;
                    }

                    $query = \adrem\getInspectionAssessmentEntries($inspectionData['id']);

                    if ($db->num_rows($query)) {
                        $assessments = null;

                        while ($assessmentData = $db->fetch_array($query)) {
                            $name = \htmlspecialchars_uni($assessmentData['name']);
                            $dateCompleted = \my_date('relative', $assessmentData['date_completed']);
                            $duration = number_format($assessmentData['duration'] * 1000) . ' ms';

                            $attributeValues = null;

                            if ($assessmentData['attribute_values']) {
                                $values = json_decode($assessmentData['attribute_values']);

                                $attributeValues = null;

                                foreach ($values as $attributeName => $attributeValue) {
                                    $attributeName = \htmlspecialchars_uni($attributeName);
                                    $attributeValue = \htmlspecialchars_uni($attributeValue);

                                    eval('$attributeValues .= "' . \adrem\tpl('modcp_inspection_attribute_value') . '";');
                                }
                            }

                            if ($assessmentData['result_data']) {
                                $resultData = json_encode(
                                    json_decode($assessmentData['result_data']),
                                    JSON_PRETTY_PRINT
                                );
                            } else {
                                $resultData = null;
                            }

                            eval('$assessments .= "' . \adrem\tpl('modcp_inspection_assessments_item') . '";');
                        }

                        eval('$inspectionAssessments .= "' . \adrem\tpl('modcp_inspection_assessments') . '";');
                    } else {
                        $inspectionAssessments = null;
                    }

                    eval('$page = "' . \adrem\tpl('modcp_inspection') . '";');

                    \output_page($page);
                } else {
                    \error_no_permission();
                }
            } else {
                \error($lang->adrem_inspection_not_found);
            }
        } else {
            \error_no_permission();
        }
    }
}

// asssessments: posts
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
                $statusText = $lang->adrem_inspection_status_triggered;
            } else {
                $statusName = 'processed';
                $statusText = $lang->adrem_inspection_status_processed;
            }
        } else {
            $statusName = 'unprocessed';
            $statusText = $lang->adrem_inspection_status_unprocessed;
        }

        $url = $mybb->settings['bburl'] . '/modcp.php?action=content_inspections&amp;type=post&amp;entity_id=' . (int)$post['pid'];

        eval('$post[\'inspection_status\'] = "' . \adrem\tpl('entity_inspection_status') . '";');
    } else {
        $post['inspection_status'] = null;
    }
}
