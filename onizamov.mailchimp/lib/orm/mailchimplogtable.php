<?php

namespace Onizamov\MailChimp\Orm;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\TextField,
    Bitrix\Main\ORM\Fields\StringField;


/**
 * Class MailchimpLogTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> status string mandatory
 * <li> request text mandatory
 * <li> response text mandatory
 * </ul>
 * @package Onizamov\MailChimp\Orm
 **/
class MailchimpLogTable extends DataManager
{
    /**
     * Возвращает название таблицы.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mailchimp_log';
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
                'id',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                    'title'        => 'id',
                ]
            ),
            new StringField(
                'status',
                [
                    'required' => true,
                    'title'    => 'status',
                ]
            ),
            new TextField(
                'request',
                [
                    'required' => true,
                    'title'    => 'request',
                ]
            ),
            new TextField(
                'response',
                [
                    'title' => 'response',
                ]
            ),
        ];
    }

}