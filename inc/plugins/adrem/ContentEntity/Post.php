<?php

namespace adrem\ContentEntity;

use adrem\ContentEntity;

class Post extends ContentEntity
{
    public function getUrl(): ?string
    {
        return \get_post_link($this->id) . '#pid' . $this->id;
    }

    public function getData(bool $extended = false, ?string $revision = null): ?array
    {
        if ($revision === null) {
            $revision = $this->defaultRevision;
        }

        if (!isset($this->data[$revision]) || ($extended && !isset($this->extendedData[$revision]))) {
            $data = \get_post($this->id);

            if ($data !== false) {
                $this->data[$revision] = [
                    'title' => $data['subject'],
                    'content' => $data['message'],
                ];
                $this->extendedData[$revision] = $data;
            } else {
                return null;
            }
        }

        return parent::getData($extended, $revision);
    }

    public function accessibleForCurrentUser(): bool
    {
        global $mybb;

        $post = \get_post($this->id);

        if ($post) {
            $thread = \get_thread($post['tid']);

            if ($thread) {
                $unviewable_forums = explode(',', get_unviewable_forums(true));
                $forumpermissions = forum_permissions($post['fid']);

                if (
                    $forumpermissions['canview'] &&
                    (!isset($forumpermissions['canonlyviewownthreads']) || $forumpermissions['canonlyviewownthreads'] != 1 || $post['uid'] == $mybb->user['uid']) &&
                    !in_array($post['fid'], $unviewable_forums) &&
                    (
                        $post['visible'] == 1 ||
                        ($post['visible'] == 0 && is_moderator($post['fid'], 'canviewunapprove') == true) ||
                        ($post['visible'] == -1 && is_moderator($post['fid'], 'canviewdeleted') == true)
                    ) &&
                    (
                        $thread['visible'] == 1 ||
                        ($thread['visible'] == 0 && is_moderator($thread['fid'], 'canviewunapprove') == true) ||
                        ($thread['visible'] == -1 && is_moderator($thread['fid'], 'canviewdeleted') == true)
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function triggerReportAction(): bool
    {
        global $db;

        require_once MYBB_ROOT . 'inc/functions_modcp.php';

        $count = $db->fetch_field(
            $db->simple_select(
                'reportedcontent',
                'COUNT(*) AS n',
                "reportstatus != 1 AND id = " . (int)$this->getData(true)['pid'] . " AND type = 'post'"
            ),
            'n'
        );

        if ($count == 0) {
            $report = [
                'id' => $this->getData(true)['pid'],
                'id2' => $this->getData(true)['tid'],
                'id3' => $this->getData(true)['fid'],
                'uid' => \adrem\getSettingValue('action_user'),
                'reasonid' => 1,
                'reason' => 'Ad Rem',
            ];

            \add_report($report, 'post');
        }

        return true;
    }

    public function triggerSoftDeleteAction(): bool
    {
        global $mybb;

        if ($mybb->settings['soft_delete']) {
            require_once MYBB_ROOT . 'inc/class_moderation.php';

            $moderation = new \Moderation();

            return $moderation->soft_delete_posts([$this->id]);
        } else {
            return false;
        }
    }

    public function triggerUnapproveAction(): bool
    {
        require_once MYBB_ROOT . 'inc/class_moderation.php';

        $moderation = new \Moderation();

        return $moderation->unapprove_posts([$this->id]);
    }
}
