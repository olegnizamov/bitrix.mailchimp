<?php

namespace Onizamov\MailChimp\Classes;

use Bitrix\Main\Config\Option;

class ApiClient
{
    /**
     * Получения клиента для работы с Маилчимпом.
     *
     * @return \MailchimpMarketing\ApiClient
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function getClient(): \MailchimpMarketing\ApiClient
    {
        return (new \MailchimpMarketing\ApiClient())->setConfig(
            [
                'apiKey' => self::getMailchimpApiKey(),
                'server' => self::getMailchimpServerPrefix(),
            ]
        );
    }

    /**
     * Метод получения server_prefix mailchimp.
     *
     * @return string
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    private static function getMailchimpServerPrefix(): string
    {
        return Option::get('onizamov.mailchimp', 'server_prefix');
    }

    /**
     * Метод получения api_key mailchimp.
     *
     * @return string
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    private static function getMailchimpApiKey(): string
    {
        return Option::get('onizamov.mailchimp', 'api_key');
    }

    /**
     * Получения клиента для работы с Маилчимпом.
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function getDefaultProperties(): array
    {
        return [
            "permission_reminder" => "Newsletter about the company’s news and events",
            "email_type_option"   => false,
            "contact"             => [
                "company"  => "RGC",
                "address1" => "Vyborgskoe Shosse 503/3",
                "city"     => "Saint-Peterburg",
                "country"  => "RU",
                "zip"      => "194362",
            ],
            "campaign_defaults"   => [
                "from_name"  => "RGC",
                "from_email" => "marketing@rglass.ru",
                "subject"    => "subject",
                "language"   => "russian",
            ],
        ];
    }
}