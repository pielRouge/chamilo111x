<?php
/* For licensing terms, see /license.txt */

/**
 * @package chamilo.messages
 */

/**
 * This script shows a compose area (wysiwyg editor if supported, otherwise
 * a simple textarea) where the user can type a message.
 * There are three modes
 * - standard: type a message, select a user to send it to, press send
 * - reply on message (when pressing reply when viewing a message)
 * - send to specific user (when pressing send message in the who is online list).
 */
$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();

if (api_get_setting('allow_message_tool') !== 'true') {
    api_not_allowed(true);
}

$allowSocial = api_get_setting('allow_social_tool') == 'true';

$nameTools = api_xml_http_response_encode(get_lang('Messages'));

$htmlHeadXtra[] = '<script>
var counter_image = 1;
function add_image_form() {
    // Multiple filepaths for image form
    var filepaths = document.getElementById("file_uploads");
    if (document.getElementById("filepath_"+counter_image)) {
        counter_image = counter_image + 1;
    }  else {
        counter_image = counter_image;
    }
    var elem1 = document.createElement("div");
    elem1.setAttribute("id","filepath_"+counter_image);
    filepaths.appendChild(elem1);
    id_elem1 = "filepath_"+counter_image;
    id_elem1 = "\'"+id_elem1+"\'";
    document.getElementById("filepath_"+counter_image).innerHTML = "<div class=\"form-group\" ><label class=\"col-sm-4\">'.get_lang('FilesAttachment').'</label><input class=\"col-sm-8\" type=\"file\" name=\"attach_"+counter_image+"\" /></div><div class=\"form-group\" ><label class=\"col-sm-4\">'.get_lang('Description').'</label><div class=\"col-sm-8\"><input style=\"width:100%\" type=\"text\" name=\"legend[]\" /></div></div>";
    if (filepaths.childNodes.length == 6) {
        var link_attach = document.getElementById("link-more-attach");
        if (link_attach) {
            link_attach.innerHTML="";
        }
    }
}
</script>';
$nameTools = get_lang('ComposeMessage');

/**
 * Shows the compose area + a list of users to select from.
 */
function show_compose_to_any($user_id)
{
    $default['user_list'] = 0;
    $online_user_list = null;
    $html = manageForm($default, $online_user_list);

    return $html;
}

function show_compose_reply_to_message($message_id, $receiver_id)
{
    $table = Database::get_main_table(TABLE_MESSAGE);
    $query = "SELECT user_sender_id
              FROM $table
              WHERE user_receiver_id = ".intval($receiver_id)." AND id = ".intval($message_id);
    $result = Database::query($query);
    $row = Database::fetch_array($result, 'ASSOC');
    $userInfo = api_get_user_info($row['user_sender_id']);
    if (empty($row['user_sender_id']) || empty($userInfo)) {
        $html = get_lang('InvalidMessageId');

        return $html;
    }

    $default['users'] = [$row['user_sender_id']];
    $html = manageForm($default, null, $userInfo['complete_name_with_username']);

    return $html;
}

function show_compose_to_user($receiver_id)
{
    $userInfo = api_get_user_info($receiver_id);
    $html = get_lang('To').':&nbsp;<strong>'.$userInfo['complete_name'].'</strong>';
    $default['title'] = api_xml_http_response_encode(get_lang('EnterTitle'));
    $default['users'] = [$receiver_id];
    $html .= manageForm($default);

    return $html;
}

/**
 * @param $default
 * @param null   $select_from_user_list
 * @param string $sent_to
 *
 * @return string
 */
function manageForm($default, $select_from_user_list = null, $sent_to = '')
{
    $group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : null;
    $message_id = isset($_GET['message_id']) ? intval($_GET['message_id']) : null;

    $form = new FormValidator(
        'compose_message',
        null,
        api_get_self(),
        null,
        ['enctype' => 'multipart/form-data']
    );
    if (empty($group_id)) {
        if (isset($select_from_user_list)) {
            $form->addText(
                'id_text_name',
                get_lang('SendMessageTo'),
                true,
                [
                    'id' => 'id_text_name',
                    'onkeyup' => 'send_request_and_search()',
                    'autocomplete' => 'off',
                ]
            );
            $form->addRule('id_text_name', get_lang('ThisFieldIsRequired'), 'required');
            $form->addElement('html', '<div id="id_div_search" style="padding:0px" class="message-select-box" >&nbsp;</div>');
            $form->addElement('hidden', 'user_list', 0, ['id' => 'user_list']);
        } else {
            if (!empty($sent_to)) {
                $form->addLabel(get_lang('SendMessageTo'), $sent_to);
            }
            if (empty($default['users'])) {
                //fb select
                $form->addElement(
                    'select_ajax',
                    'users',
                    get_lang('SendMessageTo'),
                    [],
                    [
                        'multiple' => 'multiple',
                        'url' => api_get_path(WEB_AJAX_PATH).'message.ajax.php?a=find_users',
                    ]
                );
            } else {
                $form->addElement('hidden', 'hidden_user', $default['users'][0], ['id' => 'hidden_user']);
            }
        }
    } else {
        $userGroup = new UserGroup();
        $group_info = $userGroup->get($group_id);

        $form->addElement('label', get_lang('ToGroup'), api_xml_http_response_encode($group_info['name']));
        $form->addElement('hidden', 'group_id', $group_id);
        $form->addElement('hidden', 'parent_id', $message_id);
    }

    $form->addText('title', get_lang('Subject'), true);
    $form->addHtmlEditor(
        'content',
        get_lang('Message'),
        false,
        false,
        ['ToolbarSet' => 'Messages', 'Width' => '100%', 'Height' => '250']
    );

    if (isset($_GET['re_id'])) {
        $message_reply_info = MessageManager::get_message_by_id($_GET['re_id']);
        $default['title'] = get_lang('MailSubjectReplyShort')." ".$message_reply_info['title'];
        $form->addHidden('re_id', intval($_GET['re_id']));
        $form->addHidden('save_form', 'save_form');

        // Adding reply mail
        $user_reply_info = api_get_user_info($message_reply_info['user_sender_id']);
        $default['content'] = '<p><br/></p>'.sprintf(
            get_lang('XWroteY'),
            $user_reply_info['complete_name'],
            Security::filter_terms($message_reply_info['content'])
        );
    }

    if (isset($_GET['forward_id'])) {
        $forwardId = (int) $_GET['forward_id'];
        $message_reply_info = MessageManager::get_message_by_id($forwardId);
        $default['title'] = '['.get_lang('MailSubjectForwardShort').": ".$message_reply_info['title'].']';
        $form->addHidden('forward_id', $forwardId);
        $form->addHidden('save_form', 'save_form');
        $receiverInfo = api_get_user_info($message_reply_info['user_receiver_id']);

        $forwardMessage = '---------- '.get_lang('ForwardedMessage').' ---------'.'<br />';
        $forwardMessage .= get_lang('Date').': '.api_get_local_time($message_reply_info['send_date']).'<br />';
        $forwardMessage .= get_lang('Subject').': '.$message_reply_info['title'].'<br />';
        $forwardMessage .= get_lang('To').': '.$receiverInfo['complete_name'].' - '.$receiverInfo['email'].' <br />';
        $default['content'] = '<p><br/></p>'.$forwardMessage.'<br />'.Security::filter_terms($message_reply_info['content']);
    }

    if (empty($group_id)) {
        $form->addElement(
            'label',
            '',
            '<div id="file_uploads"><div id="filepath_1">
                <div id="filepaths" class="form-horizontal">
                    <div id="paths-file" class="form-group">
                    <label class="col-sm-4">'.get_lang('FilesAttachment').'</label>
                    <input class="col-sm-8" type="file" name="attach_1"/>
                    </div>
                </div>
                <div id="paths-description" class="form-group">
                    <label class="col-sm-4">'.get_lang('Description').'</label>
                    <div class="col-sm-8">
                    <input id="file-descrtiption" class="form-control" type="text" name="legend[]" />
                    </div>
                </div>
            </div>
            </div>
            '
        );

        $form->addLabel(
            '',
            '<span id="link-more-attach"><a href="javascript://" onclick="return add_image_form()">'.get_lang('AddOneMoreFile').'</a></span>&nbsp;('.sprintf(get_lang('MaximunFileSizeX'), format_file_size(api_get_setting('message_max_upload_filesize'))).')'
        );
    }

    $form->addButtonSend(get_lang('SendMessage'), 'compose');
    $form->setRequiredNote('<span class="form_required">*</span> <small>'.get_lang('ThisFieldIsRequired').'</small>');

    if (!empty($group_id) && !empty($message_id)) {
        $message_info = MessageManager::get_message_by_id($message_id);
        $default['title'] = get_lang('MailSubjectReplyShort')." ".$message_info['title'];
    }
    $form->setDefaults($default);
    $html = '';
    if ($form->validate()) {
        $check = Security::check_token('post');
        if ($check) {
            $user_list = $default['users'];
            $file_comments = $_POST['legend'];
            $title = $default['title'];
            $content = $default['content'];
            $group_id = isset($default['group_id']) ? $default['group_id'] : null;
            $parent_id = isset($default['parent_id']) ? $default['parent_id'] : null;
            $forwardId = isset($_POST['forward_id']) ? $_POST['forward_id'] : false;

            if (is_array($user_list) && count($user_list) > 0) {
                //all is well, send the message
                foreach ($user_list as $userId) {
                    $res = MessageManager::send_message(
                        $userId,
                        $title,
                        $content,
                        $_FILES,
                        $file_comments,
                        $group_id,
                        $parent_id,
                        0,
                        0,
                        null,
                        false,
                        $forwardId
                    );
                    if ($res) {
                        $userInfo = api_get_user_info($userId);
                        Display::addFlash(Display::return_message(
                            get_lang('MessageSentTo')."&nbsp;<b>".$userInfo['complete_name_with_username']."</b>",
                            'confirmation',
                            false
                        ));
                    }
                }
            } else {
                Display::addFlash(Display::return_message('ErrorSendingMessage', 'error'));
            }
        }
        Security::clear_token();
        header('Location: '.api_get_path(WEB_PATH).'main/messages/inbox.php');
        exit;
    } else {
        $token = Security::get_token();
        $form->addElement('hidden', 'sec_token');
        $form->setConstants(['sec_token' => $token]);
        $html .= $form->returnForm();
    }

    return $html;
}

/* MAIN SECTION */
if ($allowSocial) {
    $this_section = SECTION_SOCIAL;
    $interbreadcrumb[] = [
        'url' => api_get_path(WEB_PATH).'main/social/home.php',
        'name' => get_lang('SocialNetwork'),
    ];
} else {
    $this_section = SECTION_MYPROFILE;
    $interbreadcrumb[] = [
        'url' => api_get_path(WEB_PATH).'main/auth/profile.php',
        'name' => get_lang('Profile'),
    ];
}

$interbreadcrumb[] = [
    'url' => api_get_path(WEB_PATH).'main/messages/inbox.php',
    'name' => get_lang('Messages'),
];

$group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : null;
$social_right_content = null;
if ($group_id != 0) {
    $social_right_content .= '<div class=actions>';
    $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/social/group_view.php?id='.$group_id.'">'.
        Display::return_icon('back.png', api_xml_http_response_encode(get_lang('ComposeMessage'))).'</a>';
    $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/messages/new_message.php?group_id='.$group_id.'">'.
        Display::return_icon('message_new.png', api_xml_http_response_encode(get_lang('ComposeMessage'))).'</a>';
    $social_right_content .= '</div>';
} else {
    if ($allowSocial) {
    } else {
        $social_right_content .= '<div class=actions>';
        if (api_get_setting('allow_message_tool') === 'true') {
            $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/messages/new_message.php">'.
                Display::return_icon('message_new.png', get_lang('ComposeMessage')).'</a>';
            $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/messages/inbox.php">'.
                Display::return_icon('inbox.png', get_lang('Inbox')).'</a>';
            $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/messages/outbox.php">'.
                Display::return_icon('outbox.png', get_lang('Outbox')).'</a>';
        }
        $social_right_content .= '</div>';
    }
}

// LEFT COLUMN
$social_left_content = '';
if ($allowSocial) {
    // Block Social Menu
    $social_menu_block = SocialManager::show_social_menu('messages');
    $social_right_content .= '<div class="row">';
    $social_right_content .= '<div class="col-md-12">';
    $social_right_content .= '<div class="actions">';
    $social_right_content .= '<a href="'.api_get_path(WEB_PATH).'main/messages/inbox.php">'.
        Display::return_icon('back.png', get_lang('Back'), [], 32).'</a>';
    $social_right_content .= '</div>';
    $social_right_content .= '</div>';
    $social_right_content .= '<div class="col-md-12">';
}

// MAIN CONTENT
if (!isset($_POST['compose'])) {
    if (isset($_GET['re_id'])) {
        $social_right_content .= show_compose_reply_to_message(
            $_GET['re_id'],
            api_get_user_id()
        );
    } elseif (isset($_GET['send_to_user'])) {
        $social_right_content .= show_compose_to_user($_GET['send_to_user']);
    } else {
        $social_right_content .= show_compose_to_any(api_get_user_id());
    }
} else {
    $restrict = false;
    if (isset($_POST['users'])) {
        $restrict = true;
    } elseif (isset($_POST['group_id'])) {
        $restrict = true;
    } elseif (isset($_POST['hidden_user'])) {
        $restrict = true;
    }

    $default['title'] = $_POST['title'];
    $default['content'] = $_POST['content'];

    // comes from a reply button
    if (isset($_GET['re_id']) || isset($_GET['forward_id'])) {
        $social_right_content .= manageForm($default);
    } else {
        // post
        if ($restrict) {
            if (!isset($_POST['group_id'])) {
                $default['users'] = isset($_POST['users']) ? $_POST['users'] : null;
            } else {
                $default['group_id'] = $_POST['group_id'];
            }
            if (isset($_POST['hidden_user'])) {
                $default['users'] = [$_POST['hidden_user']];
            }
            $social_right_content .= manageForm($default);
        } else {
            $social_right_content .= Display::return_message(get_lang('ErrorSendingMessage'), 'error');
        }
    }
}

if ($allowSocial) {
    $social_right_content .= '</div>';
    $social_right_content .= '</div>';
}

$tpl = new Template(get_lang('ComposeMessage'));
// Block Social Avatar
SocialManager::setSocialUserBlock($tpl, api_get_user_id(), 'messages');

if ($allowSocial) {
    $tpl->assign('social_menu_block', $social_menu_block);
    $tpl->assign('social_right_content', $social_right_content);
    $social_layout = $tpl->get_template('social/inbox.tpl');
    $tpl->display($social_layout);
} else {
    $content = $social_right_content;
    $tpl->assign('content', $content);
    $tpl->display_one_col_template();
}
