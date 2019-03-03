<?php

namespace adrem\ContentEntity;

use adrem\ContentEntity;

class User extends ContentEntity
{
    public static function isDiscoverable(): bool
    {
        return false;
    }

    public function getData(bool $extended = false): ?array
    {
        if ($this->data === null || ($extended && $this->extendedData === null)) {
            $data = \get_user($this->id);

            if ($data !== false) {
                $this->data = $data;
                $this->extendedData = $data;
            } else {
                return null;
            }
        }

        $returnData = $this->data;

        if ($extended) {
            $returnData = array_merge($returnData, $this->extendedData);
        }

        return $returnData;
    }

    public function assumePostContext(Post $contentEntity): bool
    {
        $this->id = $contentEntity->getData(true)['uid'];

        return true;
    }

    public function triggerBanAction(): bool
    {
        global $mybb, $cache, $db;

        if (array_key_exists(\adrem\getSettingValue('contententity_user_ban_time'), \fetch_ban_times())) {
            if ($this->getData(true)) {
                $query = $db->simple_select('banned', 'uid', 'uid = ' . $this->id);

                if ($db->num_rows($query) == 0) {
                    $newUsergroupId = 7;

                    $banTime = \adrem\getSettingValue('contententity_user_ban_time');

                    if ($banTime == '---') {
                        $liftDate = 0;
                    } else {
                        $liftDate = ban_date2timestamp($banTime);
                    }

                    $insert_array = [
                        'uid' => (int)$this->id,
                        'gid' => (int)$newUsergroupId,
                        'oldgroup' => (int)$this->getData(true)['usergroup'],
                        'oldadditionalgroups' => (string)$this->getData(true)['additionalgroups'],
                        'olddisplaygroup' => (int)$this->getData(true)['displaygroup'],
                        'admin' => (int)\adrem\getSettingValue('action_user'),
                        'dateline' => \TIME_NOW,
                        'bantime' => \adrem\getSettingValue('contententity_user_ban_time'),
                        'lifted' => $liftDate,
                        'reason' => $db->escape_string('Ad Rem'),
                    ];

                    $db->insert_query('banned', $insert_array);

                    $update_array = [
                        'usergroup' => $newUsergroupId,
                        'displaygroup' => 0,
                        'additionalgroups' => '',
                    ];

                    $db->update_query('users', $update_array, 'uid = ' . (int)$this->id);

                    $cache->update_banned();
                }
            }
        }

        return false;
    }

    public function triggerModeratePostsAction(): bool
    {
        global $mybb, $db;

        if ($this->getData(true)) {
            $expirationTime = \TIME_NOW + (int)\adrem\getSettingValue('contententity_user_moderation_time');

            if ($this->getData(true)['moderateposts'] == 1) {
                $expirationTime = max(
                    $this->getData(true)['moderationtime'],
                    $expirationTime
                );
            }

            $db->update_query('users', [
                'moderateposts' => 1,
                'moderationtime' => $expirationTime,
            ]);

            return true;
        } else {
            return false;
        }
    }

    public function triggerWarnAction(): bool
    {
        global $mybb;

        if ($mybb->settings['enablewarningsystem']) {
            if ($this->getData()) {
                $userPermissions = user_permissions($this->id);

                if ($userPermissions['canreceivewarnings'] == 1) {
                    $warning = array(
                        'uid' => $this->id,
                        'notes' => 'Ad Rem',
                        'type' => (int)\adrem\getSettingValue('contententity_user_warning_type_id'),
                        'pid' => $this->contextContentEntities['post']->getId(),
                    );

                    // change sender ID
                    $currentUserId = $mybb->user['uid'];

                    $mybb->user['uid'] = \adrem\getSettingValue('action_user');

                    require_once MYBB_ROOT . 'inc/datahandlers/warnings.php';

                    $warningshandler = new \WarningsHandler();

                    $warningshandler->set_data($warning);

                    $result = $warningshandler->validate_warning() && $warningshandler->insert_warning();

                    $mybb->user['uid'] = $currentUserId;

                    return $result;
                }
            }
        }

        return false;
    }
}
