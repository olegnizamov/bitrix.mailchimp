<?php

namespace Onizamov\MailChimp\Orm;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\StringField;


/**
 * Class MailchimpSegmentsTable
 *
 * Fields:
 * <ul>
 * <li> bitrix_id int mandatory
 * <li> mailchimp_list_id string mandatory
 * <li> mailchimp_segment_id string
 * <li> segment_title string
 * </ul>
 * @package Onizamov\MailChimp\Orm
 **/
class MailchimpSegmentsTable extends DataManager
{
    /**
     * Возвращает название таблицы.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mailchimp_segments';
    }

    /**
     * Возвращает карту сущности.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'UF_BITRIX_ID',
                [
                    'primary' => true,
                    'title'   => 'UF_BITRIX_ID',
                ]
            ),
            new StringField(
                'UF_MAILCHIMP_LIST_ID',
                [
                    'required' => true,
                    'title'    => 'UF_MAILCHIMP_LIST_ID',
                ]
            ),
            new StringField(
                'UF_MAILCHIMP_SEGMENT_ID',
                [
                    'title' => 'UF_MAILCHIMP_SEGMENT_ID',
                ]
            ),
            new StringField(
                'UF_MAILCHIMP_DYNAMIC_SEGMENT_ID',
                [
                    'title' => 'UF_MAILCHIMP_DYNAMIC_SEGMENT_ID',
                ]
            ),
            new StringField(
                'UF_SEGMENT_TITLE',
                [
                    'title' => 'UF_SEGMENT_TITLE',
                ]
            ),
        ];
    }

    /**
     * Метод получения ListID по Id Битрикса
     *
     * @param int $bitrixId
     * @return \Bitrix\Main\ORM\Objectify\Collection|bool|mixed|string|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMailchimpListIdByBitrixId(int $bitrixId): string
    {
        $mailchimpSegmentObj = self::getByPrimary(
            $bitrixId,
            [
                'select' => ['UF_MAILCHIMP_LIST_ID'],
            ]
        )->fetchObject();

        if (empty($mailchimpSegmentObj)) {
            return '';
        }

        return $mailchimpSegmentObj->get('UF_MAILCHIMP_LIST_ID');
    }

    /**
     * Метод получения ListID по Id Битрикса
     *
     * @param int $bitrixId
     * @return \Bitrix\Main\ORM\Objectify\Collection|bool|mixed|string|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMailchimpInfoByBitrixId(int $bitrixId): array
    {
        $mailchimpSegmentObj = self::getByPrimary(
            $bitrixId,
            [
                'select' => [
                    'UF_SEGMENT_TITLE',
                    'UF_MAILCHIMP_LIST_ID',
                    'UF_MAILCHIMP_SEGMENT_ID',
                    'UF_MAILCHIMP_DYNAMIC_SEGMENT_ID',
                ],
            ]
        )->fetchObject();

        if (empty($mailchimpSegmentObj)) {
            return [];
        }

        return [
            'mailchimp_list_id'            => $mailchimpSegmentObj->get('UF_MAILCHIMP_LIST_ID'),
            'segment_title'                => $mailchimpSegmentObj->get('UF_SEGMENT_TITLE'),
            'mailchimp_segment_id'         => $mailchimpSegmentObj->get('UF_MAILCHIMP_SEGMENT_ID'),
            'mailchimp_dynamic_segment_id' => $mailchimpSegmentObj->get('UF_MAILCHIMP_DYNAMIC_SEGMENT_ID'),
        ];
    }

}