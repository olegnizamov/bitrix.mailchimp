<?php

namespace Onizamov\MailChimp\Events;

use GuzzleHttp\Exception\RequestException;
use Onizamov\MailChimp\Classes\ApiClient;
use Onizamov\MailChimp\Classes\Logger;
use Onizamov\MailChimp\Orm\MailchimpSegmentMemberTable;
use Onizamov\MailChimp\Orm\MailchimpSegmentsTable;
use MailchimpMarketing\ApiException;

class ContactEvents
{
    public const ENTITY_TYPE_ID = 3;

    public static function onCrmContactDelete(int $contactId)
    {
        try {
            $arrSegmentMembers = MailchimpSegmentMemberTable::getList(
                [
                    'select' => ['mailchimp_list_id', 'bitrix_segment_id', 'entity_data'],
                    'filter' =>
                        [
                            '=entity_id'   => $contactId,
                            '=entity_type' => ContactEvents::ENTITY_TYPE_ID,
                        ],
                ]
            )->fetchAll();

            /** Удалаяем записи из таблицы */
            $arrBitrixSegmentId = array_column($arrSegmentMembers, 'bitrix_segment_id');
            $arrListMailchimpId = array_column($arrSegmentMembers, 'mailchimp_list_id');
            $arrEntityData = array_column($arrSegmentMembers, 'entity_data');

            foreach ($arrBitrixSegmentId as $bitrixSegmentId) {
                MailchimpSegmentMemberTable::delete(
                    [
                        'bitrix_segment_id' => $bitrixSegmentId,
                        'entity_id'         => $contactId,
                        'entity_type'       => ContactEvents::ENTITY_TYPE_ID,
                    ]
                );
            }

            /** Удаляем записи из  Mailchimp*/
            foreach ($arrListMailchimpId as $key => $listId) {
                $entityData = json_decode($arrEntityData[$key], true);
                \Onizamov\MailChimp\Classes\ApiClient::getClient()->lists->deleteListMember(
                    $listId,
                    md5($entityData['email'])
                );
            }
        } catch (\Exception $e) {
            Logger::log(
                $e->getCode(),
                json_encode(['CONTACT_ID' => $contactId, 'STATUS' => 'Удаление записи']),
                $e->getMessage()
            );
        }
    }

}