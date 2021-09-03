<?php

namespace Onizamov\MailChimp\Events;

use Onizamov\MailChimp\Classes\ApiClient;
use Onizamov\MailChimp\Classes\Logger;
use Onizamov\MailChimp\Orm\MailchimpSegmentMemberTable;
use Onizamov\MailChimp\Orm\MailchimpSegmentsTable;

class SegmentEvents
{
    /** @var string Добавление информации в название статического сегмента */
    public const STATIC_SEGMENT_PREFIX = '_Битрикс';

    /**
     * Событие добавление сегмента
     *
     * @param $event
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function onAfterAdd($event)
    {
        $mailchimpClient = ApiClient::getClient();

        $requestPropsName = [
            "name" => $event->getParameter('fields')['NAME'] ?: 'Сегмент из битрикса',
        ];
        $requestProps = array_merge(
            $requestPropsName,
            ApiClient::getDefaultProperties()
        );
        try {
            $response = $mailchimpClient->lists->createList($requestProps);
            $listId = $response->id;
        } catch (\Exception $e) {
            Logger::log($e->getCode(), json_encode($requestProps), $e->getMessage());
            return;
        }

        /** Если запрос успешно выполнен */
        MailchimpSegmentsTable::add(
            [
                'UF_BITRIX_ID'         => $event->getParameter('id'),
                'UF_MAILCHIMP_LIST_ID' => $listId,
                'UF_SEGMENT_TITLE'     => current($requestPropsName),
            ]
        );
    }

    /**
     * Событие удаление сегмента
     *
     * @param $event
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function onAfterDelete($event)
    {
        $id = $event->getParameter('id');
        if (empty($id['ID'])) {
            return;
        }
        MailchimpSegmentMemberTable::deleteRows($id['ID']);
        $mailchimpClient = ApiClient::getClient();
        try {
            $listId = MailchimpSegmentsTable::getMailchimpListIdByBitrixId($id['ID']);
            if (!empty($listId)) {
                $mailchimpClient->lists->deleteList($listId);
            }
        } catch (\Exception $e) {
            Logger::log($e->getCode(), json_encode($id['ID']), $e->getMessage());
        }
        MailchimpSegmentsTable::delete($id['ID']);
    }

    /**
     * Событие обновление сегмента.
     * Обрабатывает событие изменения названия.
     *
     * @param $event
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function onAfterUpdate($event)
    {
        /** Если не установлен параметр имени */
        if (empty($event->getParameter('fields')['NAME'])) {
            return;
        }

        $newSegmentTitle = $event->getParameter('fields')['NAME'];
        $id = $event->getParameter('id');
        $segment = MailchimpSegmentsTable::getMailchimpInfoByBitrixId($id['ID']);

        if (empty($segment['mailchimp_list_id'])
            || empty($segment['mailchimp_dynamic_segment_id'])
            || empty($segment['mailchimp_segment_id'])
            || ($segment['segment_title'] === $newSegmentTitle)
        ) {
            return;
        }

        $mailchimpClient = ApiClient::getClient();
        $requestPropsName = [
            "name" => $newSegmentTitle,
        ];
        try {
            $mailchimpClient->lists->updateList($segment['mailchimp_list_id'], $requestPropsName);
            /** Обновляем динамический сегмент */
            $mailchimpClient->lists->updateSegment(
                $segment['mailchimp_list_id'],
                $segment['mailchimp_dynamic_segment_id'],
                $requestPropsName
            );
            /** Обновляем статический сегмент */
            $mailchimpClient->lists->updateSegment(
                $segment['mailchimp_list_id'],
                $segment['mailchimp_segment_id'],
                ["name" => $newSegmentTitle . SegmentEvents::STATIC_SEGMENT_PREFIX]
            );
        } catch (\Exception $e) {
            Logger::log($e->getCode(), json_encode($id['ID']), $e->getMessage());
            return;
        }

        MailchimpSegmentsTable::update($id['ID'], ['UF_SEGMENT_TITLE' => $newSegmentTitle]);
    }

}