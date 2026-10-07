<?php

class SMS_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->getSettings();
    }

    private $smsEnabled = 'false';

    private $apiKey = '';

    private $url = '';

    private $bulkUrl = '';

    private $senderId = '';

    private $smsType = '';

    private $senderName = '';

    private $senderPhone = '';

    private function getSmsFooter()
    {
        return "\n\nThank you,\n{$this->session->userdata('name')}\nPhone: {$this->session->userdata('phone')}";
    }

    public function getSettings()
    {
        $query = $this->db->query('select * from tbl_sms_settings');
        if ($query->num_rows() == 0) {
            $this->smsEnabled = 'false';

            return;
        }

        $settings = $query->row();
        $this->smsEnabled = $settings->sms_enabled;
        $this->apiKey = $settings->api_key;
        $this->url = $settings->url;
        $this->bulkUrl = $settings->bulk_url;
        $this->smsType = $settings->sms_type;
        $this->senderId = $settings->sender_id;
        $this->senderName = $settings->sender_name;
        $this->senderPhone = $settings->sender_phone;
    }

    public function sendSms($recipient, $message)
    {
        if ($this->smsEnabled == 'false') {
            return false;
        }
        $recipient = trim($recipient);
        $smsText = urldecode($message).$this->getSmsFooter();

        $postData = [
            'api_key' => $this->apiKey,
            'type' => $this->smsType,
            'senderid' => $this->senderId,
            'msg' => $smsText,
            'contacts' => "88{$recipient}",
        ];

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        return $result;
    }

    public function sendAppSms($recipient, $message)
    {
        if ($this->smsEnabled == 'false') {
            return false;
        }
        $recipient = trim($recipient);
        $smsText = urldecode($message);

        $postData = [
            'api_key' => $this->apiKey,
            'type' => $this->smsType,
            'senderid' => $this->senderId,
            'msg' => $smsText,
            'contacts' => "88{$recipient}",
        ];

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        return $result;
    }

    public function sendBulkSms($recipients, $message)
    {
        if ($this->smsEnabled == 'false') {
            return false;
        }
        $smsText = urldecode($message);

        $messages = array_map(function ($recipient) use ($smsText) {
            $recipient = trim($recipient);

            return [
                'to' => "88{$recipient}",
                'message' => $smsText,
            ];
        }, $recipients);

        $postData = [
            'api_key' => $this->apiKey,
            'type' => $this->smsType,
            'senderid' => $this->senderId,
            'messages' => json_encode($messages),
        ];

        $ch = curl_init($this->bulkUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        return $result;
    }
}
