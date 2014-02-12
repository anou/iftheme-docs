<?php
defined('WYSIJA') or die('Restricted access');

class WYSIJA_help_shortcodes extends WYSIJA_object {

    // Email object.
    private $email;
    // Receiver object.
    private $receiver;
    // User model.
    private $userM;
    // Shortcodes found.
    private $find;
    // Replacement values for shortcodes found.
    private $replace;

    function WYSIJA_help_shortcodes() {
    }

    // Main function. Call it assigning an Email object and a Receiver object.
    private function initialize($email, $receiver) {

        // Set current object properties.
        $this->email = $email;
        $this->receiver = $receiver;
        $this->userM = WYSIJA::get('user','model');
        $this->userM->getFormat = OBJECT;
        $this->find = array();
        $this->replace = array();

    }

    public function replace_body($email, $receiver = NULL) {

        $this->initialize($email, $receiver);

        $body_tags = $this->email->tags;
        $this->loop_tags($body_tags);
        $replaced_body = str_replace($this->find, $this->replace, $this->email->body);
        return $replaced_body;

    }

    public function replace_subject($email, $receiver = NULL) {

        $this->initialize($email, $receiver);

        $subject_tags = $this->email->subject_tags;
        $this->loop_tags($subject_tags);
        $replaced_subject = str_replace($this->find, $this->replace, $this->email->subject);
        return $replaced_subject;

    }

    private function loop_tags($tags) {

        $this->find = array();
        $this->replace = array();

        // Loop through the shortcodes array and call private group functions.
        foreach($tags as $tag_find => $tag_replace){
            foreach($tag_replace as $couple_value){

                switch ($couple_value[0]) {

                    // [user:xxx | default:xxx]
                    case 'user':
                        $replacement = $this->replace_user_shortcodes($couple_value[1]);
                        if ($replacement === 'subscriber' || $replacement === 'member') {
                            continue;
                        }
                        break(2);
                    case 'default':
                        $replacement = $couple_value[1];
                        break(2);

                    // [newsletter:xxx]
                    case 'newsletter':
                        $replacement = $this->replace_newsletter_shortcodes($couple_value[1]);
                        break;

                    // [date:xxx]
                    case 'date':
                        $replacement = $this->replace_date_shortcodes($couple_value[1]);
                        break;

                    // [global:xxx]
                    case 'global':
                        $replacement = $this->replace_global_shortcodes($couple_value[1]);
                        break;

                    // [custom:xxx]
                    case 'custom':
                        $replacement = $this->replace_custom_shortcodes($couple_value[1]);
                        break;

                    default:
                        break;
                }
            }

            $this->find[] = $tag_find;
            $this->replace[] = $replacement;
            $replacement = '';

        }

    }

    // [user:firstname]
    // [user:lastname]
    // [user:email]
    // [user:displayname]
    // [user:count]
    private function replace_user_shortcodes($tag_value) {
        $replacement = '';
        if (($tag_value === 'firstname') || ($tag_value === 'lastname') || ($tag_value === 'email')) {
            if(isset($this->receiver->$tag_value) && $this->receiver->$tag_value) {
                // uppercase the initials of the first name and last name when replacing it
                if (($tag_value === 'firstname') || ($tag_value === 'lastname')){
                    $replacement = ucwords(strtolower($this->receiver->$tag_value));
                }else{
                    $replacement = $this->receiver->$tag_value;
                }

             } else {
                $replacement = 'subscriber';
             }
        }

        if ($tag_value === 'displayname') {
            $replacement = 'member';
            if(!empty($this->receiver->wpuser_id))
            {
                $user_info = get_userdata();
                if(!empty($user_info->display_name) && $user_info->display_name != false) {
                    $replacement = $user_info->display_name;
                 } elseif(!empty($user_info->user_nicename) && $user_info->user_nicename != false) {
                    $replacement = $user_info->user_nicename;
                }
            }
        }
        if ($tag_value === 'count') {
            $replacement = $this->userM->count();
        }

        return $replacement;

    }

    // [global:unsubscribe]
    // [global:manage]
    // [global:browser]
    private function replace_global_shortcodes($tag_value) {
        $replacement = '';
        if (($tag_value === 'unsubscribe')) {
            $replacement = $this->userM->getUnsubLink($this->receiver);
        }

        if ($tag_value === 'manage') {
            $replacement = $this->userM->getEditsubLink($this->receiver);
        }

        if ($tag_value === 'browser') {
            $emailH = WYSIJA::get('email','helper');
            $configM = WYSIJA::get('config','model');
            $data_email = array();
            $data_email['email_id'] = $this->email->email_id;
            $view_browser_url = $emailH->getVIB($data_email);
            $view_browser_message = $configM->viewInBrowserLink(true);
            $replacement .= $view_browser_message['pretext'];
            $replacement .= '<a href="' . $view_browser_url . '">';
            $replacement .= $view_browser_message['label'];
            $replacement .= '</a>';
            $replacement .= $view_browser_message['posttext'];
        }

        return $replacement;

    }

    // [newsletter:subject]
    // [newsletter:total]
    // [newsletter:post_title]
    // [newsletter:number]
    private function replace_newsletter_shortcodes($tag_value) {
        switch ($tag_value) {
            case 'subject':
                $replacement = $this->email->subject;
                break;

            case 'total':
                $replacement = $this->email->params['autonl']['articles']['count'];
                break;

            case 'post_title':
                $replacement = $this->email->params['autonl']['articles']['first_subject'];
                break;

            case 'number':
                // number is the issue number not the number of articles that were sent since the beginning.
                $replacement = (int)$this->email->params['autonl']['total_child'];
                break;

            default:
                $replacement = '';
                break;
        }

        return $replacement;

    }

    // [date:d]
    // [date:m]
    // [date:y]
    // [date:dtext]
    // [date:mtext]
    // [date:dordinal]
    private function replace_date_shortcodes($tag_value) {

        $current_time = current_time('timestamp');

        switch ($tag_value) {
            case 'd':
                $replacement = date_i18n( 'j', $current_time);
                break;

            case 'm':
                $replacement = date_i18n( 'n', $current_time);
                break;

            case 'y':
                $replacement = date_i18n( 'Y', $current_time);
                break;

            case 'dtext':
                $replacement = date_i18n( 'l', $current_time);
                break;

            case 'mtext':
                $replacement = date_i18n( 'F', $current_time);
                break;

            case 'dordinal':
                $replacement = date_i18n( 'jS', $current_time);
                break;

            default:
                $replacement = '';
                break;
        }

        return $replacement;

    }

    /**
     * We pass the value of the tag, the string after custom:
     * To the external filter, and we expect the filter to return a string.
     */
    // [custom:xxx]
    private function replace_custom_shortcodes($tag_value) {

        $replacement = apply_filters('wysija_shortcodes', $tag_value);

        return $replacement;

    }

}
